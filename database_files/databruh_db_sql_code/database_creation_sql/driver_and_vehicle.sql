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

