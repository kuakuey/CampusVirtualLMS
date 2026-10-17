<?php
require_once __DIR__ . '/../includes/funciones.php';
requiere_sesion();
requiere_rol(['admin', 'gestor']);

$tituloPagina = 'Rendimiento';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vaciar_caidas') {
    verificar_csrf();
    vaciar_caidas();
    mensaje_flash('success', 'Se vació el registro de caídas.');
    redirigir('admin/rendimiento.php');
}

$medicion = medir_campus();
$caidas = leer_caidas(80);
$caidasRecientes = 0;
$haceUnaHora = time() - 3600;
foreach ($caidas as $caida) {
    $momento = strtotime((string) ($caida['fecha'] ?? ''));
    if ($momento && $momento >= $haceUnaHora) {
        $caidasRecientes++;
    }
}

$baseLenta = $medicion['base']['ok'] && $medicion['base']['ms'] !== null && $medicion['base']['ms'] >= 300;
$discoBajo = $medicion['disco']['libre'] !== null && $medicion['disco']['libre'] < 200 * 1048576;
$memoriaAlta = $medicion['php']['limite'] > 0
    && ($medicion['php']['pico'] / $medicion['php']['limite']) >= 0.85;
$enAlerta = !$medicion['base']['ok'] || $baseLenta || $discoBajo || $memoriaAlta || $caidasRecientes > 0;

function texto_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1073741824) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return round($bytes / 1073741824, 2) . ' GB';
}

function texto_hace(int $segundos): string
{
    $segundos = max(0, $segundos);
    if ($segundos < 15) {
        return 'ahora';
    }
    if ($segundos < 60) {
        return 'hace ' . $segundos . ' s';
    }
    $minutos = (int) floor($segundos / 60);
    return 'hace ' . $minutos . ' min';
}

function texto_uptime(int $segundos): string
{
    $dias = intdiv($segundos, 86400);
    $horas = intdiv($segundos % 86400, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    if ($dias > 0) {
        return $dias . ' d ' . $horas . ' h';
    }
    if ($horas > 0) {
        return $horas . ' h ' . $minutos . ' min';
    }
    return $minutos . ' min';
}

function etiqueta_pagina_presencia(string $pagina): string
{
    switch ($pagina) {
        case 'leccion.php': return 'Lección';
        case 'curso.php': return 'Curso';
        case 'panel.php': return 'Panel';
        case 'cursos.php': return 'Cursos';
        case 'catalogo.php': return 'Catálogo';
        case 'perfil.php': return 'Perfil';
        case 'rendimiento.php': return 'Rendimiento';
        case 'usuarios.php': return 'Usuarios';
        case 'usuario.php': return 'Usuario';
        case 'categorias.php': return 'Categorías';
        case 'asistencia.php': return 'Asistencia';
        case 'leccion-formulario.php': return 'Editar lección';
        case 'curso-formulario.php': return 'Editar curso';
        case 'cambiar-contrasena.php': return 'Cambiar contraseña';
        default: return $pagina !== '' ? $pagina : '—';
    }
}

function etiqueta_caida(string $tipo): string
{
    switch ($tipo) {
        case 'base_de_datos': return 'Base de datos';
        case 'fatal': return 'Error grave';
        case 'excepcion': return 'Error inesperado';
        default: return 'Fallo';
    }
}

$estadoBd = $medicion['base']['estado'];
$porRol = $medicion['por_rol'];

require_once __DIR__ . '/../includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Rendimiento</h1>
        <p class="subtitle">Personas en línea, estado del campus y registro de caídas.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-primary" id="btn-recarga-auto" aria-pressed="false">
            <i class="bi bi-arrow-repeat me-1"></i> Activar recarga
        </button>
        <a href="<?= URL_RENDIMIENTO ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i> Actualizar ahora</a>
    </div>
</div>

<?php if ($enAlerta): ?>
<div class="alert alert-warning mb-4">
    <strong>Hay algo que conviene mirar.</strong>
    <ul class="mb-0 mt-2">
        <?php if (!$medicion['base']['ok']): ?><li>La base de datos no respondió.</li><?php endif; ?>
        <?php if ($baseLenta): ?><li>La base tardó más de lo normal en responder.</li><?php endif; ?>
        <?php if ($caidasRecientes > 0): ?><li>Hay <?= (int) $caidasRecientes ?> caída<?= $caidasRecientes === 1 ? '' : 's' ?> en la última hora.</li><?php endif; ?>
        <?php if ($discoBajo): ?><li>Queda poco espacio en disco.</li><?php endif; ?>
        <?php if ($memoriaAlta): ?><li>Esta página usó casi toda la memoria permitida.</li><?php endif; ?>
    </ul>
</div>
<?php else: ?>
<div class="alert alert-success">El campus responde y no hay caídas en la última hora.</div>
<?php endif; ?>

<?php if (!$medicion['presencia_lista']): ?>
<div class="alert alert-warning">
    Todavía no se puede contar quién está en línea. Falta aplicar la migración <code>017_presencia_usuarios.sql</code> desde la pantalla de instalación.
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= (int) $medicion['en_linea'] ?></div>
            <div class="stat-label">En línea ahora</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-play-circle"></i></div>
            <div class="stat-value"><?= (int) $medicion['en_leccion'] ?></div>
            <div class="stat-label">Viendo una lección</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-database"></i></div>
            <div class="stat-value">
                <?php if ($medicion['base']['ms'] !== null): ?>
                    <?= (int) round($medicion['base']['ms']) ?> ms
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>
            <div class="stat-label">Respuesta de la base</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon <?= $caidasRecientes > 0 ? 'icon-rose' : 'icon-teal' ?>"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value"><?= (int) $caidasRecientes ?></div>
            <div class="stat-label">Caídas en 1 hora</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="panel mb-4">
            <div class="panel-header">
                <h2>Quién está conectado</h2>
                <span class="small text-muted">Últimos 5 minutos</span>
            </div>
            <div class="panel-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-light text-dark border">Estudiantes: <?= (int) ($porRol['student'] ?? 0) ?></span>
                    <span class="badge bg-light text-dark border">Docentes: <?= (int) ($porRol['teacher'] ?? 0) ?></span>
                    <span class="badge bg-light text-dark border">Gestores: <?= (int) ($porRol['gestor'] ?? 0) ?></span>
                    <span class="badge bg-light text-dark border">Administradores: <?= (int) ($porRol['admin'] ?? 0) ?></span>
                </div>
                <?php if (!$medicion['personas']): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-person-x"></i>
                        <p class="mb-0">Nadie ha usado el campus en los últimos 5 minutos.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Persona</th>
                                    <th>Rol</th>
                                    <th>Dónde</th>
                                    <th>Actividad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicion['personas'] as $persona): ?>
                                <tr>
                                    <td><?= escapar($persona['name']) ?></td>
                                    <td><?= escapar(etiqueta_rol((string) $persona['role'])) ?></td>
                                    <td><?= escapar(etiqueta_pagina_presencia((string) ($persona['pagina'] ?? ''))) ?></td>
                                    <td class="text-muted"><?= escapar(texto_hace((int) ($persona['hace_segundos'] ?? 0))) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($medicion['en_linea'] > count($medicion['personas'])): ?>
                        <p class="small text-muted mt-2 mb-0">Se muestran las 200 más recientes de <?= (int) $medicion['en_linea'] ?>.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel mb-4">
            <div class="panel-header"><h2>Estado del servidor</h2></div>
            <div class="panel-body">
                <dl class="row mb-0 small">
                    <dt class="col-7">PHP</dt>
                    <dd class="col-5 text-end"><?= escapar($medicion['php']['version']) ?></dd>
                    <dt class="col-7">Memoria de esta página</dt>
                    <dd class="col-5 text-end"><?= escapar(texto_bytes((int) $medicion['php']['pico'])) ?></dd>
                    <dt class="col-7">Límite de memoria</dt>
                    <dd class="col-5 text-end"><?= $medicion['php']['limite'] > 0 ? escapar(texto_bytes((int) $medicion['php']['limite'])) : 'sin límite' ?></dd>
                    <dt class="col-7">Tiempo máximo por página</dt>
                    <dd class="col-5 text-end"><?= (int) $medicion['php']['max_tiempo'] > 0 ? (int) $medicion['php']['max_tiempo'] . ' s' : 'sin límite' ?></dd>
                    <dt class="col-7">Espacio libre</dt>
                    <dd class="col-5 text-end"><?= $medicion['disco']['libre'] !== null ? escapar(texto_bytes((int) $medicion['disco']['libre'])) : 'no disponible' ?></dd>
                    <?php if (is_array($medicion['carga']) && isset($medicion['carga'][0])): ?>
                    <dt class="col-7">Carga del servidor</dt>
                    <dd class="col-5 text-end"><?= escapar(number_format((float) $medicion['carga'][0], 2)) ?></dd>
                    <?php endif; ?>
                    <?php if ($medicion['opcache']): ?>
                    <?php
                    $usos = (int) $medicion['opcache']['aciertos'] + (int) $medicion['opcache']['fallos'];
                    $porcentajeCache = $usos > 0 ? (int) round(($medicion['opcache']['aciertos'] / $usos) * 100) : 0;
                    ?>
                    <dt class="col-7">Caché de PHP</dt>
                    <dd class="col-5 text-end"><?= $porcentajeCache ?>% de aciertos</dd>
                    <?php endif; ?>
                    <dt class="col-7">Conexiones abiertas</dt>
                    <dd class="col-5 text-end"><?= isset($estadoBd['Threads_connected']) ? (int) $estadoBd['Threads_connected'] : '—' ?></dd>
                    <dt class="col-7">Trabajando ahora</dt>
                    <dd class="col-5 text-end"><?= isset($estadoBd['Threads_running']) ? (int) $estadoBd['Threads_running'] : '—' ?></dd>
                    <dt class="col-7">Máximo de conexiones usadas</dt>
                    <dd class="col-5 text-end"><?= isset($estadoBd['Max_used_connections']) ? (int) $estadoBd['Max_used_connections'] : '—' ?></dd>
                    <dt class="col-7">Consultas lentas acumuladas</dt>
                    <dd class="col-5 text-end"><?= isset($estadoBd['Slow_queries']) ? (int) $estadoBd['Slow_queries'] : '—' ?></dd>
                    <dt class="col-7">Base encendida hace</dt>
                    <dd class="col-5 text-end"><?= isset($estadoBd['Uptime']) ? escapar(texto_uptime((int) $estadoBd['Uptime'])) : '—' ?></dd>
                </dl>
                <?php if ($medicion['base']['error'] !== ''): ?>
                    <p class="small text-muted mt-3 mb-0"><?= escapar($medicion['base']['error']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-header">
        <div>
            <h2 class="mb-0">Registro de caídas</h2>
            <p class="small text-muted mb-0">Si el campus vuelve a fallar, aquí queda la hora, la página y el motivo. No se guardan contraseñas.</p>
        </div>
        <?php if ($caidas): ?>
        <form method="post" onsubmit="return confirm('¿Vaciar el registro de caídas?');">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="vaciar_caidas">
            <button type="submit" class="btn btn-sm btn-outline-danger">Vaciar</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <?php if (!$caidas): ?>
            <div class="empty-state py-4">
                <i class="bi bi-check2-circle"></i>
                <p class="mb-0">Todavía no hay caídas registradas.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Cuándo</th>
                            <th>Tipo</th>
                            <th>Página</th>
                            <th>Qué pasó</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caidas as $caida): ?>
                        <tr>
                            <td class="text-nowrap"><?= escapar((string) ($caida['fecha'] ?? '')) ?></td>
                            <td><?= escapar(etiqueta_caida((string) ($caida['tipo'] ?? ''))) ?></td>
                            <td><?= escapar(etiqueta_pagina_presencia((string) ($caida['pagina'] ?? ''))) ?></td>
                            <td class="text-break">
                                <?= escapar((string) ($caida['mensaje'] ?? '')) ?>
                                <?php if (!empty($caida['archivo'])): ?>
                                    <span class="text-muted">(<?= escapar((string) $caida['archivo']) ?><?= !empty($caida['linea']) ? ':' . (int) $caida['linea'] : '' ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('btn-recarga-auto');
    if (!btn) return;
    var clave = 'rendimiento-recarga';
    var esperaMs = 60000;
    var aviso = null;

    function estaActiva() {
        try {
            return localStorage.getItem(clave) === '1';
        } catch (e) {
            return false;
        }
    }

    function guardar(activa) {
        try {
            localStorage.setItem(clave, activa ? '1' : '0');
        } catch (e) {}
    }

    function pintar() {
        var activa = estaActiva();
        btn.setAttribute('aria-pressed', activa ? 'true' : 'false');
        btn.classList.toggle('btn-primary', activa);
        btn.classList.toggle('btn-outline-primary', !activa);
        btn.innerHTML = activa
            ? '<i class="bi bi-pause-circle me-1"></i> Detener recarga'
            : '<i class="bi bi-arrow-repeat me-1"></i> Activar recarga';
    }

    function programar() {
        if (aviso) {
            clearTimeout(aviso);
            aviso = null;
        }
        if (!estaActiva()) return;
        aviso = setTimeout(function () {
            window.location.reload();
        }, esperaMs);
    }

    btn.addEventListener('click', function () {
        guardar(!estaActiva());
        pintar();
        programar();
    });

    pintar();
    programar();
})();
</script>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
