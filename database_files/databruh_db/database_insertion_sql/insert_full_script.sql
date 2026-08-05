
USE databruh_db;

-- 1. Depot Locations
INSERT INTO depot_location (DepotName, DepotAddress) VALUES 
('Hanoi', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Phung Xa Commune, Thach That District, Hanoi'),
('Ho Chi Minh City', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Long Thanh My Ward, Thu Duc City, Ho Chi Minh City'),
('Da Nang', 'Road No. 5G, Da Nang High-Tech Park, Hoa Lien Commune, Hoa Vang District, Da Nang'),
('Can Tho', 'Block B-15, Street No. 2, Hung Phu 1 Industrial Zone, Dong Phu Ward, Cai Rang District, Can Tho');

-- 2. Vehicle Classifications
INSERT INTO vehicle_classification (ClassificationName) VALUES
('Delivery Van'),
('Refrigerated Truck'),
('Electric Van'),
('Service Vehicle'),
('Heavy Transport Truck');

-- 3. Vehicle Operational Statuses
INSERT INTO vehicle_status (StatusName) VALUES
('Active'),
('Available'),
('Under Maintenance'),
('Awaiting Inspection'),
('Out of Service'),
('Retired');

-- 4. Vehicle Certification Types
INSERT INTO vehicle_certification_type (CertificationName) VALUES
('Standard Licence'),
('Heavy Vehicle Licence'),
('Refrigerated Transport Certification'),
('EV Certification'),
('Hazardous Goods Certification');

-- 5. Severity Levels
INSERT INTO severity_level (LevelName) VALUES
('Low'),
('Medium'),
('High'),
('Critical');

-- 6. Vehicles
INSERT INTO vehicle (VehicleID, RegistrationNumber, Manufacturer, Model, ClassificationID, YearOfManufacture, StatusID, DepotID, CurrentOdometer) VALUES
('VEH-001', '29A-123.45', 'Ford', 'Transit', 1, 2023, 1, 1, 45310),
('VEH-002', '51C-789.01', 'Isuzu', 'QKR77HE4', 2, 2022, 1, 2, 112480),
('VEH-003', '43E-456.78', 'VinFast', 'VF Pro Van', 3, 2025, 1, 3, 12300);

-- 7. Drivers
INSERT INTO driver (DriverID, FullName, DepotID, LicenseNumber, LicenseExpiration, EmploymentStatus, ContactInfo, EmergencyContactDetails) VALUES
('D-112', 'Nguyen Van An', 1, 'L-29112', '2027-04-30', 'Active', '0901121122', 'Family - 0901121123'),
('D-204', 'Tran Thi Bich', 2, 'L-51204', '2028-05-01', 'Active', '0912042044', 'Family - 0912042045'),
('D-331', 'Le Quoc Minh', 1, 'L-29331', '2028-06-21', 'Active', '0983313311', 'Family - 0983313312'),
('D-417', 'Pham Duc Long', 4, 'L-65417', '2028-11-18', 'Active', '0974174177', 'Family - 0974174178');

-- 8. Driver Certifications Owned
-- Certifications: 1=Standard, 2=Heavy Vehicle, 3=Refrigerated, 4=EV, 5=Hazardous Goods
INSERT INTO driver_certification_owned (DriverID, CertificationTypeID, IssueDate, ExpiryDate) VALUES
('D-112', 1, '2022-02-14', '2027-02-14'), -- Nguyen Van An: Standard Licence
('D-112', 4, '2023-04-30', '2027-04-30'), -- Nguyen Van An: EV Certification
('D-204', 1, '2022-08-01', '2028-08-01'), -- Tran Thi Bich: Standard Licence
('D-204', 2, '2022-12-08', '2027-12-08'), -- Tran Thi Bich: Heavy Vehicle Licence
('D-204', 3, '2023-05-01', '2028-05-01'), -- Tran Thi Bich: Refrigerated Transport Certification
('D-331', 1, '2023-06-21', '2028-06-21'), -- Le Quoc Minh: Standard Licence
('D-417', 5, '2023-11-18', '2028-11-18'), -- Pham Duc Long: Hazardous Goods Certification
-- Standard + EV certs, valid when the VEH-003 assignment began but since lapsed
('D-417', 1, '2025-01-01', '2026-06-01'),
('D-417', 4, '2025-01-01', '2026-06-01');

-- 9. Driver - Vehicle Assignment
INSERT INTO vehicle_driver_assignment (VehicleID, DriverID, StartDate, EndDate) VALUES
('VEH-001', 'D-112', '2026-05-01', '2026-05-12'),
('VEH-001', 'D-331', '2026-05-13', NULL),
('VEH-002', 'D-204', '2026-05-01', NULL),
('VEH-003', 'D-417', '2026-05-20', NULL);


-- 10. Vehicle Type Certification Requirements
INSERT INTO vehicle_type_certification_requirement (ClassificationID, CertificationTypeID) VALUES
(1, 1), -- Delivery Van requires Standard Licence
(2, 1), -- Refrigerated Truck also requires Standard Licence
(2, 2), -- Refrigerated Truck requires Heavy Vehicle Licence
(2, 3), -- Refrigerated Truck also requires Refrigerated Transport Certification
(3, 1), -- Electric Van requires Standard Licence
(3, 4), -- Electric Van also requires EV Certification
(4, 1), -- Service Vehicle requires Standard Licence
(5, 2), -- Heavy Transport Truck requires Heavy Vehicle Licence
(5, 5); -- Heavy Transport Truck also requires Hazardous Goods Certification

-- 11. Behaviour Events
-- Severities: 1=Low, 2=Medium, 3=High, 4=Critical
-- Depots: 1=Hanoi, 2=HCMC, 3=Da Nang
INSERT INTO behaviour_event (EventID, VehicleID, DriverID, DepotID, Timestamp, SeverityID, EventType, Description) VALUES
(91, 'VEH-001', 'D-112', 1, '2026-05-10 08:14:00', 1, 'Harsh Braking', 'Odometer: 45,100'),
(92, 'VEH-001', 'D-112', 1, '2026-05-10 09:30:00', 3, 'Speeding', 'Odometer: 45,140'),
(93, 'VEH-002', 'D-204', 2, '2026-05-11 11:00:00', 2, 'Sharp Cornering', 'Odometer: 112,050'),
(94, 'VEH-003', 'D-112', 3, '2026-05-12 14:20:00', 3, 'Fatigue Warning', 'Odometer: 12,300'),
(95, 'VEH-001', 'D-331', 1, '2026-05-13 07:42:00', 1, 'Excessive Idling', 'Odometer: 45,310'),
(96, 'VEH-002', 'D-204', 2, '2026-05-13 18:05:00', 4, 'Speeding', 'Odometer: 112,480'),
(97, 'VEH-001', 'D-112', 1, '2026-05-15 10:05:00', 2, 'Speeding', 'Odometer: 45,410, second speeding event this week');

-- monthly_score_log is computed automatically by triggers in
-- business_rules.sql, not hand-inserted here.




















-- 1. Workshops (One per Depot)
-- Depots: 1 = Hanoi, 2 = Ho Chi Minh City
INSERT INTO workshop (WorkshopID, WorkshopName, WorkshopAddress, DepotID) VALUES
(1, 'Ha Noi Central Workshop', 'Lot CN-08, Road No. 4, Thach That - Quoc Oai Industrial Park, Hanoi', 1),
(2, 'HCMC South Workshop', 'Plot E2-D9, Street D2, Saigon Hi-Tech Park (SHTP), Thu Duc City, Ho Chi Minh City', 2);

-- 2. Activity Certifications (Mechanic Qualifications)
INSERT INTO activity_certification (CertificationID, CertificationName, Description) VALUES
(1, 'Standard Vehicle Mechanic Licence', 'Covers routine inspections, servicing, diagnostics, emergency repairs, and component replacements.'),
(2, 'EV Technician Certification', 'Required for electric vehicle battery and electrical repairs.'),
(3, 'Refrigeration Systems Certification', 'Required for refrigeration system repairs on cold-chain trucks.'),
(4, 'Heavy Vehicle Mechanic Licence', 'Required for repairs on heavy vehicle categories.');

-- 3. Activity Types (Mapped to required Certifications)
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

-- 4. Mechanics
-- Workshops: 1 = Hanoi Central, 2 = HCMC South
INSERT INTO mechanic_worker (MechanicID, FullName, EmploymentStatus, EmergencyContactDetails, WorkshopID) VALUES
('ME-12', 'Hoang Van Duc', 'Active', 'Wife - 0901234560', 1),
('ME-15', 'Pham Thi Lan', 'Active', 'Husband - 0901234561', 1),
('ME-07', 'Nguyen Thi Mai', 'Active', 'Brother - 0901234562', 2),
('ME-09', 'Tran Quoc Bao', 'Active', 'Father - 0901234563', 2);

-- 5. Mechanics Certification History
-- Dates shifted forward to maintain valid status windows
INSERT INTO mechanic_worker_certifications_history (MechanicID, CertificationID, IssueDate, ExpiryDate) VALUES
('ME-12', 1, '2022-01-15', '2032-01-15'), -- Hoang Van Duc: Standard Mechanic
('ME-15', 1, '2023-03-20', '2033-03-20'), -- Pham Thi Lan: Standard Mechanic
('ME-07', 1, '2021-06-10', '2031-06-10'), -- Nguyen Thi Mai: Standard Mechanic
('ME-09', 1, '2020-11-05', '2030-11-05'), -- Tran Quoc Bao: Standard Mechanic
('ME-09', 3, '2023-02-15', '2028-02-15'); -- Tran Quoc Bao: Refrigeration Certified

-- 6. Alerts (Onboard Diagnostic Alerts)
INSERT INTO alert (AlertID, AlertName, VehicleID, AlertDescription, AlertTimestamp, Status) VALUES
(1, 'Brake Wear Warning', 'VEH-001', 'Brake pads worn below threshold.', '2026-05-11 20:15:00', 'Escalated'),
(2, 'Cooling System Anomaly', 'VEH-002', 'Refrigeration unit temperature fluctuations.', '2026-05-13 15:40:00', 'Escalated');

-- 7. Maintenance Jobs
INSERT INTO maintenance_job (JobID, VehicleID, WorkshopID, StartDate, EndDate, Status, AlertID, TotalCost) VALUES
(1021, 'VEH-001', 1, '2026-05-12 09:00:00', '2026-05-13 03:00:00', 'Closed', 1, 1800000), -- Total Cost: 1.8M VND, Downtime: 18h
(1022, 'VEH-002', 2, '2026-05-14 08:00:00', '2026-05-14 14:00:00', 'Closed', 2, 3610000); -- Total Cost: 3.61M VND, Downtime: 6h

-- 8. Activity Instances (Details of the Jobs)
-- Job M1021 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(101, 1021, 9, 5, 'Pads worn below 3mm'),              -- Activity 1: Brake Service (split among 2 mechanics, total 5 hrs)
(102, 1021, 10, 1, 'Worn unevenly - possible alignment issue'); -- Activity 2: Tyre Replacement (1 hr)

-- Job M1022 activities
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(103, 1022, 2, 2, 'OK'),                                -- Activity 1: Preventative Servicing (1.5 hrs)
(104, 1022, 7, 2, 'Belt cracked - 3rd replacement this year'); -- Activity 2: Refrigeration Repair (2.0 hrs)

-- 9. Mechanic Activity Assignments (Workload Distribution)
-- Job M1021: Brake Service (Hoang Van Duc [ME-12] & Pham Thi Lan [ME-15])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID, LabourHours) VALUES
(101, 'ME-12', 2.5),
(101, 'ME-15', 2.5);

-- Job M1021: Tyre Replacement (Hoang Van Duc [ME-12])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID, LabourHours) VALUES
(102, 'ME-12', 1.0);

-- Job M1022: Preventative Servicing (Nguyen Thi Mai [ME-07])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID, LabourHours) VALUES
(103, 'ME-07', 1.5);

-- Job M1022: Refrigeration Repair (Tran Quoc Bao [ME-09])
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID, LabourHours) VALUES
(104, 'ME-09', 2.0);

UPDATE activity_instance SET RepeatFault = TRUE WHERE ActivityID = 102;
UPDATE activity_instance SET WarrantyApplicable = TRUE WHERE ActivityID = 104;

-- 9b. Open Job: Repeat Refrigeration Failure on VEH-002 (third occurrence)
INSERT INTO maintenance_job (JobID, VehicleID, WorkshopID, StartDate, EndDate, Status, AlertID, TotalCost) VALUES
(1023, 'VEH-002', 2, '2026-06-01 09:00:00', NULL, 'Open', NULL, NULL);

INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult, RepeatFault) VALUES
(105, 1023, 7, 3, 'Recurring refrigeration belt failure - third occurrence, recommend full unit inspection', TRUE);

INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID, LabourHours) VALUES
(105, 'ME-09', 3.0);












-- 1. Partner Companies (Suppliers & Manufacturers)
-- Part type options: 'Supplier', 'Manufacturer'
INSERT INTO partner_company (PartnerID, PartnerName, PartnerType, DeliveryLeadTimes, ContactInfo, Description) VALUES
(1, 'Ford Vietnam', 'Manufacturer', '5 Days', 'ford_vietnam_b2b@ford.com.vn, Tel: +84-24-3766-7888', 'OEM Vehicle Manufacturer & Parts Provider'),
(2, 'Isuzu Vietnam', 'Manufacturer', '7 Days', 'isuzu_care@isuzu-vietnam.com, Tel: +84-28-3895-9203', 'Heavy Rigids & Commercial Refrigerated Truck Maker'),
(3, 'Hanoi Auto Parts JSC', 'Supplier', '2 Days', 'sales@hanoiparts.vn, Tel: +84-24-3987-6543', 'Regional aftermarket warehouse supplier'),
(4, 'Saigon Fleet Supplies Co.', 'Supplier', '3 Days', 'order@saigonfleetparts.com, Tel: +84-28-7300-1122', 'Southern district distribution center'),
(5, 'Carrier Transicold Southeast Asia', 'Supplier', '10 Days', 'global_coldchain@carrier.com, Tel: +65-6248-6100', 'Specialist Cold-Chain Tech & Parts');

-- 2. Parts Catalog
-- Mapping parts to their primary and backup suppliers from partner_company
INSERT INTO part (PartID, PartName, PrimarySupplierID, BackupSupplierID) VALUES
(501, 'Front Brake Pad Set',  3, 1), -- Front Brake Pad Set (Primary: Hanoi Parts, Backup: Ford Vietnam OEM)
(502, 'Heavy-Duty Fleet Tyre', 4, 3), -- Heavy-Duty Fleet Tyre (Primary: Saigon Fleet, Backup: Hanoi Parts)
(503, 'Refrigeration Compress Belts', 5, 4); -- Refrigeration Compress Belts (Primary: Carrier Global, Backup: Saigon Fleet)

-- 3. Supplier Product List (Pricing Matrix)
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

-- 4. Parts Used in Activities
INSERT INTO activity_instance_part_used (ActivityID, PartID, QuantityUsed, SupplierID) VALUES
(101, 501, 1, 3), -- Used 1 Set of Front Brake Pads for VEH-001, supplied by Hanoi Auto Parts JSC
(102, 502, 2, 4), -- Used 2 New Tyres for VEH-001, supplied by Saigon Fleet Supplies Co.
(104, 503, 1, 5); -- Used 1 Refrigeration Belt for VEH-002, supplied by Carrier Transicold Southeast Asia

-- 5. Warranty Claims
INSERT INTO warranty_claim (WarrantyClaimID, PartnerID, ActivityID, Status, ClaimDate, ClaimResolvedDate) VALUES
('WAR-2026-0001', 5, 104, 'On going', '2026-05-14 15:00:00', NULL);

-- 6. Warranty Part List
-- Linking the cracked refrigeration belt replacement to our active claim
INSERT INTO warranty_part_list (WarrantyClaimID, PartID) VALUES
('WAR-2026-0001', 503);

-- 7. Coaching Log
-- Events 92 and 94 are resolved (coached); 91, 93, 95, 96, 97 are not.
INSERT INTO coaching_log (DriverID, EventID, CoachDate, ConductedBy, Outcome, Notes) VALUES
('D-112', 92, '2026-05-11', 'Fleet Manager', 'Coached - Verbal Warning', 'Reviewed telematics speeding event with driver; acknowledged and corrected.'),
('D-112', 94, '2026-05-13', 'Fleet Manager', 'Retraining Required', 'Second high-severity event this week (fatigue warning). Scheduled for defensive driving refresher.'),
('D-331', NULL, '2026-05-14', 'Fleet Manager', 'Completed - No Concerns', 'Routine quarterly safety check-in; no incidents to discuss.');

-- 8. Maintenance Schedule Rules
INSERT INTO maintenance_schedule_rule (ClassificationID, IntervalDays, Description) VALUES
(1, 180, 'Delivery Van: routine service every 6 months'),
(2, 90, 'Refrigerated Truck: cold-chain duty cycle, serviced every 3 months'),
(3, 180, 'Electric Van: routine service every 6 months'),
(4, 120, 'Service Vehicle: serviced every 4 months'),
(5, 90, 'Heavy Transport Truck: serviced every 3 months');

-- 9. Part Inventory Levels
-- Tyres (502) are seeded below their reorder threshold.
UPDATE part SET QuantityOnHand = 40, ReorderThreshold = 15 WHERE PartID = 501;
UPDATE part SET QuantityOnHand = 8,  ReorderThreshold = 10 WHERE PartID = 502;
UPDATE part SET QuantityOnHand = 20, ReorderThreshold = 5  WHERE PartID = 503;

-- Additional Vehicles
INSERT INTO vehicle (VehicleID, RegistrationNumber, Manufacturer, Model, ClassificationID, YearOfManufacture, StatusID, DepotID, CurrentOdometer) VALUES
('VEH-004', '30F-224.10', 'Ford', 'Transit', 1, 2024, 1, 1, 18500),
('VEH-005', '51C-880.22', 'Isuzu', 'QKR77HE4', 2, 2023, 1, 2, 76200),
('VEH-006', '43H-901.33', 'Hyundai', 'Porter', 4, 2021, 1, 3, 98400),
('VEH-007', '51D-114.55', 'Hino', 'FL', 5, 2020, 5, 2, 210300),
('VEH-008', '65A-337.88', 'Ford', 'Transit', 1, 2022, 4, 4, 61200);

-- VEH-002 currently has an unresolved open job (1023) - mark it as
-- actually under maintenance rather than nominally Active.
UPDATE vehicle SET StatusID = 3 WHERE VehicleID = 'VEH-002';

-- Additional Drivers
INSERT INTO driver (DriverID, FullName, DepotID, LicenseNumber, LicenseExpiration, EmploymentStatus, ContactInfo, EmergencyContactDetails) VALUES
('D-528', 'Vo Thi Hoa', 3, 'L-43528', '2029-01-15', 'Active', '0935285281', 'Sister - 0935285282'),
('D-604', 'Dang Van Kiet', 2, 'L-51604', '2028-09-01', 'Active', '0916046044', 'Wife - 0916046045'),
('D-715', 'Bui Thi Ngoc', 1, 'L-29715', '2029-03-10', 'Active', '0987157155', 'Husband - 0987157156'),
('D-823', 'Ho Van Phuc', 4, 'L-65823', '2026-07-01', 'Active', '0974823822', 'Family - 0974823823'),
('D-931', 'Trinh Thi Mai', 3, 'L-43931', '2028-02-20', 'On Leave', '0935931932', 'Brother - 0935931933'),
('D-1042', 'Ly Van Son', 2, 'L-51042', '2027-10-05', 'Suspended', '0916042043', 'Father - 0916042044');

-- Additional Driver Certifications Owned
INSERT INTO driver_certification_owned (DriverID, CertificationTypeID, IssueDate, ExpiryDate) VALUES
('D-528', 1, '2020-05-01', '2026-06-01'),  -- Vo Thi Hoa: Standard Licence, EXPIRED
('D-604', 1, '2022-01-10', '2028-01-10'),  -- Dang Van Kiet: Standard Licence
('D-604', 2, '2022-06-15', '2028-06-15'),  -- Dang Van Kiet: Heavy Vehicle Licence
('D-604', 3, '2025-01-01', '2026-06-01'),  -- Dang Van Kiet: Refrigerated Transport Certification, EXPIRED
('D-715', 1, '2023-02-01', '2029-02-01'),  -- Bui Thi Ngoc: Standard Licence
('D-823', 1, '2021-07-01', '2026-07-01'),  -- Ho Van Phuc: Standard Licence, EXPIRED
('D-823', 4, '2022-03-01', '2028-03-01'),  -- Ho Van Phuc: EV Certification
('D-931', 2, '2022-04-01', '2028-04-01'),  -- Trinh Thi Mai: Heavy Vehicle Licence
('D-931', 3, '2022-04-01', '2028-04-01'),  -- Trinh Thi Mai: Refrigerated Transport Certification
('D-1042', 1, '2021-09-01', '2027-09-01'); -- Ly Van Son: Standard Licence

-- Additional Vehicle - Driver Assignments
INSERT INTO vehicle_driver_assignment (VehicleID, DriverID, StartDate, EndDate) VALUES
('VEH-004', 'D-715', '2026-02-01', NULL),
('VEH-005', 'D-604', '2026-03-01', NULL),
('VEH-006', 'D-528', '2026-04-01', NULL);

-- Additional Behaviour Events (spread Feb-Aug 2026)
-- Depots: 1=Hanoi, 2=HCMC, 3=Da Nang
INSERT INTO behaviour_event (EventID, VehicleID, DriverID, DepotID, Timestamp, SeverityID, EventType, Description) VALUES
(98,  'VEH-004', 'D-715', 1, '2026-02-05 08:10:00', 1, 'Harsh Braking',     'Odometer: 15,020'),
(99,  'VEH-001', 'D-112', 1, '2026-02-10 09:00:00', 2, 'Sharp Cornering',   'Odometer: 40,000'),
(100, 'VEH-002', 'D-204', 2, '2026-02-18 14:00:00', 1, 'Excessive Idling',  'Odometer: 100,500'),
(101, 'VEH-005', 'D-604', 2, '2026-03-03 11:20:00', 3, 'Speeding',          'Odometer: 61,200'),
(102, 'VEH-003', 'D-417', 3, '2026-03-09 07:45:00', 1, 'Harsh Braking',     'Odometer: 10,800'),
(103, 'VEH-004', 'D-715', 1, '2026-03-15 16:30:00', 2, 'Tailgating',        'Odometer: 16,100'),
(104, 'VEH-001', 'D-112', 1, '2026-03-22 09:10:00', 1, 'Harsh Braking',     'Odometer: 42,000'),
(105, 'VEH-002', 'D-204', 2, '2026-04-02 12:00:00', 2, 'Sharp Cornering',   'Odometer: 105,300'),
(106, 'VEH-006', 'D-528', 3, '2026-04-08 08:20:00', 1, 'Excessive Idling',  'Odometer: 90,000'),
(107, 'VEH-005', 'D-604', 2, '2026-04-19 17:05:00', 3, 'Speeding',          'Odometer: 68,700'),
(108, 'VEH-001', 'D-112', 1, '2026-06-02 08:45:00', 1, 'Harsh Braking',     'Odometer: 46,200'),
(109, 'VEH-004', 'D-715', 1, '2026-06-11 10:15:00', 2, 'Hard Acceleration', 'Odometer: 18,000'),
(110, 'VEH-002', 'D-204', 2, '2026-06-20 13:40:00', 4, 'Fatigue Warning',   'Odometer: 108,900'),
(111, 'VEH-003', 'D-417', 3, '2026-07-01 09:25:00', 1, 'Excessive Idling',  'Odometer: 11,600'),
(112, 'VEH-005', 'D-604', 2, '2026-07-14 15:50:00', 3, 'Speeding',          'Odometer: 72,400'),
(113, 'VEH-001', 'D-112', 1, '2026-07-25 08:05:00', 2, 'Sharp Cornering',   'Odometer: 47,800'),
(114, 'VEH-002', 'D-204', 2, '2026-08-01 07:50:00', 3, 'Tailgating',        'Odometer: 111,200'),
(115, 'VEH-004', 'D-715', 1, '2026-08-03 16:00:00', 1, 'Harsh Braking',     'Odometer: 19,200');

-- Monthly scores for these drivers are computed by trigger, not hand-entered.

-- Additional Workshop
INSERT INTO workshop (WorkshopID, WorkshopName, WorkshopAddress, DepotID) VALUES
(3, 'Da Nang Coastal Workshop', 'Road No. 5G, Da Nang High-Tech Park, Hoa Vang District, Da Nang', 3);

-- Additional Mechanics
INSERT INTO mechanic_worker (MechanicID, FullName, EmploymentStatus, EmergencyContactDetails, WorkshopID) VALUES
('ME-21', 'Le Thi Hang', 'Active', 'Husband - 0935214213', 3),
('ME-24', 'Nguyen Van Tai', 'Active', 'Wife - 0916244243', 2);

-- Additional Mechanic Certification History
INSERT INTO mechanic_worker_certifications_history (MechanicID, CertificationID, IssueDate, ExpiryDate) VALUES
('ME-21', 1, '2023-01-10', '2033-01-10'),
('ME-21', 2, '2023-05-01', '2029-05-01'),
('ME-24', 1, '2021-08-15', '2031-08-15'),
('ME-24', 4, '2022-02-01', '2028-02-01');

-- Additional Alerts
INSERT INTO alert (AlertID, AlertName, VehicleID, AlertDescription, AlertTimestamp, Status) VALUES
(3, 'Tyre Pressure Low', 'VEH-004', 'Front-left tyre pressure below recommended threshold.', '2026-06-05 09:00:00', 'New'),
(4, 'Engine Overheat Warning', 'VEH-007', 'Coolant temperature exceeding safe operating range.', '2026-07-20 13:10:00', 'Escalated'),
(5, 'Battery Health Degraded', 'VEH-003', 'EV battery state-of-health below 80%.', '2026-04-10 10:00:00', 'Resolved');

-- Additional Maintenance Jobs
INSERT INTO maintenance_job (JobID, VehicleID, WorkshopID, StartDate, EndDate, Status, AlertID, TotalCost) VALUES
(1024, 'VEH-004', 1, '2026-06-06 09:00:00', '2026-06-06 14:00:00', 'Closed', 3, 950000),
(1025, 'VEH-005', 2, '2026-04-20 08:00:00', '2026-04-21 08:00:00', 'Closed', NULL, 4200000),
(1026, 'VEH-007', 2, '2026-07-20 14:00:00', NULL, 'Open', 4, NULL),
(1027, 'VEH-003', 3, '2026-04-11 09:00:00', '2026-04-11 15:00:00', 'Closed', 5, 1250000),
(1028, 'VEH-006', 3, '2026-05-25 08:00:00', '2026-05-25 12:00:00', 'Closed', NULL, 600000),
(1029, 'VEH-001', 1, '2026-07-02 09:00:00', '2026-07-02 15:00:00', 'Closed', NULL, 500000),
(1030, 'VEH-002', 2, '2026-07-10 08:00:00', '2026-07-11 08:00:00', 'Closed', NULL, 2900000);

-- Additional Activity Instances
INSERT INTO activity_instance (ActivityID, JobID, ActivityTypeID, LabourHours, DiagnosticResult) VALUES
(106, 1024, 10, 1.5, 'Front-left tyre replaced, pressure sensor recalibrated'),
(107, 1025, 2, 6.0, 'Full service - oil, filters, brake fluid'),
(108, 1025, 9, 2.0, 'Rear brake pads replaced'),
(109, 1026, 8, 4.0, 'Engine coolant system inspection - awaiting parts for radiator replacement'),
(110, 1027, 6, 3.5, 'Battery module diagnostics - degraded cell replaced'),
(111, 1028, 1, 1.0, 'Routine inspection - all systems normal'),
(112, 1029, 1, 1.0, 'Routine inspection - all systems normal'),
(113, 1030, 9, 3.0, 'Brake pads worn - replaced both axles');

-- Additional Mechanic Activity Assignments
INSERT INTO activity_instance_worker_assigned (ActivityID, MechanicID, LabourHours) VALUES
(106, 'ME-12', 1.5),
(107, 'ME-07', 6.0),
(108, 'ME-09', 2.0),
(109, 'ME-24', 4.0),
(110, 'ME-21', 3.5),
(111, 'ME-21', 1.0),
(112, 'ME-15', 1.0),
(113, 'ME-07', 3.0);

UPDATE activity_instance SET WarrantyApplicable = TRUE WHERE ActivityID = 110;

-- Additional Suppliers
INSERT INTO partner_company (PartnerID, PartnerName, PartnerType, DeliveryLeadTimes, ContactInfo, Description) VALUES
(6, 'VinFast EV Parts Center', 'Manufacturer', '6 Days', 'evparts@vinfast.vn, Tel: +84-225-730-9999', 'OEM EV battery and drivetrain components'),
(7, 'Southern Truck Radiators Ltd.', 'Supplier', '4 Days', 'sales@southerntruckradiators.com, Tel: +84-28-3822-1010', 'Heavy truck cooling system specialist');

-- Additional Parts (inventory columns included directly)
INSERT INTO part (PartID, PartName, PrimarySupplierID, BackupSupplierID, QuantityOnHand, ReorderThreshold) VALUES
(504, 'EV Battery Module', 6, 3, 3, 2),
(505, 'Engine Coolant Radiator', 7, 2, 6, 3);

-- Additional Supplier Product List
INSERT INTO supplier_product_list (PartID, PartnerID, CostPerUnit, Description) VALUES
(504, 6, 18000000, 'OEM EV battery module for VF Pro Van'),
(504, 3, 15500000, 'Aftermarket-compatible EV battery module'),
(505, 7, 3200000, 'Heavy-duty replacement radiator for commercial trucks'),
(505, 2, 3800000, 'Isuzu Genuine OEM radiator assembly');

-- Additional Parts Used in Activities
INSERT INTO activity_instance_part_used (ActivityID, PartID, QuantityUsed, SupplierID) VALUES
(106, 502, 1, 4),
(108, 501, 1, 3),
(109, 505, 1, 7),
(110, 504, 1, 6),
(113, 501, 1, 1);

-- Additional Warranty Claims
INSERT INTO warranty_claim (WarrantyClaimID, PartnerID, ActivityID, Status, ClaimDate, ClaimResolvedDate) VALUES
('WAR-2026-0002', 6, 110, 'Resolved', '2026-04-12 09:00:00', '2026-04-20 10:00:00');

INSERT INTO warranty_part_list (WarrantyClaimID, PartID) VALUES
('WAR-2026-0002', 504);

-- Additional Coaching Log
INSERT INTO coaching_log (DriverID, EventID, CoachDate, ConductedBy, Outcome, Notes) VALUES
('D-604', 101, '2026-03-04', 'Fleet Manager', 'Coached - Verbal Warning', 'First speeding event discussed.'),
('D-604', 107, '2026-04-20', 'Fleet Manager', 'Retraining Required', 'Second speeding event within two months - mandatory retraining scheduled.'),
('D-204', 110, '2026-06-21', 'Fleet Manager', 'Coached - Written Warning', 'Fatigue warning discussed; reminded of mandatory rest breaks.'),
('D-715', 103, '2026-03-16', 'Fleet Manager', 'Completed - No Concerns', 'Minor tailgating event reviewed; no further action needed.');

-- Rebuilds monthly_score_log from the behaviour_event rows above
-- (requires business_rules.sql to already be imported).
CALL sp_recalculate_all_monthly_scores();