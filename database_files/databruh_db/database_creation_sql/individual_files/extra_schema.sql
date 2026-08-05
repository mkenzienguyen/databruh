USE databruh_db;
CREATE TABLE coaching_log (
    CoachingID INT AUTO_INCREMENT PRIMARY KEY,
    DriverID VARCHAR(50) NOT NULL,
    EventID INT NULL,
    CoachDate DATE NOT NULL,
    ConductedBy VARCHAR(255),
    Outcome VARCHAR(50) NOT NULL,
    Notes TEXT,
    FOREIGN KEY (DriverID) REFERENCES driver(DriverID) ON DELETE CASCADE,
    FOREIGN KEY (EventID) REFERENCES behaviour_event(EventID) ON DELETE SET NULL
);

CREATE TABLE maintenance_schedule_rule (
    RuleID INT AUTO_INCREMENT PRIMARY KEY,
    ClassificationID INT NOT NULL,
    IntervalDays INT NOT NULL,
    Description VARCHAR(255),
    FOREIGN KEY (ClassificationID) REFERENCES vehicle_classification(ClassificationID) ON DELETE CASCADE,
    UNIQUE KEY unique_classification_rule (ClassificationID)
);