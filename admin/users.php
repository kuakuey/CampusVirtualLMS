<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role('admin');

$pageTitle = 'Usuarios';
$q = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid !== (int) current_user()['id']) {
            $stmt = db()->prepare('UPDATE users SET status = IF(status=1,0,1) WHERE id = ?');
            $stmt->execute([$uid]);
            flash('success', 'Estado del usuario actualizado.');
        } else {
            flash('warning', 'No puedes desactivar tu propia cuenta.');
        }
        redirect('admin/users.php');
    }

    if ($action === 'change_role') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $newRole = $_POST['new_role'] ?? '';
        if ($uid !== (int) current_user()['id'] && in_array($newRole, ['admin', 'teacher', 'student'], true)) {
            $stmt = db()->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$newRole, $uid]);
            flash('success', 'Rol actualizado.');
        }
        redirect('admin/users.php');
    }

    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $newRole = $_POST['new_role'] ?? 'student';
        if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 6 && in_array($newRole, ['admin', 'teacher', 'student'], true)) {
            $check = db()->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                flash('danger', 'El correo ya existe.');
            } else {
                $stmt = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $newRole]);
                flash('success', 'Usuario creado.');
            }
        } else {
            flash('danger', 'Datos inválidos para crear usuario.');
        }
        redirect('admin/users.php');
    }

    if ($action === 'delete_user') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid !== (int) current_user()['id']) {
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            flash('success', 'Usuario eliminado.');
        }
        redirect('admin/users.php');
    }
}

$sql = 'SELECT * FROM users WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($role !== '' && in_array($role, ['admin', 'teacher', 'student'], true)) {
    $sql .= ' AND role = ?';
    $params[] = $role;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Usuarios</h1>
        <p class="subtitle"><?= count($users) ?> usuario(s)</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
    </button>
</div>

<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2" method="get">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar por nombre o correo">
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
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="user-avatar" style="width:34px;height:34px;font-size:0.75rem;"><?= e(initials($u['name'])) ?></span>
                                <div>
                                    <strong><?= e($u['name']) ?></strong>
                                    <div class="small text-muted"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ((int) $u['id'] === (int) current_user()['id']): ?>
                                <?= role_badge($u['role']) ?>
                            <?php else: ?>
                                <form method="post" class="d-flex gap-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <select name="new_role" class="form-select form-select-sm" onchange="this.form.submit()">
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
                        <td><?= format_date($u['created_at']) ?></td>
                        <td class="text-end">
                            <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $u['status'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
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
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-0">
                    <label class="form-label">Rol</label>
                    <select name="new_role" class="form-select">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
