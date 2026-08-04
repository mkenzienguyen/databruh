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