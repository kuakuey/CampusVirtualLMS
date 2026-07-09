-- AulaVirtual LMS - Esquema completo
CREATE DATABASE IF NOT EXISTS aulavirtual CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aulavirtual;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS forum_replies;
DROP TABLE IF EXISTS forum_topics;
DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT DEFAULT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','completed','dropped') NOT NULL DEFAULT 'active',
    UNIQUE KEY unique_enrollment (course_id, student_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content LONGTEXT DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    due_date DATETIME DEFAULT NULL,
    max_score DECIMAL(6,2) NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    content TEXT DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL UNIQUE,
    score DECIMAL(6,2) NOT NULL,
    feedback TEXT DEFAULT NULL,
    graded_by INT DEFAULT NULL,
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE forum_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    author_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Contraseña para todos: password123
INSERT INTO users (name, email, password, role, bio) VALUES
('Administrador', 'admin@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'admin', 'Administrador del sistema'),
('María Docente', 'docente@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'teacher', 'Profesora de matemáticas y ciencias'),
('Carlos Estudiante', 'estudiante@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'student', 'Estudiante de primer año'),
('Ana Profesora', 'ana@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'teacher', 'Docente de lenguaje'),
('Luis Alumno', 'luis@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'student', 'Estudiante de segundo año');

INSERT INTO categories (name, description) VALUES
('Matemáticas', 'Cursos de álgebra, cálculo y estadística'),
('Ciencias', 'Física, química y biología'),
('Lenguaje', 'Comunicación, literatura y redacción'),
('Tecnología', 'Programación, redes y ofimática');

INSERT INTO courses (category_id, teacher_id, title, code, description, status) VALUES
(1, 2, 'Álgebra Básica', 'MAT-101', 'Fundamentos de álgebra para principiantes. Ecuaciones, desigualdades y funciones.', 'published'),
(2, 2, 'Física Introductoria', 'FIS-101', 'Conceptos básicos de mecánica, energía y movimiento.', 'published'),
(3, 4, 'Comunicación Escrita', 'LEN-201', 'Técnicas de redacción, argumentación y análisis de textos.', 'published'),
(4, 4, 'Introducción a la Programación', 'TEC-101', 'Aprende lógica de programación con ejemplos prácticos.', 'published');

INSERT INTO enrollments (course_id, student_id) VALUES
(1, 3), (2, 3), (1, 5), (3, 5), (4, 3);

INSERT INTO lessons (course_id, title, content, sort_order) VALUES
(1, 'Números y operaciones', '<p>Repaso de operaciones básicas con números enteros, fracciones y decimales.</p><ul><li>Suma y resta</li><li>Multiplicación y división</li><li>Orden de operaciones</li></ul>', 1),
(1, 'Ecuaciones lineales', '<p>Resolución de ecuaciones de primer grado con una incógnita.</p><p>Ejemplo: 2x + 5 = 15 → x = 5</p>', 2),
(1, 'Sistemas de ecuaciones', '<p>Métodos de sustitución y eliminación para sistemas 2x2.</p>', 3),
(2, 'Magnitudes y unidades', '<p>Sistema Internacional de Unidades y conversiones.</p>', 1),
(2, 'Movimiento rectilíneo', '<p>Velocidad, aceleración y gráficas de movimiento.</p>', 2),
(3, 'Estructura del párrafo', '<p>Cómo construir párrafos coherentes con idea principal y apoyo.</p>', 1),
(4, 'Qué es programar', '<p>Algoritmos, pseudocódigo y pensamiento computacional.</p>', 1),
(4, 'Variables y tipos', '<p>Enteros, cadenas, booleanos y operadores.</p>', 2);

INSERT INTO assignments (course_id, title, description, due_date, max_score) VALUES
(1, 'Tarea 1: Ecuaciones', 'Resuelve los 10 ejercicios del PDF adjunto y sube tu solución.', DATE_ADD(NOW(), INTERVAL 14 DAY), 100),
(1, 'Tarea 2: Sistemas', 'Resuelve 5 sistemas de ecuaciones usando ambos métodos.', DATE_ADD(NOW(), INTERVAL 21 DAY), 100),
(2, 'Problemas de movimiento', 'Calcula velocidad y aceleración en los casos propuestos.', DATE_ADD(NOW(), INTERVAL 10 DAY), 50),
(4, 'Primer algoritmo', 'Escribe un algoritmo que calcule el promedio de 5 notas.', DATE_ADD(NOW(), INTERVAL 7 DAY), 100);

INSERT INTO forum_topics (course_id, author_id, title, body) VALUES
(1, 3, 'Duda sobre fracciones', '¿Cómo se simplifica 24/36 de forma rápida?'),
(4, 5, 'Recomendaciones de editores', '¿Qué editor recomiendan para empezar a programar?');

INSERT INTO forum_replies (topic_id, author_id, body) VALUES
(1, 2, 'Divide numerador y denominador entre su máximo común divisor. En este caso 12: 24÷12=2 y 36÷12=3, queda 2/3.'),
(2, 4, 'Visual Studio Code o Notepad++ son excelentes opciones para principiantes.');
