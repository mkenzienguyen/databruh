USE databruh_db;

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
INSERT INTO activity_instance_part_used (ActivityID, PartID, QuantityUsed, SupplierID) VALUES
(101, 501, 1, 3), -- Used 1 Set of Front Brake Pads for VEH-001, supplied by Hanoi Auto Parts JSC
(102, 502, 2, 4), -- Used 2 New Tyres for VEH-001, supplied by Saigon Fleet Supplies Co.
(104, 503, 1, 5); -- Used 1 Refrigeration Belt for VEH-002, supplied by Carrier Transicold Southeast Asia

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

-- ==========================================
-- 7. Part Inventory Levels
-- ==========================================
-- Tyres (502) are seeded below their reorder threshold.
UPDATE part SET QuantityOnHand = 40, ReorderThreshold = 15 WHERE PartID = 501;
UPDATE part SET QuantityOnHand = 8,  ReorderThreshold = 10 WHERE PartID = 502;
UPDATE part SET QuantityOnHand = 20, ReorderThreshold = 5  WHERE PartID = 503;

-- ==========================================
-- Additional Suppliers
-- ==========================================
INSERT INTO partner_company (PartnerID, PartnerName, PartnerType, DeliveryLeadTimes, ContactInfo, Description) VALUES
(6, 'VinFast EV Parts Center', 'Manufacturer', '6 Days', 'evparts@vinfast.vn, Tel: +84-225-730-9999', 'OEM EV battery and drivetrain components'),
(7, 'Southern Truck Radiators Ltd.', 'Supplier', '4 Days', 'sales@southerntruckradiators.com, Tel: +84-28-3822-1010', 'Heavy truck cooling system specialist');

-- ==========================================
-- Additional Parts (inventory columns included directly)
-- ==========================================
INSERT INTO part (PartID, PartName, PrimarySupplierID, BackupSupplierID, QuantityOnHand, ReorderThreshold) VALUES
(504, 'EV Battery Module', 6, 3, 3, 2),
(505, 'Engine Coolant Radiator', 7, 2, 6, 3);

-- ==========================================
-- Additional Supplier Product List
-- ==========================================
INSERT INTO supplier_product_list (PartID, PartnerID, CostPerUnit, Description) VALUES
(504, 6, 18000000, 'OEM EV battery module for VF Pro Van'),
(504, 3, 15500000, 'Aftermarket-compatible EV battery module'),
(505, 7, 3200000, 'Heavy-duty replacement radiator for commercial trucks'),
(505, 2, 3800000, 'Isuzu Genuine OEM radiator assembly');

-- ==========================================
-- Additional Parts Used in Activities
-- ==========================================
INSERT INTO activity_instance_part_used (ActivityID, PartID, QuantityUsed, SupplierID) VALUES
(106, 502, 1, 4),
(108, 501, 1, 3),
(109, 505, 1, 7),
(110, 504, 1, 6),
(113, 501, 1, 1);

-- ==========================================
-- Additional Warranty Claims
-- ==========================================
INSERT INTO warranty_claim (WarrantyClaimID, PartnerID, ActivityID, Status, ClaimDate, ClaimResolvedDate) VALUES
('WAR-2026-0002', 6, 110, 'Resolved', '2026-04-12 09:00:00', '2026-04-20 10:00:00');

INSERT INTO warranty_part_list (WarrantyClaimID, PartID) VALUES
('WAR-2026-0002', 504);
