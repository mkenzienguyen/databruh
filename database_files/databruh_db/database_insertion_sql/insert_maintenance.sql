USE databruh_db;
-- ==========================================
-- 1. Workshops (One per Depot)
-- ==========================================
-- Depots: 1 = Hanoi, 2 = Ho Chi Minh City
INSERT INTO workshop (WorkshopID, WorkshopName, WorkshopAddress, DepotID) VALUES
(1, 'Ha Noi Central Workshop', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Hanoi', 1),
(2, 'HCMC South Workshop', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Thu Duc City, Ho Chi Minh City', 2);

-- ==========================================
-- 2. Activity Certifications (Mechanic Qualifications)
-- ==========================================
INSERT INTO activity_certification (CertificationID, CertificationName, Description) VALUES
(1, 'Standard Vehicle Mechanic Licence', 'Covers routine inspections, servicing, diagnostics, emergency repairs, and component replacements.'),
(2, 'EV Technician Certification', 'Required for electric vehicle battery and electrical repairs.'),
(3, 'Refrigeration Systems Certification', 'Required for refrigeration system repairs on cold-chain trucks.'),
(4, 'Heavy Vehicle Mechanic Licence', 'Required for repairs on heavy vehicle categories.');

-- ==========================================
-- 3. Activity Types (Mapped to required Certifications)
-- ==========================================
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

-- ==========================================
-- 4. Mechanics
-- ==========================================
-- Workshops: 1 = Hanoi Central, 2 = HCMC South
INSERT INTO mechanic_worker (MechanicID, FullName, EmploymentStatus, EmergencyContactDetails, WorkshopID) VALUES
('ME-12', 'Hoang Van Duc', 'Active', 'Wife - 0901234560', 1),
('ME-15', 'Pham Thi Lan', 'Active', 'Husband - 0901234561', 1),
('ME-07', 'Nguyen Thi Mai', 'Active', 'Brother - 0901234562', 2),
('ME-09', 'Tran Quoc Bao', 'Active', 'Father - 0901234563', 2);

-- ==========================================
-- 5. Mechanics Certification History
-- Dates shifted forward to maintain valid status windows
-- ==========================================
INSERT INTO mechanic_worker_certifications_history (MechanicID, CertificationID, IssueDate, ExpiryDate) VALUES
('ME-12', 1, '2022-01-15', '2032-01-15'), -- Hoang Van Duc: Standard Mechanic
('ME-15', 1, '2023-03-20', '2033-03-20'), -- Pham Thi Lan: Standard Mechanic
('ME-07', 1, '2021-06-10', '2031-06-10'), -- Nguyen Thi Mai: Standard Mechanic
('ME-09', 1, '2020-11-05', '2030-11-05'), -- Tran Quoc Bao: Standard Mechanic
('ME-09', 3, '2023-02-15', '2028-02-15'); -- Tran Quoc Bao: Refrigeration Certified

-- ==========================================
-- 6. Alerts (Onboard Diagnostic Alerts)
-- ==========================================
INSERT INTO alert (AlertID, AlertName, VehicleID, AlertDescription, AlertTimestamp, Status) VALUES
(1, 'Brake Wear Warning', 'VEH-001', 'Brake pads worn below threshold.', '2026-05-11 20:15:00', 'Escalated'),
(2, 'Cooling System Anomaly', 'VEH-002', 'Refrigeration unit temperature fluctuations.', '2026-05-13 15:40:00', 'Escalated');

-- ==========================================
-- 7. Maintenance Jobs
-- ==========================================
INSERT INTO maintenance_job (JobID, VehicleID, WorkshopID, StartDate, EndDate, Status, AlertID, TotalCost) VALUES
(1021, 'VEH-001', 1, '2026-05-12 09:00:00', '2026-05-13 03:00:00', 'Closed', 1, 1800000), -- Total Cost: 1.8M VND, Downtime: 18h
(1022, 'VEH-002', 2, '2026-05-14 08:00:00', '2026-05-14 14:00:00', 'Closed', 2, 3610000); -- Total Cost: 3.61M VND, Downtime: 6h

-- ==========================================
-- 8. Activity Instances (Details of the Jobs)
-- ==========================================
-- Job M1021 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(101, 1021, 9, 5, 'Pads worn below 3mm'),              -- Activity 1: Brake Service (split among 2 mechanics, total 5 hrs)
(102, 1021, 10, 1, 'Worn unevenly - possible alignment issue'); -- Activity 2: Tyre Replacement (1 hr)

-- Job M1022 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(103, 1022, 2, 2, 'OK'),                                -- Activity 1: Preventative Servicing (1.5 hrs)
(104, 1022, 7, 2, 'Belt cracked - 3rd replacement this year'); -- Activity 2: Refrigeration Repair (2.0 hrs)

-- ==========================================
-- 9. Mechanic Activity Assignments (Workload Distribution)
-- ==========================================
-- Job M1021: Brake Service (Hoang Van Duc [ME-12] & Pham Thi Lan [ME-15])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID) VALUES
(101, 'ME-12'),
(101, 'ME-15');

-- Job M1021: Tyre Replacement (Hoang Van Duc [ME-12])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID) VALUES
(102, 'ME-12');

-- Job M1022: Preventative Servicing (Nguyen Thi Mai [ME-07])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID) VALUES
(103, 'ME-07');

-- Job M1022: Refrigeration Repair (Tran Quoc Bao [ME-09])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID) VALUES
(104, 'ME-09');