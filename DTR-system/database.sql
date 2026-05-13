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
    photo_path VARCHAR(255) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idnumber) REFERENCES user(idnumber) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);

-- Insert default cutoff times
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('morning_cutoff', '12:00:00');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('afternoon_cutoff', '13:00:00');

-- Insert a dummy user so you can test the time in/out immediately
INSERT INTO user (idnumber, name) VALUES ('2024-03574', 'Eric Fermo');

-- Insert a dummy admin account
-- Password is 'admin123'
INSERT INTO admin (idnumber, password, name) VALUES ('admin', 'admin123', 'System Administrator');