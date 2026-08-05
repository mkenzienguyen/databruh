
USE databruh_db;

-- ==========================================
-- 1. Depot Locations
-- ==========================================
INSERT INTO depot_location (DepotName, DepotAddress) VALUES 
('Hanoi', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Phung Xa Commune, Thach That District, Hanoi'),
('Ho Chi Minh City', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Long Thanh My Ward, Thu Duc City, Ho Chi Minh City'),
('Da Nang', 'Road No. 5G, Da Nang High-Tech Park, Hoa Lien Commune, Hoa Vang District, Da Nang'),
('Can Tho', 'Block B-15, Street No. 2, Hung Phu 1 Industrial Zone, Dong Phu Ward, Cai Rang District, Can Tho');

-- ==========================================
-- 2. Vehicle Classifications
-- ==========================================
INSERT INTO vehicle_classification (ClassificationName) VALUES
('Delivery Van'),
('Refrigerated Truck'),
('Electric Van'),
('Service Vehicle'),
('Heavy Transport Truck');

-- ==========================================
-- 3. Vehicle Operational Statuses
-- ==========================================
INSERT INTO vehicle_status (StatusName) VALUES
('Active'),
('Available'),
('Under Maintenance'),
('Awaiting Inspection'),
('Out of Service'),
('Retired');

-- ==========================================
-- 4. Vehicle Certification Types
-- ==========================================
INSERT INTO vehicle_certification_type (CertificationName) VALUES
('Standard Licence'),
('Heavy Vehicle Licence'),
('Refrigerated Transport Certification'),
('EV Certification'),
('Hazardous Goods Certification');

-- ==========================================
-- 5. Severity Levels
-- ==========================================
INSERT INTO severity_level (LevelName) VALUES
('Low'),
('Medium'),
('High'),
('Critical');

USE databruh_db;

-- ==========================================
-- 1. Depot Locations
-- ==========================================
INSERT INTO depot_location (DepotName, DepotAddress) VALUES 
('Hanoi', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Phung Xa Commune, Thach That District, Hanoi'),
('Ho Chi Minh City', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Long Thanh My Ward, Thu Duc City, Ho Chi Minh City'),
('Da Nang', 'Road No. 5G, Da Nang High-Tech Park, Hoa Lien Commune, Hoa Vang District, Da Nang'),
('Can Tho', 'Block B-15, Street No. 2, Hung Phu 1 Industrial Zone, Dong Phu Ward, Cai Rang District, Can Tho');

-- ==========================================
-- 2. Vehicle Classifications
-- ==========================================
INSERT INTO vehicle_classification (ClassificationName) VALUES
('Delivery Van'),
('Refrigerated Truck'),
('Electric Van'),
('Service Vehicle'),
('Heavy Transport Truck');

-- ==========================================
-- 3. Vehicle Operational Statuses
-- ==========================================
INSERT INTO vehicle_status (StatusName) VALUES
('Active'),
('Available'),
('Under Maintenance'),
('Awaiting Inspection'),
('Out of Service'),
('Retired');

-- ==========================================
-- 4. Vehicle Certification Types
-- ==========================================
INSERT INTO vehicle_certification_type (CertificationName) VALUES
('Standard Licence'),
('Heavy Vehicle Licence'),
('Refrigerated Transport Certification'),
('EV Certification'),
('Hazardous Goods Certification');

-- ==========================================
-- 5. Severity Levels
-- ==========================================
INSERT INTO severity_level (LevelName) VALUES
('Low'),
('Medium'),
('High'),
('Critical');

-- ==========================================
-- 6. Vehicles
-- ==========================================
INSERT INTO vehicle (VehicleID, RegistrationNumber, Manufacturer, Model, ClassificationID, YearOfManufacture, StatusID, DepotID, CurrentOdometer) VALUES
('VEH-001', '29A-123.45', 'Ford', 'Transit', 1, 2023, 1, 1, 45310),
('VEH-002', '51C-789.01', 'Isuzu', 'QKR77HE4', 2, 2022, 1, 2, 112480),
('VEH-003', '43E-456.78', 'VinFast', 'VF Pro Van', 3, 2025, 1, 3, 12300),
('VEH-004', '43F-112.34', 'Isuzu', 'NQR75L', 5, 2021, 1, 3, 158200),
('VEH-005', '65G-908.76', 'Ford', 'Ranger', 4, 2022, 2, 4, 61250),
('VEH-006', '30A-556.19', 'VinFast', 'VF e34 Cargo', 3, 2024, 1, 1, 8420),
('VEH-007', '51D-223.90', 'Hino', '300 Series', 2, 2020, 3, 2, 201340),
('VEH-008', '43E-771.02', 'Ford', 'Transit', 1, 2023, 1, 3, 33810),
('VEH-009', '65H-334.55', 'Isuzu', 'QKR77HE4', 2, 2022, 2, 4, 97650),
('VEH-010', '30B-889.21', 'VinFast', 'VF Pro Van', 3, 2025, 4, 1, 2100);

-- ==========================================
-- 7. Drivers
-- ==========================================
INSERT INTO driver (DriverID, FullName, DepotID, LicenseNumber, LicenseExpiration, EmploymentStatus, ContactInfo, EmergencyContactDetails) VALUES
('D-112', 'Nguyen Van An', 1, 'L-29112', '2027-04-30', 'Active', '0901121122', 'Family - 0901121123'),
('D-204', 'Tran Thi Bich', 2, 'L-51204', '2028-05-01', 'Active', '0912042044', 'Family - 0912042045'),
('D-331', 'Le Quoc Minh', 1, 'L-29331', '2028-06-21', 'Active', '0983313311', 'Family - 0983313312'),
('D-417', 'Pham Duc Long', 4, 'L-65417', '2028-11-18', 'Active', '0974174177', 'Family - 0974174178'),
('D-501', 'Vo Thi Hoa', 3, 'L-43501', '2029-01-15', 'Active', '0905015011', 'Sister - 0905015012'),
('D-502', 'Dang Van Sang', 4, 'L-65502', '2027-09-09', 'Active', '0916025022', 'Wife - 0916025023'),
('D-503', 'Bui Thi Kim', 1, 'L-29503', '2028-03-03', 'Active', '0927035033', 'Husband - 0927035034'),
('D-504', 'Ngo Van Phuc', 2, 'L-51504', '2027-12-25', 'Active', '0938045044', 'Mother - 0938045045'),
('D-505', 'Ly Thi Ngoc', 3, 'L-43505', '2029-06-30', 'Inactive', '0949055055', 'Father - 0949055056');

-- ==========================================
-- 8. Driver Certifications Owned
-- ==========================================
-- Certifications: 1=Standard, 2=Heavy Vehicle, 3=Refrigerated, 4=EV, 5=Hazardous Goods
INSERT INTO driver_certification_owned (DriverID, CertificationTypeID, IssueDate, ExpiryDate) VALUES
('D-112', 1, '2022-02-14', '2027-02-14'), -- Nguyen Van An: Standard Licence
('D-112', 4, '2023-04-30', '2027-04-30'), -- Nguyen Van An: EV Certification
('D-204', 2, '2022-12-08', '2027-12-08'), -- Tran Thi Bich: Heavy Vehicle Licence
('D-204', 3, '2023-05-01', '2028-05-01'), -- Tran Thi Bich: Refrigerated Transport Certification
('D-331', 1, '2023-06-21', '2028-06-21'), -- Le Quoc Minh: Standard Licence
('D-417', 5, '2023-11-18', '2028-11-18'), -- Pham Duc Long: Hazardous Goods Certification
('D-501', 2, '2024-01-15', '2029-01-15'), -- Vo Thi Hoa: Heavy Vehicle Licence
('D-502', 1, '2022-09-09', '2027-09-09'), -- Dang Van Sang: Standard Licence
('D-503', 1, '2023-03-03', '2028-03-03'), -- Bui Thi Kim: Standard Licence
('D-504', 2, '2022-12-25', '2027-12-25'), -- Ngo Van Phuc: Heavy Vehicle Licence
('D-504', 3, '2023-01-10', '2028-01-10'), -- Ngo Van Phuc: Refrigerated Transport Certification
('D-505', 1, '2024-06-30', '2029-06-30'); -- Ly Thi Ngoc: Standard Licence

-- ==========================================
-- 9. Driver - Vehicle Assignment
-- ==========================================
INSERT INTO vehicle_driver_assignment (VehicleID, DriverID, StartDate, EndDate) VALUES
('VEH-001', 'D-112', '2026-05-01', '2026-05-12'),
('VEH-001', 'D-331', '2026-05-13', NULL),
('VEH-002', 'D-204', '2026-05-01', NULL),
('VEH-004', 'D-501', '2026-02-01', NULL),
('VEH-005', 'D-502', '2026-03-15', NULL),
('VEH-006', 'D-503', '2026-04-01', NULL),
('VEH-007', 'D-504', '2026-01-20', NULL),
('VEH-009', 'D-504', '2025-11-01', '2026-01-19');


-- ==========================================
-- 10. Vehicle Type Certification Requirements
-- ==========================================
INSERT INTO vehicle_type_certification_requirement (ClassificationID, CertificationTypeID) VALUES
(1, 1), -- Delivery Van requires Standard Licence
(2, 2), -- Refrigerated Truck requires Heavy Vehicle Licence
(2, 3), -- Refrigerated Truck also requires Refrigerated Transport Certification
(3, 1), -- Electric Van requires Standard Licence
(3, 4), -- Electric Van also requires EV Certification
(5, 2); -- Heavy Transport Truck requires Heavy Vehicle Licence;
(4, 1); -- Service Vehicle requires Standard Licence

-- ==========================================
-- 11. Behaviour Events
-- ==========================================
-- Severities: 1=Low, 2=Medium, 3=High, 4=Critical
-- Depots: 1=Hanoi, 2=HCMC, 3=Da Nang
INSERT INTO behaviour_event (EventID, VehicleID, DriverID, DepotID, Timestamp, SeverityID, EventType, Description) VALUES
(91, 'VEH-001', 'D-112', 1, '2026-05-10 08:14:00', 1, 'Harsh Braking', 'Odometer: 45,100'),
(92, 'VEH-001', 'D-112', 1, '2026-05-10 09:30:00', 3, 'Speeding', 'Odometer: 45,140'),
(93, 'VEH-002', 'D-204', 2, '2026-05-11 11:00:00', 2, 'Sharp Cornering', 'Odometer: 112,050'),
(94, 'VEH-003', 'D-112', 3, '2026-05-12 14:20:00', 3, 'Fatigue Warning', 'Odometer: 12,300'),
(95, 'VEH-001', 'D-331', 1, '2026-05-13 07:42:00', 1, 'Excessive Idling', 'Odometer: 45,310'),
(96, 'VEH-002', 'D-204', 2, '2026-05-13 18:05:00', 4, 'Speeding', 'Odometer: 112,480'),
(97, 'VEH-004', 'D-501', 3, '2026-05-14 07:55:00', 2, 'Harsh Braking', 'Odometer: 158,050'),
(98, 'VEH-005', 'D-502', 4, '2026-05-14 10:12:00', 1, 'Excessive Idling', 'Odometer: 61,180'),
(99, 'VEH-006', 'D-503', 1, '2026-05-15 06:40:00', 1, 'Rapid Acceleration', 'Odometer: 8,390'),
(100, 'VEH-007', 'D-504', 2, '2026-05-15 13:25:00', 4, 'Speeding', 'Odometer: 201,300'),
(101, 'VEH-009', 'D-504', 4, '2026-05-16 09:05:00', 3, 'Fatigue Warning', 'Odometer: 97,600'),
(102, 'VEH-004', 'D-501', 3, '2026-05-17 16:48:00', 2, 'Sharp Cornering', 'Odometer: 158,190');

-- ==========================================
-- 11. Monthly Score Log
-- ==========================================
INSERT INTO monthly_score_log (DriverID, Month, Year, Score) VALUES
('D-112', 4, 2026, 95),  -- Nguyen Van An (April performance)
('D-112', 5, 2026, 88),  -- Nguyen Van An (May performance drops due to telematics alerts)
('D-204', 4, 2026, 100), -- Tran Thi Bich (Perfect safety record)
('D-204', 5, 2026, 92),  -- Tran Thi Bich (Minor penalty for sharp cornering)
('D-331', 5, 2026, 98),  -- Le Quoc Minh (Clean record since joining May assignment)
('D-417', 5, 2026, 100), -- Pham Duc Long (Flawless score)
('D-501', 5, 2026, 90),
('D-502', 5, 2026, 97),
('D-503', 5, 2026, 100),
('D-504', 4, 2026, 85),
('D-504', 5, 2026, 80),
('D-112', 6, 2026, 91),
('D-204', 6, 2026, 89);























-- ==========================================
-- 1. Workshops (One per Depot)
-- ==========================================
-- Depots: 1 = Hanoi, 2 = Ho Chi Minh City
INSERT INTO workshop (WorkshopID, WorkshopName, WorkshopAddress, DepotID) VALUES
(1, 'Ha Noi Central Workshop', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Hanoi', 1),
(2, 'HCMC South Workshop', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Thu Duc City, Ho Chi Minh City', 2),
(3, 'Da Nang Regional Workshop', 'Road No. 5G, Da Nang High-Tech Park, Hoa Vang District, Da Nang', 3),
(4, 'Can Tho Delta Workshop', 'Block B-15, Street No. 2, Hung Phu 1 Industrial Zone, Cai Rang District, Can Tho', 4);

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
('ME-09', 'Tran Quoc Bao', 'Active', 'Father - 0901234563', 2),
('ME-21', 'Truong Van Hieu', 'Active', 'Wife - 0900112233', 3),
('ME-25', 'Le Thi Thu', 'Active', 'Husband - 0900223344', 3),
('ME-31', 'Phan Van Tuan', 'Active', 'Mother - 0900334455', 4),
('ME-35', 'Huynh Thi Yen', 'Active', 'Brother - 0900445566', 4);

-- ==========================================
-- 5. Mechanics Certification History
-- Dates shifted forward to maintain valid status windows
-- ==========================================
INSERT INTO mechanic_worker_certifications_history (MechanicID, CertificationID, IssueDate, ExpiryDate) VALUES
('ME-12', 1, '2022-01-15', '2032-01-15'), -- Hoang Van Duc: Standard Mechanic
('ME-15', 1, '2023-03-20', '2033-03-20'), -- Pham Thi Lan: Standard Mechanic
('ME-07', 1, '2021-06-10', '2031-06-10'), -- Nguyen Thi Mai: Standard Mechanic
('ME-09', 1, '2020-11-05', '2030-11-05'), -- Tran Quoc Bao: Standard Mechanic
('ME-09', 3, '2023-02-15', '2028-02-15'), -- Tran Quoc Bao: Refrigeration Certified
('ME-21', 1, '2021-08-01', '2031-08-01'), -- Truong Van Hieu: Standard Mechanic
('ME-21', 4, '2022-09-12', '2032-09-12'), -- Truong Van Hieu: Heavy Vehicle Mechanic
('ME-25', 1, '2023-02-20', '2033-02-20'), -- Le Thi Thu: Standard Mechanic
('ME-25', 2, '2024-01-05', '2034-01-05'), -- Le Thi Thu: EV Technician
('ME-31', 1, '2020-05-18', '2030-05-18'), -- Phan Van Tuan: Standard Mechanic
('ME-35', 1, '2022-07-22', '2032-07-22'), -- Huynh Thi Yen: Standard Mechanic
('ME-35', 3, '2023-08-30', '2028-08-30'); -- Huynh Thi Yen: Refrigeration Certified

-- ==========================================
-- 6. Alerts (Onboard Diagnostic Alerts)
-- ==========================================
INSERT INTO alert (AlertID, AlertName, VehicleID, AlertDescription, AlertTimestamp, Status) VALUES
(1, 'Brake Wear Warning', 'VEH-001', 'Brake pads worn below threshold.', '2026-05-11 20:15:00', 'Escalated'),
(2, 'Cooling System Anomaly', 'VEH-002', 'Refrigeration unit temperature fluctuations.', '2026-05-13 15:40:00', 'Escalated'),
(3, 'Engine Overheat Warning', 'VEH-004', 'Coolant temperature exceeded safe threshold.', '2026-05-14 08:20:00', 'Escalated'),
(4, 'Battery Health Alert', 'VEH-006', 'EV battery cell voltage imbalance detected.', '2026-05-15 07:10:00', 'Open'),
(5, 'Cooling System Anomaly', 'VEH-007', 'Refrigeration unit failed to reach setpoint.', '2026-05-15 14:00:00', 'Escalated'),
(6, 'Tyre Pressure Warning', 'VEH-009', 'Rear left tyre pressure below threshold.', '2026-05-16 09:30:00', 'Resolved');

-- ==========================================
-- 7. Maintenance Jobs
-- ==========================================
INSERT INTO maintenance_job (JobID, VehicleID, WorkshopID, StartDate, EndDate, Status, AlertID, TotalCost) VALUES
(1021, 'VEH-001', 1, '2026-05-12 09:00:00', '2026-05-13 03:00:00', 'Closed', 1, 1800000), -- Total Cost: 1.8M VND, Downtime: 18h
(1022, 'VEH-002', 2, '2026-05-14 08:00:00', '2026-05-14 14:00:00', 'Closed', 2, 3610000), -- Total Cost: 3.61M VND, Downtime: 6h
(1023, 'VEH-004', 4, '2026-05-14 09:00:00', '2026-05-15 17:00:00', 'Closed', 3, 5200000), -- Downtime: 32h
(1024, 'VEH-006', 1, '2026-05-15 08:00:00', '2026-05-15 12:00:00', 'Closed', 4, 2100000), -- Downtime: 4h
(1025, 'VEH-007', 2, '2026-05-15 15:00:00', NULL, 'In Progress', 5, NULL),
(1026, 'VEH-009', 4, '2026-05-16 10:00:00', '2026-05-16 11:30:00', 'Closed', 6, 350000); -- Downtime: 1.5h

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

-- Job 1023 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(105, 1023, 3, 4, 'Coolant leak found at radiator hose junction'),
(106, 1023, 4, 3, 'Radiator hose and thermostat replaced');
 
-- Job 1024 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(107, 1024, 6, 4, 'Cell voltage imbalance corrected via BMS recalibration');
 
-- Job 1025 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(108, 1025, 7, 3, 'Compressor clutch worn - pending part delivery');
 
-- Job 1026 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(109, 1026, 10, 1, 'Slow puncture repaired, tyre reseated');
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
(104, 'ME-09'),
(105, 'ME-31'),
(106, 'ME-31'),
(107, 'ME-25'),
(108, 'ME-35'),
(109, 'ME-31');











-- ==========================================
-- 1. Partner Companies (Suppliers & Manufacturers)
-- ==========================================
-- Part type options: 'Supplier', 'Manufacturer'
INSERT INTO partner_company (PartnerID, PartnerName, PartnerType, DeliveryLeadTimes, ContactInfo, Description) VALUES
(1, 'Ford Vietnam', 'Manufacturer', '5 Days', 'ford_vietnam_b2b@ford.com.vn, Tel: +84-24-3766-7888', 'OEM Vehicle Manufacturer & Parts Provider'),
(2, 'Isuzu Vietnam', 'Manufacturer', '7 Days', 'isuzu_care@isuzu-vietnam.com, Tel: +84-28-3895-9203', 'Heavy Rigids & Commercial Refrigerated Truck Maker'),
(3, 'Hanoi Auto Parts JSC', 'Supplier', '2 Days', 'sales@hanoiparts.vn, Tel: +84-24-3987-6543', 'Regional aftermarket warehouse supplier'),
(4, 'Saigon Fleet Supplies Co.', 'Supplier', '3 Days', 'order@saigonfleetparts.com, Tel: +84-28-7300-1122', 'Southern district distribution center'),
(5, 'Carrier Transicold Southeast Asia', 'Supplier', '10 Days', 'global_coldchain@carrier.com, Tel: +65-6248-6100', 'Specialist Cold-Chain Tech & Parts'),
(6, 'VinFast Energy Solutions', 'Manufacturer', '6 Days', 'ev_parts@vinfast.vn, Tel: +84-24-3900-1234', 'EV Battery, Motor, and Charging Component Supplier');

-- ==========================================
-- 2. Parts Catalog
-- ==========================================
-- Mapping parts to their primary and backup suppliers from partner_company
INSERT INTO part (PartID, PartName, PrimarySupplierID, BackupSupplierID) VALUES
(501, 'Front Brake Pad Set',  3, 1), -- Front Brake Pad Set (Primary: Hanoi Parts, Backup: Ford Vietnam OEM)
(502, 'Heavy-Duty Fleet Tyre', 4, 3), -- Heavy-Duty Fleet Tyre (Primary: Saigon Fleet, Backup: Hanoi Parts)
(503, 'Refrigeration Compress Belts', 5, 4), -- Refrigeration Compress Belts (Primary: Carrier Global, Backup: Saigon Fleet)
(504, 'Radiator Hose & Thermostat Kit', 3, 1),
(505, 'EV Battery Management Module', 6, 1),
(506, 'A/C Compressor Clutch Assembly', 5, 4),
(507, 'Fleet Van Tyre Repair Patch Kit', 3, 4);

-- ==========================================
-- 3. Supplier Product List (Pricing Matrix)
-- ==========================================
INSERT INTO supplier_product_list (PartID, PartnerID, CostPerUnit, Description) VALUES
-- Part 501: Front Brake Pads
(501, 3, 450000, 'Aftermarket premium brake pads for light commercial vans'),
(501, 1, 750000, 'Ford Genuine OEM Transit front brake pads'),
-- Part 502: Tires
(502, 4, 1200000, 'All-season high-durability transit/light truck tire'),
(502, 3, 1350000, 'Premium radial heavy-duty cargo tire'),
-- Part 503: Drive Belts
(503, 5, 800000, 'Specialist heavy-duty thermal-resistant refrigeration belt'),
(503, 4, 950000, 'Standard replacement cooling system alternator belt'),
(504, 3, 620000, 'Aftermarket radiator hose and thermostat kit for medium trucks'),
(504, 1, 980000, 'Ford Genuine OEM radiator hose kit'),
(505, 6, 4500000, 'VinFast Genuine BMS control module for e34/VF Pro platform'),
(506, 5, 2750000, 'Carrier OEM compressor clutch assembly for cold-chain units'),
(506, 4, 2400000, 'Aftermarket compressor clutch assembly'),
(507, 3, 85000, 'Standard tyre puncture repair patch kit, box of 20');

-- ==========================================
-- 4. Parts Used in Activities
-- ==========================================
-- Activity 101: Brake Service on Job M1021 (Requires Brake Pads)
-- Activity 102: Tyre Replacement on Job M1021 (Requires Tyre)
-- Activity 104: Refrigeration Repair on Job M1022 (Requires Belts)
INSERT INTO activity_instance_part_used (ActivityID, PartID, QuantityUsed) VALUES
(101, 501, 1), -- Used 1 Set of Front Brake Pads for VEH-001
(102, 502, 2), -- Used 2 New Tyres for VEH-001
(104, 503, 1), -- Used 1 Refrigeration Belt for VEH-002
(106, 504, 1), -- Radiator hose kit used for VEH-004
(107, 505, 1), -- BMS module used for VEH-006
(109, 507, 1); -- Puncture patch kit used for VEH-009

-- ==========================================
-- 5. Warranty Claims
-- ==========================================
INSERT INTO warranty_claim (WarrantyClaimID, PartnerID, ActivityID, Status, ClaimDate, ClaimResolvedDate) VALUES
('WAR-2026-0001', 5, 104, 'On going', '2026-05-14 15:00:00', NULL),
('WAR-2026-0002', 6, 107, 'On going', '2026-05-15 12:30:00', NULL),
('WAR-2026-0003', 1, 106, 'Approved', '2026-05-15 18:00:00', '2026-05-20 10:00:00');

-- ==========================================
-- 6. Warranty Part List
-- ==========================================
-- Linking the cracked refrigeration belt replacement to our active claim
INSERT INTO warranty_part_list (WarrantyClaimID, PartID) VALUES
('WAR-2026-0001', 503),
('WAR-2026-0002', 505),
('WAR-2026-0003', 504);