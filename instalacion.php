<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/gestor_bd.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function inst_escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function inst_token_csrf(): string
{
    if (empty($_SESSION['token_csrf_instalacion'])) {
        $_SESSION['token_csrf_instalacion'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf_instalacion'];
}

function inst_campo_csrf(): string
{
    return '<input type="hidden" name="token_csrf" value="' . inst_escapar(inst_token_csrf()) . '">';
}

function inst_verificar_csrf(): void
{
    $token = $_POST['token_csrf'] ?? '';
    if (!hash_equals(inst_token_csrf(), $token)) {
        $_SESSION['mensaje_instalacion'] = ['tipo' => 'danger', 'mensaje' => 'Token de seguridad inválido.'];
        header('Location: ' . URL_INSTALACION);
        exit;
    }
}

function inst_mensaje(string $tipo, string $texto): void
{
    $_SESSION['mensaje_instalacion'] = ['tipo' => $tipo, 'mensaje' => $texto];
}

function inst_obtener_mensaje(): ?array
{
    if (!isset($_SESSION['mensaje_instalacion'])) {
        return null;
    }
    $mensaje = $_SESSION['mensaje_instalacion'];
    unset($_SESSION['mensaje_instalacion']);
    return $mensaje;
}

function inst_redirigir(): void
{
    header('Location: ' . URL_INSTALACION);
    exit;
}

function inst_formatear_fecha(?string $fechaHora): string
{
    return $fechaHora ? date('d/m/Y H:i', strtotime($fechaHora)) : '—';
}

$tituloPagina = 'Instalación';
$mensaje = inst_obtener_mensaje();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    inst_verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_bd') {
        $resultado = GestorBd::crearBaseDatos();
        inst_mensaje($resultado['exito'] ? 'success' : 'danger', $resultado['mensaje']);
        inst_redirigir();
    }

    if ($accion === 'actualizar_tablas') {
        $resultado = GestorBd::ejecutarMigracionesPendientes(true);
        inst_mensaje($resultado['exito'] ? 'success' : 'danger', $resultado['mensaje']);
        inst_redirigir();
    }

    if ($accion === 'ejecutar_migracion') {
        $archivo = basename($_POST['migracion'] ?? '');
        $resultado = GestorBd::ejecutarMigracion($archivo);
        inst_mensaje($resultado['exito'] ? 'success' : 'danger', $resultado['mensaje']);
        inst_redirigir();
    }

    if ($accion === 'instalacion_rapida') {
        $crear = GestorBd::crearBaseDatos();
        if (!$crear['exito']) {
            inst_mensaje('danger', $crear['mensaje']);
            inst_redirigir();
        }
        $migrar = GestorBd::ejecutarMigracionesPendientes(false);
        inst_mensaje($migrar['exito'] ? 'success' : 'danger', $migrar['mensaje']);
        inst_redirigir();
    }
}

$resumen = GestorBd::obtenerResumen();
$todoListo = $resumen['servidor']['exito'] && $resumen['bd_conectada'] && $resumen['tablas_listas'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= inst_escapar($tituloPagina) ?> · <?= inst_escapar(NOMBRE_APP) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= URL_APP ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="app-body">
<div class="container py-4 py-lg-5" style="max-width: 1100px;">
    <div class="text-center mb-4">
        <div class="brand-mark d-inline-flex mb-2" style="width:48px;height:48px;font-size:1.4rem;background:var(--av-teal);color:#fff;border-radius:12px;">
            <i class="bi bi-database-gear"></i>
        </div>
        <h1 class="h3 mb-1" style="color:var(--av-navy);">Instalación de <?= inst_escapar(NOMBRE_APP) ?></h1>
        <p class="text-muted mb-0">Verifica la conexión y configura la base de datos sin iniciar sesión</p>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= inst_escapar($mensaje['tipo']) ?> alert-dismissible fade show shadow-sm">
        <?= inst_escapar($mensaje['mensaje']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($todoListo): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 shadow-sm">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>
            <strong>Sistema listo.</strong> La base de datos está conectada y todas las tablas están instaladas.
            <a href="<?= URL_INICIO_SESION ?>" class="alert-link ms-1">Ir al login</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?= $resumen['servidor']['exito'] ? 'icon-teal' : 'icon-rose' ?>">
                    <i class="bi bi-hdd-network"></i>
                </div>
                <div class="stat-value"><?= $resumen['servidor']['exito'] ? 'Conectado' : 'Error' ?></div>
                <div class="stat-label">Servidor MySQL</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?= $resumen['bd_conectada'] ? 'icon-teal' : 'icon-amber' ?>">
                    <i class="bi bi-database"></i>
                </div>
                <div class="stat-value"><?= $resumen['bd_conectada'] ? 'Conectada' : ($resumen['existe_bd'] ? 'Sin acceso' : 'No existe') ?></div>
                <div class="stat-label">Base de datos</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?= $resumen['tablas_listas'] ? 'icon-teal' : 'icon-amber' ?>">
                    <i class="bi bi-table"></i>
                </div>
                <div class="stat-value"><?= (int) $resumen['cantidad_tablas'] ?>/<?= (int) $resumen['total_tablas'] ?></div>
                <div class="stat-label">Tablas instaladas</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel mb-4">
                <div class="panel-header"><h2>Acciones</h2></div>
                <div class="panel-body d-grid gap-2">
                    <form method="post">
                        <?= inst_campo_csrf() ?>
                        <input type="hidden" name="accion" value="crear_bd">
                        <button type="submit" class="btn btn-outline-primary w-100 text-start" <?= !$resumen['servidor']['exito'] ? 'disabled' : '' ?>>
                            <i class="bi bi-database-add me-2"></i> Crear base de datos
                        </button>
                    </form>
                    <form method="post">
                        <?= inst_campo_csrf() ?>
                        <input type="hidden" name="accion" value="actualizar_tablas">
                        <button type="submit" class="btn btn-primary w-100 text-start" <?= !$resumen['existe_bd'] ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-repeat me-2"></i> Actualizar tablas
                            <?php
                            $pendientesEstructura = array_filter($resumen['migraciones_pendientes'], function ($f) {
                                return strpos($f, 'datos_iniciales') === false;
                            });
                            if ($pendientesEstructura): ?>
                                <span class="badge bg-warning text-dark ms-1"><?= count($pendientesEstructura) ?> pendiente(s)</span>
                            <?php endif; ?>
                        </button>
                    </form>
                    <form method="post" onsubmit="return confirm('¿Ejecutar instalación completa?');">
                        <?= inst_campo_csrf() ?>
                        <input type="hidden" name="accion" value="instalacion_rapida">
                        <button type="submit" class="btn btn-success w-100 text-start" <?= !$resumen['servidor']['exito'] ? 'disabled' : '' ?>>
                            <i class="bi bi-lightning-charge me-2"></i> Instalación completa (BD + tablas + admin)
                        </button>
                    </form>
                </div>
            </div>
            <div class="panel">
                <div class="panel-header"><h2>Conexión</h2></div>
                <div class="panel-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Host</dt><dd class="col-7"><code><?= inst_escapar($resumen['host_bd']) ?></code></dd>
                        <dt class="col-5">Base de datos</dt><dd class="col-7"><code><?= inst_escapar($resumen['nombre_bd']) ?></code></dd>
                        <dt class="col-5">Usuario</dt><dd class="col-7"><code><?= inst_escapar(BD_USUARIO) ?></code></dd>
                        <dt class="col-5">Servidor</dt>
                        <dd class="col-7"><span class="badge <?= $resumen['servidor']['exito'] ? 'bg-success' : 'bg-danger' ?>"><?= $resumen['servidor']['exito'] ? 'Conectado' : 'Sin conexión' ?></span></dd>
                        <dt class="col-5">Base de datos</dt>
                        <dd class="col-7"><span class="badge <?= $resumen['bd_conectada'] ? 'bg-success' : 'bg-secondary' ?>"><?= $resumen['bd_conectada'] ? 'Conectada' : 'No conectada' ?></span></dd>
                    </dl>
                    <?php if (!$resumen['servidor']['exito']): ?>
                        <div class="alert alert-danger small mt-3 mb-0"><?= inst_escapar($resumen['servidor']['mensaje']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel mb-4">
                <div class="panel-header"><h2>Migraciones</h2><span class="badge bg-secondary"><?= count($resumen['migraciones_aplicadas']) ?> aplicadas</span></div>
                <div class="panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Archivo</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
                            <tbody>
                                <?php
                                $mapaAplicadas = [];
                                foreach ($resumen['migraciones_aplicadas'] as $m) {
                                    $mapaAplicadas[$m['migration']] = $m['executed_at'];
                                }
                                foreach (GestorBd::obtenerArchivosMigracion() as $archivo):
                                    $aplicada = isset($mapaAplicadas[$archivo]);
                                ?>
                                <tr>
                                    <td><code><?= inst_escapar($archivo) ?></code></td>
                                    <td><span class="badge <?= $aplicada ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $aplicada ? 'Aplicada' : 'Pendiente' ?></span></td>
                                    <td class="small text-muted"><?= $aplicada ? inst_formatear_fecha($mapaAplicadas[$archivo]) : '—' ?></td>
                                    <td class="text-end">
                                        <?php if (!$aplicada && $resumen['existe_bd']): ?>
                                        <form method="post" class="d-inline">
                                            <?= inst_campo_csrf() ?>
                                            <input type="hidden" name="accion" value="ejecutar_migracion">
                                            <input type="hidden" name="migracion" value="<?= inst_escapar($archivo) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Ejecutar</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="panel">
                <div class="panel-header"><h2>Tablas</h2></div>
                <div class="panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Tabla</th><th>Estado</th><th class="text-end">Registros</th></tr></thead>
                            <tbody>
                                <?php foreach ($resumen['tablas'] as $tabla): ?>
                                <tr>
                                    <td><code><?= inst_escapar($tabla['nombre']) ?></code></td>
                                    <td><span class="badge <?= $tabla['existe'] ? 'bg-success' : 'bg-secondary' ?>"><?= $tabla['existe'] ? 'OK' : 'Falta' ?></span></td>
                                    <td class="text-end"><?= $tabla['registros'] !== null ? number_format($tabla['registros']) : '—' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="text-center mt-4">
        <a href="<?= URL_INICIO_SESION ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i> Ir al login</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
