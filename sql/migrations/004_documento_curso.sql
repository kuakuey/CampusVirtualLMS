-- Migración 004: Documento opcional en cursos
ALTER TABLE courses ADD COLUMN document_path VARCHAR(255) DEFAULT NULL AFTER image;
