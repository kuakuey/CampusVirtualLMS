-- Migración 012: Tiempo de video por estudiante (10 minutos / completada)
CREATE TABLE IF NOT EXISTS lesson_watch_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    student_id INT NOT NULL,
    seconds_watched INT NOT NULL DEFAULT 0,
    reached_required_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lesson_watch (lesson_id, student_id),
    INDEX idx_watch_lesson (lesson_id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
