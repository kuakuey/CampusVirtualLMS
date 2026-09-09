<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

if (!usuario_debe_cambiar_clave()) {
    redirigir('panel.php');
}

$usuario = usuario_real();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $nueva = $_POST['new_password'] ?? '';
    $confirma = $_POST['confirm_password'] ?? '';

    if (strlen($nueva) < 6) {
        $errors[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
    }
    if ($nueva !== $confirma) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (!$errors) {
        try {
            $consulta = bd()->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?');
            $consulta->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $usuario['id']]);
        } catch (PDOException $e) {
            $consulta = bd()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $consulta->execute([password_hash($nueva, PASSWORD_DEFAULT), (int) $usuario['id']]);
        }
        sincronizar_sesion_usuario((int) $usuario['id']);
        $_SESSION['usuario']['must_change_password'] = 0;
        mensaje_flash('success', 'Contraseña actualizada. Ya puedes usar el campus.');
        redirigir('panel.php');
    }
}

$tituloPagina = 'Nueva contraseña';
require_once __DIR__ . '/includes/encabezado.php';
?>
<div class="auth-card">
    <div class="auth-top">
        <div class="logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h1 class="h4 mb-1">Crea tu contraseña</h1>
        <p class="mb-0 opacity-75">Entraste con una contraseña temporal. Define una nueva para continuar.</p>
    </div>
    <div class="auth-body-inner">
        <?php if ($errors): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?><li><?= escapar($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p class="small text-muted mb-3">Hola, <strong><?= escapar($usuario['name'] ?? '') ?></strong>. Esta contraseña reemplazará la temporal.</p>
        <form method="post" data-password-match>
            <?= campo_csrf() ?>
            <div class="mb-3">
                <label class="form-label" for="new_password">Nueva contraseña</label>
                <div class="password-field">
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-toggle-password="new_password" aria-label="Mostrar contraseña" aria-pressed="false">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirmar contraseña</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password" aria-describedby="password-match-msg">
                <div class="form-text password-match-msg" id="password-match-msg" aria-live="polite"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Guardar y entrar</button>
        </form>
        <p class="text-center mt-3 mb-0">
            <a href="<?= URL_CERRAR_SESION ?>" class="small text-muted">Cerrar sesión</a>
        </p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/pie.php'; ?>
