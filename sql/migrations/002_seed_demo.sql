-- Migración 002: Datos de demostración (contraseña: password123)
INSERT IGNORE INTO users (id, name, email, password, role, bio) VALUES
(1, 'Administrador', 'admin@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'admin', 'Administrador del sistema'),
(2, 'María Docente', 'docente@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'teacher', 'Profesora de matemáticas y ciencias'),
(3, 'Carlos Estudiante', 'estudiante@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'student', 'Estudiante de primer año'),
(4, 'Ana Profesora', 'ana@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'teacher', 'Docente de lenguaje'),
(5, 'Luis Alumno', 'luis@aulavirtual.com', '$2y$10$vRI6s4RNxy37V.BXiPWasOdBP44.JLhf5l/WBMDKc97WdMT.wIMae', 'student', 'Estudiante de segundo año');

INSERT IGNORE INTO categories (id, name, description) VALUES
(1, 'Matemáticas', 'Cursos de álgebra, cálculo y estadística'),
(2, 'Ciencias', 'Física, química y biología'),
(3, 'Lenguaje', 'Comunicación, literatura y redacción'),
(4, 'Tecnología', 'Programación, redes y ofimática');

INSERT IGNORE INTO courses (id, category_id, teacher_id, title, code, description, status) VALUES
(1, 1, 2, 'Álgebra Básica', 'MAT-101', 'Fundamentos de álgebra para principiantes. Ecuaciones, desigualdades y funciones.', 'published'),
(2, 2, 2, 'Física Introductoria', 'FIS-101', 'Conceptos básicos de mecánica, energía y movimiento.', 'published'),
(3, 3, 4, 'Comunicación Escrita', 'LEN-201', 'Técnicas de redacción, argumentación y análisis de textos.', 'published'),
(4, 4, 4, 'Introducción a la Programación', 'TEC-101', 'Aprende lógica de programación con ejemplos prácticos.', 'published');

INSERT IGNORE INTO enrollments (course_id, student_id) VALUES
(1, 3), (2, 3), (1, 5), (3, 5), (4, 3);

INSERT IGNORE INTO lessons (course_id, title, content, sort_order) VALUES
(1, 'Números y operaciones', '<p>Repaso de operaciones básicas con números enteros, fracciones y decimales.</p><ul><li>Suma y resta</li><li>Multiplicación y división</li><li>Orden de operaciones</li></ul>', 1),
(1, 'Ecuaciones lineales', '<p>Resolución de ecuaciones de primer grado con una incógnita.</p><p>Ejemplo: 2x + 5 = 15 → x = 5</p>', 2),
(1, 'Sistemas de ecuaciones', '<p>Métodos de sustitución y eliminación para sistemas 2x2.</p>', 3),
(2, 'Magnitudes y unidades', '<p>Sistema Internacional de Unidades y conversiones.</p>', 1),
(2, 'Movimiento rectilíneo', '<p>Velocidad, aceleración y gráficas de movimiento.</p>', 2),
(3, 'Estructura del párrafo', '<p>Cómo construir párrafos coherentes con idea principal y apoyo.</p>', 1),
(4, 'Qué es programar', '<p>Algoritmos, pseudocódigo y pensamiento computacional.</p>', 1),
(4, 'Variables y tipos', '<p>Enteros, cadenas, booleanos y operadores.</p>', 2);

INSERT IGNORE INTO assignments (course_id, title, description, due_date, max_score) VALUES
(1, 'Tarea 1: Ecuaciones', 'Resuelve los 10 ejercicios del PDF adjunto y sube tu solución.', DATE_ADD(NOW(), INTERVAL 14 DAY), 100),
(1, 'Tarea 2: Sistemas', 'Resuelve 5 sistemas de ecuaciones usando ambos métodos.', DATE_ADD(NOW(), INTERVAL 21 DAY), 100),
(2, 'Problemas de movimiento', 'Calcula velocidad y aceleración en los casos propuestos.', DATE_ADD(NOW(), INTERVAL 10 DAY), 50),
(4, 'Primer algoritmo', 'Escribe un algoritmo que calcule el promedio de 5 notas.', DATE_ADD(NOW(), INTERVAL 7 DAY), 100);

INSERT IGNORE INTO forum_topics (course_id, author_id, title, body) VALUES
(1, 3, 'Duda sobre fracciones', '¿Cómo se simplifica 24/36 de forma rápida?'),
(4, 5, 'Recomendaciones de editores', '¿Qué editor recomiendan para empezar a programar?');

INSERT IGNORE INTO forum_replies (topic_id, author_id, body) VALUES
(1, 2, 'Divide numerador y denominador entre su máximo común divisor. En este caso 12: 24÷12=2 y 36÷12=3, queda 2/3.'),
(2, 4, 'Visual Studio Code o Notepad++ son excelentes opciones para principiantes.');
