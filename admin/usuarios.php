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

    if (!$puedeEditarUsuarios && in_array($accion, ['cambiar_estado', 'cambiar_rol', 'crear_usuario', 'clave_temporal', 'editar_usuario'], true)) {
        mensaje_flash('danger', 'Solo un administrador puede editar usuarios.');
        redirigir('admin/usuarios.php');
    }

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
        if ($idUsuario !== (int) usuario_actual()['id'] && in_array($nuevoRol, roles_sistema(), true)) {
            $consulta = bd()->prepare('UPDATE users SET role = ? WHERE id = ?');
            $consulta->execute([$nuevoRol, $idUsuario]);
            mensaje_flash('success', 'Rol actualizado.');
        }
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
            }
        } else {
            mensaje_flash('danger', 'Datos inválidos para crear usuario.');
        }
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'editar_usuario') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $nuevoRol = $_POST['nuevo_rol'] ?? '';
        $estado = (int) ($_POST['estado'] ?? 1) === 1 ? 1 : 0;
        $esPropio = $idUsuario === (int) usuario_actual()['id'];

        $consulta = bd()->prepare('SELECT id, role, status FROM users WHERE id = ? LIMIT 1');
        $consulta->execute([$idUsuario]);
        $destino = $consulta->fetch();

        if (!$destino || $nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            mensaje_flash('danger', 'Datos inválidos para actualizar el usuario.');
            redirigir('admin/usuarios.php');
        }

        $duplicado = bd()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $duplicado->execute([$correo, $idUsuario]);
        if ($duplicado->fetch()) {
            mensaje_flash('danger', 'El correo ya está en uso por otro usuario.');
            redirigir('admin/usuarios.php');
        }

        if ($esPropio) {
            $nuevoRol = $destino['role'];
            $estado = (int) $destino['status'];
        } elseif (!in_array($nuevoRol, roles_sistema(), true)) {
            mensaje_flash('danger', 'El rol seleccionado no es válido.');
            redirigir('admin/usuarios.php');
        }

        $actualizar = bd()->prepare('UPDATE users SET name = ?, email = ?, bio = ?, role = ?, status = ? WHERE id = ?');
        $actualizar->execute([$nombre, $correo, $bio !== '' ? $bio : null, $nuevoRol, $estado, $idUsuario]);
        sincronizar_sesion_usuario($idUsuario);
        mensaje_flash('success', 'Información del usuario actualizada.');
        redirigir('admin/usuarios.php');
    }

    if ($accion === 'clave_temporal') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        if ($idUsuario === (int) usuario_actual()['id']) {
            mensaje_flash('warning', 'No puedes generar una contraseña temporal para tu propia cuenta.');
            redirigir('admin/usuarios.php');
        }
        $consulta = bd()->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
        $consulta->execute([$idUsuario]);
        $destino = $consulta->fetch();
        $clave = $destino ? asignar_contrasena_temporal($idUsuario) : null;
        if ($clave && $destino) {
            $_SESSION['clave_temporal_mostrada'] = [
                'nombre' => $destino['name'],
                'email' => $destino['email'],
                'clave' => $clave,
            ];
            mensaje_flash('success', 'Contraseña temporal creada. Entrégasela al usuario; al entrar deberá definir una nueva.');
        } else {
            mensaje_flash('danger', 'No se pudo crear la contraseña temporal. Actualiza las tablas en instalación.');
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
$claveTemporal = $_SESSION['clave_temporal_mostrada'] ?? null;
unset($_SESSION['clave_temporal_mostrada']);

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

<?php if ($claveTemporal): ?>
<div class="alert alert-info shadow-sm">
    <div class="fw-semibold mb-1">Contraseña temporal para <?= escapar($claveTemporal['nombre']) ?></div>
    <div class="small mb-2"><?= escapar($claveTemporal['email']) ?></div>
    <div class="input-group" style="max-width: 360px;">
        <input type="text" class="form-control fw-semibold" id="clave-temporal-generada" value="<?= escapar($claveTemporal['clave']) ?>" readonly>
        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('clave-temporal-generada').value)"><i class="bi bi-clipboard"></i> Copiar</button>
    </div>
    <div class="small mt-2 mb-0">Al iniciar sesión con esta clave, el usuario irá a crear su contraseña definitiva y luego entrará al campus.</div>
</div>
<?php endif; ?>

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
                        <td>
                            <?php if ($puedeEditarUsuarios && (int) $u['id'] !== (int) usuario_actual()['id']): ?>
                                <form method="post" class="d-flex gap-1">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="cambiar_rol">
                                    <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                    <select name="nuevo_rol" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="gestor" <?= $u['role'] === 'gestor' ? 'selected' : '' ?>>Gestor</option>
                                        <option value="teacher" <?= $u['role'] === 'teacher' ? 'selected' : '' ?>>Docente</option>
                                        <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Estudiante</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <?= insignia_rol($u['role']) ?>
                            <?php endif; ?>
                        </td>
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
                            <?php $esPropio = (int) $u['id'] === (int) usuario_actual()['id']; ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                title="Editar información"
                                data-bs-toggle="modal"
                                data-bs-target="#editUserModal"
                                data-id="<?= (int) $u['id'] ?>"
                                data-nombre="<?= escapar($u['name']) ?>"
                                data-correo="<?= escapar($u['email']) ?>"
                                data-bio="<?= escapar($u['bio'] ?? '') ?>"
                                data-rol="<?= escapar($u['role']) ?>"
                                data-estado="<?= (int) $u['status'] ?>"
                                data-propio="<?= $esPropio ? '1' : '0' ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if (!$esPropio): ?>
                            <form method="post" class="d-inline">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="clave_temporal">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-primary" type="submit" title="Crear contraseña temporal" onclick="return confirm('¿Generar una contraseña temporal? El usuario deberá crear una nueva al entrar.');">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            <form method="post" class="d-inline">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="cambiar_estado">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $u['status'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <?php if (puede_eliminar()): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar_usuario">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small ms-1">Tú</span>
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
<?php endif; ?>

<?php if ($puedeEditarUsuarios): ?>
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="editar_usuario">
            <input type="hidden" name="id_usuario" id="editar-id-usuario" value="">
            <div class="modal-header">
                <h5 class="modal-title">Editar usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="editar-nombre">Nombre</label>
                    <input type="text" name="nombre" id="editar-nombre" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editar-correo">Correo</label>
                    <input type="email" name="correo" id="editar-correo" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editar-bio">Biografía</label>
                    <textarea name="bio" id="editar-bio" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editar-rol">Rol</label>
                    <select name="nuevo_rol" id="editar-rol" class="form-select">
                        <option value="student">Estudiante</option>
                        <option value="teacher">Docente</option>
                        <option value="gestor">Gestor</option>
                        <option value="admin">Administrador</option>
                    </select>
                    <div class="form-text" id="editar-rol-ayuda" hidden>No puedes cambiar tu propio rol.</div>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="editar-estado">Estado</label>
                    <select name="estado" id="editar-estado" class="form-select">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    <div class="form-text" id="editar-estado-ayuda" hidden>No puedes desactivar tu propia cuenta.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeEditarUsuarios): ?>
<script>
(function () {
    const check = document.getElementById('usar-clave-temporal');
    const clave = document.getElementById('clave-nuevo-usuario');
    if (check && clave) {
        function sync() {
            clave.required = !check.checked;
            clave.disabled = check.checked;
            if (check.checked) clave.value = '';
        }
        check.addEventListener('change', sync);
        sync();
    }

    const modal = document.getElementById('editUserModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (evento) {
        const boton = evento.relatedTarget;
        if (!boton) return;
        const propio = boton.getAttribute('data-propio') === '1';
        document.getElementById('editar-id-usuario').value = boton.getAttribute('data-id') || '';
        document.getElementById('editar-nombre').value = boton.getAttribute('data-nombre') || '';
        document.getElementById('editar-correo').value = boton.getAttribute('data-correo') || '';
        document.getElementById('editar-bio').value = boton.getAttribute('data-bio') || '';
        document.getElementById('editar-rol').value = boton.getAttribute('data-rol') || 'student';
        document.getElementById('editar-estado').value = boton.getAttribute('data-estado') || '1';
        document.getElementById('editar-rol').disabled = propio;
        document.getElementById('editar-estado').disabled = propio;
        document.getElementById('editar-rol-ayuda').hidden = !propio;
        document.getElementById('editar-estado-ayuda').hidden = !propio;
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
