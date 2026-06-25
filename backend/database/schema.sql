-- MediCare Clinic Appointment System — Full Database Schema
-- Run this file in phpMyAdmin or: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS medicare_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medicare_db;

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLES
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id         INT            NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100)   NOT NULL,
    email      VARCHAR(100)   NOT NULL UNIQUE,
    password   VARCHAR(255)   NOT NULL,
    role       ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
    phone      VARCHAR(20)    NULL,
    address    TEXT           NULL,
    position   VARCHAR(100)   NULL,
    is_active  TINYINT(1)     NOT NULL DEFAULT 1,
    created_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctors (
    id              INT          NOT NULL AUTO_INCREMENT,
    user_id         INT          NOT NULL,
    specialization  VARCHAR(100) NOT NULL,
    availability    VARCHAR(255) NULL,
    work_days       VARCHAR(50)  NULL,       -- ISO day numbers 1=Mon…7=Sun, e.g. "1,2,3,4,5"
    work_start      TIME         NULL,       -- e.g. '09:00:00'
    work_end        TIME         NULL,       -- e.g. '17:00:00'
    license_number  VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_doctors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id               INT  NOT NULL AUTO_INCREMENT,
    patient_id       INT  NOT NULL,
    doctor_id        INT  NOT NULL,
    date             DATE NOT NULL,
    time             TIME NOT NULL,
    appointment_type VARCHAR(100) NULL,
    duration_minutes INT  NOT NULL DEFAULT 30,
    status           ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes            TEXT NULL,
    doctor_comment   TEXT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES users(id),
    CONSTRAINT fk_appt_doctor  FOREIGN KEY (doctor_id)  REFERENCES doctors(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS off_day_requests (
    id           INT  NOT NULL AUTO_INCREMENT,
    doctor_id    INT  NOT NULL,
    date         DATE NOT NULL,
    reason       TEXT NOT NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_remark TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_offday_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
-- SEED DATA
-- All demo passwords are: password
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ─────────────────────────────────────────────────────────────────────────────

DELETE FROM off_day_requests;
DELETE FROM appointments;
DELETE FROM doctors;
DELETE FROM users;

-- USERS (password is 'password' hashed with password_hash bcrypt)
INSERT INTO users (id, name, email, password, role, phone, is_active) VALUES
(1, 'John Patient',  'patient@medicare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', '011-12345678', 1),
(2, 'Dr. Sarah Lee', 'doctor@medicare.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor',  '011-87654321', 1),
(3, 'Admin User',    'admin@medicare.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   '011-11111111', 1);

-- DOCTORS
INSERT INTO doctors (id, user_id, specialization, availability, work_days, work_start, work_end) VALUES
(1, 2, 'Cardiology', 'Mon-Fri, 9am-5pm', '1,2,3,4,5', '09:00:00', '17:00:00');

-- No appointments or off days for clean start

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT          NOT NULL AUTO_INCREMENT,
    email      VARCHAR(100) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;
