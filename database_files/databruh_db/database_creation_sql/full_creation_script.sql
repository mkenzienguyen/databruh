DROP DATABASE IF EXISTS databruh_db;
CREATE DATABASE databruh_db;
USE databruh_db;

CREATE TABLE depot_location (
    DepotID INT AUTO_INCREMENT PRIMARY KEY,
    DepotName VARCHAR(255) NOT NULL,
    DepotAddress VARCHAR(255)
);

CREATE TABLE vehicle_status (
    StatusID INT AUTO_INCREMENT PRIMARY KEY,
    StatusName VARCHAR(255) NOT NULL
);

CREATE TABLE vehicle_classification (
    ClassificationID INT AUTO_INCREMENT PRIMARY KEY,
    ClassificationName VARCHAR(255) NOT NULL
);

CREATE TABLE vehicle_certification_type (
    CertificationTypeID INT AUTO_INCREMENT PRIMARY KEY,
    CertificationName VARCHAR(255) NOT NULL
);

CREATE TABLE vehicle_type_certification_requirement (
    ClassificationID INT,
    CertificationTypeID INT,
    PRIMARY KEY (ClassificationID, CertificationTypeID),
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE CASCADE,
    FOREIGN KEY (CertificationTypeID) REFERENCES vehicle_certification_type(CertificationTypeID) ON DELETE CASCADE
);

CREATE TABLE vehicle (
    VehicleID VARCHAR(50) PRIMARY KEY,
    RegistrationNumber VARCHAR(100) NOT NULL UNIQUE,
    Manufacturer VARCHAR(255),
    Model VARCHAR(255),
    ClassificationID INT,
    YearOfManufacture INT,
    StatusID INT,
    DepotID INT,
    CurrentOdometer INT NOT NULL DEFAULT 0,
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE SET NULL,
    FOREIGN KEY (StatusID) REFERENCES vehicle_status(StatusID) ON DELETE SET NULL,
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID) ON DELETE SET NULL
);

CREATE TABLE driver (
    DriverID VARCHAR(50) PRIMARY KEY,
    FullName VARCHAR(255) NOT NULL,
    DepotID INT,
    LicenseNumber VARCHAR(50) NOT NULL UNIQUE,
    LicenseExpiration DATE NOT NULL,
    EmploymentStatus VARCHAR(100) DEFAULT 'Active',
    ContactInfo VARCHAR(255),
    EmergencyContactDetails VARCHAR(100),
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID) ON DELETE SET NULL
);

CREATE TABLE vehicle_driver_assignment (
    AssignmentID INT AUTO_INCREMENT PRIMARY KEY,
    VehicleID VARCHAR(50) NOT NULL,
    DriverID VARCHAR(50) NOT NULL,
    StartDate DATE NOT NULL,
    EndDate DATE,
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID) ON DELETE CASCADE,
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE
);

CREATE TABLE driver_certification_owned (
    DriverID VARCHAR(50),
    CertificationTypeID INT,
    IssueDate DATE NOT NULL,
    ExpiryDate DATE,
    PRIMARY KEY (DriverID, CertificationTypeID),
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE,
    FOREIGN KEY (CertificationTypeID) REFERENCES vehicle_certification_type(CertificationTypeID) ON DELETE CASCADE
);

CREATE TABLE monthly_score_log (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    DriverID VARCHAR(50) NOT NULL,
    Month INT NOT NULL CHECK (Month BETWEEN 1 AND 12),
    Year INT NOT NULL,
    Score INT NOT NULL DEFAULT 100 CHECK (Score BETWEEN 0 AND 100),
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE,
    CONSTRAINT unique_driver_month_year UNIQUE (DriverID, Month, Year)
);

CREATE TABLE severity_level (
    SeverityID INT AUTO_INCREMENT PRIMARY KEY,
    LevelName VARCHAR(50) NOT NULL
);

CREATE TABLE behaviour_event (
    EventID INT AUTO_INCREMENT PRIMARY KEY,
    VehicleID VARCHAR(50) NOT NULL,
    DriverID VARCHAR(50) NULL,
    DepotID INT,
    Timestamp DATETIME NOT NULL,
    SeverityID INT,
    EventType VARCHAR(100) NOT NULL,
    Description TEXT,
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID),
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID),
    FOREIGN KEY (SeverityID) REFERENCES severity_level(SeverityID),
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID)
);

CREATE TABLE workshop (
    WorkshopID INT AUTO_INCREMENT PRIMARY KEY,
    WorkshopName VARCHAR(255) NOT NULL,
    WorkshopAddress VARCHAR(255),
    DepotID INT,
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID) ON DELETE SET NULL,
    UNIQUE KEY uq_workshop_depot (DepotID)
);

CREATE TABLE alert (
    AlertID INT AUTO_INCREMENT PRIMARY KEY,
    AlertName VARCHAR(100) NOT NULL,
    VehicleID VARCHAR(50) NOT NULL,
    AlertDescription TEXT,
    AlertTimestamp DATETIME NOT NULL,
    Status VARCHAR(50) DEFAULT 'New',
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID) ON DELETE CASCADE
);

CREATE TABLE maintenance_job (
    JobID INT AUTO_INCREMENT PRIMARY KEY,
    VehicleID VARCHAR(50) NOT NULL,
    WorkshopID INT NOT NULL,
    StartDate DATETIME NOT NULL,
    EndDate DATETIME,
    Status VARCHAR(50),
    AlertID INT,
    ToTalCost INT,
    FOREIGN KEY (VehicleID) REFERENCES vehicle(VehicleID),
    FOREIGN KEY (WorkshopID) REFERENCES workshop(WorkshopID),
    FOREIGN KEY (AlertID) REFERENCES alert(AlertID)
);

CREATE TABLE activity_certification (
    CertificationID INT AUTO_INCREMENT PRIMARY KEY,
    CertificationName VARCHAR(255) NOT NULL,
    Description TEXT
);

CREATE TABLE activity_type (
    ActivityTypeID INT AUTO_INCREMENT PRIMARY KEY,
    ActivityTypeName VARCHAR(255) NOT NULL UNIQUE,
    CertificationID INT,
    FOREIGN KEY (CertificationID) REFERENCES activity_certification(CertificationID) ON DELETE SET NULL
);

CREATE TABLE mechanic_worker (
    MechanicID VARCHAR(50) PRIMARY KEY,
    FullName VARCHAR(255) NOT NULL,
    EmploymentStatus VARCHAR(100),
    EmergencyContactDetails VARCHAR(100),
    WorkshopID INT,
    FOREIGN KEY (WorkshopID) REFERENCES workshop(WorkshopID) ON DELETE SET NULL
);

CREATE TABLE mechanic_worker_certifications_history (
    CertificationLogID INT AUTO_INCREMENT PRIMARY KEY,
    MechanicID VARCHAR(50),
    CertificationID INT,
    IssueDate DATE NOT NULL,
    ExpiryDate DATE,
    FOREIGN KEY (MechanicID) REFERENCES mechanic_worker(MechanicID),
    FOREIGN KEY (CertificationID) REFERENCES activity_certification(CertificationID)
);

CREATE TABLE activity_instance (
    ActivityID INT AUTO_INCREMENT PRIMARY KEY,
    JobID INT NOT NULL,
    ActivityTypeID INT NOT NULL,
    LabourHours DECIMAL(4,2),
    DiagnosticResult TEXT,
    RepeatFault BOOLEAN NOT NULL DEFAULT FALSE,
    WarrantyApplicable BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (JobID) REFERENCES maintenance_job(JobID) ON DELETE CASCADE,
    FOREIGN KEY (ActivityTypeID) REFERENCES activity_type(ActivityTypeID)
);

CREATE TABLE activity_instance_worker_assigned (
    ActivityID INT NOT NULL,
    MechanicID VARCHAR(50) NOT NULL,
    LabourHours DECIMAL(4,2) NULL,
    PRIMARY KEY (ActivityID, MechanicID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID) ON DELETE CASCADE,
    FOREIGN KEY (MechanicID) REFERENCES mechanic_worker(MechanicID) ON DELETE CASCADE
);

CREATE TABLE partner_company (
    PartnerID INT AUTO_INCREMENT PRIMARY KEY,
    PartnerName VARCHAR(255),
    PartnerType VARCHAR(50),
    DeliveryLeadTimes VARCHAR(10),
    ContactInfo VARCHAR(255),
    Description TEXT
);

CREATE TABLE part (
    PartID INT AUTO_INCREMENT PRIMARY KEY,
    PartName VARCHAR(255),
    PrimarySupplierID INT NOT NULL,
    BackupSupplierID INT,
    QuantityOnHand INT NOT NULL DEFAULT 0,
    ReorderThreshold INT NOT NULL DEFAULT 0,
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
    SupplierID INT NULL,
    PRIMARY KEY (ActivityID, PartID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID),
    FOREIGN KEY (PartID) REFERENCES part(PartID),
    FOREIGN KEY (SupplierID) REFERENCES partner_company(PartnerID)
);

