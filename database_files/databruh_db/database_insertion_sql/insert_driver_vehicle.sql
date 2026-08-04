
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
('VEH-002', 'D-204', '2026-05-01', NULL),
-- D-417 only holds Hazardous Goods Certification (5), not the Standard (1)
-- or EV (4) certifications VEH-003's Electric Van classification requires —
-- demonstrates view_unauthorized_vehicle_operation below.
('VEH-003', 'D-417', '2026-05-20', NULL);

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
(96, 'VEH-002', 'D-204', 2, '2026-05-13 18:05:00', 4, 'Speeding', 'Odometer: 112,480'),
(97, 'VEH-001', 'D-112', 1, '2026-05-15 10:05:00', 2, 'Speeding', 'Odometer: 45,410, second speeding event this week');

-- ==========================================
-- 12. Monthly Score Log
-- ==========================================
INSERT INTO monthly_score_log (DriverID, Month, Year, Score) VALUES
('D-112', 4, 2026, 95),  -- Nguyen Van An (April performance)
('D-112', 5, 2026, 88),  -- Nguyen Van An (May performance drops due to telematics alerts)
('D-204', 4, 2026, 100), -- Tran Thi Bich (Perfect safety record)
('D-204', 5, 2026, 92),  -- Tran Thi Bich (Minor penalty for sharp cornering)
('D-331', 5, 2026, 98),  -- Le Quoc Minh (Clean record since joining May assignment)
('D-417', 5, 2026, 100); -- Pham Duc Long (Flawless score)

-- ==========================================
-- 13. Coaching Log
-- ==========================================
-- Events 92 and 94 are resolved (coached). Events 91, 93, 95, 96, 97 are
-- left uncoached so view_incident_resolution has both resolved and
-- unresolved rows to demonstrate against.
INSERT INTO coaching_log (DriverID, EventID, CoachDate, ConductedBy, Outcome, Notes) VALUES
('D-112', 92, '2026-05-11', 'Fleet Manager', 'Coached - Verbal Warning', 'Reviewed telematics speeding event with driver; acknowledged and corrected.'),
('D-112', 94, '2026-05-13', 'Fleet Manager', 'Retraining Required', 'Second high-severity event this week (fatigue warning). Scheduled for defensive driving refresher.'),
('D-331', NULL, '2026-05-14', 'Fleet Manager', 'Completed - No Concerns', 'Routine quarterly safety check-in; no incidents to discuss.');

-- ==========================================
-- Additional Vehicles
-- ==========================================
INSERT INTO vehicle (VehicleID, RegistrationNumber, Manufacturer, Model, ClassificationID, YearOfManufacture, StatusID, DepotID, CurrentOdometer) VALUES
('VEH-004', '30F-224.10', 'Ford', 'Transit', 1, 2024, 1, 1, 18500),
('VEH-005', '51C-880.22', 'Isuzu', 'QKR77HE4', 2, 2023, 1, 2, 76200),
('VEH-006', '43H-901.33', 'Hyundai', 'Porter', 4, 2021, 1, 3, 98400),
('VEH-007', '51D-114.55', 'Hino', 'FL', 5, 2020, 5, 2, 210300),
('VEH-008', '65A-337.88', 'Ford', 'Transit', 1, 2022, 4, 4, 61200);

-- VEH-002 currently has an unresolved open job (1023, see
-- insert_maintenance.sql) - mark it as actually under maintenance
-- rather than nominally Active.
UPDATE vehicle SET StatusID = 3 WHERE VehicleID = 'VEH-002';

-- ==========================================
-- Additional Drivers
-- ==========================================
INSERT INTO driver (DriverID, FullName, DepotID, LicenseNumber, LicenseExpiration, EmploymentStatus, ContactInfo, EmergencyContactDetails) VALUES
('D-528', 'Vo Thi Hoa', 3, 'L-43528', '2029-01-15', 'Active', '0935285281', 'Sister - 0935285282'),
('D-604', 'Dang Van Kiet', 2, 'L-51604', '2028-09-01', 'Active', '0916046044', 'Wife - 0916046045'),
('D-715', 'Bui Thi Ngoc', 1, 'L-29715', '2029-03-10', 'Active', '0987157155', 'Husband - 0987157156'),
('D-823', 'Ho Van Phuc', 4, 'L-65823', '2026-07-01', 'Active', '0974823822', 'Family - 0974823823'),
('D-931', 'Trinh Thi Mai', 3, 'L-43931', '2028-02-20', 'On Leave', '0935931932', 'Brother - 0935931933'),
('D-1042', 'Ly Van Son', 2, 'L-51042', '2027-10-05', 'Suspended', '0916042043', 'Father - 0916042044');

-- ==========================================
-- Additional Driver Certifications Owned
-- ==========================================
-- D-528's Standard Licence has lapsed, and D-823's Standard Licence
-- expires the same day as their driving licence - both feed
-- view_expired_certifications.
INSERT INTO driver_certification_owned (DriverID, CertificationTypeID, IssueDate, ExpiryDate) VALUES
('D-528', 1, '2020-05-01', '2026-06-01'),  -- Vo Thi Hoa: Standard Licence, EXPIRED
('D-604', 1, '2022-01-10', '2028-01-10'),  -- Dang Van Kiet: Standard Licence
('D-604', 2, '2022-06-15', '2028-06-15'),  -- Dang Van Kiet: Heavy Vehicle Licence (no Refrigerated cert)
('D-715', 1, '2023-02-01', '2029-02-01'),  -- Bui Thi Ngoc: Standard Licence
('D-823', 1, '2021-07-01', '2026-07-01'),  -- Ho Van Phuc: Standard Licence, EXPIRED
('D-823', 4, '2022-03-01', '2028-03-01'),  -- Ho Van Phuc: EV Certification
('D-931', 2, '2022-04-01', '2028-04-01'),  -- Trinh Thi Mai: Heavy Vehicle Licence
('D-931', 3, '2022-04-01', '2028-04-01'),  -- Trinh Thi Mai: Refrigerated Transport Certification
('D-1042', 1, '2021-09-01', '2027-09-01'); -- Ly Van Son: Standard Licence

-- ==========================================
-- Additional Vehicle - Driver Assignments
-- ==========================================
-- D-604 holds Standard + Heavy Vehicle certs but not the Refrigerated
-- Transport cert VEH-005's classification also requires - a second
-- view_unauthorized_vehicle_operation case alongside D-417/VEH-003.
INSERT INTO vehicle_driver_assignment (VehicleID, DriverID, StartDate, EndDate) VALUES
('VEH-004', 'D-715', '2026-02-01', NULL),
('VEH-005', 'D-604', '2026-03-01', NULL),
('VEH-006', 'D-528', '2026-04-01', NULL);

-- ==========================================
-- Additional Behaviour Events (spread Feb-Aug 2026)
-- ==========================================
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

-- ==========================================
-- Additional Monthly Score Log
-- ==========================================
INSERT INTO monthly_score_log (DriverID, Month, Year, Score) VALUES
-- D-112: declining trend through the year
('D-112', 2, 2026, 96),
('D-112', 3, 2026, 94),
('D-112', 6, 2026, 85),
('D-112', 7, 2026, 80),
('D-112', 8, 2026, 78),
-- D-204: declining trend
('D-204', 2, 2026, 98),
('D-204', 3, 2026, 97),
('D-204', 6, 2026, 90),
('D-204', 7, 2026, 85),
('D-204', 8, 2026, 82),
-- D-331: stable, clean record
('D-331', 3, 2026, 99),
('D-331', 4, 2026, 97),
('D-331', 6, 2026, 96),
('D-331', 7, 2026, 95),
('D-331', 8, 2026, 94),
-- D-417: stable, clean record
('D-417', 6, 2026, 99),
('D-417', 7, 2026, 97),
('D-417', 8, 2026, 96),
-- D-604: sharp decline tied to repeated speeding
('D-604', 3, 2026, 90),
('D-604', 4, 2026, 82),
('D-604', 5, 2026, 75),
('D-604', 6, 2026, 70),
('D-604', 7, 2026, 65),
-- D-715: mild decline
('D-715', 2, 2026, 95),
('D-715', 3, 2026, 93),
('D-715', 4, 2026, 94),
('D-715', 6, 2026, 91),
('D-715', 7, 2026, 90),
('D-715', 8, 2026, 88),
-- D-528 and D-823: light history
('D-528', 4, 2026, 96),
('D-528', 5, 2026, 95),
('D-823', 5, 2026, 99),
('D-823', 6, 2026, 98);

-- ==========================================
-- Additional Coaching Log
-- ==========================================
INSERT INTO coaching_log (DriverID, EventID, CoachDate, ConductedBy, Outcome, Notes) VALUES
('D-604', 101, '2026-03-04', 'Fleet Manager', 'Coached - Verbal Warning', 'First speeding event discussed.'),
('D-604', 107, '2026-04-20', 'Fleet Manager', 'Retraining Required', 'Second speeding event within two months - mandatory retraining scheduled.'),
('D-204', 110, '2026-06-21', 'Fleet Manager', 'Coached - Written Warning', 'Fatigue warning discussed; reminded of mandatory rest breaks.'),
('D-715', 103, '2026-03-16', 'Fleet Manager', 'Completed - No Concerns', 'Minor tailgating event reviewed; no further action needed.');
