USE databruh_db;

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

    SELECT vs.StatusName, v.ClassificationID INTO v_status, v_classification
    FROM vehicle v
    JOIN vehicle_status vs ON v.StatusID = vs.StatusID
    WHERE v.VehicleID = p_vehicle;

    IF v_status IN ('Under Maintenance', 'Out of Service') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'This vehicle cannot be assigned while Under Maintenance or Out of Service.';
    END IF;

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