USE databruh_db;

-- An event with no matching coaching_log row is an unresolved incident.
CREATE TABLE IF NOT EXISTS coaching_log (
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
