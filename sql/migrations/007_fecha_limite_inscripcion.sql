-- Migración 007: Fecha límite de inscripción en catálogo
ALTER TABLE courses ADD COLUMN enrollment_deadline DATE DEFAULT NULL AFTER enrollment_token;
