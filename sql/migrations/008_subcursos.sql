-- Migración 008: Subcursos dentro de cada curso
CREATE TABLE IF NOT EXISTS subcourses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_subcourses_course (course_id, sort_order)
) ENGINE=InnoDB;

ALTER TABLE lessons ADD COLUMN subcourse_id INT DEFAULT NULL AFTER course_id;
ALTER TABLE lessons ADD CONSTRAINT fk_lessons_subcourse FOREIGN KEY (subcourse_id) REFERENCES subcourses(id) ON DELETE CASCADE;

INSERT INTO subcourses (course_id, title, sort_order)
SELECT l.course_id, 'Contenido', 1
FROM lessons l
LEFT JOIN subcourses s ON s.course_id = l.course_id
WHERE s.id IS NULL
GROUP BY l.course_id;

UPDATE lessons l
INNER JOIN subcourses s ON s.course_id = l.course_id AND s.sort_order = 1
SET l.subcourse_id = s.id
WHERE l.subcourse_id IS NULL;
