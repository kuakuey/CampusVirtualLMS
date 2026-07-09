<?php
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$flash = get_flash();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= $user ? 'app-body' : 'auth-body' ?>">
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= APP_URL ?>/dashboard.php">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
            <span class="fw-semibold"><?= e(APP_NAME) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i> Panel
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPage, ['courses.php','course.php','lesson.php'], true) ? 'active' : '' ?>" href="<?= APP_URL ?>/courses.php">
                        <i class="bi bi-journal-bookmark me-1"></i> Cursos
                    </a>
                </li>
                <?php if ($user['role'] === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'catalog.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/catalog.php">
                        <i class="bi bi-grid me-1"></i> Catálogo
                    </a>
                </li>
                <?php endif; ?>
                <?php if (in_array($user['role'], ['admin','teacher'], true)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'announcements.php' ? 'active' : '' ?>" href="<?= APP_URL ?>/announcements.php">
                        <i class="bi bi-megaphone me-1"></i> Anuncios
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($currentPage, ['users.php','categories.php'], true) ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i> Administración
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/users.php"><i class="bi bi-people me-2"></i>Usuarios</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/categories.php"><i class="bi bi-tags me-2"></i>Categorías</a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/courses.php"><i class="bi bi-collection me-2"></i>Cursos</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <a class="d-flex align-items-center gap-2 text-decoration-none text-white dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <span class="user-avatar"><?= e(initials($user['name'])) ?></span>
                        <span class="d-none d-md-inline small">
                            <strong><?= e($user['name']) ?></strong><br>
                            <span class="opacity-75"><?= e(role_label($user['role'])) ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/profile.php"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="<?= $user ? 'app-main' : '' ?>">
    <div class="<?= $user ? 'container-fluid px-3 px-lg-4 py-4' : '' ?>">
        <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
