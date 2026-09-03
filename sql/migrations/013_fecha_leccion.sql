-- Migración 013: Fecha de la sesión/lección
ALTER TABLE lessons ADD COLUMN lesson_date DATE DEFAULT NULL AFTER title;
