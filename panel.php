<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$usuario = usuario_actual();
$tituloPagina = 'Panel principal';

$stats = [];
$cursosRecientes = [];
$pendientes = [];

if ($usuario['role'] === 'admin' || $usuario['role'] === 'gestor') {
    $stats = [
        ['label' => 'Usuarios', 'value' => contar_consulta('SELECT COUNT(*) FROM users'), 'icon' => 'bi-people', 'class' => 'icon-navy'],
        ['label' => 'Cursos', 'value' => contar_consulta('SELECT COUNT(*) FROM courses'), 'icon' => 'bi-journal-bookmark', 'class' => 'icon-teal'],
        ['label' => 'Matrículas', 'value' => contar_consulta('SELECT COUNT(*) FROM enrollments WHERE status = "active"'), 'icon' => 'bi-person-check', 'class' => 'icon-amber'],
        ['label' => 'Docentes', 'value' => contar_consulta('SELECT COUNT(*) FROM users WHERE role = "teacher"'), 'icon' => 'bi-person-workspace', 'class' => 'icon-rose'],
    ];
    $stmt = bd()->query(
        'SELECT c.*, u.name AS teacher_name, cat.name AS category_name,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = "active") AS students
         FROM courses c
         JOIN users u ON u.id = c.teacher_id
         LEFT JOIN categories cat ON cat.id = c.category_id
         WHERE c.status NOT IN ("draft", "archived")
         ORDER BY c.created_at DESC LIMIT 6'
    );
    $cursosRecientes = $stmt->fetchAll();
} elseif ($usuario['role'] === 'teacher') {
    $tid = $usuario['id'];
    $stats = [
        ['label' => 'Mis cursos', 'value' => contar_consulta('SELECT COUNT(*) FROM courses WHERE teacher_id = ?', [$tid]), 'icon' => 'bi-journal-bookmark', 'class' => 'icon-teal'],
        ['label' => 'Estudiantes', 'value' => contar_consulta('SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = ? AND e.status = "active"', [$tid]), 'icon' => 'bi-people', 'class' => 'icon-navy'],
        ['label' => 'Tareas', 'value' => contar_consulta('SELECT COUNT(*) FROM assignments a JOIN courses c ON c.id = a.course_id WHERE c.teacher_id = ?', [$tid]), 'icon' => 'bi-clipboard-check', 'class' => 'icon-amber'],
        ['label' => 'Por calificar', 'value' => contar_consulta('SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN courses c ON c.id = a.course_id LEFT JOIN grades g ON g.submission_id = s.id WHERE c.teacher_id = ? AND g.id IS NULL', [$tid]), 'icon' => 'bi-pencil-square', 'class' => 'icon-rose'],
    ];
    $stmt = bd()->prepare(
        'SELECT c.*, cat.name AS category_name,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = "active") AS students
         FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
         WHERE c.teacher_id = ? AND c.status NOT IN ("draft", "archived")
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([$tid]);
    $cursosRecientes = $stmt->fetchAll();

    $stmt = bd()->prepare(
        'SELECT s.*, a.title AS assignment_title, u.name AS student_name, c.title AS course_title, c.id AS course_id
         FROM submissions s
         JOIN assignments a ON a.id = s.assignment_id
         JOIN courses c ON c.id = a.course_id
         JOIN users u ON u.id = s.student_id
         LEFT JOIN grades g ON g.submission_id = s.id
         WHERE c.teacher_id = ? AND g.id IS NULL
         ORDER BY s.submitted_at DESC LIMIT 8'
    );
    $stmt->execute([$tid]);
    $pendientes = $stmt->fetchAll();
} else {
    $sid = $usuario['id'];
    $stats = [
        ['label' => 'Cursos inscritos', 'value' => contar_consulta('SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = "active"', [$sid]), 'icon' => 'bi-journal-bookmark', 'class' => 'icon-teal'],
        ['label' => 'Tareas pendientes', 'value' => contar_consulta(
            'SELECT COUNT(*) FROM assignments a
             JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = ? AND e.status = "active"
             LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
             WHERE s.id IS NULL',
            [$sid, $sid]
        ), 'icon' => 'bi-clipboard', 'class' => 'icon-amber'],
        ['label' => 'Entregas', 'value' => contar_consulta('SELECT COUNT(*) FROM submissions WHERE student_id = ?', [$sid]), 'icon' => 'bi-upload', 'class' => 'icon-navy'],
        ['label' => 'Calificaciones', 'value' => contar_consulta('SELECT COUNT(*) FROM grades g JOIN submissions s ON s.id = g.submission_id WHERE s.student_id = ?', [$sid]), 'icon' => 'bi-award', 'class' => 'icon-rose'],
    ];
    $stmt = bd()->prepare(
        'SELECT c.*, u.name AS teacher_name, cat.name AS category_name
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         JOIN users u ON u.id = c.teacher_id
         LEFT JOIN categories cat ON cat.id = c.category_id
         WHERE e.student_id = ? AND e.status = "active" AND c.status NOT IN ("draft", "archived")
         ORDER BY e.enrolled_at DESC'
    );
    $stmt->execute([$sid]);
    $cursosRecientes = $stmt->fetchAll();

    $stmt = bd()->prepare(
        'SELECT a.*, c.title AS course_title, c.id AS course_id, s.id AS submission_id
         FROM assignments a
         JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = ? AND e.status = "active"
         JOIN courses c ON c.id = a.course_id
         LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
         WHERE s.id IS NULL
         ORDER BY a.due_date ASC LIMIT 8'
    );
    $stmt->execute([$sid, $sid]);
    $pendientes = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Hola, <?= escapar(explode(' ', $usuario['name'])[0]) ?></h1>
        <p class="subtitle">Resumen de tu actividad en <?= escapar(NOMBRE_APP) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (in_array($usuario['role'], ['admin', 'gestor', 'teacher'], true)): ?>
            <a href="<?= URL_APP ?>/curso-formulario.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo curso</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon <?= escapar($stat['class']) ?>"><i class="bi <?= escapar($stat['icon']) ?>"></i></div>
            <div class="stat-value"><?= (int) $stat['value'] ?></div>
            <div class="stat-label"><?= escapar($stat['label']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="panel mb-4">
            <div class="panel-header">
                <h2><?= $usuario['role'] === 'student' ? 'Mis cursos' : ($usuario['role'] === 'teacher' ? 'Mis cursos' : 'Cursos recientes') ?></h2>
                <a href="<?= URL_APP ?>/cursos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="panel-body">
                <?php if (!$cursosRecientes): ?>
                    <div class="empty-state">
                        <i class="bi bi-journal-x"></i>
                        <p class="mb-0">No hay cursos para mostrar.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($cursosRecientes as $curso): ?>
                        <div class="col-md-6">
                            <div class="course-card">
                                <div class="course-banner">
                                    <span class="code"><?= escapar($curso['code']) ?></span>
                                </div>
                                <div class="course-body">
                                    <h3><?= escapar($curso['title']) ?></h3>
                                    <?php $textoBreve = descripcion_lista_curso($curso, 90); ?>
                                    <?php if ($textoBreve !== ''): ?>
                                        <p class="mb-2"><?= escapar($textoBreve) ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <small class="text-muted">
                                            <?= escapar($curso['category_name'] ?? 'Sin categoría') ?>
                                            <?php if (isset($curso['students'])): ?> · <?= (int) $curso['students'] ?> alumnos<?php endif; ?>
                                        </small>
                                        <a href="<?= URL_APP ?>/curso.php?id=<?= (int) $curso['id'] ?>" class="btn btn-sm btn-primary">Abrir</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pendientes): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><?= $usuario['role'] === 'teacher' ? 'Entregas por calificar' : 'Tareas pendientes' ?></h2>
            </div>
            <div class="panel-body">
                <?php foreach ($pendientes as $item): ?>
                    <div class="assignment-item">
                        <div>
                            <strong><?= escapar($item['assignment_title'] ?? $item['title']) ?></strong>
                            <div class="small text-muted">
                                <?= escapar($item['course_title']) ?>
                                <?php if (!empty($item['student_name'])): ?> · <?= escapar($item['student_name']) ?><?php endif; ?>
                                <?php if (!empty($item['due_date'])): ?> · Vence <?= formatear_fecha($item['due_date'], true) ?><?php endif; ?>
                            </div>
                        </div>
                        <a href="<?= URL_APP ?>/curso.php?id=<?= (int) $item['course_id'] ?>&pestaña=tareas" class="btn btn-sm btn-outline-primary">
                            <?= $usuario['role'] === 'teacher' ? 'Calificar' : 'Entregar' ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
