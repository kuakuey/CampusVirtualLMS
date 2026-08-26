<?php
require_once __DIR__ . '/funciones.php';
$tituloPagina = $tituloPagina ?? NOMBRE_APP;
$usuario = usuario_actual();
$mensaje = obtener_mensaje();
$paginaActual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapar($tituloPagina) ?> · <?= escapar(NOMBRE_APP) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= URL_APP ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= $usuario ? 'app-body' : 'auth-body' ?>">
<?php if ($usuario): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= URL_PANEL ?>">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
            <span class="fw-semibold"><?= escapar(NOMBRE_APP) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipal">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navPrincipal">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 app-main-nav">
                <li class="nav-item">
                    <a class="nav-link <?= $paginaActual === 'panel.php' ? 'active' : '' ?>" href="<?= URL_PANEL ?>">
                        <i class="bi bi-speedometer2 me-1"></i> Panel
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($paginaActual, ['cursos.php','curso.php','leccion.php','leccion-formulario.php','curso-formulario.php','curso-asistencia.php'], true) ? 'active' : '' ?>" href="<?= URL_CURSOS ?>">
                        <i class="bi bi-journal-bookmark me-1"></i> Cursos
                    </a>
                </li>
                <?php if ($usuario['role'] === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $paginaActual === 'catalogo.php' ? 'active' : '' ?>" href="<?= URL_CATALOGO ?>">
                        <i class="bi bi-grid me-1"></i> Catálogo
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= $paginaActual === 'perfil.php' ? 'active' : '' ?>" href="<?= URL_PERFIL ?>">
                        <i class="bi bi-person me-1"></i> Perfil
                    </a>
                </li>
                <?php if ($usuario['role'] === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($paginaActual, ['usuarios.php','categorias.php'], true) ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i> Administración
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= URL_USUARIOS ?>"><i class="bi bi-people me-2"></i>Usuarios</a></li>
                        <li><a class="dropdown-item" href="<?= URL_CATEGORIAS ?>"><i class="bi bi-tags me-2"></i>Categorías</a></li>
                        <li><a class="dropdown-item" href="<?= URL_CURSOS ?>"><i class="bi bi-collection me-2"></i>Cursos</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <a class="d-flex align-items-center gap-2 text-decoration-none text-white dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <?= renderizar_avatar_usuario($usuario) ?>
                        <span class="d-none d-md-inline small">
                            <strong><?= escapar($usuario['name']) ?></strong><br>
                            <span class="opacity-75"><?= escapar(etiqueta_rol($usuario['role'])) ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item text-danger" href="<?= URL_CERRAR_SESION ?>"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="<?= $usuario ? 'app-main' : '' ?>">
    <div class="<?= $usuario ? 'container-fluid px-3 px-lg-4 py-4' : '' ?>">
        <?php if ($mensaje): ?>
        <div class="alert alert-<?= escapar($mensaje['tipo']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= escapar($mensaje['mensaje']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
