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