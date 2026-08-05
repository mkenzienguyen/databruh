-- Apply manually with: mysql -u root databruh_db < suggested_indexes.sql
-- Targets query patterns not already covered by PRIMARY KEY / FOREIGN KEY
-- auto-indexes.

CREATE INDEX idx_alert_status_timestamp ON alert (Status, AlertTimestamp);

CREATE INDEX idx_maintenance_job_status ON maintenance_job (Status);
CREATE INDEX idx_maintenance_job_startdate ON maintenance_job (StartDate);

-- Vehicle -> current driver, and driver -> current vehicle lookups
-- ("... AND EndDate IS NULL") aren't covered by the single-column FK
-- indexes alone.
CREATE INDEX idx_vda_vehicle_enddate ON vehicle_driver_assignment (VehicleID, EndDate);
CREATE INDEX idx_vda_driver_enddate ON vehicle_driver_assignment (DriverID, EndDate);

CREATE INDEX idx_driver_fullname ON driver (FullName);
CREATE INDEX idx_mechanic_worker_fullname ON mechanic_worker (FullName);
CREATE INDEX idx_part_partname ON part (PartName);

CREATE INDEX idx_behaviour_event_driver_timestamp ON behaviour_event (DriverID, Timestamp);

-- supplier_product_list had no PK/UNIQUE constraint at all beyond the
-- two auto FK indexes, so the same (PartID, PartnerID) pair could be
-- inserted twice.
CREATE UNIQUE INDEX idx_supplier_product_list_part_partner ON supplier_product_list (PartID, PartnerID);

-- Matches sp_check_assignment_eligibility's ORDER BY Year DESC, Month
-- DESC LIMIT 1 (business_rules.sql), which the existing UNIQUE(DriverID,
-- Month, Year) column order doesn't serve without a filesort.
CREATE INDEX idx_monthly_score_log_driver_year_month ON monthly_score_log (DriverID, Year, Month);

CREATE INDEX idx_coaching_log_driver_outcome ON coaching_log (DriverID, Outcome);
CREATE INDEX idx_driver_cert_owned_expiry ON driver_certification_owned (ExpiryDate);

-- Not fixable by an index: MONTH()/YEAR(Timestamp) filters (dashboard
-- stat, sp_recalculate_driver_month_score) are non-sargable and need a
-- sargable range rewrite instead. view_parts_below_reorder's
-- QuantityOnHand <= ReorderThreshold compares two columns of the same
-- row, which no B-tree index can accelerate either.
