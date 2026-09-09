<?php
require_once __DIR__ . '/../includes/funciones.php';
requiere_sesion();
requiere_rol(['admin', 'gestor']);

if (!puede_editar_usuarios()) {
    mensaje_flash('danger', 'Solo un administrador puede editar usuarios.');
    redirigir('admin/usuarios.php');
}

$idUsuario = (int) ($_GET['id'] ?? $_POST['id_usuario'] ?? 0);
$consulta = bd()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$consulta->execute([$idUsuario]);
$ficha = $consulta->fetch();

if (!$ficha) {
    mensaje_flash('danger', 'Usuario no encontrado.');
    redirigir('admin/usuarios.php');
}

$esPropio = $idUsuario === (int) usuario_actual()['id'];
$errors = [];
$tituloPagina = 'Editar usuario';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'editar_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $nuevoRol = $_POST['nuevo_rol'] ?? '';
        $estado = (int) ($_POST['estado'] ?? 1) === 1 ? 1 : 0;

        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Nombre y correo válidos son obligatorios.';
        }

        if (!$errors) {
            $duplicado = bd()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $duplicado->execute([$correo, $idUsuario]);
            if ($duplicado->fetch()) {
                $errors[] = 'El correo ya está en uso por otro usuario.';
            }
        }

        if ($esPropio) {
            $nuevoRol = $ficha['role'];
            $estado = (int) $ficha['status'];
        } elseif (!in_array($nuevoRol, roles_sistema(), true)) {
            $errors[] = 'El rol seleccionado no es válido.';
        }

        if (!$errors) {
            $actualizar = bd()->prepare('UPDATE users SET name = ?, email = ?, bio = ?, role = ?, status = ? WHERE id = ?');
            $actualizar->execute([$nombre, $correo, $bio !== '' ? $bio : null, $nuevoRol, $estado, $idUsuario]);
            sincronizar_sesion_usuario($idUsuario);
            mensaje_flash('success', 'Información del usuario actualizada.');
            redirigir('admin/usuario.php?id=' . $idUsuario);
        }

        $ficha['name'] = $nombre;
        $ficha['email'] = $correo;
        $ficha['bio'] = $bio;
        if (!$esPropio) {
            $ficha['role'] = $nuevoRol;
            $ficha['status'] = $estado;
        }
    }

    if ($accion === 'clave_temporal') {
        if ($esPropio) {
            mensaje_flash('warning', 'No puedes generar una contraseña temporal para tu propia cuenta.');
            redirigir('admin/usuario.php?id=' . $idUsuario);
        }
        $clave = asignar_contrasena_temporal($idUsuario);
        if ($clave) {
            $_SESSION['clave_temporal_mostrada'] = [
                'nombre' => $ficha['name'],
                'email' => $ficha['email'],
                'clave' => $clave,
            ];
            mensaje_flash('success', 'Contraseña temporal creada. Entrégasela al usuario; al entrar deberá definir una nueva.');
        } else {
            mensaje_flash('danger', 'No se pudo crear la contraseña temporal. Actualiza las tablas en instalación.');
        }
        redirigir('admin/usuario.php?id=' . $idUsuario);
    }
}

$claveTemporal = $_SESSION['clave_temporal_mostrada'] ?? null;
unset($_SESSION['clave_temporal_mostrada']);

require_once __DIR__ . '/../includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <a href="<?= URL_USUARIOS ?>" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Volver a usuarios</a>
        <h1 class="mt-1">Editar usuario</h1>
        <p class="subtitle"><?= escapar($ficha['name']) ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= escapar($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

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

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-body text-center py-4">
                <?= renderizar_avatar_usuario($ficha, 96, 'mx-auto mb-3 profile-avatar-lg') ?>
                <h2 class="h5 mb-1"><?= escapar($ficha['name']) ?></h2>
                <div class="mb-2"><?= insignia_rol($ficha['role']) ?></div>
                <p class="text-muted small mb-1"><?= escapar($ficha['email']) ?></p>
                <span class="badge <?= !empty($ficha['status']) ? 'bg-success' : 'bg-secondary' ?>">
                    <?= !empty($ficha['status']) ? 'Activo' : 'Inactivo' ?>
                </span>
                <?php if (!empty($ficha['must_change_password'])): ?>
                    <div class="mt-2"><span class="badge bg-warning text-dark">Clave temporal pendiente</span></div>
                <?php endif; ?>
                <p class="text-muted small mt-3 mb-0">Miembro desde <?= formatear_fecha($ficha['created_at']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel mb-4">
            <div class="panel-header"><h2>Información del usuario</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="editar_usuario">
                    <input type="hidden" name="id_usuario" value="<?= (int) $ficha['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" value="<?= escapar($ficha['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="correo">Correo</label>
                        <input type="email" name="correo" id="correo" class="form-control" value="<?= escapar($ficha['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bio">Biografía</label>
                        <textarea name="bio" id="bio" class="form-control" rows="3"><?= escapar($ficha['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nuevo_rol">Rol</label>
                            <select name="nuevo_rol" id="nuevo_rol" class="form-select" <?= $esPropio ? 'disabled' : '' ?>>
                                <option value="student" <?= $ficha['role'] === 'student' ? 'selected' : '' ?>>Estudiante</option>
                                <option value="teacher" <?= $ficha['role'] === 'teacher' ? 'selected' : '' ?>>Docente</option>
                                <option value="gestor" <?= $ficha['role'] === 'gestor' ? 'selected' : '' ?>>Gestor</option>
                                <option value="admin" <?= $ficha['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                            <?php if ($esPropio): ?>
                                <div class="form-text">No puedes cambiar tu propio rol.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="estado">Estado</label>
                            <select name="estado" id="estado" class="form-select" <?= $esPropio ? 'disabled' : '' ?>>
                                <option value="1" <?= !empty($ficha['status']) ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= empty($ficha['status']) ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <?php if ($esPropio): ?>
                                <div class="form-text">No puedes desactivar tu propia cuenta.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">Guardar cambios</button>
                </form>
            </div>
        </div>

        <?php if (!$esPropio): ?>
        <div class="panel">
            <div class="panel-header"><h2>Contraseña temporal</h2></div>
            <div class="panel-body">
                <p class="text-muted small">Genera una clave temporal. El usuario deberá crear una nueva al iniciar sesión y luego entrará al campus.</p>
                <form method="post" onsubmit="return confirm('¿Generar una contraseña temporal? El usuario deberá crear una nueva al entrar.');">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="clave_temporal">
                    <input type="hidden" name="id_usuario" value="<?= (int) $ficha['id'] ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-key me-1"></i> Generar contraseña temporal
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
