<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role('student');

$user = current_user();
$pageTitle = 'Catálogo de cursos';
$q = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$sql = 'SELECT c.*, u.name AS teacher_name, cat.name AS category_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students,
               EXISTS(SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status = "active") AS enrolled
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.status = "published"';
$params = [$user['id']];

if ($q !== '') {
    $sql .= ' AND (c.title LIKE ? OR c.description LIKE ? OR c.code LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($categoryId > 0) {
    $sql .= ' AND c.category_id = ?';
    $params[] = $categoryId;
}
$sql .= ' ORDER BY c.title';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course_id'])) {
    verify_csrf();
    $courseId = (int) $_POST['enroll_course_id'];
    $course = get_course($courseId);
    if ($course && $course['status'] === 'published') {
        if (is_enrolled($courseId)) {
            flash('info', 'Ya estás inscrito en este curso.');
        } else {
            $ins = db()->prepare('INSERT INTO enrollments (course_id, student_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = "active"');
            $ins->execute([$courseId, $user['id']]);
            flash('success', 'Te has inscrito en «' . $course['title'] . '».');
        }
    } else {
        flash('danger', 'Curso no disponible.');
    }
    redirect('catalog.php');
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Catálogo</h1>
        <p class="subtitle">Explora e inscríbete en cursos publicados</p>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2" method="get">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar cursos...">
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select">
                    <option value="0">Todas las categorías</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php if (!$courses): ?>
        <div class="col-12"><div class="panel"><div class="panel-body empty-state"><i class="bi bi-search"></i><p class="mb-0">No hay cursos que coincidan.</p></div></div></div>
    <?php endif; ?>
    <?php foreach ($courses as $course): ?>
    <div class="col-md-6 col-xl-4">
        <div class="course-card">
            <div class="course-banner"><span class="code"><?= e($course['code']) ?></span></div>
            <div class="course-body">
                <h3><?= e($course['title']) ?></h3>
                <p><?= e(mb_strimwidth($course['description'] ?? '', 0, 110, '…')) ?></p>
                <div class="small text-muted mb-3">
                    <?= e($course['teacher_name']) ?> · <?= e($course['category_name'] ?? 'General') ?> · <?= (int) $course['students'] ?> alumnos
                </div>
                <?php if ($course['enrolled']): ?>
                    <a href="<?= APP_URL ?>/course.php?id=<?= (int) $course['id'] ?>" class="btn btn-success w-100"><i class="bi bi-check2 me-1"></i> Ya inscrito · Abrir</a>
                <?php else: ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="enroll_course_id" value="<?= (int) $course['id'] ?>">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Inscribirme</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
