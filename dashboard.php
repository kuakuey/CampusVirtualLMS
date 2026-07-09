<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$pageTitle = 'Panel principal';

$stats = [];
$announcements = [];
$recentCourses = [];
$pending = [];

if ($user['role'] === 'admin') {
    $stats = [
        ['label' => 'Usuarios', 'value' => count_query('SELECT COUNT(*) FROM users'), 'icon' => 'bi-people', 'class' => 'icon-navy'],
        ['label' => 'Cursos', 'value' => count_query('SELECT COUNT(*) FROM courses'), 'icon' => 'bi-journal-bookmark', 'class' => 'icon-teal'],
        ['label' => 'Matrículas', 'value' => count_query('SELECT COUNT(*) FROM enrollments'), 'icon' => 'bi-person-check', 'class' => 'icon-amber'],
        ['label' => 'Docentes', 'value' => count_query('SELECT COUNT(*) FROM users WHERE role = "teacher"'), 'icon' => 'bi-person-workspace', 'class' => 'icon-rose'],
    ];
    $stmt = db()->query(
        'SELECT c.*, u.name AS teacher_name, cat.name AS category_name,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
         FROM courses c
         JOIN users u ON u.id = c.teacher_id
         LEFT JOIN categories cat ON cat.id = c.category_id
         ORDER BY c.created_at DESC LIMIT 6'
    );
    $recentCourses = $stmt->fetchAll();
} elseif ($user['role'] === 'teacher') {
    $tid = $user['id'];
    $stats = [
        ['label' => 'Mis cursos', 'value' => count_query('SELECT COUNT(*) FROM courses WHERE teacher_id = ?', [$tid]), 'icon' => 'bi-journal-bookmark', 'class' => 'icon-teal'],
        ['label' => 'Estudiantes', 'value' => count_query('SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = ?', [$tid]), 'icon' => 'bi-people', 'class' => 'icon-navy'],
        ['label' => 'Tareas', 'value' => count_query('SELECT COUNT(*) FROM assignments a JOIN courses c ON c.id = a.course_id WHERE c.teacher_id = ?', [$tid]), 'icon' => 'bi-clipboard-check', 'class' => 'icon-amber'],
        ['label' => 'Por calificar', 'value' => count_query('SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN courses c ON c.id = a.course_id LEFT JOIN grades g ON g.submission_id = s.id WHERE c.teacher_id = ? AND g.id IS NULL', [$tid]), 'icon' => 'bi-pencil-square', 'class' => 'icon-rose'],
    ];
    $stmt = db()->prepare(
        'SELECT c.*, cat.name AS category_name,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
         FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
         WHERE c.teacher_id = ?
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([$tid]);
    $recentCourses = $stmt->fetchAll();

    $stmt = db()->prepare(
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
    $pending = $stmt->fetchAll();
} else {
    $sid = $user['id'];
    $stats = [
        ['label' => 'Cursos inscritos', 'value' => count_query('SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = "active"', [$sid]), 'icon' => 'bi-journal-bookmark', 'class' => 'icon-teal'],
        ['label' => 'Tareas pendientes', 'value' => count_query(
            'SELECT COUNT(*) FROM assignments a
             JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = ? AND e.status = "active"
             LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
             WHERE s.id IS NULL',
            [$sid, $sid]
        ), 'icon' => 'bi-clipboard', 'class' => 'icon-amber'],
        ['label' => 'Entregas', 'value' => count_query('SELECT COUNT(*) FROM submissions WHERE student_id = ?', [$sid]), 'icon' => 'bi-upload', 'class' => 'icon-navy'],
        ['label' => 'Calificaciones', 'value' => count_query('SELECT COUNT(*) FROM grades g JOIN submissions s ON s.id = g.submission_id WHERE s.student_id = ?', [$sid]), 'icon' => 'bi-award', 'class' => 'icon-rose'],
    ];
    $stmt = db()->prepare(
        'SELECT c.*, u.name AS teacher_name, cat.name AS category_name
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         JOIN users u ON u.id = c.teacher_id
         LEFT JOIN categories cat ON cat.id = c.category_id
         WHERE e.student_id = ? AND e.status = "active"
         ORDER BY e.enrolled_at DESC'
    );
    $stmt->execute([$sid]);
    $recentCourses = $stmt->fetchAll();

    $stmt = db()->prepare(
        'SELECT a.*, c.title AS course_title, c.id AS course_id, s.id AS submission_id
         FROM assignments a
         JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = ? AND e.status = "active"
         JOIN courses c ON c.id = a.course_id
         LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
         WHERE s.id IS NULL
         ORDER BY a.due_date ASC LIMIT 8'
    );
    $stmt->execute([$sid, $sid]);
    $pending = $stmt->fetchAll();
}

$stmt = db()->prepare(
    'SELECT a.*, u.name AS author_name, c.title AS course_title
     FROM announcements a
     JOIN users u ON u.id = a.author_id
     LEFT JOIN courses c ON c.id = a.course_id
     WHERE a.is_global = 1
        OR (a.course_id IS NOT NULL AND (
            EXISTS (SELECT 1 FROM courses co WHERE co.id = a.course_id AND co.teacher_id = ?)
            OR EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = a.course_id AND e.student_id = ?)
            OR ? = "admin"
        ))
     ORDER BY a.created_at DESC LIMIT 6'
);
$stmt->execute([$user['id'], $user['id'], $user['role']]);
$announcements = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Hola, <?= e(explode(' ', $user['name'])[0]) ?></h1>
        <p class="subtitle">Resumen de tu actividad en <?= e(APP_NAME) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($user['role'] === 'student'): ?>
            <a href="<?= APP_URL ?>/catalog.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Explorar cursos</a>
        <?php elseif (in_array($user['role'], ['admin', 'teacher'], true)): ?>
            <a href="<?= APP_URL ?>/course_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo curso</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon <?= e($stat['class']) ?>"><i class="bi <?= e($stat['icon']) ?>"></i></div>
            <div class="stat-value"><?= (int) $stat['value'] ?></div>
            <div class="stat-label"><?= e($stat['label']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel mb-4">
            <div class="panel-header">
                <h2><?= $user['role'] === 'student' ? 'Mis cursos' : ($user['role'] === 'teacher' ? 'Mis cursos' : 'Cursos recientes') ?></h2>
                <a href="<?= APP_URL ?>/courses.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="panel-body">
                <?php if (!$recentCourses): ?>
                    <div class="empty-state">
                        <i class="bi bi-journal-x"></i>
                        <p class="mb-2">No hay cursos para mostrar.</p>
                        <?php if ($user['role'] === 'student'): ?>
                            <a href="<?= APP_URL ?>/catalog.php" class="btn btn-primary btn-sm">Ir al catálogo</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($recentCourses as $course): ?>
                        <div class="col-md-6">
                            <div class="course-card">
                                <div class="course-banner">
                                    <span class="code"><?= e($course['code']) ?></span>
                                </div>
                                <div class="course-body">
                                    <h3><?= e($course['title']) ?></h3>
                                    <p class="mb-2"><?= e(mb_strimwidth($course['description'] ?? '', 0, 90, '…')) ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <small class="text-muted">
                                            <?= e($course['category_name'] ?? 'Sin categoría') ?>
                                            <?php if (isset($course['students'])): ?> · <?= (int) $course['students'] ?> alumnos<?php endif; ?>
                                            <?php if (!empty($course['teacher_name'])): ?> · <?= e($course['teacher_name']) ?><?php endif; ?>
                                        </small>
                                        <a href="<?= APP_URL ?>/course.php?id=<?= (int) $course['id'] ?>" class="btn btn-sm btn-primary">Abrir</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pending): ?>
        <div class="panel">
            <div class="panel-header">
                <h2><?= $user['role'] === 'teacher' ? 'Entregas por calificar' : 'Tareas pendientes' ?></h2>
            </div>
            <div class="panel-body">
                <?php foreach ($pending as $item): ?>
                    <div class="assignment-item">
                        <div>
                            <strong><?= e($item['assignment_title'] ?? $item['title']) ?></strong>
                            <div class="small text-muted">
                                <?= e($item['course_title']) ?>
                                <?php if (!empty($item['student_name'])): ?> · <?= e($item['student_name']) ?><?php endif; ?>
                                <?php if (!empty($item['due_date'])): ?> · Vence <?= format_date($item['due_date'], true) ?><?php endif; ?>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/course.php?id=<?= (int) $item['course_id'] ?>&tab=assignments" class="btn btn-sm btn-outline-primary">
                            <?= $user['role'] === 'teacher' ? 'Calificar' : 'Entregar' ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header">
                <h2>Anuncios</h2>
                <?php if (in_array($user['role'], ['admin', 'teacher'], true)): ?>
                    <a href="<?= APP_URL ?>/announcements.php" class="btn btn-sm btn-outline-primary">Gestionar</a>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <?php if (!$announcements): ?>
                    <div class="empty-state py-4"><i class="bi bi-megaphone"></i><p class="mb-0">Sin anuncios recientes.</p></div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between gap-2">
                                <strong><?= e($ann['title']) ?></strong>
                                <?php if ($ann['is_global']): ?><span class="badge bg-info">Global</span><?php endif; ?>
                            </div>
                            <p class="small text-muted mb-1"><?= e(mb_strimwidth($ann['body'], 0, 120, '…')) ?></p>
                            <small class="text-muted">
                                <?= e($ann['author_name']) ?>
                                <?php if ($ann['course_title']): ?> · <?= e($ann['course_title']) ?><?php endif; ?>
                                · <?= format_date($ann['created_at']) ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
