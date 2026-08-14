-- Patient Advisor & Writer Management System
-- MySQL schema

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS ilaaj_crm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ilaaj_crm;

DROP TABLE IF EXISTS excel_import_errors;
DROP TABLE IF EXISTS excel_imports;
DROP TABLE IF EXISTS meeting_patients;
DROP TABLE IF EXISTS meeting_workers;
DROP TABLE IF EXISTS meetings;
DROP TABLE IF EXISTS workers;
DROP TABLE IF EXISTS patient_images;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS system_state;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Patients
CREATE TABLE patients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  mother_name VARCHAR(255) NULL,
  number VARCHAR(50) NOT NULL,
  country VARCHAR(120) NULL,
  city VARCHAR(120) NULL,
  occupation VARCHAR(150) NULL,
  notes TEXT NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_patients_number (number),
  INDEX idx_patients_name (name),
  INDEX idx_patients_mother (mother_name),
  INDEX idx_patients_country (country),
  INDEX idx_patients_city (city),
  INDEX idx_patients_occupation (occupation),
  INDEX idx_patients_archived (is_archived),
  INDEX idx_patients_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Normalized conversation messages
CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  sender_type ENUM('patient', 'ameer_sahab') NOT NULL,
  message_text TEXT NOT NULL,
  message_date DATE NULL,
  import_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_messages_patient
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX idx_messages_patient (patient_id),
  INDEX idx_messages_date (message_date),
  INDEX idx_messages_patient_date_order (patient_id, message_date, import_order, id),
  INDEX idx_messages_sender (sender_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Google Drive image URLs
CREATE TABLE patient_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  image_url VARCHAR(1000) NOT NULL,
  drive_file_id VARCHAR(128) NULL,
  description VARCHAR(500) NULL,
  is_profile_picture TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_images_patient
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  INDEX idx_images_patient (patient_id),
  INDEX idx_images_profile (patient_id, is_profile_picture),
  INDEX idx_images_drive_file (drive_file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workers
CREATE TABLE workers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_workers_name (name),
  INDEX idx_workers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meetings
CREATE TABLE meetings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  meeting_date DATE NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  location VARCHAR(255) NULL,
  description TEXT NULL,
  notes TEXT NULL,
  meeting_link VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_meetings_date (meeting_date),
  INDEX idx_meetings_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meeting workers (attendance)
CREATE TABLE meeting_workers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id INT UNSIGNED NOT NULL,
  worker_id INT UNSIGNED NOT NULL,
  attended TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mw_meeting
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
  CONSTRAINT fk_mw_worker
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_meeting_worker (meeting_id, worker_id),
  INDEX idx_mw_meeting (meeting_id),
  INDEX idx_mw_worker (worker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meeting patients (attendance)
CREATE TABLE meeting_patients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id INT UNSIGNED NOT NULL,
  patient_id INT UNSIGNED NOT NULL,
  attended TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mp_meeting
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
  CONSTRAINT fk_mp_patient
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  UNIQUE KEY uq_meeting_patient (meeting_id, patient_id),
  INDEX idx_mp_meeting (meeting_id),
  INDEX idx_mp_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editor → Ameer Sahab live selection
CREATE TABLE system_state (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  active_patient_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  present_nonce INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_state_patient
    FOREIGN KEY (active_patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_state (id, active_patient_id) VALUES (1, NULL);

-- Login accounts (no public registration — insert users yourself with a normal password)
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(120) NOT NULL,
  password VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Excel import history
CREATE TABLE excel_imports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  status ENUM('preview', 'completed', 'cancelled', 'failed') NOT NULL DEFAULT 'preview',
  total_rows INT UNSIGNED NOT NULL DEFAULT 0,
  imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
  new_patients INT UNSIGNED NOT NULL DEFAULT 0,
  updated_patients INT UNSIGNED NOT NULL DEFAULT 0,
  messages_created INT UNSIGNED NOT NULL DEFAULT 0,
  errors_count INT UNSIGNED NOT NULL DEFAULT 0,
  warnings_count INT UNSIGNED NOT NULL DEFAULT 0,
  preview_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  INDEX idx_imports_status (status),
  INDEX idx_imports_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE excel_import_errors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_id INT UNSIGNED NOT NULL,
  row_number INT UNSIGNED NULL,
  severity ENUM('error', 'warning') NOT NULL DEFAULT 'error',
  message VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_import_errors
    FOREIGN KEY (import_id) REFERENCES excel_imports(id) ON DELETE CASCADE,
  INDEX idx_import_errors_import (import_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional demo data
INSERT INTO workers (name, phone) VALUES
  ('Ahmed Khan', '03001234567'),
  ('Ali Raza', '03007654321'),
  ('Hassan Ali', '03111222333'),
  ('Usman Sheikh', '03223334455');

INSERT INTO patients (name, mother_name, number, country, city, occupation, notes) VALUES
  ('Muhammad Ali', 'Fatima Bibi', '03001112233', 'Pakistan', 'Karachi', 'Teacher', 'Follow up needed after first consultation.'),
  ('Ahmed Ali', 'Ayesha', '03001112233', 'Pakistan', 'Lahore', 'Engineer', 'Same number as Muhammad Ali — keep separate.'),
  ('Sara Khan', 'Nadia', '03219876543', 'Pakistan', 'Islamabad', 'Doctor', NULL);

INSERT INTO messages (patient_id, sender_type, message_text, message_date, import_order) VALUES
  (1, 'patient', 'I have been experiencing anxiety for several weeks.', '2026-08-01', 1),
  (1, 'ameer_sahab', 'Please share when it started and whether sleep is affected.', '2026-08-01', 2),
  (1, 'patient', 'It started after a family issue. Sleep is irregular.', '2026-08-03', 3),
  (1, 'ameer_sahab', 'Continue daily routine and share progress next week.', '2026-08-04', 4),
  (2, 'patient', 'I need guidance regarding workplace stress.', '2026-08-05', 1),
  (2, 'ameer_sahab', 'Describe your daily schedule and main stressors.', '2026-08-05', 2),
  (3, 'patient', 'Requesting advice for a relative.', '2026-08-08', 1);

-- Add Google Drive image URLs via the Editor UI (Images section).
