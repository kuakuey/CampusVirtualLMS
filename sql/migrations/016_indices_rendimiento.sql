-- Índices para las consultas de lección, listado del curso y panel del estudiante.
ALTER TABLE lessons
    ADD INDEX idx_lessons_course_order (course_id, subcourse_id, sort_order, id);

ALTER TABLE lesson_completions
    ADD INDEX idx_completions_student (student_id, lesson_id);

ALTER TABLE enrollments
    ADD INDEX idx_enrollments_student (student_id, status, course_id);
