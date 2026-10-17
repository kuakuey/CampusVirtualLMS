-- Migración 017: última actividad, para saber quién está en línea
CREATE TABLE IF NOT EXISTS user_presence (
    user_id INT NOT NULL,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pagina VARCHAR(80) NOT NULL DEFAULT '',
    PRIMARY KEY (user_id),
    INDEX idx_presence_seen (last_seen_at),
    CONSTRAINT fk_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
