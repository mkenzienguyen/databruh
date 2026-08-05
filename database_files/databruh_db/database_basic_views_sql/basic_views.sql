USE databruh_db;

-- View 1: Expired driver certifications
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

-- View 2: Fleet telematics & driver behaviour tracking
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

-- View 3: Workshop maintenance summaries
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

-- View 4: Active predictive telematics alerts
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

-- View 5: Mechanic workforce qualifications ledger
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

-- View 6: Part replacement & lifecycle analysis
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

-- View 7: Warranty claims checker
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

-- View 8: Driver monthly score anomaly detection (Z-score vs. each
-- driver's own history; needs 2+ months before a baseline exists).
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

-- View 9: Incident review & resolution (unresolved = no coaching_log row)
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

-- View 10: Driver risk & retraining summary
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

-- View 11: Repeat speeding drivers
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

-- View 12: Vehicles associated with severe incidents
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

-- View 13: Drivers operating outside their authorised vehicle category
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

-- View 14: Vehicles requiring urgent repair (unresolved alert, or Out of Service)
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

-- View 15: Vehicles awaiting inspection
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

-- View 16: Workshop workload
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

-- View 17: Maintenance cost by vehicle model
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

-- View 18: Vehicles overdue for service (no history: measured from
-- Jan 1 of YearOfManufacture)
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

-- View 19: Vehicles with repeated component failures (same activity
-- type performed 2+ times)
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

-- View 20: Parts below reorder threshold
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

-- View 21: Coaching / training compliance (score <= 75 needs coaching,
-- <= 50 blocks assignment)
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

-- View 22: Supplier performance (attributed via activity_instance_part_used.SupplierID)
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
