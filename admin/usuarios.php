<?php
require_once __DIR__ . '/../includes/funciones.php';
requiere_sesion();
requiere_rol('admin');

$tituloPagina = 'Usuarios';
$buscar = trim($_GET['buscar'] ?? '');
$role = $_GET['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_estado') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        if ($idUsuario !== (int) usuario_actual()['id']) {
            $consulta = bd()->prepare('UPDATE users SET status = IF(status=1,0,1) WHERE id = ?');
            $consulta->execute([$idUsuario]);
            mensaje_flash('success', 'Estado del usuario actualizado.');
        } else {
            mensaje_flash('warning', 'No puedes desactivar tu propia cuenta.');
        }
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'cambiar_rol') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $nuevoRol = $_POST['nuevo_rol'] ?? '';
        if ($idUsuario !== (int) usuario_actual()['id'] && in_array($nuevoRol, ['admin', 'teacher', 'student'], true)) {
            $consulta = bd()->prepare('UPDATE users SET role = ? WHERE id = ?');
            $consulta->execute([$nuevoRol, $idUsuario]);
            mensaje_flash('success', 'Rol actualizado.');
        }
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'crear_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $clave = $_POST['clave'] ?? '';
        $nuevoRol = $_POST['nuevo_rol'] ?? 'student';
        if ($nombre && filter_var($correo, FILTER_VALIDATE_EMAIL) && strlen($clave) >= 6 && in_array($nuevoRol, ['admin', 'teacher', 'student'], true)) {
            $verificar = bd()->prepare('SELECT id FROM users WHERE email = ?');
            $verificar->execute([$correo]);
            if ($verificar->fetch()) {
                mensaje_flash('danger', 'El correo ya existe.');
            } else {
                $consulta = bd()->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
                $consulta->execute([$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $nuevoRol]);
                mensaje_flash('success', 'Usuario creado.');
            }
        } else {
            mensaje_flash('danger', 'Datos inválidos para crear usuario.');
        }
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'eliminar_usuario') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        if ($idUsuario !== (int) usuario_actual()['id']) {
            $consulta = bd()->prepare('DELETE FROM users WHERE id = ?');
            $consulta->execute([$idUsuario]);
            mensaje_flash('success', 'Usuario eliminado.');
        }
        redirigir('admin/usuarios.php');
    }
}

$sql = 'SELECT * FROM users WHERE 1=1';
$parametros = [];
if ($buscar !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ?)';
    $like = '%' . $buscar . '%';
    $parametros[] = $like;
    $parametros[] = $like;
}
if ($role !== '' && in_array($role, ['admin', 'teacher', 'student'], true)) {
    $sql .= ' AND role = ?';
    $parametros[] = $role;
}
$sql .= ' ORDER BY created_at DESC';
$consulta = bd()->prepare($sql);
$consulta->execute($parametros);
$usuarios = $consulta->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Usuarios</h1>
        <p class="subtitle"><?= count($usuarios) ?> usuario(s)</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
    </button>
</div>

<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2" method="get">
            <div class="col-md-6">
                <input type="text" name="buscar" class="form-control" value="<?= escapar($buscar) ?>" placeholder="Buscar por nombre o correo">
            </div>
            <div class="col-md-4">
                <select name="role" class="form-select">
                    <option value="">Todos los roles</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    <option value="teacher" <?= $role === 'teacher' ? 'selected' : '' ?>>Docente</option>
                    <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Estudiante</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="user-avatar" style="width:34px;height:34px;font-size:0.75rem;"><?= escapar(iniciales($u['name'])) ?></span>
                                <div>
                                    <strong><?= escapar($u['name']) ?></strong>
                                    <div class="small text-muted"><?= escapar($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ((int) $u['id'] === (int) usuario_actual()['id']): ?>
                                <?= insignia_rol($u['role']) ?>
                            <?php else: ?>
                                <form method="post" class="d-flex gap-1">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="cambiar_rol">
                                    <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                    <select name="nuevo_rol" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="teacher" <?= $u['role'] === 'teacher' ? 'selected' : '' ?>>Docente</option>
                                        <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Estudiante</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $u['status'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $u['status'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td><?= formatear_fecha($u['created_at']) ?></td>
                        <td class="text-end">
                            <?php if ((int) $u['id'] !== (int) usuario_actual()['id']): ?>
                            <form method="post" class="d-inline">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="cambiar_estado">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $u['status'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar_usuario">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted small">Tú</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="crear_usuario">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo</label>
                    <input type="email" name="correo" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="clave" class="form-control" required minlength="6">
                </div>
                <div class="mb-0">
                    <label class="form-label">Rol</label>
                    <select name="nuevo_rol" class="form-select">
                        <option value="student">Estudiante</option>
                        <option value="teacher">Docente</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie.php'; ?>
