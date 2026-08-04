USE databruh_db;

-- VIEW 1: Expired Driver Certifications
CREATE OR REPLACE VIEW view_expired_certifications AS
SELECT 
    d.DriverID,
    d.FullName AS DriverName,
    dl.DepotName AS AssignedDepot,
    vct.CertificationName,
    dco.ExpiryDate,
    'Expired' AS CertificationStatus
FROM driver_certification_owned dco
JOIN driver d ON dco.DriverID = d.DriverID
JOIN depot_location dl ON d.DepotID = dl.DepotID
JOIN vehicle_certification_type vct ON dco.CertificationTypeID = vct.CertificationTypeID
WHERE dco.ExpiryDate < CURDATE();

-- VIEW 2: Fleet Telematics & Driver Behavior Tracking
CREATE OR REPLACE VIEW view_driver_incidents AS
SELECT 
    be.EventID,
    be.Timestamp,
    v.RegistrationNumber AS VehiclePlate,
    vc.ClassificationName AS VehicleCategory,
    d.DriverID,
    d.FullName AS DriverName,
    dl.DepotName,
    be.EventType,
    sl.LevelName AS SeverityLevel,
    be.Description
FROM behaviour_event be
JOIN vehicle v ON be.VehicleID = v.VehicleID
JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
LEFT JOIN driver d ON be.DriverID = d.DriverID
LEFT JOIN depot_location dl ON be.DepotID = dl.DepotID
JOIN severity_level sl ON be.SeverityID = sl.SeverityID;

-- =====================================================================
-- VIEW 3: Workshop Maintenance Summaries
-- =====================================================================
CREATE OR REPLACE VIEW view_vehicle_maintenance_summary AS
SELECT 
    mj.JobID,
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    v.Manufacturer,
    v.Model,
    vc.ClassificationName AS VehicleCategory,
    w.WorkshopName,
    mj.StartDate AS OpenedDate,
    mj.EndDate AS ClosedDate,
    TIMESTAMPDIFF(HOUR, mj.StartDate, mj.EndDate) AS DowntimeHours,
    mj.TotalCost AS TotalCostVND,
    mj.Status AS JobStatus
FROM maintenance_job mj
JOIN vehicle v ON mj.VehicleID = v.VehicleID
JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
JOIN workshop w ON mj.WorkshopID = w.WorkshopID;


-- VIEW 4: Active Predictive Telematics Alerts
CREATE OR REPLACE VIEW view_active_alerts AS
SELECT 
    a.AlertID,
    a.AlertName,
    a.AlertDescription,
    a.AlertTimestamp,
    a.Status AS AlertStatus,
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    dl.DepotName,
    vs.StatusName AS CurrentVehicleStatus
FROM alert a
JOIN vehicle v ON a.VehicleID = v.VehicleID
JOIN depot_location dl ON v.DepotID = dl.DepotID
JOIN vehicle_status vs ON v.StatusID = vs.StatusID
WHERE a.Status IN ('New', 'Escalated');

-- =====================================================================
-- VIEW 5: Mechanic Workforce Qualifications Ledger
-- =====================================================================
CREATE OR REPLACE VIEW view_mechanic_certifications AS
SELECT 
    m.MechanicID,
    m.FullName AS MechanicName,
    w.WorkshopName,
    ac.CertificationName AS QualificationName,
    mch.ExpiryDate,
    CASE 
        WHEN mch.ExpiryDate < CURDATE() THEN 'Expired'
        ELSE 'Valid'
    END AS QualificationStatus
FROM mechanic_worker m
JOIN workshop w ON m.WorkshopID = w.WorkshopID
JOIN mechanic_worker_certifications_history mch ON m.MechanicID = mch.MechanicID
JOIN activity_certification ac ON mch.CertificationID = ac.CertificationID;


-- =====================================================================
-- VIEW 6: Part Replacement & Lifecycle Analysis
-- =====================================================================
CREATE OR REPLACE VIEW view_part_consumption_lifecycle AS
SELECT 
    p.PartID,
    p.PartName,
    pc.PartnerName AS PrimarySupplier,
    COUNT(DISTINCT mj.VehicleID) AS UniqueVehiclesAffected,
    COUNT(aipu.ActivityID) AS TotalReplacementIncidents,
    SUM(aipu.QuantityUsed) AS TotalQuantityConsumed,
    SUM(aipu.QuantityUsed * spl.CostPerUnit) AS CumulativePartsCostVND
FROM part p
JOIN partner_company pc ON p.PrimarySupplierID = pc.PartnerID
LEFT JOIN activity_instance_part_used aipu ON p.PartID = aipu.PartID
LEFT JOIN activity_instance ai ON aipu.ActivityID = ai.ActivityID
LEFT JOIN maintenance_job mj ON ai.JobID = mj.JobID
LEFT JOIN supplier_product_list spl ON p.PartID = spl.PartID AND p.PrimarySupplierID = spl.PartnerID
GROUP BY p.PartID, p.PartName, pc.PartnerName;

-- =====================================================================
-- VIEW 6: Warranty Claims Checker
-- =====================================================================
CREATE OR REPLACE VIEW view_active_warranty_ledger AS
SELECT 
    wc.WarrantyClaimID,
    mj.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    pc.PartnerName AS WarrantyProvider,
    at.ActivityTypeName AS OperationalContext,
    p.PartName AS ClaimedComponent,
    wc.ClaimDate,
    wc.Status AS ClaimStatus,
    spl.CostPerUnit AS EstimatedRecoveryValueVND
FROM warranty_claim wc
JOIN partner_company pc ON wc.PartnerID = pc.PartnerID
JOIN activity_instance ai ON wc.ActivityID = ai.ActivityID
JOIN activity_type at ON ai.ActivityTypeID = at.ActivityTypeID
JOIN maintenance_job mj ON ai.JobID = mj.JobID
JOIN vehicle v ON mj.VehicleID = v.VehicleID
JOIN warranty_part_list wpl ON wc.WarrantyClaimID = wpl.WarrantyClaimID
JOIN part p ON wpl.PartID = p.PartID
LEFT JOIN supplier_product_list spl ON p.PartID = spl.PartID AND pc.PartnerID = spl.PartnerID;

-- =====================================================================
-- VIEW 7: Driver Monthly Score Anomaly Detection
--
-- Statistical (Z-score) outlier detection: for each driver, compares
-- every monthly score against that same driver's own historical mean
-- and standard deviation (STDDEV_SAMP), rather than a fleet-wide or
-- hand-picked threshold. A driver needs at least 2 recorded months
-- before a baseline can be established; until then ZScore/AnomalyStatus
-- are NULL/'Insufficient history' rather than dividing by zero.
-- Thresholds follow the common outlier convention of |Z| >= 2 for a
-- strong outlier, |Z| >= 1 for a mild one — applied only to score DROPS
-- (negative Z), since a score rising above a driver's own average is
-- not a safety concern.
-- =====================================================================
CREATE OR REPLACE VIEW view_driver_score_anomalies AS
SELECT
    d.DriverID,
    d.FullName AS DriverName,
    dl.DepotName,
    msl.Year,
    msl.Month,
    msl.Score,
    ROUND(stats.AvgScore, 2) AS DriverAvgScore,
    ROUND(stats.StdDevScore, 2) AS DriverStdDevScore,
    stats.MonthsRecorded,
    CASE
        WHEN stats.MonthsRecorded < 2 OR stats.StdDevScore IS NULL OR stats.StdDevScore = 0
            THEN NULL
        ELSE ROUND((msl.Score - stats.AvgScore) / stats.StdDevScore, 2)
    END AS ZScore,
    CASE
        WHEN stats.MonthsRecorded < 2 OR stats.StdDevScore IS NULL OR stats.StdDevScore = 0
            THEN 'Insufficient history'
        WHEN (msl.Score - stats.AvgScore) / stats.StdDevScore <= -2
            THEN 'Critical anomaly'
        WHEN (msl.Score - stats.AvgScore) / stats.StdDevScore <= -1
            THEN 'Notable anomaly'
        ELSE 'Within normal range'
    END AS AnomalyStatus
FROM monthly_score_log msl
JOIN driver d ON msl.DriverID = d.DriverID
LEFT JOIN depot_location dl ON d.DepotID = dl.DepotID
JOIN (
    SELECT
        DriverID,
        AVG(Score) AS AvgScore,
        STDDEV_SAMP(Score) AS StdDevScore,
        COUNT(*) AS MonthsRecorded
    FROM monthly_score_log
    GROUP BY DriverID
) stats ON stats.DriverID = msl.DriverID
ORDER BY ZScore ASC;

-- =====================================================================
-- VIEW 8: Incident Review & Resolution
--
-- Every behaviour_event, joined to its coaching_log entry (if any). An
-- event with no matching coaching_log row is 'Unresolved' — this is the
-- single source of truth for "review driver incidents" and "monitor
-- unresolved incidents" on the fleet manager dashboard, and doubles as
-- the base table for driver/vehicle/depot/event type/severity/date
-- range search.
-- =====================================================================
CREATE OR REPLACE VIEW view_incident_resolution AS
SELECT
    vdi.EventID,
    vdi.Timestamp,
    vdi.VehiclePlate,
    vdi.VehicleCategory,
    vdi.DriverID,
    vdi.DriverName,
    vdi.DepotName,
    vdi.EventType,
    vdi.SeverityLevel,
    vdi.Description,
    cl.CoachingID,
    cl.CoachDate,
    cl.ConductedBy,
    cl.Outcome AS CoachingOutcome,
    cl.Notes AS CoachingNotes,
    CASE WHEN cl.CoachingID IS NULL THEN 'Unresolved' ELSE 'Resolved' END AS ResolutionStatus
FROM view_driver_incidents vdi
LEFT JOIN coaching_log cl ON cl.EventID = vdi.EventID;

-- =====================================================================
-- VIEW 9: Driver Risk & Retraining Summary
--
-- Per-driver incident counts (used to monitor high-risk drivers) plus a
-- count of coaching_log rows explicitly flagged 'Retraining Required'
-- (used to identify drivers requiring retraining). Severity thresholds
-- for what counts as "high risk" are applied in the application layer,
-- not baked into the view, so the dashboard can tune them without a
-- schema change.
-- =====================================================================
CREATE OR REPLACE VIEW view_driver_risk_summary AS
SELECT
    d.DriverID,
    d.FullName AS DriverName,
    dl.DepotName,
    COUNT(be.EventID) AS TotalIncidents,
    SUM(CASE WHEN sl.LevelName = 'Critical' THEN 1 ELSE 0 END) AS CriticalIncidents,
    SUM(CASE WHEN sl.LevelName = 'High' THEN 1 ELSE 0 END) AS HighIncidents,
    SUM(CASE WHEN sl.LevelName IN ('High', 'Critical') THEN 1 ELSE 0 END) AS SevereIncidents,
    MAX(be.Timestamp) AS MostRecentIncident,
    (SELECT COUNT(*) FROM coaching_log cl WHERE cl.DriverID = d.DriverID AND cl.Outcome = 'Retraining Required') AS RetrainingFlags
FROM driver d
LEFT JOIN depot_location dl ON d.DepotID = dl.DepotID
LEFT JOIN behaviour_event be ON be.DriverID = d.DriverID
LEFT JOIN severity_level sl ON be.SeverityID = sl.SeverityID
GROUP BY d.DriverID, d.FullName, dl.DepotName;

-- =====================================================================
-- VIEW 10: Repeat Speeding Drivers
-- =====================================================================
CREATE OR REPLACE VIEW view_repeat_speeding_drivers AS
SELECT
    d.DriverID,
    d.FullName AS DriverName,
    dl.DepotName,
    COUNT(*) AS SpeedingIncidents,
    MAX(be.Timestamp) AS MostRecentSpeedingIncident
FROM behaviour_event be
JOIN driver d ON be.DriverID = d.DriverID
LEFT JOIN depot_location dl ON d.DepotID = dl.DepotID
WHERE be.EventType = 'Speeding'
GROUP BY d.DriverID, d.FullName, dl.DepotName
HAVING COUNT(*) >= 2
ORDER BY SpeedingIncidents DESC;

-- =====================================================================
-- VIEW 11: Vehicles Associated With Severe Incidents
-- =====================================================================
CREATE OR REPLACE VIEW view_severe_incident_vehicles AS
SELECT
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    vc.ClassificationName AS VehicleCategory,
    dl.DepotName,
    COUNT(*) AS SevereIncidentCount,
    MAX(be.Timestamp) AS MostRecentSevereIncident
FROM behaviour_event be
JOIN vehicle v ON be.VehicleID = v.VehicleID
LEFT JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
LEFT JOIN depot_location dl ON v.DepotID = dl.DepotID
JOIN severity_level sl ON be.SeverityID = sl.SeverityID
WHERE sl.LevelName IN ('High', 'Critical')
GROUP BY v.VehicleID, v.RegistrationNumber, vc.ClassificationName, dl.DepotName
ORDER BY SevereIncidentCount DESC;

-- =====================================================================
-- VIEW 12: Drivers Operating Outside Their Authorised Vehicle Categories
--
-- For every currently active vehicle-driver assignment, checks every
-- certification that vehicle's classification requires against the
-- driver's certification_owned records. A row here means the driver is
-- missing (or has let expire) a certification their currently assigned
-- vehicle requires.
-- =====================================================================
CREATE OR REPLACE VIEW view_unauthorized_vehicle_operation AS
SELECT
    d.DriverID,
    d.FullName AS DriverName,
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    vc.ClassificationName AS VehicleCategory,
    vct.CertificationName AS MissingCertification,
    vda.StartDate AS AssignmentStart
FROM vehicle_driver_assignment vda
JOIN driver d ON vda.DriverID = d.DriverID
JOIN vehicle v ON vda.VehicleID = v.VehicleID
JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
JOIN vehicle_type_certification_requirement vtcr ON vtcr.ClassificationID = vc.ClassificationID
JOIN vehicle_certification_type vct ON vct.CertificationTypeID = vtcr.CertificationTypeID
WHERE vda.EndDate IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM driver_certification_owned dco
      WHERE dco.DriverID = d.DriverID
        AND dco.CertificationTypeID = vtcr.CertificationTypeID
        AND (dco.ExpiryDate IS NULL OR dco.ExpiryDate >= CURDATE())
  );

-- =====================================================================
-- VIEW 13: Vehicles Requiring Urgent Repair
--
-- A vehicle needs urgent attention if it has an unresolved (New or
-- Escalated) predictive alert, or its current status is Out of Service.
-- =====================================================================
CREATE OR REPLACE VIEW view_urgent_repair_vehicles AS
SELECT DISTINCT
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    vc.ClassificationName AS VehicleCategory,
    dl.DepotName,
    vs.StatusName AS VehicleStatus,
    a.AlertID,
    a.AlertName,
    a.Status AS AlertStatus,
    a.AlertTimestamp
FROM vehicle v
LEFT JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
LEFT JOIN depot_location dl ON v.DepotID = dl.DepotID
LEFT JOIN vehicle_status vs ON v.StatusID = vs.StatusID
LEFT JOIN alert a ON a.VehicleID = v.VehicleID AND a.Status IN ('New', 'Escalated')
WHERE vs.StatusName = 'Out of Service' OR a.AlertID IS NOT NULL
ORDER BY a.AlertTimestamp DESC;

-- =====================================================================
-- VIEW 14: Vehicles Awaiting Inspection
-- =====================================================================
CREATE OR REPLACE VIEW view_vehicles_awaiting_inspection AS
SELECT
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    vc.ClassificationName AS VehicleCategory,
    dl.DepotName,
    v.CurrentOdometer
FROM vehicle v
JOIN vehicle_status vs ON v.StatusID = vs.StatusID
LEFT JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
LEFT JOIN depot_location dl ON v.DepotID = dl.DepotID
WHERE vs.StatusName = 'Awaiting Inspection';

-- =====================================================================
-- VIEW 15: Workshop Workload
-- =====================================================================
CREATE OR REPLACE VIEW view_workshop_workload AS
SELECT
    w.WorkshopID,
    w.WorkshopName,
    dl.DepotName,
    COUNT(DISTINCT CASE WHEN mj.Status <> 'Closed' THEN mj.JobID END) AS OpenJobs,
    COUNT(DISTINCT CASE WHEN mj.Status = 'Closed' THEN mj.JobID END) AS ClosedJobs,
    COUNT(DISTINCT mw.MechanicID) AS MechanicsOnStaff
FROM workshop w
LEFT JOIN depot_location dl ON w.DepotID = dl.DepotID
LEFT JOIN maintenance_job mj ON mj.WorkshopID = w.WorkshopID
LEFT JOIN mechanic_worker mw ON mw.WorkshopID = w.WorkshopID
GROUP BY w.WorkshopID, w.WorkshopName, dl.DepotName;

-- =====================================================================
-- VIEW 16: Maintenance Cost By Vehicle Model
-- =====================================================================
CREATE OR REPLACE VIEW view_maintenance_cost_by_model AS
SELECT
    v.Manufacturer,
    v.Model,
    COUNT(mj.JobID) AS ClosedJobs,
    SUM(mj.ToTalCost) AS TotalCostVND,
    ROUND(AVG(mj.ToTalCost), 0) AS AvgCostPerJobVND,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, mj.StartDate, mj.EndDate)), 1) AS AvgDowntimeHours
FROM maintenance_job mj
JOIN vehicle v ON mj.VehicleID = v.VehicleID
WHERE mj.Status = 'Closed'
GROUP BY v.Manufacturer, v.Model
ORDER BY TotalCostVND DESC;

-- =====================================================================
-- VIEW 17: Vehicles Overdue For Service
--
-- Compares each vehicle's last closed maintenance_job against its
-- classification's current maintenance_schedule_rule. A vehicle with no
-- service history is measured from an assumed commission date of
-- January 1 of its YearOfManufacture. Changing a rule's IntervalDays
-- only changes this forward-looking comparison — it never rewrites the
-- underlying maintenance_job rows.
-- =====================================================================
CREATE OR REPLACE VIEW view_vehicles_overdue_for_service AS
SELECT
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    vc.ClassificationName AS VehicleCategory,
    dl.DepotName,
    msr.IntervalDays,
    lastService.LastServiceDate,
    COALESCE(
        DATEDIFF(CURDATE(), lastService.LastServiceDate),
        DATEDIFF(CURDATE(), MAKEDATE(v.YearOfManufacture, 1))
    ) AS DaysSinceService
FROM vehicle v
JOIN vehicle_classification vc ON v.ClassificationID = vc.ClassificationID
LEFT JOIN depot_location dl ON v.DepotID = dl.DepotID
JOIN maintenance_schedule_rule msr ON msr.ClassificationID = v.ClassificationID
LEFT JOIN (
    SELECT VehicleID, MAX(EndDate) AS LastServiceDate
    FROM maintenance_job
    WHERE Status = 'Closed'
    GROUP BY VehicleID
) lastService ON lastService.VehicleID = v.VehicleID
HAVING DaysSinceService > msr.IntervalDays
ORDER BY DaysSinceService DESC;

-- =====================================================================
-- VIEW 18: Vehicles With Repeated Component Failures
--
-- Flags a vehicle when the same activity type (e.g. "Brake Service")
-- has been performed on it two or more times — a signal of a recurring
-- fault rather than routine maintenance.
-- =====================================================================
CREATE OR REPLACE VIEW view_repeated_component_failures AS
SELECT
    v.VehicleID,
    v.RegistrationNumber AS VehiclePlate,
    at.ActivityTypeName,
    COUNT(*) AS OccurrenceCount,
    MAX(mj.StartDate) AS MostRecentOccurrence
FROM activity_instance ai
JOIN maintenance_job mj ON ai.JobID = mj.JobID
JOIN vehicle v ON mj.VehicleID = v.VehicleID
JOIN activity_type at ON ai.ActivityTypeID = at.ActivityTypeID
GROUP BY v.VehicleID, v.RegistrationNumber, at.ActivityTypeName
HAVING COUNT(*) >= 2
ORDER BY OccurrenceCount DESC;

-- =====================================================================
-- VIEW 19: Parts Below Reorder Threshold
-- =====================================================================
CREATE OR REPLACE VIEW view_parts_below_reorder AS
SELECT
    p.PartID,
    p.PartName,
    p.QuantityOnHand,
    p.ReorderThreshold,
    pc.PartnerName AS PrimarySupplier
FROM part p
JOIN partner_company pc ON p.PrimarySupplierID = pc.PartnerID
WHERE p.QuantityOnHand <= p.ReorderThreshold
ORDER BY (p.ReorderThreshold - p.QuantityOnHand) DESC;

-- =====================================================================
-- VIEW 20: Supplier Performance
--
-- Parts supplied is attributed via activity_instance_part_used.SupplierID
-- (the supplier that actually fulfilled each recorded usage), not the
-- part's current PrimarySupplierID, so this stays accurate even after a
-- part's primary supplier changes.
-- =====================================================================
-- =====================================================================
-- VIEW 21: Coaching / Training Compliance
--
-- "A driver with a score of 75 or below must attend driver coaching. A
-- driver with a safety score of 50 or below cannot be assigned to a
-- vehicle until they complete safety training." Surfaces this against
-- each driver's most recent scored month (the assignment-eligibility
-- trigger in business_rules.sql enforces the <=50 half of this at the
-- database layer; this view is what lets the fleet manager see it, and
-- act on the <=75 half, which is a monitoring requirement rather than a
-- hard block).
-- =====================================================================
CREATE OR REPLACE VIEW view_coaching_required AS
SELECT
    d.DriverID,
    d.FullName AS DriverName,
    dl.DepotName,
    latest.Year,
    latest.Month,
    latest.Score,
    CASE
        WHEN latest.Score <= 50 THEN 'Blocked from assignment'
        WHEN latest.Score <= 75 THEN 'Coaching required'
        ELSE 'OK'
    END AS ComplianceStatus
FROM driver d
LEFT JOIN depot_location dl ON d.DepotID = dl.DepotID
JOIN (
    SELECT msl.DriverID, msl.Year, msl.Month, msl.Score
    FROM monthly_score_log msl
    INNER JOIN (
        SELECT DriverID, MAX(Year * 100 + Month) AS ym
        FROM monthly_score_log
        GROUP BY DriverID
    ) latestym ON latestym.DriverID = msl.DriverID AND (msl.Year * 100 + msl.Month) = latestym.ym
) latest ON latest.DriverID = d.DriverID
WHERE latest.Score <= 75
ORDER BY latest.Score ASC;

CREATE OR REPLACE VIEW view_supplier_performance AS
SELECT
    pc.PartnerID,
    pc.PartnerName,
    pc.PartnerType,
    pc.DeliveryLeadTimes,
    COUNT(DISTINCT aipu.ActivityID) AS PartsUsageEvents,
    SUM(aipu.QuantityUsed) AS TotalUnitsSupplied,
    COUNT(DISTINCT wc.WarrantyClaimID) AS WarrantyClaims,
    SUM(CASE WHEN wc.Status = 'On going' THEN 1 ELSE 0 END) AS OpenWarrantyClaims
FROM partner_company pc
LEFT JOIN activity_instance_part_used aipu ON aipu.SupplierID = pc.PartnerID
LEFT JOIN warranty_claim wc ON wc.PartnerID = pc.PartnerID
GROUP BY pc.PartnerID, pc.PartnerName, pc.PartnerType, pc.DeliveryLeadTimes
ORDER BY TotalUnitsSupplied DESC;