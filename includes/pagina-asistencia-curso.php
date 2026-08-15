<?php
if (!asegurar_tabla_asistencias()) {
    mensaje_flash('danger', 'No se pudo preparar la tabla de asistencias. Actualiza las tablas en instalación.');
    redirigir('curso.php?id=' . $id);
}

$vista = $_GET['vista'] ?? ($esPropietario ? 'tomar' : 'resumen');
if ($esPropietario && !in_array($vista, ['tomar', 'reportes'], true)) {
    $vista = 'tomar';
}
if (!$esPropietario) {
    $vista = 'resumen';
}

$fecha = fecha_asistencia_valida($_GET['fecha'] ?? $_POST['fecha'] ?? '') ?: date('Y-m-d');
$desde = fecha_asistencia_valida($_GET['desde'] ?? '') ?: date('Y-m-01');
$hasta = fecha_asistencia_valida($_GET['hasta'] ?? '') ?: date('Y-m-d');
if ($desde > $hasta) {
    $tmp = $desde;
    $desde = $hasta;
    $hasta = $tmp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_asistencia' && $esPropietario) {
        $fechaGuardar = fecha_asistencia_valida($_POST['fecha'] ?? '');
        $estados = $_POST['estado'] ?? [];
        if (!$fechaGuardar) {
            mensaje_flash('danger', 'La fecha de asistencia no es válida.');
            redirigir(url_asistencia_curso($id));
        }
        if (!is_array($estados) || !$estados) {
            mensaje_flash('warning', 'No se recibió el listado de estudiantes.');
            redirigir(url_asistencia_curso($id, ['fecha' => $fechaGuardar]));
        }
        $guardados = guardar_asistencias($id, $fechaGuardar, $estados, (int) $usuario['id']);
        mensaje_flash('success', 'Asistencia del ' . formatear_fecha($fechaGuardar) . ' guardada (' . $guardados . ' estudiante(s)).');
        redirigir(url_asistencia_curso($id, ['fecha' => $fechaGuardar]));
    }

    mensaje_flash('danger', 'No se pudo procesar la acción.');
    redirigir(url_asistencia_curso($id));
}

$estudiantes = [];
$asistenciasFecha = [];
$yaRegistrada = false;
$reporteEstudiantes = [];
$reporteFechas = [];
$detallePropio = [];
$resumen = [
    'sesiones' => 0,
    'presentes' => 0,
    'ausentes' => 0,
    'tardes' => 0,
    'justificados' => 0,
    'promedio' => 0,
];

if ($esPropietario) {
    $estudiantes = estudiantes_activos_curso($id);
    $asistenciasFecha = asistencias_por_fecha($id, $fecha);
    $yaRegistrada = count($asistenciasFecha) > 0;
    $reporteEstudiantes = reporte_asistencia_estudiantes($id, $desde, $hasta);
    $reporteFechas = reporte_asistencia_fechas($id, $desde, $hasta);
    $resumen = resumen_desde_fechas($reporteFechas);
} else {
    $detallePropio = asistencias_estudiante_curso((int) $usuario['id'], $id);
    $resumen = resumen_asistencias($detallePropio);
}

$tituloPagina = 'Asistencia · ' . $curso['title'];
require_once __DIR__ . '/encabezado.php';
?>

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <span class="badge text-bg-light border"><?= escapar($curso['code']) ?></span>
            <?php if (!empty($curso['category_name'])): ?>
                <span class="badge bg-secondary"><?= escapar($curso['category_name']) ?></span>
            <?php endif; ?>
        </div>
        <h1>Asistencia</h1>
        <p class="subtitle mb-0"><?= escapar($curso['title']) ?></p>
    </div>
    <a href="<?= URL_CURSO ?>?id=<?= $id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver al curso
    </a>
</div>

<?php if ($esPropietario): ?>
<ul class="nav nav-pills gap-2 mb-4 flex-wrap">
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'tomar' ? 'active' : '' ?>" href="<?= escapar(url_asistencia_curso($id, ['vista' => 'tomar', 'fecha' => $fecha])) ?>">
            <i class="bi bi-calendar-check me-1"></i> Tomar asistencia
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'reportes' ? 'active' : '' ?>" href="<?= escapar(url_asistencia_curso($id, ['vista' => 'reportes'])) ?>">
            <i class="bi bi-bar-chart me-1"></i> Reportes
        </a>
    </li>
</ul>

<?php if ($vista !== 'reportes'): ?>
<div class="panel mb-4">
    <div class="panel-header"><h2>Generar listado</h2></div>
    <div class="panel-body">
        <form class="row g-3 align-items-end" method="get" action="<?= URL_APP ?>/curso.php">
            <input type="hidden" name="id" value="<?= (int) $id ?>/asistencia">
            <input type="hidden" name="vista" value="tomar">
            <div class="col-md-6">
                <label class="form-label" for="fecha-asistencia">Fecha</label>
                <input type="date" name="fecha" id="fecha-asistencia" class="form-control" value="<?= escapar($fecha) ?>" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-people me-1"></i> Generar listado
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$estudiantes): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p class="mb-0">Este curso no tiene estudiantes inscritos activos.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <form method="post" id="form-asistencia" action="<?= escapar(url_asistencia_curso($id)) ?>">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar_asistencia">
        <input type="hidden" name="id" value="<?= (int) $id ?>/asistencia">
        <input type="hidden" name="fecha" value="<?= escapar($fecha) ?>">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="mb-1">Lista del <?= formatear_fecha($fecha) ?></h2>
                    <p class="small text-muted mb-0">
                        <?= count($estudiantes) ?> estudiante(s)
                        <?php if ($yaRegistrada): ?>
                            · <span class="text-success">Ya hay un registro; puedes actualizarlo.</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-success" data-marcar="present">Todos presentes</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-marcar="absent">Todos ausentes</button>
                </div>
            </div>
            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 attendance-table">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $st): ?>
                                <?php
                                $sid = (int) $st['id'];
                                $estadoActual = $asistenciasFecha[$sid] ?? 'present';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?= renderizar_avatar_usuario($st, 36) ?>
                                            <div>
                                                <strong><?= escapar($st['name']) ?></strong>
                                                <div class="small text-muted"><?= escapar($st['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group attendance-status-group flex-wrap" role="group">
                                            <?php foreach (['present' => 'Presente', 'absent' => 'Ausente', 'late' => 'Tarde', 'excused' => 'Justificado'] as $valor => $etiqueta): ?>
                                                <input type="radio"
                                                       class="btn-check"
                                                       name="estado[<?= $sid ?>]"
                                                       id="est-<?= $sid ?>-<?= $valor ?>"
                                                       value="<?= $valor ?>"
                                                       <?= $estadoActual === $valor ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-<?= $valor === 'present' ? 'success' : ($valor === 'absent' ? 'danger' : ($valor === 'late' ? 'warning' : 'info')) ?>" for="est-<?= $sid ?>-<?= $valor ?>">
                                                    <?= $etiqueta ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel-body border-top d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-save me-1"></i> Guardar asistencia
                </button>
            </div>
        </div>
    </form>
    <script>
    document.querySelectorAll('[data-marcar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var estado = boton.getAttribute('data-marcar');
            document.querySelectorAll('#form-asistencia input[type="radio"][value="' + estado + '"]').forEach(function (radio) {
                radio.checked = true;
            });
        });
    });
    </script>
<?php endif; ?>

<?php else: ?>
<div class="panel mb-4">
    <div class="panel-header"><h2>Filtros del reporte</h2></div>
    <div class="panel-body">
        <form class="row g-3 align-items-end" method="get" action="<?= URL_APP ?>/curso.php">
            <input type="hidden" name="id" value="<?= (int) $id ?>/asistencia">
            <input type="hidden" name="vista" value="reportes">
            <div class="col-md-4">
                <label class="form-label" for="desde-asistencia">Desde</label>
                <input type="date" name="desde" id="desde-asistencia" class="form-control" value="<?= escapar($desde) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="hasta-asistencia">Hasta</label>
                <input type="date" name="hasta" id="hasta-asistencia" class="form-control" value="<?= escapar($hasta) ?>">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-search me-1"></i> Ver reporte
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-calendar3"></i></div>
            <div class="stat-value"><?= (int) $resumen['sesiones'] ?></div>
            <div class="stat-label">Sesiones</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-percent"></i></div>
            <div class="stat-value"><?= (int) $resumen['promedio'] ?>%</div>
            <div class="stat-label">Asistencia</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-person-check"></i></div>
            <div class="stat-value"><?= (int) $resumen['presentes'] ?></div>
            <div class="stat-label">Presentes</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-rose"><i class="bi bi-person-x"></i></div>
            <div class="stat-value"><?= (int) $resumen['ausentes'] ?></div>
            <div class="stat-label">Ausentes</div>
        </div>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-header"><h2>Por estudiante</h2></div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Estudiante</th>
                        <th class="text-center">Presente</th>
                        <th class="text-center">Ausente</th>
                        <th class="text-center">Tarde</th>
                        <th class="text-center">Justificado</th>
                        <th class="text-center">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$reporteEstudiantes): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Sin estudiantes inscritos.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reporteEstudiantes as $st): ?>
                        <?php
                        $total = (int) $st['total'];
                        $pct = porcentaje_asistencia((int) $st['presentes'], (int) $st['tardes'], (int) $st['justificados'], $total);
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?= renderizar_avatar_usuario($st, 32) ?>
                                    <div>
                                        <strong><?= escapar($st['name']) ?></strong>
                                        <div class="small text-muted"><?= escapar($st['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center"><?= (int) $st['presentes'] ?></td>
                            <td class="text-center"><?= (int) $st['ausentes'] ?></td>
                            <td class="text-center"><?= (int) $st['tardes'] ?></td>
                            <td class="text-center"><?= (int) $st['justificados'] ?></td>
                            <td class="text-center">
                                <span class="badge <?= $pct >= 80 ? 'bg-success' : ($pct >= 60 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                    <?= $pct ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Por fecha</h2></div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th class="text-center">Presente</th>
                        <th class="text-center">Ausente</th>
                        <th class="text-center">Tarde</th>
                        <th class="text-center">Justificado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$reporteFechas): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay asistencias registradas en este período.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reporteFechas as $fila): ?>
                        <tr>
                            <td><?= formatear_fecha($fila['attendance_date']) ?></td>
                            <td class="text-center"><?= (int) $fila['presentes'] ?></td>
                            <td class="text-center"><?= (int) $fila['ausentes'] ?></td>
                            <td class="text-center"><?= (int) $fila['tardes'] ?></td>
                            <td class="text-center"><?= (int) $fila['justificados'] ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= escapar(url_asistencia_curso($id, ['vista' => 'tomar', 'fecha' => $fila['attendance_date']])) ?>">
                                    Ver lista
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-calendar3"></i></div>
            <div class="stat-value"><?= (int) $resumen['sesiones'] ?></div>
            <div class="stat-label">Sesiones</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-percent"></i></div>
            <div class="stat-value"><?= (int) $resumen['promedio'] ?>%</div>
            <div class="stat-label">Porcentaje de asistencia</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-person-check"></i></div>
            <div class="stat-value"><?= (int) $resumen['presentes'] ?></div>
            <div class="stat-label">Presentes</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-rose"><i class="bi bi-person-x"></i></div>
            <div class="stat-value"><?= (int) $resumen['ausentes'] ?></div>
            <div class="stat-label">Ausentes</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-clock-history"></i></div>
            <div class="stat-value"><?= (int) $resumen['tardes'] ?></div>
            <div class="stat-label">Tarde</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-info-circle"></i></div>
            <div class="stat-value"><?= (int) $resumen['justificados'] ?></div>
            <div class="stat-label">Justificados</div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Tus asistencias en este curso</h2></div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$detallePropio): ?>
                        <tr><td colspan="2" class="text-center text-muted py-4">Aún no hay asistencias registradas en este curso.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($detallePropio as $fila): ?>
                        <tr>
                            <td><?= formatear_fecha($fila['attendance_date']) ?></td>
                            <td><?= insignia_asistencia($fila['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/pie.php'; ?>
