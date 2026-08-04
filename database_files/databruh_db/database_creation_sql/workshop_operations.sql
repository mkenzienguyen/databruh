USE databruh_db;

-- Per-classification service interval, used to flag vehicles overdue for
-- service (see view_vehicles_overdue_for_service in basic_views.sql).
-- Updating a rule's IntervalDays only changes how *future* overdue checks
-- are evaluated — it never rewrites the maintenance_job history the check
-- is compared against.
CREATE TABLE IF NOT EXISTS maintenance_schedule_rule (
    RuleID INT AUTO_INCREMENT PRIMARY KEY,
    ClassificationID INT NOT NULL,
    IntervalDays INT NOT NULL,
    Description VARCHAR(255),
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE CASCADE,
    UNIQUE KEY unique_classification_rule (ClassificationID)
);

-- Stock tracking for reorder monitoring. Defaults to 0/0 so existing rows
-- don't break; workshop managers set real levels via the dashboard.
ALTER TABLE part
    ADD COLUMN IF NOT EXISTS QuantityOnHand INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ReorderThreshold INT NOT NULL DEFAULT 0;

-- Captures which supplier actually fulfilled a specific part usage, at
-- the time it was recorded. Without this, attribution would fall back to
-- part.PrimarySupplierID — a mutable field — so historical consumption
-- would silently get reattributed to a vehicle's *current* supplier if
-- that supplier is ever changed later. Nullable/backfilled for rows
-- recorded before this column existed.
ALTER TABLE activity_instance_part_used
    ADD COLUMN IF NOT EXISTS SupplierID INT NULL,
    ADD CONSTRAINT fk_aipu_supplier FOREIGN KEY IF NOT EXISTS (SupplierID) REFERENCES partner_company(PartnerID);

UPDATE activity_instance_part_used aipu
JOIN part p ON aipu.PartID = p.PartID
SET aipu.SupplierID = p.PrimarySupplierID
WHERE aipu.SupplierID IS NULL;
