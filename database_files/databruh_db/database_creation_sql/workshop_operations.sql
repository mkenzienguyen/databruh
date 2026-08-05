USE databruh_db;

CREATE TABLE IF NOT EXISTS maintenance_schedule_rule (
    RuleID INT AUTO_INCREMENT PRIMARY KEY,
    ClassificationID INT NOT NULL,
    IntervalDays INT NOT NULL,
    Description VARCHAR(255),
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE CASCADE,
    UNIQUE KEY unique_classification_rule (ClassificationID)
);

ALTER TABLE part
    ADD COLUMN QuantityOnHand INT NOT NULL DEFAULT 0,
    ADD COLUMN ReorderThreshold INT NOT NULL DEFAULT 0;

ALTER TABLE activity_instance_part_used
    ADD COLUMN SupplierID INT NULL,
    ADD CONSTRAINT fk_aipu_supplier FOREIGN KEY (SupplierID) REFERENCES partner_company(PartnerID);

UPDATE activity_instance_part_used aipu
JOIN part p ON aipu.PartID = p.PartID
SET aipu.SupplierID = p.PrimarySupplierID
WHERE aipu.SupplierID IS NULL;