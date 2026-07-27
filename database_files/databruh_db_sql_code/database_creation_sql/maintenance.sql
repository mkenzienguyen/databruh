USE databruh_db;
CREATE TABLE workshop (
    WorkshopID INT AUTO_INCREMENT PRIMARY KEY,
    WorkshopName VARCHAR(255) NOT NULL,
    WorkshopAddress VARCHAR(255),
    DepotID INT,
    FOREIGN KEY (DepotID) REFERENCES depot_location(DepotID) ON DELETE SET NULL
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
    FOREIGN KEY (JobID) REFERENCES maintenance_job(JobID) ON DELETE CASCADE,
    FOREIGN KEY (ActivityTypeID) REFERENCES activity_type(ActivityTypeID)
);


CREATE TABLE activity_instance_worker_assigned (
    ActivityID INT NOT NULL,
    MechanicID VARCHAR(50) NOT NULL,
    PRIMARY KEY (ActivityID, MechanicID),
    FOREIGN KEY (ActivityID) REFERENCES activity_instance(ActivityID) ON DELETE CASCADE,
    FOREIGN KEY (MechanicID) REFERENCES mechanic_worker(MechanicID) ON DELETE CASCADE
);