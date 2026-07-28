DROP DATABASE IF EXISTS databruh_password_db;
CREATE DATABASE databruh_password_db;

USE databruh_password_db;

CREATE TABLE account_type (
    TypeID VARCHAR(10) PRIMARY KEY,
    TypeName VARCHAR(255) NOT NULL
);

INSERT INTO account_type (TypeID, TypeName) VALUES
('ADMIN',     'System Administrator'),
('FLEET_MGR', 'Fleet Operations Manager'),
('WS_MGR',    'Workshop Manager'),
('MECHANIC',  'Mechanic'),
('DRIVER',    'Driver');


CREATE TABLE account (
    AccountID INT AUTO_INCREMENT PRIMARY KEY,
    FullName VARCHAR(255) NOT NULL,
    Email VARCHAR(255) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    TypeID VARCHAR(10) NOT NULL,
    FOREIGN KEY (TypeID) REFERENCES account_type(TypeID)
);