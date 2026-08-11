-- Attendance flags for meeting expected attendees (run once)
USE ilaaj_crm;

-- Patients: add attended if missing
ALTER TABLE meeting_patients
  ADD COLUMN attended TINYINT(1) NOT NULL DEFAULT 0 AFTER patient_id;

-- Workers: ensure default is absent until marked present
ALTER TABLE meeting_workers
  MODIFY COLUMN attended TINYINT(1) NOT NULL DEFAULT 0;
