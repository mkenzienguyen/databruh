USE databruh_db;

-- =====================================================================
-- Business rule enforcement.
--
-- The brief states several rules as hard constraints on the data, not
-- just things to report on:
--   - a vehicle Under Maintenance or Out of Service cannot be assigned
--   - a driver cannot be assigned to a vehicle category they are not
--     certified for, or whose certification has expired
--   - a driver with a safety score of 50 or below cannot be assigned
--     until they complete safety training
--   - a driver with an unresolved critical safety event is made
--     inactive (unable to be assigned) until the review is completed
--     or they complete safety training
--
-- Previously these were only detectable after the fact via views
-- (view_unauthorized_vehicle_operation, view_expired_certifications,
-- view_incident_resolution). This file adds a BEFORE INSERT/UPDATE
-- trigger on vehicle_driver_assignment that actually blocks a
-- violating assignment at the database layer, regardless of which
-- application code path tries to create one.
--
-- It also makes the monthly safety score a computed value derived
-- from behaviour_event using the exact penalty table in the brief,
-- instead of a hand-entered number, and adds the remaining schema
-- gaps identified against the brief (per-mechanic labour hours,
-- explicit repeat-fault/warranty indicators on each activity, and a
-- one-workshop-per-depot constraint).
--
-- Safe to re-run: columns/constraints use IF NOT EXISTS, and
-- triggers/procedures are dropped and recreated.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Schema additions
-- ---------------------------------------------------------------------

-- One workshop per depot, per "The company operates one workshop per
-- depot" in the brief. NULL DepotID values are still allowed to repeat
-- (a workshop with no depot assigned yet), which is standard SQL UNIQUE
-- semantics.
ALTER TABLE workshop
    ADD UNIQUE INDEX IF NOT EXISTS uq_workshop_depot (DepotID);

-- Activity-level information the brief lists alongside diagnostic
-- result: a repeat-fault indicator and a warranty indicator, per
-- activity. (The extension task's warranty_claim/warranty_part_list
-- tables still carry the detailed claim -- provider, resolution, which
-- parts -- this is just the simple yes/no flag the core spec asks for.)
ALTER TABLE activity_instance
    ADD COLUMN IF NOT EXISTS RepeatFault BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS WarrantyApplicable BOOLEAN NOT NULL DEFAULT FALSE;

-- The brief's own example activity table records labour hours per
-- mechanic on the activity (two mechanics on the same brake service,
-- each logging their own hours), not one total per activity. Move
-- LabourHours down to the join table; activity_instance.LabourHours is
-- kept and automatically maintained (see trigger below) as the sum, so
-- every existing query that reads it keeps working unchanged.
ALTER TABLE activity_instance_worker_assigned
    ADD COLUMN IF NOT EXISTS LabourHours DECIMAL(4,2) NULL;

-- Backfill: for any activity created before this column existed, give
-- each assigned mechanic the activity's existing total (matches how
-- the seed data already recorded it, e.g. two mechanics both logging
-- the same hours on a shared brake service).
UPDATE activity_instance_worker_assigned aiwa
JOIN activity_instance ai ON aiwa.ActivityID = ai.ActivityID
SET aiwa.LabourHours = ai.LabourHours
WHERE aiwa.LabourHours IS NULL;

-- ---------------------------------------------------------------------
-- 2. Keep activity_instance.LabourHours in sync with the per-mechanic
--    hours recorded against it.
-- ---------------------------------------------------------------------

DELIMITER $$

DROP TRIGGER IF EXISTS trg_activity_worker_hours_after_insert$$
CREATE TRIGGER trg_activity_worker_hours_after_insert
AFTER INSERT ON activity_instance_worker_assigned
FOR EACH ROW
BEGIN
    UPDATE activity_instance
    SET LabourHours = (
        SELECT SUM(LabourHours) FROM activity_instance_worker_assigned WHERE ActivityID = NEW.ActivityID
    )
    WHERE ActivityID = NEW.ActivityID;
END$$

DROP TRIGGER IF EXISTS trg_activity_worker_hours_after_update$$
CREATE TRIGGER trg_activity_worker_hours_after_update
AFTER UPDATE ON activity_instance_worker_assigned
FOR EACH ROW
BEGIN
    UPDATE activity_instance
    SET LabourHours = (
        SELECT SUM(LabourHours) FROM activity_instance_worker_assigned WHERE ActivityID = NEW.ActivityID
    )
    WHERE ActivityID = NEW.ActivityID;
END$$

DROP TRIGGER IF EXISTS trg_activity_worker_hours_after_delete$$
CREATE TRIGGER trg_activity_worker_hours_after_delete
AFTER DELETE ON activity_instance_worker_assigned
FOR EACH ROW
BEGIN
    UPDATE activity_instance
    SET LabourHours = (
        SELECT SUM(LabourHours) FROM activity_instance_worker_assigned WHERE ActivityID = OLD.ActivityID
    )
    WHERE ActivityID = OLD.ActivityID;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 3. Monthly safety score, computed from behaviour_event.
--
-- Base 100, minus per-event penalties (Low -2, Medium -5, High -10,
-- Critical -20), minus the additional flat deductions in the brief's
-- Event Penalties table (>3 speeding events in the month: -10, >2
-- fatigue warnings in the month: -15, any critical event: -10 on top
-- of that event's own -20). Clamped to [0, 100].
--
-- sp_recalculate_driver_month_score recomputes a single driver/month
-- and upserts it into monthly_score_log. It is called automatically
-- whenever a behaviour_event is inserted, updated, or deleted (via the
-- triggers below), and can also be called directly to fix up one
-- driver/month. sp_recalculate_all_monthly_scores rebuilds every
-- driver/month that has at least one recorded event -- useful for a
-- one-off backfill after loading historical event data, or from an
-- admin "recalculate scores" action.
-- ---------------------------------------------------------------------

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_recalculate_driver_month_score$$
CREATE PROCEDURE sp_recalculate_driver_month_score(
    IN p_driver VARCHAR(50), IN p_year INT, IN p_month INT
)
BEGIN
    DECLARE v_low INT DEFAULT 0;
    DECLARE v_medium INT DEFAULT 0;
    DECLARE v_high INT DEFAULT 0;
    DECLARE v_critical INT DEFAULT 0;
    DECLARE v_speeding INT DEFAULT 0;
    DECLARE v_fatigue INT DEFAULT 0;
    DECLARE v_deduction INT DEFAULT 0;
    DECLARE v_score INT DEFAULT 100;

    IF p_driver IS NOT NULL AND p_year IS NOT NULL AND p_month IS NOT NULL THEN
        SELECT
            COALESCE(SUM(sl.LevelName = 'Low'), 0),
            COALESCE(SUM(sl.LevelName = 'Medium'), 0),
            COALESCE(SUM(sl.LevelName = 'High'), 0),
            COALESCE(SUM(sl.LevelName = 'Critical'), 0),
            COALESCE(SUM(be.EventType = 'Speeding'), 0),
            COALESCE(SUM(be.EventType = 'Fatigue Warning'), 0)
        INTO v_low, v_medium, v_high, v_critical, v_speeding, v_fatigue
        FROM behaviour_event be
        JOIN severity_level sl ON be.SeverityID = sl.SeverityID
        WHERE be.DriverID = p_driver
          AND YEAR(be.Timestamp) = p_year
          AND MONTH(be.Timestamp) = p_month;

        SET v_deduction = (v_low * 2) + (v_medium * 5) + (v_high * 10) + (v_critical * 20);

        IF v_speeding > 3 THEN
            SET v_deduction = v_deduction + 10;
        END IF;
        IF v_fatigue > 2 THEN
            SET v_deduction = v_deduction + 15;
        END IF;
        IF v_critical >= 1 THEN
            SET v_deduction = v_deduction + 10;
        END IF;

        SET v_score = GREATEST(0, LEAST(100, 100 - v_deduction));

        INSERT INTO monthly_score_log (DriverID, Month, Year, Score)
        VALUES (p_driver, p_month, p_year, v_score)
        ON DUPLICATE KEY UPDATE Score = v_score;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_recalculate_all_monthly_scores$$
CREATE PROCEDURE sp_recalculate_all_monthly_scores()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE c_driver VARCHAR(50);
    DECLARE c_year INT;
    DECLARE c_month INT;
    DECLARE cur CURSOR FOR
        SELECT DISTINCT DriverID, YEAR(Timestamp), MONTH(Timestamp)
        FROM behaviour_event
        WHERE DriverID IS NOT NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO c_driver, c_year, c_month;
        IF done THEN
            LEAVE read_loop;
        END IF;
        CALL sp_recalculate_driver_month_score(c_driver, c_year, c_month);
    END LOOP;
    CLOSE cur;
END$$

DROP TRIGGER IF EXISTS trg_behaviour_event_score_after_insert$$
CREATE TRIGGER trg_behaviour_event_score_after_insert
AFTER INSERT ON behaviour_event
FOR EACH ROW
BEGIN
    IF NEW.DriverID IS NOT NULL THEN
        CALL sp_recalculate_driver_month_score(NEW.DriverID, YEAR(NEW.Timestamp), MONTH(NEW.Timestamp));
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_behaviour_event_score_after_update$$
CREATE TRIGGER trg_behaviour_event_score_after_update
AFTER UPDATE ON behaviour_event
FOR EACH ROW
BEGIN
    IF OLD.DriverID IS NOT NULL THEN
        CALL sp_recalculate_driver_month_score(OLD.DriverID, YEAR(OLD.Timestamp), MONTH(OLD.Timestamp));
    END IF;
    IF NEW.DriverID IS NOT NULL
       AND (NEW.DriverID <> OLD.DriverID
            OR YEAR(NEW.Timestamp) <> YEAR(OLD.Timestamp)
            OR MONTH(NEW.Timestamp) <> MONTH(OLD.Timestamp)) THEN
        CALL sp_recalculate_driver_month_score(NEW.DriverID, YEAR(NEW.Timestamp), MONTH(NEW.Timestamp));
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_behaviour_event_score_after_delete$$
CREATE TRIGGER trg_behaviour_event_score_after_delete
AFTER DELETE ON behaviour_event
FOR EACH ROW
BEGIN
    IF OLD.DriverID IS NOT NULL THEN
        CALL sp_recalculate_driver_month_score(OLD.DriverID, YEAR(OLD.Timestamp), MONTH(OLD.Timestamp));
    END IF;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 4. Assignment eligibility enforcement.
--
-- sp_check_assignment_eligibility raises SIGNAL SQLSTATE '45000' (which
-- aborts the triggering INSERT/UPDATE and surfaces MESSAGE_TEXT back to
-- the caller -- mysqli throws this as a mysqli_sql_exception with that
-- text as getMessage()) the first time it finds a rule violated. Reused
-- by both the INSERT and UPDATE triggers below so a reactivated
-- assignment is checked exactly the same way as a new one.
--
-- Certification and score checks are evaluated as of the assignment's
-- StartDate rather than "today". This matters for a fleet management
-- system that also records historical assignments: a driver who held a
-- valid certificate when an assignment began, which has since lapsed
-- without the assignment being renewed, is still a legitimate historical
-- record (and is exactly what view_unauthorized_vehicle_operation and
-- view_expired_certifications exist to surface for the fleet manager to
-- act on) -- it should not make importing that history impossible.
-- Vehicle status is checked against its current value, since the vehicle
-- table only tracks current status, not a history of status changes.
-- ---------------------------------------------------------------------

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_check_assignment_eligibility$$
CREATE PROCEDURE sp_check_assignment_eligibility(
    IN p_vehicle VARCHAR(50), IN p_driver VARCHAR(50), IN p_start_date DATE
)
BEGIN
    DECLARE v_status VARCHAR(255);
    DECLARE v_classification INT;
    DECLARE v_missing_cert VARCHAR(255);
    DECLARE v_score INT;
    DECLARE v_unresolved_critical INT DEFAULT 0;
    DECLARE eff_date DATE;

    SET eff_date = COALESCE(p_start_date, CURDATE());

    -- Rule: a vehicle Under Maintenance or Out of Service cannot be assigned.
    SELECT vs.StatusName, v.ClassificationID INTO v_status, v_classification
    FROM vehicle v
    JOIN vehicle_status vs ON v.StatusID = vs.StatusID
    WHERE v.VehicleID = p_vehicle;

    IF v_status IN ('Under Maintenance', 'Out of Service') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This vehicle cannot be assigned while Under Maintenance or Out of Service.';
    END IF;

    -- Rule: driver must hold every certification the vehicle's category
    -- requires, unexpired as of the assignment start date.
    SELECT vct.CertificationName INTO v_missing_cert
    FROM vehicle_type_certification_requirement vtcr
    JOIN vehicle_certification_type vct ON vct.CertificationTypeID = vtcr.CertificationTypeID
    WHERE vtcr.ClassificationID = v_classification
      AND NOT EXISTS (
          SELECT 1 FROM driver_certification_owned dco
          WHERE dco.DriverID = p_driver
            AND dco.CertificationTypeID = vtcr.CertificationTypeID
            AND dco.IssueDate <= eff_date
            AND (dco.ExpiryDate IS NULL OR dco.ExpiryDate >= eff_date)
      )
    LIMIT 1;

    IF v_missing_cert IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Driver is missing a required, unexpired certification for this vehicle category.';
    END IF;

    -- Rule: a driver with a safety score of 50 or below cannot be
    -- assigned until they complete safety training. Uses the most
    -- recent scored month at or before the assignment start; if the
    -- driver has no score history yet, this check is skipped rather
    -- than blocking on missing data.
    SELECT msl.Score INTO v_score
    FROM monthly_score_log msl
    WHERE msl.DriverID = p_driver
      AND (msl.Year < YEAR(eff_date) OR (msl.Year = YEAR(eff_date) AND msl.Month <= MONTH(eff_date)))
    ORDER BY msl.Year DESC, msl.Month DESC
    LIMIT 1;

    IF v_score IS NOT NULL AND v_score <= 50 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Driver safety score is 50 or below and must complete safety training before being assigned.';
    END IF;

    -- Rule: a critical event makes a driver inactive (unassignable)
    -- until the review is completed (a coaching_log entry recorded
    -- against that event) or they complete safety training.
    SELECT COUNT(*) INTO v_unresolved_critical
    FROM behaviour_event be
    JOIN severity_level sl ON be.SeverityID = sl.SeverityID
    WHERE be.DriverID = p_driver
      AND sl.LevelName = 'Critical'
      AND DATE(be.Timestamp) <= eff_date
      AND NOT EXISTS (
          SELECT 1 FROM coaching_log cl
          WHERE cl.EventID = be.EventID AND cl.CoachDate <= eff_date
      );

    IF v_unresolved_critical > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Driver has an unresolved critical safety event and cannot be assigned until it is reviewed.';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_vehicle_driver_assignment_before_insert$$
CREATE TRIGGER trg_vehicle_driver_assignment_before_insert
BEFORE INSERT ON vehicle_driver_assignment
FOR EACH ROW
BEGIN
    CALL sp_check_assignment_eligibility(NEW.VehicleID, NEW.DriverID, NEW.StartDate);
END$$

-- Only re-checked when an assignment is being (re)activated -- i.e. it
-- is ending up with EndDate NULL and either wasn't active before, or
-- the vehicle/driver on the row is changing. Ending an assignment
-- (setting EndDate) never trips this.
DROP TRIGGER IF EXISTS trg_vehicle_driver_assignment_before_update$$
CREATE TRIGGER trg_vehicle_driver_assignment_before_update
BEFORE UPDATE ON vehicle_driver_assignment
FOR EACH ROW
BEGIN
    IF NEW.EndDate IS NULL
       AND (OLD.EndDate IS NOT NULL OR NEW.VehicleID <> OLD.VehicleID OR NEW.DriverID <> OLD.DriverID) THEN
        CALL sp_check_assignment_eligibility(NEW.VehicleID, NEW.DriverID, NEW.StartDate);
    END IF;
END$$

DELIMITER ;
