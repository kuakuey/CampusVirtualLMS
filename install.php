<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db_manager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function install_e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function install_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function install_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . install_e(install_csrf_token()) . '">';
}

function install_verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(install_csrf_token(), $token)) {
        $_SESSION['install_flash'] = ['type' => 'danger', 'message' => 'Token de seguridad inválido.'];
        header('Location: ' . APP_URL . '/install.php');
        exit;
    }
}

function install_flash(string $type, string $message): void
{
    $_SESSION['install_flash'] = ['type' => $type, 'message' => $message];
}

function install_get_flash(): ?array
{
    if (!isset($_SESSION['install_flash'])) {
        return null;
    }
    $flash = $_SESSION['install_flash'];
    unset($_SESSION['install_flash']);
    return $flash;
}

function install_redirect(): never
{
    header('Location: ' . APP_URL . '/install.php');
    exit;
}

function install_format_date(?string $datetime): string
{
    return $datetime ? date('d/m/Y H:i', strtotime($datetime)) : '—';
}

$pageTitle = 'Instalación';
$flash = install_get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    install_verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_database') {
        $result = DbManager::createDatabase();
        install_flash($result['ok'] ? 'success' : 'danger', $result['message']);
        install_redirect();
    }

    if ($action === 'run_migrations') {
        $result = DbManager::runPendingMigrations(false);
        install_flash($result['ok'] ? 'success' : 'danger', $result['message']);
        install_redirect();
    }

    if ($action === 'run_migration') {
        $file = basename($_POST['migration'] ?? '');
        $result = DbManager::runMigration($file);
        install_flash($result['ok'] ? 'success' : 'danger', $result['message']);
        install_redirect();
    }

    if ($action === 'install_all') {
        $create = DbManager::createDatabase();
        if (!$create['ok']) {
            install_flash('danger', $create['message']);
            install_redirect();
        }
        $migrate = DbManager::runPendingMigrations(false);
        install_flash($migrate['ok'] ? 'success' : 'danger', $migrate['message']);
        install_redirect();
    }

    if ($action === 'install_with_seed') {
        $create = DbManager::createDatabase();
        if (!$create['ok']) {
            install_flash('danger', $create['message']);
            install_redirect();
        }
        $migrate = DbManager::runPendingMigrations(true);
        install_flash($migrate['ok'] ? 'success' : 'danger', $migrate['message']);
        install_redirect();
    }
}

$summary = DbManager::getSummary();
$allReady = $summary['server']['ok'] && $summary['database_connected'] && $summary['tables_ready'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= install_e($pageTitle) ?> · <?= install_e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="app-body">
<div class="container py-4 py-lg-5" style="max-width: 1100px;">
    <div class="text-center mb-4">
        <div class="brand-mark d-inline-flex mb-2" style="width:48px;height:48px;font-size:1.4rem;background:var(--av-teal);color:#fff;border-radius:12px;">
            <i class="bi bi-database-gear"></i>
        </div>
        <h1 class="h3 mb-1" style="color:var(--av-navy);">Instalación de <?= install_e(APP_NAME) ?></h1>
        <p class="text-muted mb-0">Verifica la conexión y configura la base de datos sin iniciar sesión</p>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= install_e($flash['type']) ?> alert-dismissible fade show shadow-sm">
        <?= install_e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($allReady): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 shadow-sm">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>
            <strong>Sistema listo.</strong> La base de datos está conectada y todas las tablas están instaladas.
            <a href="<?= APP_URL ?>/login.php" class="alert-link ms-1">Ir al login</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?= $summary['server']['ok'] ? 'icon-teal' : 'icon-rose' ?>">
                    <i class="bi bi-hdd-network"></i>
                </div>
                <div class="stat-value"><?= $summary['server']['ok'] ? 'Conectado' : 'Error' ?></div>
                <div class="stat-label">Servidor MySQL</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?= $summary['database_connected'] ? 'icon-teal' : 'icon-amber' ?>">
                    <i class="bi bi-database"></i>
                </div>
                <div class="stat-value"><?= $summary['database_connected'] ? 'Conectada' : ($summary['database_exists'] ? 'Sin acceso' : 'No existe') ?></div>
                <div class="stat-label">Base de datos</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?= $summary['tables_ready'] ? 'icon-teal' : 'icon-amber' ?>">
                    <i class="bi bi-table"></i>
                </div>
                <div class="stat-value"><?= (int) $summary['tables_count'] ?>/<?= (int) $summary['tables_total'] ?></div>
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
                        <?= install_csrf_field() ?>
                        <input type="hidden" name="action" value="create_database">
                        <button type="submit" class="btn btn-outline-primary w-100 text-start" <?= !$summary['server']['ok'] ? 'disabled' : '' ?>>
                            <i class="bi bi-database-add me-2"></i> Crear base de datos
                        </button>
                    </form>

                    <form method="post">
                        <?= install_csrf_field() ?>
                        <input type="hidden" name="action" value="run_migrations">
                        <button type="submit" class="btn btn-primary w-100 text-start" <?= !$summary['database_exists'] ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-repeat me-2"></i> Actualizar tablas
                            <?php
                            $pendingStructure = array_filter($summary['pending_migrations'], fn ($f) => !str_contains($f, 'seed'));
                            if ($pendingStructure): ?>
                                <span class="badge bg-warning text-dark ms-1"><?= count($pendingStructure) ?> pendiente(s)</span>
                            <?php endif; ?>
                        </button>
                    </form>

                    <form method="post" onsubmit="return confirm('¿Ejecutar instalación completa (BD + tablas)?');">
                        <?= install_csrf_field() ?>
                        <input type="hidden" name="action" value="install_all">
                        <button type="submit" class="btn btn-success w-100 text-start" <?= !$summary['server']['ok'] ? 'disabled' : '' ?>>
                            <i class="bi bi-lightning-charge me-2"></i> Instalación rápida (BD + tablas)
                        </button>
                    </form>

                    <form method="post" onsubmit="return confirm('¿Instalar con datos de demostración?');">
                        <?= install_csrf_field() ?>
                        <input type="hidden" name="action" value="install_with_seed">
                        <button type="submit" class="btn btn-outline-success w-100 text-start" <?= !$summary['server']['ok'] ? 'disabled' : '' ?>>
                            <i class="bi bi-box-seam me-2"></i> Instalar con datos demo
                        </button>
                    </form>

                    <div class="alert alert-info small mb-0 mt-1">
                        <i class="bi bi-shield-check me-1"></i>
                        Las actualizaciones no borran datos. Usa <strong>Actualizar tablas</strong> cuando subas una nueva versión.
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><h2>Conexión</h2></div>
                <div class="panel-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Host</dt>
                        <dd class="col-7"><code><?= install_e($summary['database_host']) ?></code></dd>
                        <dt class="col-5">Base de datos</dt>
                        <dd class="col-7"><code><?= install_e($summary['database_name']) ?></code></dd>
                        <dt class="col-5">Usuario</dt>
                        <dd class="col-7"><code><?= install_e(DB_USER) ?></code></dd>
                        <dt class="col-5">Servidor</dt>
                        <dd class="col-7">
                            <span class="badge <?= $summary['server']['ok'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $summary['server']['ok'] ? 'Conectado' : 'Sin conexión' ?>
                            </span>
                        </dd>
                        <dt class="col-5">Base de datos</dt>
                        <dd class="col-7">
                            <span class="badge <?= $summary['database_connected'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $summary['database_connected'] ? 'Conectada' : 'No conectada' ?>
                            </span>
                        </dd>
                    </dl>
                    <?php if (!$summary['server']['ok']): ?>
                        <div class="alert alert-danger small mt-3 mb-0"><?= install_e($summary['server']['message']) ?></div>
                    <?php elseif (!$summary['database_connected'] && $summary['database_exists']): ?>
                        <div class="alert alert-warning small mt-3 mb-0">La base de datos existe pero no se puede acceder. Revisa usuario y contraseña en <code>config/config.php</code>.</div>
                    <?php elseif (!$summary['database_exists']): ?>
                        <div class="alert alert-warning small mt-3 mb-0">La base de datos aún no existe. Usa <strong>Crear base de datos</strong> o <strong>Instalación rápida</strong>.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel mb-4">
                <div class="panel-header">
                    <h2>Migraciones</h2>
                    <span class="badge bg-secondary"><?= count($summary['applied_migrations']) ?> aplicadas</span>
                </div>
                <div class="panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Archivo</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $appliedMap = [];
                                foreach ($summary['applied_migrations'] as $m) {
                                    $appliedMap[$m['migration']] = $m['executed_at'];
                                }
                                foreach (DbManager::getMigrationFiles() as $file):
                                    $isApplied = isset($appliedMap[$file]);
                                ?>
                                <tr>
                                    <td><code><?= install_e($file) ?></code></td>
                                    <td>
                                        <span class="badge <?= $isApplied ? 'bg-success' : 'bg-warning text-dark' ?>">
                                            <?= $isApplied ? 'Aplicada' : 'Pendiente' ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= $isApplied ? install_format_date($appliedMap[$file]) : '—' ?></td>
                                    <td class="text-end">
                                        <?php if (!$isApplied && $summary['database_exists']): ?>
                                        <form method="post" class="d-inline">
                                            <?= install_csrf_field() ?>
                                            <input type="hidden" name="action" value="run_migration">
                                            <input type="hidden" name="migration" value="<?= install_e($file) ?>">
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
                            <thead class="table-light">
                                <tr>
                                    <th>Tabla</th>
                                    <th>Estado</th>
                                    <th class="text-end">Registros</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['tables'] as $table): ?>
                                <tr>
                                    <td><code><?= install_e($table['name']) ?></code></td>
                                    <td>
                                        <span class="badge <?= $table['exists'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $table['exists'] ? 'OK' : 'Falta' ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= $table['rows'] !== null ? number_format($table['rows']) : '—' ?></td>
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
        <a href="<?= APP_URL ?>/login.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i> Ir al login
        </a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
