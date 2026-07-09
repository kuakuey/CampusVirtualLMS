<?php
require_once __DIR__ . '/includes/funciones.php';

if (esta_logueado()) {
    redirigir('panel.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Completa todos los campos.';
    } else {
        $stmt = bd()->prepare('SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            unset($usuario['password']);
            $_SESSION['usuario'] = $usuario;
            mensaje_flash('success', '¡Bienvenido/a, ' . $usuario['name'] . '!');
            redirigir('panel.php');
        }
        $error = 'Correo o contraseña incorrectos.';
    }
}

$tituloPagina = 'Iniciar sesión';
require_once __DIR__ . '/includes/encabezado.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="logo"><i class="bi bi-mortarboard-fill"></i></div>
        <h1 class="h4 mb-1"><?= escapar(NOMBRE_APP) ?></h1>
        <p class="mb-0 opacity-75">Tu plataforma de aprendizaje</p>
    </div>
    <div class="auth-body-inner">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= escapar($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= campo_csrf() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= escapar($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Entrar</button>
        </form>
        <p class="text-center mb-3">¿No tienes cuenta? <a href="<?= URL_APP ?>/registrarse.php">Regístrate</a></p>
        <div class="demo-accounts">
            <strong>Cuentas demo</strong> (contraseña: <code>password123</code>)
            <ul class="mb-0 mt-1 ps-3">
                <li>admin@aulavirtual.com</li>
                <li>docente@aulavirtual.com</li>
                <li>estudiante@aulavirtual.com</li>
            </ul>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/pie.php'; ?>
