-- Migración 006: Métodos de inscripción en cursos
ALTER TABLE courses ADD COLUMN enrollment_type ENUM('public','password','url') NOT NULL DEFAULT 'public' AFTER status;
ALTER TABLE courses ADD COLUMN enrollment_password VARCHAR(255) DEFAULT NULL AFTER enrollment_type;
ALTER TABLE courses ADD COLUMN enrollment_token VARCHAR(64) DEFAULT NULL AFTER enrollment_password;
ALTER TABLE courses ADD UNIQUE KEY unique_enrollment_token (enrollment_token);
