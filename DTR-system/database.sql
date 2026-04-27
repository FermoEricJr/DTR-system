CREATE DATABASE IF NOT EXISTS DTRsystem;
USE DTRsystem;

CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idnumber VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idnumber VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idnumber VARCHAR(50) NOT NULL,
    record_type ENUM('timein', 'timeout') NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idnumber) REFERENCES user(idnumber) ON DELETE CASCADE
);

-- Insert a dummy user so you can test the time in/out immediately
INSERT INTO user (idnumber, name) VALUES ('12345', 'John Doe');

-- Insert a dummy admin account
-- Password is 'admin123'
INSERT INTO admin (idnumber, password, name) VALUES ('admin', 'admin123', 'System Administrator');