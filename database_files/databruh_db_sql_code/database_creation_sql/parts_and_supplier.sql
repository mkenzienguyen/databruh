USE databruh_db;

CREATE TABLE partner_company (
    PartnerID INT AUTO_INCREMENT PRIMARY KEY,
    PartnerName VARCHAR(255),
    PartnerType VARCHAR(50),
    DeliveryLeadTimes VARCHAR(10),
    ContactInfo VARCHAR(255),
    Description  TEXT
);



CREATE TABLE part (
    PartID INT AUTO_INCREMENT PRIMARY KEY,
    PartName VARCHAR(255),
    PrimarySupplierID INT NOT NULL,
    BackupSupplierID INT,
    FOREIGN KEY (PrimarySupplierID) REFERENCES partner_company(PartnerID) ON DELETE CASCADE,
    FOREIGN KEY (BackupSupplierID) REFERENCES partner_company(PartnerID) ON DELETE CASCADE
);

CREATE TABLE supplier_product_list (
    PartID INT NOT NULL,
    PartnerID INT NOT NULL,
    CostPerUnit INT,
    Description TEXT,
    FOREIGN KEY (PartID) REFERENCES part(PartID) ON DELETE CASCADE,
    FOREIGN KEY (PartnerID) REFERENCES partner_company(PartnerID) ON DELETE CASCADE
);


CREATE TABLE warranty_claim (
    WarrantyClaimID VARCHAR(50) PRIMARY KEY,
    PartnerID INT NOT NULL,
    ActivityID INT NOT NULL,
    Status VARCHAR(20) DEFAULT 'On going',
    ClaimDate DATETIME NOT NULL,
    ClaimResolvedDate DATETIME,
    FOREIGN KEY (PartnerID) REFERENCES partner_company(PartnerID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID) ON DELETE CASCADE
);

CREATE TABLE warranty_part_list (
    WarrantyClaimID VARCHAR(50) NOT NULL,
    PartID INT NOT NULL,
    PRIMARY KEY (WarrantyClaimID, PartID),
    FOREIGN KEY (WarrantyClaimID) REFERENCES warranty_claim(WarrantyClaimID) ON DELETE CASCADE,
    FOREIGN KEY (PartID) REFERENCES part(PartID) ON DELETE CASCADE
);


CREATE TABLE activity_instance_part_used (
    ActivityID INT NOT NULL,
    PartID INT NOT NULL,
    QuantityUsed INT,
    PRIMARY KEY (ActivityID, PartID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID),
    FOREIGN KEY (PartID) REFERENCES part(PartID)

);