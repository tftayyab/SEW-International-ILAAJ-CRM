ALTER TABLE patients
  ADD COLUMN response_sent TINYINT(1) NOT NULL DEFAULT 1 AFTER is_archived;

UPDATE patients SET response_sent = 1;

CREATE INDEX idx_patients_response_sent ON patients (response_sent);
