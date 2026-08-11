-- Add Drive file id support (run once if DB already imported)
USE ilaaj_crm;

ALTER TABLE patient_images
  ADD COLUMN drive_file_id VARCHAR(128) NULL AFTER image_url,
  ADD INDEX idx_images_drive_file (drive_file_id);
