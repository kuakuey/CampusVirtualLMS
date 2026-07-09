<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['admin', 'teacher']);

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$course = $id ? get_course($id) : null;
$pageTitle = $course ? 'Editar curso' : 'Nuevo curso';

if ($course && $user['role'] === 'teacher' && (int) $course['teacher_id'] !== (int) $user['id']) {
    flash('danger', 'No puedes editar este curso.');
    redirect('courses.php');
}

$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$teachers = [];
if ($user['role'] === 'admin') {
    $teachers = db()->query('SELECT id, name FROM users WHERE role = "teacher" AND status = 1 ORDER BY name')->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
    $status = $_POST['status'] ?? 'draft';
    $teacherId = $user['role'] === 'admin' ? (int) ($_POST['teacher_id'] ?? 0) : (int) $user['id'];

    if ($title === '') $errors[] = 'El título es obligatorio.';
    if ($code === '') $errors[] = 'El código es obligatorio.';
    if (!in_array($status, ['draft', 'published', 'archived'], true)) $status = 'draft';
    if ($user['role'] === 'admin' && $teacherId <= 0) $errors[] = 'Selecciona un docente.';

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM courses WHERE code = ? AND id != ?');
        $check->execute([$code, $id]);
        if ($check->fetch()) {
            $errors[] = 'Ese código de curso ya existe.';
        }
    }

    if (!$errors) {
        if ($course) {
            $stmt = db()->prepare('UPDATE courses SET category_id=?, teacher_id=?, title=?, code=?, description=?, status=? WHERE id=?');
            $stmt->execute([$categoryId, $teacherId, $title, $code, $description, $status, $id]);
            flash('success', 'Curso actualizado.');
        } else {
            $stmt = db()->prepare('INSERT INTO courses (category_id, teacher_id, title, code, description, status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$categoryId, $teacherId, $title, $code, $description, $status]);
            $id = (int) db()->lastInsertId();
            flash('success', 'Curso creado correctamente.');
        }
        redirect('course.php?id=' . $id);
    }
} else {
    $_POST = $course ?: [
        'title' => '', 'code' => '', 'description' => '', 'category_id' => '', 'status' => 'draft', 'teacher_id' => $user['id']
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p class="subtitle">Completa la información del curso</p>
    </div>
    <a href="<?= APP_URL ?>/courses.php" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="panel" style="max-width: 760px;">
    <div class="panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Título</label>
                    <input type="text" name="title" class="form-control" value="<?= e($_POST['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Código</label>
                    <input type="text" name="code" class="form-control" value="<?= e($_POST['code'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="4"><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Categoría</label>
                    <select name="category_id" class="form-select">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (int) ($_POST['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <?php foreach (['draft' => 'Borrador', 'published' => 'Publicado', 'archived' => 'Archivado'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($_POST['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                <div class="col-12">
                    <label class="form-label">Docente</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (int) ($_POST['teacher_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="<?= APP_URL ?>/courses.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
