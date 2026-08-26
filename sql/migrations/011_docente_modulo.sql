-- Migración 011: Docente asignado a cada módulo
ALTER TABLE subcourses ADD COLUMN teacher_id INT DEFAULT NULL AFTER title;
ALTER TABLE subcourses ADD CONSTRAINT fk_subcourses_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL;
