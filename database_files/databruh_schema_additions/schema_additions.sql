USE databruh_db;

-- Soft-delete support for vehicle and driver
ALTER TABLE vehicle
    ADD COLUMN IsDeleted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN DeletedAt DATETIME NULL;

ALTER TABLE driver
    ADD COLUMN IsDeleted TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN DeletedAt DATETIME NULL;

-- Review comments / coaching recommendations attached to a safety incident.
CREATE TABLE incident_review (
    ReviewID INT AUTO_INCREMENT PRIMARY KEY,
    EventID INT NOT NULL,
    ReviewerName VARCHAR(255),
    Comment TEXT NOT NULL,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (EventID) REFERENCES behaviour_event(EventID) ON DELETE CASCADE
);
