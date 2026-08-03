-- Migración 003: Grupos de cursos
CREATE TABLE IF NOT EXISTS course_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE courses ADD COLUMN group_id INT DEFAULT NULL AFTER category_id;
ALTER TABLE courses ADD CONSTRAINT fk_courses_group FOREIGN KEY (group_id) REFERENCES course_groups(id) ON DELETE SET NULL;
