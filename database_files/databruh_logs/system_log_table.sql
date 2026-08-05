USE databruh_db;

CREATE TABLE system_log (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    EntityType VARCHAR(50) NOT NULL,               -- e.g. 'driver', 'part', 'maintenance_job'
    EntityID VARCHAR(50) NOT NULL,                 -- the PK of whatever got created
    Action VARCHAR(20) NOT NULL DEFAULT 'CREATE',  -- room to add UPDATE / DELETE later
    Description VARCHAR(255),                      -- human-readable summary
    LoggedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
