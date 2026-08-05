#!/usr/bin/env python3
"""
Generate bulk mock data for databruh_db, in the shape of the existing
seed in database_insertion_sql/insert_full_script.sql.

Purpose: the shipped seed (8 vehicles, ~25 behaviour events) is far too
small to demonstrate anything about index performance -- every table
fits in a page or two, so the optimiser correctly ignores indexes and
full-scans instead. This produces enough volume for EXPLAIN and the
Handler_read_* counters to show a real difference.

Usage
-----
    python3 tools/generate_mock_data.py --events 200000 --jobs 20000 \
        --drivers 400 --vehicles 300 -o mock_data.sql

Then, from database_files/databruh_db/:

    mysql -u root databruh_db < ../../mock_data.sql
    mysql -u root databruh_db < database_creation_sql/business_rules.sql

The second command is required: this script drops the per-row triggers
before loading (they call a stored procedure per inserted row, which
turns a 200k insert into an overnight job), and re-running
business_rules.sql puts them back. Monthly scores are computed here in
Python using the same penalty rules, so nothing is lost by bypassing
the trigger during load.

The generated data APPENDS to the existing seed. IDs start at high
offsets so nothing collides with the hand-written demo rows, which stay
intact and keep demonstrating the edge cases they were written for.
"""

from __future__ import annotations

import argparse
import datetime as dt
import random
import sys
from pathlib import Path

# --------------------------------------------------------------------
# Reference data, mirroring the seed's lookup tables.
# --------------------------------------------------------------------

DEPOTS = {1: "Hanoi", 2: "Ho Chi Minh City", 3: "Da Nang", 4: "Can Tho"}

# Registration prefixes follow the seed's regional convention.
DEPOT_PLATE_PREFIX = {1: "29", 2: "51", 3: "43", 4: "65"}
PLATE_SERIES = "ABCDEFGH"

# ClassificationID -> name (1..5, matching vehicle_classification)
CLASSIFICATIONS = {
    1: "Delivery Van",
    2: "Refrigerated Truck",
    3: "Electric Van",
    4: "Service Vehicle",
    5: "Heavy Transport Truck",
}

# Assignable statuses only. 3 = Under Maintenance and 5 = Out of Service
# are excluded because sp_check_assignment_eligibility rejects them.
ASSIGNABLE_STATUS = [1, 2]          # Active, Available
ALL_STATUS = [1, 2, 3, 4, 5]        # + Under Maintenance, Awaiting Inspection, Out of Service

# vehicle_type_certification_requirement, straight from the brief matrix.
CLASS_REQUIRED_CERTS = {
    1: {1},
    2: {1, 2, 3},
    3: {1, 4},
    4: {1},
    5: {2, 5},
}

MODELS_BY_CLASS = {
    1: [("Ford", "Transit"), ("Hyundai", "Solati"), ("Toyota", "HiAce")],
    2: [("Isuzu", "QKR77HE4"), ("Hino", "XZU720"), ("Thaco", "Ollin S700")],
    3: [("VinFast", "VF Pro Van"), ("BYD", "ETM6"), ("Wuling", "EV Cargo")],
    4: [("Hyundai", "Porter"), ("Suzuki", "Carry"), ("Kia", "K200")],
    5: [("Hino", "FL"), ("Howo", "A7"), ("Dongfeng", "Hoang Huy")],
}

SURNAMES = ["Nguyen", "Tran", "Le", "Pham", "Hoang", "Phan", "Vu", "Dang",
            "Bui", "Do", "Ho", "Ngo", "Duong", "Ly", "Vo", "Trinh", "Dinh"]
MIDDLE = ["Van", "Thi", "Quoc", "Duc", "Minh", "Thanh", "Ngoc", "Huu", "Xuan", "Kim"]
GIVEN = ["An", "Bich", "Minh", "Long", "Hoa", "Kiet", "Ngoc", "Phuc", "Mai", "Son",
         "Tuan", "Linh", "Hung", "Trang", "Nam", "Yen", "Dung", "Thao", "Khanh", "Vy"]

# (EventType, SeverityID) with weights. Severity: 1 Low, 2 Med, 3 High, 4 Critical.
EVENT_PROFILE = [
    ("Harsh Braking",     [(1, 70), (2, 25), (3, 5)]),
    ("Speeding",          [(1, 20), (2, 40), (3, 32), (4, 8)]),
    ("Sharp Cornering",   [(1, 55), (2, 38), (3, 7)]),
    ("Excessive Idling",  [(1, 90), (2, 10)]),
    ("Fatigue Warning",   [(2, 30), (3, 55), (4, 15)]),
    ("Hard Acceleration", [(1, 60), (2, 35), (3, 5)]),
    ("Tailgating",        [(2, 50), (3, 45), (4, 5)]),
    ("Seatbelt Violation", [(1, 65), (2, 35)]),
    ("Phone Distraction", [(2, 45), (3, 50), (4, 5)]),
]
EVENT_WEIGHTS = [26, 18, 14, 12, 8, 9, 6, 4, 3]

ALERT_TYPES = [
    "Brake Wear Warning", "Engine Overheat Warning", "Battery Health Degraded",
    "Oil Quality Alert", "Transmission Fault", "Cooling System Anomaly",
    "Tyre Pressure Low",
]
ALERT_STATUSES = [("Resolved", 60), ("New", 20), ("Escalated", 20)]

# ActivityTypeID -> required mechanic CertificationID (activity_type table)
ACTIVITY_TYPES = {
    1: ("Routine Inspection", 1), 2: ("Preventative Servicing", 1),
    3: ("Diagnostic Testing", 1), 4: ("Emergency Repair", 1),
    5: ("Component Replacement", 1), 6: ("EV Battery / Electrical Repair", 2),
    7: ("Refrigeration System Repair", 3), 8: ("Heavy Vehicle Repair", 4),
    9: ("Brake Service", 1), 10: ("Tyre Replacement", 1),
}

# Which activity types make sense for which vehicle class.
CLASS_ACTIVITIES = {
    1: [1, 2, 3, 4, 5, 9, 10],
    2: [1, 2, 3, 4, 5, 7, 9, 10],
    3: [1, 2, 3, 5, 6, 10],
    4: [1, 2, 3, 4, 5, 9, 10],
    5: [1, 2, 3, 4, 5, 8, 9, 10],
}

DIAGNOSTICS = [
    "Within tolerance", "Pads worn below 3mm", "Belt cracked - replaced",
    "Coolant level low, topped up", "Sensor recalibrated", "No fault found",
    "Worn unevenly - possible alignment issue", "Cell voltage imbalance corrected",
    "Seals replaced", "Filter clogged - replaced",
]

# Existing seed IDs we must not collide with.
ID_OFFSET = 100000
DRIVER_PREFIX = "D-9"
VEHICLE_PREFIX = "VEH-9"
MECHANIC_PREFIX = "ME-9"

# Monthly safety score penalties (must match sp_recalculate_driver_month_score).
SEVERITY_PENALTY = {1: 2, 2: 5, 3: 10, 4: 20}


def weighted(rng: random.Random, pairs):
    """pairs = [(value, weight), ...]"""
    total = sum(w for _, w in pairs)
    r = rng.uniform(0, total)
    upto = 0.0
    for value, w in pairs:
        upto += w
        if r <= upto:
            return value
    return pairs[-1][0]


def esc(s: str | None) -> str:
    if s is None:
        return "NULL"
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


def batched_insert(out, table: str, columns: list[str], rows: list[tuple], batch: int = 500):
    """Emit multi-row INSERTs. One statement per 500 rows keeps each
    packet well under max_allowed_packet while staying fast."""
    if not rows:
        return
    collist = ", ".join(columns)
    for i in range(0, len(rows), batch):
        chunk = rows[i:i + batch]
        out.write(f"INSERT INTO {table} ({collist}) VALUES\n")
        lines = []
        for r in chunk:
            vals = ", ".join(
                "NULL" if v is None
                else esc(v) if isinstance(v, str)
                else ("TRUE" if v is True else "FALSE" if v is False else str(v))
                for v in r
            )
            lines.append(f"({vals})")
        out.write(",\n".join(lines))
        out.write(";\n")
    out.write("\n")


def build(args) -> str:
    rng = random.Random(args.seed)
    from io import StringIO
    out = StringIO()

    start = dt.date.today() - dt.timedelta(days=int(args.years * 365))
    end = dt.date.today()
    span_days = (end - start).days

    def rand_dt(after: dt.date | None = None) -> dt.datetime:
        lo = after or start
        d = lo + dt.timedelta(days=rng.randint(0, max(1, (end - lo).days)))
        return dt.datetime.combine(d, dt.time(rng.randint(5, 21), rng.choice([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55])))

    out.write(f"""-- Generated by tools/generate_mock_data.py
-- seed={args.seed}  drivers={args.drivers}  vehicles={args.vehicles}
-- mechanics={args.mechanics}  events={args.events}  jobs={args.jobs}
-- period: {start} .. {end}
--
-- Appends to the existing seed; IDs are offset so nothing collides.
--
-- IMPORTANT: the per-row triggers are dropped below. They call a stored
-- procedure for every inserted row, which makes a bulk load take hours.
-- Monthly scores are computed here instead, using the same penalty
-- rules. After loading this file, re-run business_rules.sql to restore
-- the triggers:
--     mysql -u root databruh_db < database_creation_sql/business_rules.sql

USE databruh_db;

SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0;
SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0;
SET @OLD_AUTOCOMMIT = @@AUTOCOMMIT, AUTOCOMMIT = 0;

DROP TRIGGER IF EXISTS trg_behaviour_event_score_after_insert;
DROP TRIGGER IF EXISTS trg_behaviour_event_score_after_update;
DROP TRIGGER IF EXISTS trg_behaviour_event_score_after_delete;
DROP TRIGGER IF EXISTS trg_activity_worker_hours_after_insert;
DROP TRIGGER IF EXISTS trg_activity_worker_hours_after_update;
DROP TRIGGER IF EXISTS trg_activity_worker_hours_after_delete;
DROP TRIGGER IF EXISTS trg_vehicle_driver_assignment_before_insert;
DROP TRIGGER IF EXISTS trg_vehicle_driver_assignment_before_update;

""")

    # ---------------- vehicles ----------------
    vehicles = []
    used_plates = set()
    for i in range(args.vehicles):
        vid = f"{VEHICLE_PREFIX}{i:04d}"
        cls = weighted(rng, [(1, 35), (2, 22), (3, 13), (4, 18), (5, 12)])
        depot = rng.randint(1, 4)
        while True:
            plate = (f"{DEPOT_PLATE_PREFIX[depot]}{rng.choice(PLATE_SERIES)}-"
                     f"{rng.randint(100, 999)}.{rng.randint(10, 99)}")
            if plate not in used_plates:
                used_plates.add(plate)
                break
        make, model = rng.choice(MODELS_BY_CLASS[cls])
        year = rng.randint(2016, 2025)
        # Most vehicles assignable; a minority in maintenance / out of service.
        status = weighted(rng, [(1, 46), (2, 30), (3, 10), (4, 7), (5, 7)])
        odo = rng.randint(5_000, 320_000)
        vehicles.append((vid, plate, make, model, cls, year, status, depot, odo))

    batched_insert(out, "vehicle",
                   ["VehicleID", "RegistrationNumber", "Manufacturer", "Model",
                    "ClassificationID", "YearOfManufacture", "StatusID", "DepotID",
                    "CurrentOdometer"],
                   vehicles)

    # ---------------- drivers ----------------
    drivers = []
    for i in range(args.drivers):
        did = f"{DRIVER_PREFIX}{i:04d}"
        name = f"{rng.choice(SURNAMES)} {rng.choice(MIDDLE)} {rng.choice(GIVEN)}"
        depot = rng.randint(1, 4)
        lic = f"L-9{i:05d}"
        lic_exp = end + dt.timedelta(days=rng.randint(-200, 1500))
        emp = weighted(rng, [("Active", 88), ("On Leave", 6), ("Suspended", 3), ("Terminated", 3)])
        phone = f"09{rng.randint(10_000_000, 99_999_999)}"
        drivers.append((did, name, depot, lic, lic_exp.isoformat(), emp,
                        phone, f"Family - 09{rng.randint(10_000_000, 99_999_999)}"))

    batched_insert(out, "driver",
                   ["DriverID", "FullName", "DepotID", "LicenseNumber",
                    "LicenseExpiration", "EmploymentStatus", "ContactInfo",
                    "EmergencyContactDetails"],
                   drivers)

    # ---------------- driver certifications ----------------
    # Every generated driver holds all five certifications, issued before
    # the data window and expiring after it. That guarantees any
    # assignment satisfies sp_check_assignment_eligibility regardless of
    # which vehicle class it draws. A small slice get a lapsed cert for
    # realism -- those are excluded from assignment below.
    cert_rows = []
    lapsed_drivers = set()
    for did, *_ in drivers:
        lapse = rng.random() < 0.06
        if lapse:
            lapsed_drivers.add(did)
        for ct in (1, 2, 3, 4, 5):
            issue = start - dt.timedelta(days=rng.randint(200, 1200))
            if lapse and ct == 1:
                expiry = end - dt.timedelta(days=rng.randint(10, 300))
            else:
                expiry = end + dt.timedelta(days=rng.randint(200, 1600))
            cert_rows.append((did, ct, issue.isoformat(), expiry.isoformat()))

    batched_insert(out, "driver_certification_owned",
                   ["DriverID", "CertificationTypeID", "IssueDate", "ExpiryDate"],
                   cert_rows)

    # ---------------- mechanics ----------------
    mechanics = []
    mech_cert_rows = []
    for i in range(args.mechanics):
        mid = f"{MECHANIC_PREFIX}{i:03d}"
        name = f"{rng.choice(SURNAMES)} {rng.choice(MIDDLE)} {rng.choice(GIVEN)}"
        ws = rng.randint(1, 3)
        mechanics.append((mid, name, "Active",
                          f"Family - 09{rng.randint(10_000_000, 99_999_999)}", ws))
        # All four mechanic certifications, valid across the window, so any
        # activity type can be staffed.
        for ct in (1, 2, 3, 4):
            issue = start - dt.timedelta(days=rng.randint(200, 1500))
            expiry = end + dt.timedelta(days=rng.randint(300, 2000))
            mech_cert_rows.append((mid, ct, issue.isoformat(), expiry.isoformat()))

    batched_insert(out, "mechanic_worker",
                   ["MechanicID", "FullName", "EmploymentStatus",
                    "EmergencyContactDetails", "WorkshopID"], mechanics)
    batched_insert(out, "mechanic_worker_certifications_history",
                   ["MechanicID", "CertificationID", "IssueDate", "ExpiryDate"],
                   mech_cert_rows)

    # ---------------- vehicle-driver assignments ----------------
    # Assignments are dated before the event window so that the
    # "unresolved critical event" and "score <= 50" checks in the trigger
    # have nothing to trip on (both look only at data at or before the
    # assignment start date).
    assignable = [v for v in vehicles if v[6] in ASSIGNABLE_STATUS]
    eligible_drivers = [d[0] for d in drivers
                        if d[0] not in lapsed_drivers and d[5] == "Active"]
    assignments = []
    driver_vehicle = {}   # driver -> vehicle, for realistic event pairing
    if eligible_drivers:
        for idx, v in enumerate(assignable):
            did = eligible_drivers[idx % len(eligible_drivers)]
            sd = start + dt.timedelta(days=rng.randint(0, 20))
            # A minority are historical (closed) assignments.
            if rng.random() < 0.22:
                ed = sd + dt.timedelta(days=rng.randint(30, max(31, span_days - 30)))
                assignments.append((v[0], did, sd.isoformat(), ed.isoformat()))
            else:
                assignments.append((v[0], did, sd.isoformat(), None))
                driver_vehicle.setdefault(did, []).append((v[0], v[7], v[4]))

    batched_insert(out, "vehicle_driver_assignment",
                   ["VehicleID", "DriverID", "StartDate", "EndDate"], assignments)

    # ---------------- behaviour events ----------------
    # Only drivers with a current vehicle generate events, so every event
    # is attributable to a plausible vehicle/depot pairing.
    pairs = [(d, v, depot) for d, vs in driver_vehicle.items() for (v, depot, _) in vs]
    event_rows = []
    # month -> driver -> [severity, event_type] for score computation
    monthly: dict[tuple[str, int, int], list[tuple[int, str]]] = {}
    event_window_start = start + dt.timedelta(days=30)

    if pairs:
        for n in range(args.events):
            did, vid, depot = rng.choice(pairs)
            etype_idx = weighted(rng, list(zip(range(len(EVENT_PROFILE)), EVENT_WEIGHTS)))
            etype, sev_dist = EVENT_PROFILE[etype_idx]
            sev = weighted(rng, sev_dist)
            days_range = max(1, (end - event_window_start).days)
            ts = dt.datetime.combine(
                event_window_start + dt.timedelta(days=rng.randint(0, days_range)),
                dt.time(rng.randint(5, 22), rng.randrange(0, 60)),
            )
            event_rows.append((ID_OFFSET + n, vid, did, depot,
                               ts.strftime("%Y-%m-%d %H:%M:%S"), sev, etype, None))
            monthly.setdefault((did, ts.year, ts.month), []).append((sev, etype))

    batched_insert(out, "behaviour_event",
                   ["EventID", "VehicleID", "DriverID", "DepotID", "Timestamp",
                    "SeverityID", "EventType", "Description"],
                   event_rows, batch=1000)

    # ---------------- monthly scores (computed, not triggered) ----------
    # Mirrors sp_recalculate_driver_month_score exactly: base 100, minus
    # per-event penalties, minus the flat monthly deductions.
    score_rows = []
    for (did, year, month), evs in monthly.items():
        deduction = sum(SEVERITY_PENALTY[s] for s, _ in evs)
        speeding = sum(1 for _, t in evs if t == "Speeding")
        fatigue = sum(1 for _, t in evs if t == "Fatigue Warning")
        critical = sum(1 for s, _ in evs if s == 4)
        if speeding > 3:
            deduction += 10
        if fatigue > 2:
            deduction += 15
        if critical >= 1:
            deduction += 10
        score_rows.append((did, month, year, max(0, min(100, 100 - deduction))))

    batched_insert(out, "monthly_score_log",
                   ["DriverID", "Month", "Year", "Score"], score_rows, batch=1000)

    # ---------------- alerts ----------------
    alert_rows = []
    n_alerts = max(1, args.jobs // 2)
    for n in range(n_alerts):
        v = rng.choice(vehicles)
        ts = rand_dt()
        alert_rows.append((ID_OFFSET + n, rng.choice(ALERT_TYPES), v[0],
                           "Generated diagnostic alert.",
                           ts.strftime("%Y-%m-%d %H:%M:%S"),
                           weighted(rng, ALERT_STATUSES)))
    batched_insert(out, "alert",
                   ["AlertID", "AlertName", "VehicleID", "AlertDescription",
                    "AlertTimestamp", "Status"], alert_rows, batch=1000)

    # ---------------- maintenance jobs + activities ----------------
    job_rows, act_rows, worker_rows = [], [], []
    act_id = ID_OFFSET
    for n in range(args.jobs):
        v = rng.choice(vehicles)
        cls = v[4]
        depot = v[7]
        workshop = depot if depot <= 3 else rng.randint(1, 3)   # 3 workshops seeded
        sd = rand_dt()
        closed = rng.random() < 0.82
        job_id = ID_OFFSET + n
        alert_id = (ID_OFFSET + rng.randrange(n_alerts)) if rng.random() < 0.45 else None
        if closed:
            ed = sd + dt.timedelta(hours=rng.randint(2, 72))
            cost = rng.randrange(300_000, 12_000_000, 10_000)
            job_rows.append((job_id, v[0], workshop, sd.strftime("%Y-%m-%d %H:%M:%S"),
                             ed.strftime("%Y-%m-%d %H:%M:%S"), "Closed", alert_id, cost))
        else:
            job_rows.append((job_id, v[0], workshop, sd.strftime("%Y-%m-%d %H:%M:%S"),
                             None, "Open", alert_id, None))

        for _ in range(rng.randint(1, 3)):
            atype = rng.choice(CLASS_ACTIVITIES[cls])
            n_mech = 1 if rng.random() < 0.75 else 2
            per_hours = round(rng.uniform(0.5, 4.0), 2)
            act_rows.append((act_id, job_id, atype, round(per_hours * n_mech, 2),
                             rng.choice(DIAGNOSTICS),
                             rng.random() < 0.18, rng.random() < 0.09))
            chosen = rng.sample(mechanics, min(n_mech, len(mechanics)))
            for m in chosen:
                worker_rows.append((act_id, m[0], per_hours))
            act_id += 1

    batched_insert(out, "maintenance_job",
                   ["JobID", "VehicleID", "WorkshopID", "StartDate", "EndDate",
                    "Status", "AlertID", "ToTalCost"], job_rows, batch=1000)
    batched_insert(out, "activity_instance",
                   ["ActivityID", "JobID", "ActivityTypeID", "LabourHours",
                    "DiagnosticResult", "RepeatFault", "WarrantyApplicable"],
                   act_rows, batch=1000)
    batched_insert(out, "activity_instance_worker_assigned",
                   ["ActivityID", "MechanicID", "LabourHours"], worker_rows, batch=1000)

    # ---------------- coaching, for a slice of severe incidents ----------
    coaching_rows = []
    severe = [e for e in event_rows if e[5] in (3, 4)]
    rng.shuffle(severe)
    for e in severe[:int(len(severe) * 0.55)]:
        ts = dt.datetime.strptime(e[4], "%Y-%m-%d %H:%M:%S").date()
        coach_date = ts + dt.timedelta(days=rng.randint(1, 14))
        if coach_date > end:
            continue
        outcome = weighted(rng, [("Coached - Verbal Warning", 45),
                                 ("Coached - Written Warning", 25),
                                 ("Retraining Required", 15),
                                 ("Completed - No Concerns", 15)])
        coaching_rows.append((e[2], e[0], coach_date.isoformat(),
                              "Fleet Manager", outcome, None))
    batched_insert(out, "coaching_log",
                   ["DriverID", "EventID", "CoachDate", "ConductedBy", "Outcome", "Notes"],
                   coaching_rows, batch=1000)

    out.write("""COMMIT;

SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS;
SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
SET AUTOCOMMIT = @OLD_AUTOCOMMIT;

-- Refresh optimiser statistics so EXPLAIN reflects the new row counts.
-- Without this, index benchmarking can read from stale cardinality.
ANALYZE TABLE behaviour_event, maintenance_job, activity_instance,
              activity_instance_worker_assigned, vehicle, driver,
              vehicle_driver_assignment, monthly_score_log, alert,
              coaching_log;

-- Reminder: re-run business_rules.sql now to restore the triggers this
-- file dropped.
""")

    return out.getvalue()


def main() -> int:
    p = argparse.ArgumentParser(
        description="Generate bulk mock data for databruh_db.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter)
    p.add_argument("--drivers", type=int, default=400)
    p.add_argument("--vehicles", type=int, default=300)
    p.add_argument("--mechanics", type=int, default=40)
    p.add_argument("--events", type=int, default=200_000,
                   help="behaviour_event rows -- the main table for index tests")
    p.add_argument("--jobs", type=int, default=20_000)
    p.add_argument("--years", type=float, default=3.0,
                   help="how far back the generated history runs")
    p.add_argument("--seed", type=int, default=20260805,
                   help="RNG seed; same seed gives byte-identical output")
    p.add_argument("-o", "--output", default="mock_data.sql")
    args = p.parse_args()

    sql = build(args)
    path = Path(args.output)
    path.write_text(sql, encoding="utf-8")

    size_mb = path.stat().st_size / (1024 * 1024)
    print(f"Wrote {path}  ({size_mb:.1f} MB)", file=sys.stderr)
    print(f"  vehicles={args.vehicles} drivers={args.drivers} "
          f"mechanics={args.mechanics}", file=sys.stderr)
    print(f"  behaviour_event={args.events} maintenance_job={args.jobs}", file=sys.stderr)
    print(file=sys.stderr)
    print("Load with:", file=sys.stderr)
    print(f"  mysql -u root databruh_db < {path}", file=sys.stderr)
    print("  mysql -u root databruh_db < database_files/databruh_db/"
          "database_creation_sql/business_rules.sql", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
