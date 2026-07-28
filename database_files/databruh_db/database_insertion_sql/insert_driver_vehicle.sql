
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