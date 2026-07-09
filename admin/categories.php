<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role('admin');

$pageTitle = 'Categorías';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name !== '') {
            $stmt = db()->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description]);
            flash('success', 'Categoría creada.');
        }
        redirect('admin/categories.php');
    }

    if ($action === 'update') {
        $cid = (int) ($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($cid && $name !== '') {
            $stmt = db()->prepare('UPDATE categories SET name=?, description=? WHERE id=?');
            $stmt->execute([$name, $description, $cid]);
            flash('success', 'Categoría actualizada.');
        }
        redirect('admin/categories.php');
    }

    if ($action === 'delete') {
        $cid = (int) ($_POST['category_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$cid]);
        flash('success', 'Categoría eliminada.');
        redirect('admin/categories.php');
    }
}

$categories = db()->query(
    'SELECT cat.*, (SELECT COUNT(*) FROM courses c WHERE c.category_id = cat.id) AS courses_count
     FROM categories cat ORDER BY cat.name'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Categorías</h1>
        <p class="subtitle">Organiza los cursos por área</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header"><h2>Nueva categoría</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Guardar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header"><h2>Listado</h2></div>
            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Cursos</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <strong><?= e($cat['name']) ?></strong>
                                    <?php if ($cat['description']): ?>
                                        <div class="small text-muted"><?= e($cat['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $cat['courses_count'] ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit<?= (int) $cat['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar categoría?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="edit<?= (int) $cat['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Editar categoría</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Nombre</label>
                                                <input type="text" name="name" class="form-control" value="<?= e($cat['name']) ?>" required>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Descripción</label>
                                                <textarea name="description" class="form-control" rows="3"><?= e($cat['description'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-primary">Guardar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
