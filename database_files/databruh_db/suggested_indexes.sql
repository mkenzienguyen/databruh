-- =====================================================================
-- SUGGESTED INDEXES — databruh_db
-- =====================================================================
-- This file is a proposal only. It is NOT included in the creation/
-- insertion/views scripts and is NOT run automatically by anything in
-- this project. Nothing here has been applied to the working database.
-- Apply manually with, e.g.:
--   mysql -u root databruh_db < suggested_indexes.sql
--
-- Method: every WHERE / JOIN ON / ORDER BY / GROUP BY column actually
-- used across php_files/*.php and the views in
-- database_basic_views_sql/basic_views.sql was catalogued, then checked
-- against SHOW INDEX on the live schema to see what InnoDB already
-- covers automatically.
--
-- Baseline InnoDB already gives us for free, so these are NOT repeated
-- below:
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
-- Out of current scope
-- =====================================================================
-- warranty_claim, warranty_part_list, activity_instance_part_used, and
-- driver_certification_owned are defined and have views built on top
-- of them (view_active_warranty_ledger, view_part_consumption_lifecycle,
-- view_expired_certifications), but no page in php_files/ currently
-- queries them. Left unindexed beyond their existing PK/FK coverage
-- until a dashboard actually reads from them — indexing unused access
-- paths is guesswork, not evidence-based.
