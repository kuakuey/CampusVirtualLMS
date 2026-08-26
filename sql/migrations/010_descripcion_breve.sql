-- Migración 010: Descripción breve para listados de cursos
ALTER TABLE courses ADD COLUMN short_description VARCHAR(255) DEFAULT NULL AFTER description;
