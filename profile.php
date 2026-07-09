<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$pageTitle = 'Mi perfil';
$errors = [];

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        } else {
            $stmt = db()->prepare('UPDATE users SET name=?, bio=? WHERE id=?');
            $stmt->execute([$name, $bio, $user['id']]);
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['bio'] = $bio;
            flash('success', 'Perfil actualizado.');
            redirect('profile.php');
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $profile['password'])) {
            $errors[] = 'La contraseña actual no es correcta.';
        } elseif (strlen($new) < 6) {
            $errors[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($new !== $confirm) {
            $errors[] = 'La confirmación no coincide.';
        } else {
            $stmt = db()->prepare('UPDATE users SET password=? WHERE id=?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Contraseña actualizada.');
            redirect('profile.php');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Mi perfil</h1>
        <p class="subtitle">Administra tu información personal</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-body text-center py-4">
                <div class="user-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:1.4rem;"><?= e(initials($profile['name'])) ?></div>
                <h2 class="h5 mb-1"><?= e($profile['name']) ?></h2>
                <div class="mb-2"><?= role_badge($profile['role']) ?></div>
                <p class="text-muted small mb-0"><?= e($profile['email']) ?></p>
                <p class="text-muted small">Miembro desde <?= format_date($profile['created_at']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel mb-4">
            <div class="panel-header"><h2>Datos personales</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" value="<?= e($profile['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" class="form-control" value="<?= e($profile['email']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Biografía</label>
                        <textarea name="bio" class="form-control" rows="3"><?= e($profile['bio'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h2>Cambiar contraseña</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label class="form-label">Contraseña actual</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
