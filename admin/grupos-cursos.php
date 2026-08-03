<?php
require_once __DIR__ . '/../includes/funciones.php';
requiere_sesion();
requiere_rol('admin');

$tituloPagina = 'Grupos de cursos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $orden = (int) ($_POST['orden'] ?? 0);
        if ($nombre !== '') {
            $consulta = bd()->prepare('INSERT INTO course_groups (name, description, sort_order) VALUES (?, ?, ?)');
            $consulta->execute([$nombre, $descripcion ?: null, $orden]);
            mensaje_flash('success', 'Grupo creado.');
        }
        redirigir('admin/grupos-cursos.php');
    }

    if ($accion === 'actualizar') {
        $id = (int) ($_POST['id_grupo'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $orden = (int) ($_POST['orden'] ?? 0);
        if ($id && $nombre !== '') {
            $consulta = bd()->prepare('UPDATE course_groups SET name=?, description=?, sort_order=? WHERE id=?');
            $consulta->execute([$nombre, $descripcion ?: null, $orden, $id]);
            mensaje_flash('success', 'Grupo actualizado.');
        }
        redirigir('admin/grupos-cursos.php');
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id_grupo'] ?? 0);
        if ($id) {
            $consulta = bd()->prepare('DELETE FROM course_groups WHERE id = ?');
            $consulta->execute([$id]);
            mensaje_flash('success', 'Grupo eliminado.');
        }
        redirigir('admin/grupos-cursos.php');
    }
}

$grupos = bd()->query(
    'SELECT g.*, (SELECT COUNT(*) FROM courses c WHERE c.group_id = g.id) AS courses_count
     FROM course_groups g ORDER BY g.sort_order, g.name'
)->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Grupos de cursos</h1>
        <p class="subtitle">Agrupa cursos en programas o rutas de aprendizaje</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header"><h2>Nuevo grupo</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="crear">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Ej. Escuela de Liderazgo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" value="0" min="0">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Guardar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header"><h2>Listado</h2><span class="badge bg-secondary"><?= count($grupos) ?></span></div>
            <div class="panel-body p-0">
                <?php if (!$grupos): ?>
                    <div class="empty-state py-5"><i class="bi bi-collection"></i><p class="mb-0">Aún no hay grupos. Crea el primero.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Cursos</th>
                                <th>Orden</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $grupo): ?>
                            <tr>
                                <td>
                                    <strong><?= escapar($grupo['name']) ?></strong>
                                    <?php if ($grupo['description']): ?>
                                        <div class="small text-muted"><?= escapar($grupo['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $grupo['courses_count'] ?></td>
                                <td><?= (int) $grupo['sort_order'] ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editar<?= (int) $grupo['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este grupo? Los cursos quedarán sin grupo.');">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_grupo" value="<?= (int) $grupo['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editar<?= (int) $grupo['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="actualizar">
                                        <input type="hidden" name="id_grupo" value="<?= (int) $grupo['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Editar grupo</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Nombre</label>
                                                <input type="text" name="nombre" class="form-control" value="<?= escapar($grupo['name']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Descripción</label>
                                                <textarea name="descripcion" class="form-control" rows="3"><?= escapar($grupo['description'] ?? '') ?></textarea>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Orden</label>
                                                <input type="number" name="orden" class="form-control" value="<?= (int) $grupo['sort_order'] ?>" min="0">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie.php'; ?>
