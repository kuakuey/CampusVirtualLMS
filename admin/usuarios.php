<?php
require_once __DIR__ . '/../includes/funciones.php';
requiere_sesion();
requiere_rol(['admin', 'gestor']);

$tituloPagina = 'Usuarios';
$buscar = trim($_GET['buscar'] ?? '');
$role = $_GET['role'] ?? '';

$puedeEditarUsuarios = puede_editar_usuarios();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if (!$puedeEditarUsuarios && in_array($accion, ['crear_usuario'], true)) {
        mensaje_flash('danger', 'Solo un administrador puede crear usuarios.');
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'crear_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $usarTemporal = !empty($_POST['clave_temporal']);
        $clave = $usarTemporal ? generar_contrasena_temporal() : ($_POST['clave'] ?? '');
        $nuevoRol = $_POST['nuevo_rol'] ?? 'student';
        if ($nombre && filter_var($correo, FILTER_VALIDATE_EMAIL) && strlen($clave) >= 6 && in_array($nuevoRol, roles_sistema(), true)) {
            $verificar = bd()->prepare('SELECT id FROM users WHERE email = ?');
            $verificar->execute([$correo]);
            if ($verificar->fetch()) {
                mensaje_flash('danger', 'El correo ya existe.');
            } else {
                try {
                    $consulta = bd()->prepare('INSERT INTO users (name, email, password, role, must_change_password) VALUES (?,?,?,?,?)');
                    $consulta->execute([$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $nuevoRol, $usarTemporal ? 1 : 0]);
                } catch (PDOException $e) {
                    $consulta = bd()->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
                    $consulta->execute([$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $nuevoRol]);
                }
                $idNuevo = (int) bd()->lastInsertId();
                if ($usarTemporal) {
                    $_SESSION['clave_temporal_mostrada'] = [
                        'nombre' => $nombre,
                        'email' => $correo,
                        'clave' => $clave,
                    ];
                    mensaje_flash('success', 'Usuario creado con contraseña temporal. Entrégasela para que defina una nueva al entrar.');
                } else {
                    mensaje_flash('success', 'Usuario creado.');
                }
                if ($idNuevo > 0) {
                    redirigir('admin/usuario.php?id=' . $idNuevo);
                }
            }
        } else {
            mensaje_flash('danger', 'Datos inválidos para crear usuario.');
        }
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'eliminar_usuario') {
        if (!puede_eliminar()) {
            mensaje_flash('danger', 'No tienes permiso para eliminar usuarios.');
            redirigir('admin/usuarios.php');
        }
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        if ($idUsuario !== (int) usuario_actual()['id']) {
            $consultaAvatar = bd()->prepare('SELECT avatar FROM users WHERE id = ?');
            $consultaAvatar->execute([$idUsuario]);
            if ($filaAvatar = $consultaAvatar->fetch()) {
                eliminar_archivo_subida($filaAvatar['avatar'] ?? null);
            }
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
if ($role !== '' && in_array($role, roles_sistema(), true)) {
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
        <p class="subtitle"><?= count($usuarios) ?> usuario(s)<?= $puedeEditarUsuarios ? '' : ' · Solo lectura' ?></p>
    </div>
    <?php if ($puedeEditarUsuarios): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
    </button>
    <?php endif; ?>
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
                    <option value="gestor" <?= $role === 'gestor' ? 'selected' : '' ?>>Gestor</option>
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
                    <?php $esPropio = (int) $u['id'] === (int) usuario_actual()['id']; ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?= renderizar_avatar_usuario($u, 34) ?>
                                <div>
                                    <strong><?= escapar($u['name']) ?></strong>
                                    <div class="small text-muted"><?= escapar($u['email']) ?></div>
                                    <?php if (!empty($u['must_change_password'])): ?>
                                        <span class="badge bg-warning text-dark mt-1">Clave temporal</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= insignia_rol($u['role']) ?></td>
                        <td>
                            <span class="badge <?= $u['status'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $u['status'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td><?= formatear_fecha($u['created_at']) ?></td>
                        <td class="text-end">
                            <?php if (!$puedeEditarUsuarios): ?>
                                <span class="text-muted small">—</span>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= URL_USUARIO ?>?id=<?= (int) $u['id'] ?>" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (!$esPropio && puede_eliminar()): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?');">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="eliminar_usuario">
                                    <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($puedeEditarUsuarios): ?>
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
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
                    <input type="password" name="clave" class="form-control" id="clave-nuevo-usuario" minlength="6">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="clave_temporal" value="1" id="usar-clave-temporal" checked>
                        <label class="form-check-label" for="usar-clave-temporal">Crear contraseña temporal (el usuario la cambiará al entrar)</label>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Rol</label>
                    <select name="nuevo_rol" class="form-select">
                        <option value="student">Estudiante</option>
                        <option value="teacher">Docente</option>
                        <option value="gestor">Gestor</option>
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
<script>
(function () {
    const check = document.getElementById('usar-clave-temporal');
    const clave = document.getElementById('clave-nuevo-usuario');
    if (!check || !clave) return;
    function sync() {
        clave.required = !check.checked;
        clave.disabled = check.checked;
        if (check.checked) clave.value = '';
    }
    check.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
