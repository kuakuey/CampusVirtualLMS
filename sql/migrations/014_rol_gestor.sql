-- Migración 014: Rol gestor (permisos de administrador sin eliminar)
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','gestor','teacher','student') NOT NULL DEFAULT 'student';
