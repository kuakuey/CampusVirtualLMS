<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Completa todos los campos.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;
            flash('success', '¡Bienvenido/a, ' . $user['name'] . '!');
            redirect('dashboard.php');
        }
        $error = 'Correo o contraseña incorrectos.';
    }
}

$pageTitle = 'Iniciar sesión';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="logo"><i class="bi bi-mortarboard-fill"></i></div>
        <h1 class="h4 mb-1"><?= e(APP_NAME) ?></h1>
        <p class="mb-0 opacity-75">Tu plataforma de aprendizaje</p>
    </div>
    <div class="auth-body-inner">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Entrar</button>
        </form>
        <p class="text-center mb-3">¿No tienes cuenta? <a href="<?= APP_URL ?>/register.php">Regístrate</a></p>
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
