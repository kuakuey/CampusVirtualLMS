-- Migración 002: Usuario administrador inicial (contraseña: password123)
INSERT IGNORE INTO users (id, name, email, password, role, bio) VALUES
(1, 'Administrador', 'admin@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'admin', 'Administrador del sistema');
