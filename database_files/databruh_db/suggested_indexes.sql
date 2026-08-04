-- Apply manually with
--   mysql -u root databruh_db < suggested_indexes.sql
--
-- Method: every WHERE / JOIN ON / ORDER BY / GROUP BY column actually
-- used across php_files/*.php and the views in
-- database_basic_views_sql/basic_views.sql was catalogued, then checked
-- against SHOW INDEX on the live schema to see what InnoDB already
-- covers automatically.
--
--   - Every PRIMARY KEY and UNIQUE column (AccountID, Email, LinkedID,
--     VehicleID, RegistrationNumber, DriverID, LicenseNumber,
--     MechanicID, WorkshopID, PartID, AlertID, JobID, ActivityID, ...)
--   - Every single FOREIGN KEY column — InnoDB requires and
--     auto-creates an index wherever a FK column is the LEFTMOST column
--     of some index, e.g. vehicle.ClassificationID/StatusID/DepotID,
--     driver.DepotID, behaviour_event.VehicleID/DriverID/SeverityID/
--     DepotID, maintenance_job.VehicleID/WorkshopID/AlertID, etc.
--
-- Everything below targets a query pattern that is NOT already served
-- by one of those automatic indexes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- alert
-- ---------------------------------------------------------------------
-- Status and AlertTimestamp are plain VARCHAR/DATETIME columns with no
-- index at all today (only AlertID and VehicleID are indexed).
--
-- Serves:
--   - "SELECT COUNT(*) FROM alert WHERE Status IN ('New','Escalated')"
--     (dashboard_admin.php, dashboard_ws_mgr.php)
--   - view_active_alerts: "WHERE a.Status IN ('New','Escalated')"
--   - "... ORDER BY a.AlertTimestamp DESC" (dashboard_admin.php,
--     dashboard_ws_mgr.php, dashboard_driver.php)
-- A composite index lets a status-filtered, time-ordered query use a
-- single index instead of a full scan + filesort.
CREATE INDEX idx_alert_status_timestamp ON alert (Status, AlertTimestamp);


-- ---------------------------------------------------------------------
-- maintenance_job
-- ---------------------------------------------------------------------
-- Status and StartDate are unindexed (only JobID/VehicleID/WorkshopID/
-- AlertID are indexed).
--
-- Serves:
--   - "SELECT COUNT(*) FROM maintenance_job WHERE Status <> 'Closed'"
--     (dashboard_admin.php, dashboard_ws_mgr.php)
CREATE INDEX idx_maintenance_job_status ON maintenance_job (Status);

--   - "... ORDER BY mj.StartDate DESC" on the full jobs listing
--     (dashboard_admin.php, dashboard_ws_mgr.php)
CREATE INDEX idx_maintenance_job_startdate ON maintenance_job (StartDate);


-- ---------------------------------------------------------------------
-- vehicle_driver_assignment
-- ---------------------------------------------------------------------
-- VehicleID and DriverID each have a single-column auto-index from
-- their FK constraints, but the app's dominant access pattern is
-- "find the CURRENT assignment", i.e. VehicleID/DriverID filtered
-- together with "EndDate IS NULL" — a single-column index on just
-- VehicleID or DriverID can't also satisfy that second condition
-- efficiently. Composite indexes fix both directions:
--
-- Serves (vehicle -> current driver):
--   - "WHERE VehicleID = ? AND EndDate IS NULL" (dashboard_fleet_mgr.php,
--     checked before creating a new assignment)
--   - "LEFT JOIN vehicle_driver_assignment vda ON v.VehicleID =
--     vda.VehicleID AND vda.EndDate IS NULL" (vehicle directory,
--     dashboard_fleet_mgr.php)
CREATE INDEX idx_vda_vehicle_enddate ON vehicle_driver_assignment (VehicleID, EndDate);

-- Serves (driver -> current vehicle):
--   - "LEFT JOIN vehicle_driver_assignment vda ON d.DriverID =
--     vda.DriverID AND vda.EndDate IS NULL" (driver directory,
--     dashboard_fleet_mgr.php)
--   - "JOIN vehicle_driver_assignment vda ON a.VehicleID =
--     vda.VehicleID WHERE vda.DriverID = ? AND vda.EndDate IS NULL"
--     (dashboard_driver.php — alerts on the driver's current vehicle)
CREATE INDEX idx_vda_driver_enddate ON vehicle_driver_assignment (DriverID, EndDate);

-- Also serves the plain "WHERE EndDate IS NULL" / "ORDER BY StartDate
-- DESC" active-assignments listing (dashboard_fleet_mgr.php) as a
-- side effect of either composite index above.


-- ---------------------------------------------------------------------
-- driver / mechanic_worker / part — alphabetical listing columns
-- ---------------------------------------------------------------------
-- FullName / PartName are free-text columns with no index, used to
-- order dropdowns and directory tables. At current (seed) data volumes
-- this is irrelevant, but avoids a filesort once these tables have a
-- realistic number of rows.
--
-- Serves: "ORDER BY d.FullName" (dashboard_fleet_mgr.php driver
-- directory, signup.php driver dropdown)
CREATE INDEX idx_driver_fullname ON driver (FullName);

-- Serves: "ORDER BY m.FullName" (dashboard_admin.php / dashboard_ws_mgr.php
-- mechanic workload table, signup.php mechanic dropdown)
CREATE INDEX idx_mechanic_worker_fullname ON mechanic_worker (FullName);

-- Serves: "ORDER BY p.PartName" (dashboard_admin.php / dashboard_ws_mgr.php
-- parts & suppliers table)
CREATE INDEX idx_part_partname ON part (PartName);


-- ---------------------------------------------------------------------
-- behaviour_event
-- ---------------------------------------------------------------------
-- Serves the driver-scoped incident lookup (dashboard_driver.php, via
-- view_driver_incidents "WHERE DriverID = ? ORDER BY Timestamp DESC"):
-- a composite beats the existing single-column DriverID index because
-- it also covers the ORDER BY without a separate sort step.
CREATE INDEX idx_behaviour_event_driver_timestamp ON behaviour_event (DriverID, Timestamp);

-- NOTE — query rewrite needed, not just an index:
-- dashboard_fleet_mgr.php's "critical incidents this month" stat does
--   WHERE MONTH(be.Timestamp) = MONTH(CURDATE())
--     AND YEAR(be.Timestamp) = YEAR(CURDATE())
-- Wrapping an indexed column in MONTH()/YEAR() makes it non-sargable —
-- no index on Timestamp (including the one above) can be used for this
-- WHERE clause as written, because MySQL/MariaDB would have to evaluate
-- the function on every row first. Rewriting as a sargable range fixes
-- this and lets idx_behaviour_event_driver_timestamp (or a plain index
-- on Timestamp) actually be used:
--   WHERE be.Timestamp >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
--     AND be.Timestamp <  DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')


-- =====================================================================
-- Notable schema finding — not an index, a missing constraint
-- =====================================================================
-- supplier_product_list has NO PRIMARY KEY and NO UNIQUE constraint at
-- all (confirmed via SHOW CREATE TABLE): just two separate single-
-- column, non-unique indexes that InnoDB auto-created to satisfy its
-- two FK constraints (PartID, PartnerID). Two consequences:
--   1. Data integrity gap — nothing stops the same (PartID, PartnerID)
--      pair from being inserted twice, even though this is clearly a
--      part-to-supplier pricing link that should be unique per pair.
--   2. Query cost — "... ON p.PartID = spl.PartID AND
--      p.PrimarySupplierID = spl.PartnerID" (used in dashboard_admin.php,
--      dashboard_ws_mgr.php, and the view_part_consumption_lifecycle /
--      view_active_warranty_ledger views) has to combine two separate
--      single-column indexes instead of seeking a single composite one.
-- A composite UNIQUE index fixes both at once:
CREATE UNIQUE INDEX idx_supplier_product_list_part_partner ON supplier_product_list (PartID, PartnerID);


-- =====================================================================
-- Checked and found ALREADY well-covered — no new index suggested
-- =====================================================================
-- - monthly_score_log: UNIQUE(DriverID, Month, Year) already serves
--   "WHERE DriverID = ? ORDER BY Year, Month" (dashboard_driver.php,
--   view_driver_score_anomalies) for free, since DriverID is the
--   leftmost column and Month/Year are already in sorted order behind
--   it for a given driver.
-- - activity_instance_worker_assigned: its PRIMARY KEY is a composite
--   (ActivityID, MechanicID), so a lookup by MechanicID alone
--   ("WHERE aiwa.MechanicID = ?" in dashboard_mechanic.php) can't use
--   the PK's leftmost-prefix — but InnoDB already auto-created a
--   SEPARATE single-column index on MechanicID to satisfy its own FK
--   constraint to mechanic_worker, so this access pattern is already
--   fast. (Worth knowing: this is exactly the scenario where InnoDB's
--   automatic FK indexing and "leftmost column of the PK" are two
--   different things — don't assume a composite PK covers a lookup on
--   its second column.)
-- - Every lookup table (account_type, severity_level, vehicle_status,
--   vehicle_classification, depot_location, workshop) is small enough
--   (a handful of rows) that indexing beyond the PK has no measurable
--   benefit either way.


-- =====================================================================
-- Added after business_rules.sql and the fleet/workshop-manager
-- dashboards started actually querying tables this file previously
-- marked "out of scope" for lack of a reader. Confirmed with EXPLAIN
-- against the live schema (see PR discussion / session notes) before
-- adding — table sizes are still tiny today so the optimizer doesn't
-- always pick these yet, but the access pattern is real and will only
-- get worse as the tables grow.
-- =====================================================================

-- ---------------------------------------------------------------------
-- monthly_score_log
-- ---------------------------------------------------------------------
-- sp_check_assignment_eligibility (business_rules.sql) runs this on
-- every vehicle_driver_assignment INSERT and reactivation:
--   SELECT Score FROM monthly_score_log
--   WHERE DriverID = ? AND (Year < ? OR (Year = ? AND Month <= ?))
--   ORDER BY Year DESC, Month DESC LIMIT 1
-- The existing UNIQUE(DriverID, Month, Year) has DriverID leftmost, so
-- the equality filter uses it, but its column order (Month before Year)
-- doesn't match this query's ORDER BY (Year, Month) — confirmed via
-- EXPLAIN: "Using index condition; Using where; Using filesort". This
-- also assists view_coaching_required's per-driver latest-score lookup.
-- A trigger firing on every assignment write is exactly the kind of hot
-- path worth avoiding a filesort on:
CREATE INDEX idx_monthly_score_log_driver_year_month ON monthly_score_log (DriverID, Year, Month);

-- ---------------------------------------------------------------------
-- coaching_log
-- ---------------------------------------------------------------------
-- view_driver_risk_summary runs this once per driver row (correlated
-- subquery):
--   SELECT COUNT(*) FROM coaching_log
--   WHERE DriverID = ? AND Outcome = 'Retraining Required'
-- Only DriverID is indexed today (auto FK index); Outcome is filtered
-- after. EXPLAIN currently shows a full scan (table is 8 rows, so the
-- optimizer skips even the DriverID index) — the composite becomes
-- worth it once coaching_log has real history behind it:
CREATE INDEX idx_coaching_log_driver_outcome ON coaching_log (DriverID, Outcome);

-- ---------------------------------------------------------------------
-- driver_certification_owned
-- ---------------------------------------------------------------------
-- view_expired_certifications: "WHERE dco.ExpiryDate < CURDATE()".
-- ExpiryDate isn't covered by the (DriverID, CertificationTypeID)
-- PRIMARY KEY at all — EXPLAIN confirms a full table scan (type: ALL,
-- possible_keys: NULL) since this filters across all drivers, not one:
CREATE INDEX idx_driver_cert_owned_expiry ON driver_certification_owned (ExpiryDate);


-- =====================================================================
-- NOTE — the behaviour_event non-sargable date filter is now a hotter
-- path than when first flagged above
-- =====================================================================
-- sp_recalculate_driver_month_score (business_rules.sql) runs on EVERY
-- behaviour_event insert/update/delete via trigger, not just on a
-- dashboard page load:
--   WHERE be.DriverID = p_driver
--     AND YEAR(be.Timestamp) = p_year AND MONTH(be.Timestamp) = p_month
-- Same non-sargable wrapping already called out above for the "critical
-- incidents this month" dashboard stat — no index (existing or new) can
-- be used for this WHERE clause as written. Two real fixes, in order of
-- preference:
--   1. Rewrite the procedure's WHERE to a sargable range:
--        AND be.Timestamp >= STR_TO_DATE(CONCAT(p_year, '-', p_month, '-01'), '%Y-%m-%d')
--        AND be.Timestamp <  DATE_ADD(STR_TO_DATE(CONCAT(p_year, '-', p_month, '-01'), '%Y-%m-%d'), INTERVAL 1 MONTH)
--      then idx_behaviour_event_driver_timestamp above already serves it.
--   2. If the procedure can't be touched, add generated+indexed columns
--      instead: ADD COLUMN EventYear SMALLINT GENERATED ALWAYS AS
--      (YEAR(Timestamp)) STORED, EventMonth TINYINT GENERATED ALWAYS AS
--      (MONTH(Timestamp)) STORED, then index (DriverID, EventYear,
--      EventMonth) — but this only helps once something actually
--      queries the generated columns instead of YEAR()/MONTH(Timestamp).
-- An index alone does not fix this either way.


-- =====================================================================
-- Not indexable — column-to-column comparison
-- =====================================================================
-- view_parts_below_reorder: "WHERE p.QuantityOnHand <= p.ReorderThreshold"
-- compares two columns of the same row rather than a column to a
-- constant. No B-tree index (single-column, composite, or otherwise)
-- can accelerate this — it's a full scan by construction. Table is 5
-- rows today so this is moot in practice; if it ever mattered at scale,
-- the fix would be a generated boolean column (LowStock GENERATED
-- ALWAYS AS (QuantityOnHand <= ReorderThreshold)) with an index on
-- that, not an index on the two source columns.


-- =====================================================================
-- Checked, still out of current scope
-- =====================================================================
-- warranty_claim, warranty_part_list, and activity_instance_part_used
-- are now read by view_supplier_performance and the workshop
-- manager/mechanic dashboards, but every join into them is on a
-- FOREIGN KEY column (ActivityID, PartID, PartnerID, SupplierID) that
-- InnoDB already auto-indexes — same reasoning as the
-- activity_instance_worker_assigned.MechanicID case below. Nothing new
-- needed there.
--
-- view_part_consumption_lifecycle and view_active_warranty_ledger are
-- still unread by any page in php_files/ — left as-is until something
-- actually queries them.
