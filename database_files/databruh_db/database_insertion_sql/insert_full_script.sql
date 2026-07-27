
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
('VEH-003', '43E-456.78', 'VinFast', 'VF Pro Van', 3, 2025, 1, 3, 12300);

-- ==========================================
-- 7. Drivers
-- ==========================================
INSERT INTO driver (DriverID, FullName, DepotID, LicenseNumber, LicenseExpiration, EmploymentStatus, ContactInfo, EmergencyContactDetails) VALUES
('D-112', 'Nguyen Van An', 1, 'L-29112', '2027-04-30', 'Active', '0901121122', 'Family - 0901121123'),
('D-204', 'Tran Thi Bich', 2, 'L-51204', '2028-05-01', 'Active', '0912042044', 'Family - 0912042045'),
('D-331', 'Le Quoc Minh', 1, 'L-29331', '2028-06-21', 'Active', '0983313311', 'Family - 0983313312'),
('D-417', 'Pham Duc Long', 4, 'L-65417', '2028-11-18', 'Active', '0974174177', 'Family - 0974174178');

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
('D-417', 5, '2023-11-18', '2028-11-18'); -- Pham Duc Long: Hazardous Goods Certification

-- ==========================================
-- 9. Driver - Vehicle Assignment
-- ==========================================
INSERT INTO vehicle_driver_assignment (VehicleID, DriverID, StartDate, EndDate) VALUES
('VEH-001', 'D-112', '2026-05-01', '2026-05-12'),
('VEH-001', 'D-331', '2026-05-13', NULL),
('VEH-002', 'D-204', '2026-05-01', NULL);


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
(96, 'VEH-002', 'D-204', 2, '2026-05-13 18:05:00', 4, 'Speeding', 'Odometer: 112,480');

-- ==========================================
-- 11. Monthly Score Log
-- ==========================================
INSERT INTO monthly_score_log (DriverID, Month, Year, Score) VALUES
('D-112', 4, 2026, 95),  -- Nguyen Van An (April performance)
('D-112', 5, 2026, 88),  -- Nguyen Van An (May performance drops due to telematics alerts)
('D-204', 4, 2026, 100), -- Tran Thi Bich (Perfect safety record)
('D-204', 5, 2026, 92),  -- Tran Thi Bich (Minor penalty for sharp cornering)
('D-331', 5, 2026, 98),  -- Le Quoc Minh (Clean record since joining May assignment)
('D-417', 5, 2026, 100); -- Pham Duc Long (Flawless score)























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












-- ==========================================
-- 1. Partner Companies (Suppliers & Manufacturers)
-- ==========================================
-- Part type options: 'Supplier', 'Manufacturer'
INSERT INTO partner_company (PartnerID, PartnerName, PartnerType, DeliveryLeadTimes, ContactInfo, Description) VALUES
(1, 'Ford Vietnam', 'Manufacturer', '5 Days', 'ford_vietnam_b2b@ford.com.vn, Tel: +84-24-3766-7888', 'OEM Vehicle Manufacturer & Parts Provider'),
(2, 'Isuzu Vietnam', 'Manufacturer', '7 Days', 'isuzu_care@isuzu-vietnam.com, Tel: +84-28-3895-9203', 'Heavy Rigids & Commercial Refrigerated Truck Maker'),
(3, 'Hanoi Auto Parts JSC', 'Supplier', '2 Days', 'sales@hanoiparts.vn, Tel: +84-24-3987-6543', 'Regional aftermarket warehouse supplier'),
(4, 'Saigon Fleet Supplies Co.', 'Supplier', '3 Days', 'order@saigonfleetparts.com, Tel: +84-28-7300-1122', 'Southern district distribution center'),
(5, 'Carrier Transicold Southeast Asia', 'Supplier', '10 Days', 'global_coldchain@carrier.com, Tel: +65-6248-6100', 'Specialist Cold-Chain Tech & Parts');

-- ==========================================
-- 2. Parts Catalog
-- ==========================================
-- Mapping parts to their primary and backup suppliers from partner_company
INSERT INTO part (PartID, PartName, PrimarySupplierID, BackupSupplierID) VALUES
(501, 'Front Brake Pad Set',  3, 1), -- Front Brake Pad Set (Primary: Hanoi Parts, Backup: Ford Vietnam OEM)
(502, 'Heavy-Duty Fleet Tyre', 4, 3), -- Heavy-Duty Fleet Tyre (Primary: Saigon Fleet, Backup: Hanoi Parts)
(503, 'Refrigeration Compress Belts', 5, 4); -- Refrigeration Compress Belts (Primary: Carrier Global, Backup: Saigon Fleet)

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
(503, 4, 950000, 'Standard replacement cooling system alternator belt');

-- ==========================================
-- 4. Parts Used in Activities
-- ==========================================
-- Activity 101: Brake Service on Job M1021 (Requires Brake Pads)
-- Activity 102: Tyre Replacement on Job M1021 (Requires Tyre)
-- Activity 104: Refrigeration Repair on Job M1022 (Requires Belts)
INSERT INTO activity_instance_part_used (ActivityID, PartID, QuantityUsed) VALUES
(101, 501, 1), -- Used 1 Set of Front Brake Pads for VEH-001
(102, 502, 2), -- Used 2 New Tyres for VEH-001
(104, 503, 1); -- Used 1 Refrigeration Belt for VEH-002

-- ==========================================
-- 5. Warranty Claims
-- ==========================================
INSERT INTO warranty_claim (WarrantyClaimID, PartnerID, ActivityID, Status, ClaimDate, ClaimResolvedDate) VALUES
('WAR-2026-0001', 5, 104, 'On going', '2026-05-14 15:00:00', NULL);

-- ==========================================
-- 6. Warranty Part List
-- ==========================================
-- Linking the cracked refrigeration belt replacement to our active claim
INSERT INTO warranty_part_list (WarrantyClaimID, PartID) VALUES
('WAR-2026-0001', 503);