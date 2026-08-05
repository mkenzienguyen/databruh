USE databruh_db;

-- Per-classification service interval, used to flag overdue vehicles.
CREATE TABLE IF NOT EXISTS maintenance_schedule_rule (
    RuleID INT AUTO_INCREMENT PRIMARY KEY,
    ClassificationID INT NOT NULL,
    IntervalDays INT NOT NULL,
    Description VARCHAR(255),
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE CASCADE,
    UNIQUE KEY unique_classification_rule (ClassificationID)
);

-- Stock tracking for reorder monitoring.
ALTER TABLE part
    ADD COLUMN IF NOT EXISTS QuantityOnHand INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ReorderThreshold INT NOT NULL DEFAULT 0;

-- Supplier that fulfilled each part usage; nullable/backfilled below for
-- rows recorded before this column existed.
ALTER TABLE activity_instance_part_used
    ADD COLUMN IF NOT EXISTS SupplierID INT NULL,
    ADD CONSTRAINT fk_aipu_supplier FOREIGN KEY IF NOT EXISTS (SupplierID) REFERENCES partner_company(PartnerID);

UPDATE activity_instance_part_used aipu
JOIN part p ON aipu.PartID = p.PartID
SET aipu.SupplierID = p.PrimarySupplierID
WHERE aipu.SupplierID IS NULL;

-- An event with no matching coaching_log row is an unresolved incident.
CREATE TABLE IF NOT EXISTS coaching_log (
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
