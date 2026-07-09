<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$pageTitle = 'Cursos';
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$sql = 'SELECT c.*, u.name AS teacher_name, cat.name AS category_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lessons_count
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE 1=1';
$params = [];

if ($user['role'] === 'teacher') {
    $sql .= ' AND c.teacher_id = ?';
    $params[] = $user['id'];
} elseif ($user['role'] === 'student') {
    $sql .= ' AND EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status = "active")';
    $params[] = $user['id'];
}

if ($q !== '') {
    $sql .= ' AND (c.title LIKE ? OR c.code LIKE ? OR c.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status !== '' && in_array($status, ['draft', 'published', 'archived'], true)) {
    $sql .= ' AND c.status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY c.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= $user['role'] === 'student' ? 'Mis cursos' : 'Cursos' ?></h1>
        <p class="subtitle"><?= count($courses) ?> curso(s) encontrado(s)</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($user['role'] === 'student'): ?>
            <a href="<?= APP_URL ?>/catalog.php" class="btn btn-outline-primary"><i class="bi bi-grid me-1"></i> Catálogo</a>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['admin', 'teacher'], true)): ?>
            <a href="<?= APP_URL ?>/course_form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo curso</a>
        <?php endif; ?>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Título, código o descripción">
            </div>
            <?php if ($user['role'] !== 'student'): ?>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publicado</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Borrador</option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archivado</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$courses): ?>
    <div class="panel"><div class="panel-body empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">No se encontraron cursos.</p></div></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($courses as $course): ?>
    <div class="col-md-6 col-xl-4">
        <div class="course-card">
            <div class="course-banner">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <span class="code"><?= e($course['code']) ?></span>
                    <?= status_badge($course['status']) ?>
                </div>
            </div>
            <div class="course-body">
                <h3><?= e($course['title']) ?></h3>
                <p><?= e(mb_strimwidth($course['description'] ?? '', 0, 100, '…')) ?></p>
                <div class="small text-muted mb-3">
                    <div><i class="bi bi-person me-1"></i><?= e($course['teacher_name']) ?></div>
                    <div><i class="bi bi-tag me-1"></i><?= e($course['category_name'] ?? 'Sin categoría') ?></div>
                    <div><i class="bi bi-people me-1"></i><?= (int) $course['students'] ?> alumnos · <?= (int) $course['lessons_count'] ?> lecciones</div>
                </div>
                <div class="d-flex gap-2 mt-auto">
                    <a href="<?= APP_URL ?>/course.php?id=<?= (int) $course['id'] ?>" class="btn btn-primary flex-fill">Abrir</a>
                    <?php if ($user['role'] === 'admin' || ($user['role'] === 'teacher' && (int) $course['teacher_id'] === (int) $user['id'])): ?>
                        <a href="<?= APP_URL ?>/course_form.php?id=<?= (int) $course['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
