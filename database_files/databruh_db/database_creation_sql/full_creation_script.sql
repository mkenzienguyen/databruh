DROP DATABASE databruh_db;
CREATE DATABASE databruh_db;
USE databruh_db;

CREATE TABLE depot_location (
    DepotID INT AUTO_INCREMENT PRIMARY KEY,
    DepotName VARCHAR(255) NOT NULL,
    DepotAddress VARCHAR(255)   
);

CREATE TABLE vehicle_status (
    StatusID INT AUTO_INCREMENT PRIMARY KEY,
    StatusName VARCHAR(255) NOT NULL
);

CREATE TABLE vehicle_classification (
    ClassificationID INT AUTO_INCREMENT PRIMARY KEY,
    ClassificationName VARCHAR(255) NOT NULL
);


CREATE TABLE vehicle_certification_type (
    CertificationTypeID INT AUTO_INCREMENT PRIMARY KEY,
    CertificationName VARCHAR(255) NOT NULL
);

CREATE TABLE vehicle_type_certification_requirement (
    ClassificationID INT,
    CertificationTypeID INT,
    PRIMARY KEY (ClassificationID, CertificationTypeID),
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE CASCADE,
    FOREIGN KEY (CertificationTypeID) REFERENCES vehicle_certification_type(CertificationTypeID) ON DELETE CASCADE
);

CREATE TABLE vehicle (
    VehicleID VARCHAR(50) PRIMARY KEY,
    RegistrationNumber VARCHAR(100) NOT NULL UNIQUE,
    Manufacturer VARCHAR(255),
    Model VARCHAR(255),
    ClassificationID INT,
    YearOfManufacture INT,
    StatusID INT,
    DepotID INT,
    CurrentOdometer INT NOT NULL DEFAULT 0,
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE SET NULL,
    FOREIGN KEY (StatusID) REFERENCES vehicle_status(StatusID) ON DELETE SET NULL,
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID) ON DELETE SET NULL
);

CREATE TABLE driver (
    DriverID VARCHAR(50) PRIMARY KEY,
    FullName VARCHAR(255) NOT NULL,
    DepotID INT,
    LicenseNumber VARCHAR(50) NOT NULL UNIQUE,
    LicenseExpiration DATE NOT NULL,
    EmploymentStatus VARCHAR(100) DEFAULT 'Active',
    ContactInfo VARCHAR(255),
    EmergencyContactDetails VARCHAR(100),
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID) ON DELETE SET NULL
);


CREATE TABLE vehicle_driver_assignment (
    AssignmentID INT AUTO_INCREMENT PRIMARY KEY,
    VehicleID VARCHAR(50) NOT NULL,
    DriverID VARCHAR(50) NOT NULL,
    StartDate DATE NOT NULL,
    EndDate DATE,
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID) ON DELETE CASCADE,
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE
);

CREATE TABLE driver_certification_owned (
    DriverID VARCHAR(50),
    CertificationTypeID INT,
    IssueDate DATE NOT NULL,
    ExpiryDate DATE,
    PRIMARY KEY (DriverID, CertificationTypeID),
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE,
    FOREIGN KEY (CertificationTypeID) REFERENCES vehicle_certification_type(CertificationTypeID) ON DELETE CASCADE
);


CREATE TABLE monthly_score_log (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    DriverID VARCHAR(50) NOT NULL,
    Month INT NOT NULL CHECK (Month BETWEEN 1 AND 12),
    Year INT NOT NULL,
    Score INT NOT NULL DEFAULT 100 CHECK (Score BETWEEN 0 AND 100),
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE,
    CONSTRAINT unique_driver_month_year UNIQUE (DriverID, Month, Year)
);


CREATE TABLE severity_level (
    SeverityID INT AUTO_INCREMENT PRIMARY KEY,
    LevelName VARCHAR(50) NOT NULL
);


CREATE TABLE behaviour_event (
    EventID INT AUTO_INCREMENT PRIMARY KEY,
    VehicleID VARCHAR(50) NOT NULL,
    DriverID VARCHAR(50) NULL,
    DepotID INT,
    Timestamp DATETIME NOT NULL,
    SeverityID INT,
    EventType VARCHAR(100) NOT NULL,
    Description TEXT,
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID),
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID),
    FOREIGN KEY (SeverityID) REFERENCES severity_level(SeverityID),
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID)
    
);

CREATE TABLE coaching_log (
    CoachingID INT AUTO_INCREMENT PRIMARY KEY,
    DriverID VARCHAR(50) NOT NULL,
    EventID INT NULL,
    CoachDate DATE NOT NULL,
    ConductedBy VARCHAR(255),
    Outcome VARCHAR(50) NOT NULL,
    Notes TEXT,
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE,
    FOREIGN KEY (EventID) REFERENCES behaviour_event(EventID) ON DELETE SET NULL
);

















CREATE TABLE workshop (
    WorkshopID INT AUTO_INCREMENT PRIMARY KEY,
    WorkshopName VARCHAR(255) NOT NULL,
    WorkshopAddress VARCHAR(255),
    DepotID INT,
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID) ON DELETE SET NULL,
    UNIQUE KEY uq_workshop_depot (DepotID)
);


CREATE TABLE alert (
    AlertID INT AUTO_INCREMENT PRIMARY KEY,
    AlertName VARCHAR(100) NOT NULL,
    VehicleID VARCHAR(50) NOT NULL,
    AlertDescription TEXT,
    AlertTimestamp DATETIME NOT NULL,
    Status VARCHAR(50) DEFAULT 'New',
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID) ON DELETE CASCADE
    
);

CREATE TABLE maintenance_job (
    JobID INT AUTO_INCREMENT PRIMARY KEY,
    VehicleID VARCHAR(50) NOT NULL,
    WorkshopID INT NOT NULL,
    StartDate DATETIME NOT NULL,
    EndDate DATETIME,
    Status VARCHAR(50),
    AlertID INT,
    ToTalCost INT,
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID),
    FOREIGN KEY (WorkshopID) REFERENCES workshop(WorkshopID),
    FOREIGN KEY (AlertID) REFERENCES alert(AlertID)

);


CREATE TABLE activity_certification (
    CertificationID INT AUTO_INCREMENT PRIMARY KEY,
    CertificationName VARCHAR(255) NOT NULL,
    Description TEXT
);



CREATE TABLE activity_type (
    ActivityTypeID INT AUTO_INCREMENT PRIMARY KEY,
    ActivityTypeName VARCHAR(255) NOT NULL UNIQUE,
    CertificationID INT,
    FOREIGN KEY (CertificationID) REFERENCES activity_certification(CertificationID) ON DELETE SET NULL
);


CREATE TABLE mechanic_worker (
    MechanicID VARCHAR(50) PRIMARY KEY,
    FullName VARCHAR(255) NOT NULL,
    EmploymentStatus VARCHAR(100),
    EmergencyContactDetails VARCHAR(100),
    WorkshopID INT,
    FOREIGN KEY (WorkshopID) REFERENCES workshop(WorkshopID) ON DELETE SET NULL
);


CREATE TABLE mechanic_worker_certifications_history (
    CertificationLogID INT AUTO_INCREMENT PRIMARY KEY,
    MechanicID VARCHAR(50),
    CertificationID INT,
    IssueDate DATE NOT NULL,
    ExpiryDate DATE,
    FOREIGN KEY (MechanicID) REFERENCES mechanic_worker(MechanicID),
    FOREIGN KEY (CertificationID) REFERENCES activity_certification(CertificationID)
);

CREATE TABLE activity_instance (
    ActivityID INT AUTO_INCREMENT PRIMARY KEY,
    JobID INT NOT NULL,
    ActivityTypeID INT NOT NULL,
    LabourHours DECIMAL(4,2),
    DiagnosticResult TEXT,
    RepeatFault BOOLEAN NOT NULL DEFAULT FALSE,
    WarrantyApplicable BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (JobID) REFERENCES maintenance_job(JobID) ON DELETE CASCADE,
    FOREIGN KEY (ActivityTypeID) REFERENCES activity_type(ActivityTypeID)
);

CREATE TABLE activity_instance_worker_assigned (
    ActivityID INT NOT NULL,
    MechanicID VARCHAR(50) NOT NULL,
    LabourHours DECIMAL(4,2) NULL,
    PRIMARY KEY (ActivityID, MechanicID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID) ON DELETE CASCADE,
    FOREIGN KEY (MechanicID) REFERENCES mechanic_worker(MechanicID) ON DELETE CASCADE
);



-- Per-classification service interval, used to flag overdue vehicles.
CREATE TABLE maintenance_schedule_rule (
    RuleID INT AUTO_INCREMENT PRIMARY KEY,
    ClassificationID INT NOT NULL,
    IntervalDays INT NOT NULL,
    Description VARCHAR(255),
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE CASCADE,
    UNIQUE KEY unique_classification_rule (ClassificationID)
);










CREATE TABLE partner_company (
    PartnerID INT AUTO_INCREMENT PRIMARY KEY,
    PartnerName VARCHAR(255),
    PartnerType VARCHAR(50),
    DeliveryLeadTimes VARCHAR(10),
    ContactInfo VARCHAR(255),
    Description  TEXT
);



CREATE TABLE part (
    PartID INT AUTO_INCREMENT PRIMARY KEY,
    PartName VARCHAR(255),
    PrimarySupplierID INT NOT NULL,
    BackupSupplierID INT,
    QuantityOnHand INT NOT NULL DEFAULT 0,
    ReorderThreshold INT NOT NULL DEFAULT 0,
    FOREIGN KEY (PrimarySupplierID) REFERENCES partner_company(PartnerID) ON DELETE CASCADE,
    FOREIGN KEY (BackupSupplierID) REFERENCES partner_company(PartnerID) ON DELETE CASCADE
);

CREATE TABLE supplier_product_list (
    PartID INT NOT NULL,
    PartnerID INT NOT NULL,
    CostPerUnit INT,
    Description TEXT,
    PRIMARY KEY (PartID, PartnerID),
    FOREIGN KEY (PartID) REFERENCES part(PartID) ON DELETE CASCADE,
    FOREIGN KEY (PartnerID) REFERENCES partner_company(PartnerID) ON DELETE CASCADE
);


CREATE TABLE warranty_claim (
    WarrantyClaimID VARCHAR(50) PRIMARY KEY,
    PartnerID INT NOT NULL,
    ActivityID INT NOT NULL,
    Status VARCHAR(20) DEFAULT 'On going',
    ClaimDate DATETIME NOT NULL,
    ClaimResolvedDate DATETIME,
    FOREIGN KEY (PartnerID) REFERENCES partner_company(PartnerID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID) ON DELETE CASCADE
);

CREATE TABLE warranty_part_list (
    WarrantyClaimID VARCHAR(50) NOT NULL,
    PartID INT NOT NULL,
    PRIMARY KEY (WarrantyClaimID, PartID),
    FOREIGN KEY (WarrantyClaimID) REFERENCES warranty_claim(WarrantyClaimID) ON DELETE CASCADE,
    FOREIGN KEY (PartID) REFERENCES part(PartID) ON DELETE CASCADE
);


CREATE TABLE activity_instance_part_used (
    ActivityID INT NOT NULL,
    PartID INT NOT NULL,
    QuantityUsed INT,
    SupplierID INT NULL,
    PRIMARY KEY (ActivityID, PartID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID) ON DELETE CASCADE,
    FOREIGN KEY (PartID) REFERENCES part(PartID) ON DELETE CASCADE,
    FOREIGN KEY (SupplierID) REFERENCES partner_company(PartnerID)

);



























-- Business Rules
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

-- 3. Monthly safety score, computed from behaviour_event.
-- Base 100, minus per-event penalties (Low -2, Medium -5, High -10,
-- Critical -20), minus flat deductions (>3 speeding: -10, >2 fatigue
-- warnings: -15, any critical event: -10 more). Clamped to [0, 100].
-- sp_recalculate_all_monthly_scores rebuilds every driver/month; useful
-- for backfilling after loading historical event data.

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

-- 4. Assignment eligibility enforcement.
-- Raises SIGNAL SQLSTATE '45000' on the first rule violated (mysqli
-- surfaces this as a mysqli_sql_exception). Certification/score checks
-- use the assignment's StartDate, not today, so historical assignments
-- stay valid even if a cert has since lapsed. Vehicle status uses its
-- current value only (no status history tracked).

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

    -- No score history yet skips this check rather than blocking.
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

-- Only re-checked when (re)activating an assignment; ending one (setting EndDate) never trips this.
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





























-- Base line defined data

-- Depot Locations
INSERT INTO depot_location (DepotName, DepotAddress) VALUES 
('Hanoi', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Phung Xa Commune, Thach That District, Hanoi'),
('Ho Chi Minh City', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Long Thanh My Ward, Thu Duc City, Ho Chi Minh City'),
('Da Nang', 'Road No. 5G, Da Nang High-Tech Park, Hoa Lien Commune, Hoa Vang District, Da Nang'),
('Can Tho', 'Block B-15, Street No. 2, Hung Phu 1 Industrial Zone, Dong Phu Ward, Cai Rang District, Can Tho');

-- Vehicle Classifications
INSERT INTO vehicle_classification (ClassificationName) VALUES
('Delivery Van'),
('Refrigerated Truck'),
('Electric Van'),
('Service Vehicle'),
('Heavy Transport Truck');

-- Vehicle Operational Statuses
INSERT INTO vehicle_status (StatusName) VALUES
('Active'),
('Available'),
('Under Maintenance'),
('Awaiting Inspection'),
('Out of Service'),
('Retired');

-- Vehicle Certification Types
INSERT INTO vehicle_certification_type (CertificationName) VALUES
('Standard Licence'),
('Heavy Vehicle Licence'),
('Refrigerated Transport Certification'),
('EV Certification'),
('Hazardous Goods Certification');

-- Severity Levels
INSERT INTO severity_level (LevelName) VALUES
('Low'),
('Medium'),
('High'),
('Critical');


-- Vehicle Type Certification Requirements
INSERT INTO vehicle_type_certification_requirement (ClassificationID, CertificationTypeID) VALUES
(1, 1), -- Delivery Van requires Standard Licence
(2, 1), -- Refrigerated Truck also requires Standard Licence
(2, 2), -- Refrigerated Truck requires Heavy Vehicle Licence
(2, 3), -- Refrigerated Truck also requires Refrigerated Transport Certification
(3, 1), -- Electric Van requires Standard Licence
(3, 4), -- Electric Van also requires EV Certification
(4, 1), -- Service Vehicle requires Standard Licence
(5, 2), -- Heavy Transport Truck requires Heavy Vehicle Licence
(5, 5); -- Heavy Transport Truck also requires Hazardous Goods Certification






INSERT INTO workshop (WorkshopID, WorkshopName, WorkshopAddress, DepotID) VALUES
(1, 'Ha Noi Central Workshop', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Hanoi', 1),
(2, 'HCMC South Workshop', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Thu Duc City, Ho Chi Minh City', 2),
(3, 'Da Nang Coastal Workshop', 'Road No. 5G, Da Nang High-Tech Park, Hoa Vang District, Da Nang', 3);

INSERT INTO activity_certification (CertificationID, CertificationName, Description) VALUES
(1, 'Standard Vehicle Mechanic Licence', 'Covers routine inspections, servicing, diagnostics, emergency repairs, and component replacements.'),
(2, 'EV Technician Certification', 'Required for electric vehicle battery and electrical repairs.'),
(3, 'Refrigeration Systems Certification', 'Required for refrigeration system repairs on cold-chain trucks.'),
(4, 'Heavy Vehicle Mechanic Licence', 'Required for repairs on heavy vehicle categories.');

INSERT INTO activity_type (ActivityTypeID, ActivityTypeName, CertificationID) VALUES
(1, 'Routine Inspection', 1),
(2, 'Preventative Servicing', 1),
(3, 'Diagnostic Testing', 1),
(4, 'Emergency Repair', 1),
(5, 'Component Replacement', 1),
(6, 'EV Battery / Electrical Repair', 2),
(7, 'Refrigeration System Repair', 3),
(8, 'Heavy Vehicle Repair', 4),
(9, 'Brake Service', 1),
(10, 'Tyre Replacement', 1);

INSERT INTO maintenance_schedule_rule (ClassificationID, IntervalDays, Description) VALUES
(1, 180, 'Delivery Van: routine service every 6 months'),
(2, 90, 'Refrigerated Truck: cold-chain duty cycle, serviced every 3 months'),
(3, 180, 'Electric Van: routine service every 6 months'),
(4, 120, 'Service Vehicle: serviced every 4 months'),
(5, 90, 'Heavy Transport Truck: serviced every 3 months');