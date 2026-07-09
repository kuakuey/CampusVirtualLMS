<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $role = $_POST['role'] ?? 'student';

    if ($name === '') $errors[] = 'El nombre es obligatorio.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Correo inválido.';
    if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2) $errors[] = 'Las contraseñas no coinciden.';
    if (!in_array($role, ['student', 'teacher'], true)) $role = 'student';

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'Ese correo ya está registrado.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $hash, $role]);
            flash('success', 'Cuenta creada. Ya puedes iniciar sesión.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Registro';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="logo"><i class="bi bi-person-plus-fill"></i></div>
        <h1 class="h4 mb-1">Crear cuenta</h1>
        <p class="mb-0 opacity-75">Únete a <?= e(APP_NAME) ?></p>
    </div>
    <div class="auth-body-inner">
        <?php if ($errors): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="name">Nombre completo</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="role">Tipo de cuenta</label>
                <select class="form-select" id="role" name="role">
                    <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Estudiante</option>
                    <option value="teacher" <?= ($_POST['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Docente</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password2">Confirmar contraseña</label>
                <input type="password" class="form-control" id="password2" name="password2" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Registrarme</button>
        </form>
        <p class="text-center mb-0">¿Ya tienes cuenta? <a href="<?= APP_URL ?>/login.php">Inicia sesión</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
