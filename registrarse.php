<?php
require_once __DIR__ . '/includes/funciones.php';

if (esta_logueado()) {
    redirigir('panel.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $role = 'student';

    if ($name === '') $errors[] = 'El nombre es obligatorio.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Correo inválido.';
    if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2) $errors[] = 'Las contraseñas no coinciden.';

    if (!$errors) {
        $check = bd()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'Ese correo ya está registrado.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = bd()->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $hash, $role]);
            mensaje_flash('success', 'Cuenta creada. Ya puedes iniciar sesión.');
            redirigir('iniciar-sesion.php');
        }
    }
}

$tituloPagina = 'Registro';
require_once __DIR__ . '/includes/encabezado.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="logo"><i class="bi bi-person-plus-fill"></i></div>
        <h1 class="h4 mb-1">Crear cuenta</h1>
        <p class="mb-0 opacity-75">Únete a <?= escapar(NOMBRE_APP) ?></p>
    </div>
    <div class="auth-body-inner">
        <?php if ($errors): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?><li><?= escapar($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" data-password-match>
            <?= campo_csrf() ?>
            <div class="mb-3">
                <label class="form-label" for="name">Nombre completo</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= escapar($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= escapar($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6" autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password2">Confirmar contraseña</label>
                <input type="password" class="form-control" id="password2" name="password2" required minlength="6" autocomplete="new-password" aria-describedby="password-match-msg">
                <div class="form-text password-match-msg" id="password-match-msg" aria-live="polite"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Registrarme</button>
        </form>
        <p class="text-center mb-0">¿Ya tienes cuenta? <a href="<?= URL_APP ?>/iniciar-sesion.php">Inicia sesión</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/pie.php'; ?>
