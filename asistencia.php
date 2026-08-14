<?php
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/gestor_bd.php';
requiere_sesion();

try {
    bd()->query('SELECT 1 FROM attendances LIMIT 1');
} catch (PDOException $e) {
    $migracion = GestorBd::ejecutarMigracion('009_asistencias.sql');
    if (empty($migracion['exito'])) {
        mensaje_flash('danger', 'No se pudo preparar la tabla de asistencias. Actualiza las tablas en instalación.');
        redirigir('panel.php');
    }
}

$usuario = usuario_actual();
$esGestor = in_array($usuario['role'], ['admin', 'teacher'], true);
$vista = $_GET['vista'] ?? ($esGestor ? 'tomar' : 'reportes');
if (!in_array($vista, ['tomar', 'reportes'], true)) {
    $vista = $esGestor ? 'tomar' : 'reportes';
}
if ($vista === 'tomar' && !$esGestor) {
    $vista = 'reportes';
}

$cursos = cursos_para_asistencia($usuario);
$idCurso = (int) ($_GET['curso'] ?? $_POST['curso'] ?? 0);
$curso = $idCurso ? obtener_curso($idCurso) : null;

if ($curso && $esGestor) {
    if (!puede_gestionar_asistencia($curso)) {
        mensaje_flash('danger', 'No tienes acceso a la asistencia de este curso.');
        redirigir('asistencia.php');
    }
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

    if ($accion === 'guardar_asistencia' && $esGestor && $curso) {
        $fechaGuardar = fecha_asistencia_valida($_POST['fecha'] ?? '');
        $estados = $_POST['estado'] ?? [];
        if (!$fechaGuardar) {
            mensaje_flash('danger', 'La fecha de asistencia no es válida.');
            redirigir('asistencia.php?vista=tomar&curso=' . $idCurso);
        }
        if (!is_array($estados) || !$estados) {
            mensaje_flash('warning', 'No se recibió el listado de estudiantes.');
            redirigir('asistencia.php?vista=tomar&curso=' . $idCurso . '&fecha=' . urlencode($fechaGuardar));
        }
        $guardados = guardar_asistencias($idCurso, $fechaGuardar, $estados, (int) $usuario['id']);
        mensaje_flash('success', 'Asistencia del ' . formatear_fecha($fechaGuardar) . ' guardada (' . $guardados . ' estudiante(s)).');
        redirigir('asistencia.php?vista=tomar&curso=' . $idCurso . '&fecha=' . urlencode($fechaGuardar));
    }

    mensaje_flash('danger', 'No se pudo procesar la acción.');
    redirigir('asistencia.php');
}

$estudiantes = [];
$asistenciasFecha = [];
$yaRegistrada = false;
if ($vista === 'tomar' && $curso) {
    $estudiantes = estudiantes_activos_curso($idCurso);
    $asistenciasFecha = asistencias_por_fecha($idCurso, $fecha);
    $yaRegistrada = count($asistenciasFecha) > 0;
}

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
if (!$esGestor) {
    $detallePropio = asistencias_estudiante((int) $usuario['id']);
    $resumen = resumen_asistencias($detallePropio);
} elseif ($vista === 'reportes' && $curso) {
    $reporteEstudiantes = reporte_asistencia_estudiantes($idCurso, $desde, $hasta);
    $reporteFechas = reporte_asistencia_fechas($idCurso, $desde, $hasta);
    $resumen['sesiones'] = count($reporteFechas);
    foreach ($reporteFechas as $fila) {
        $resumen['presentes'] += (int) $fila['presentes'];
        $resumen['ausentes'] += (int) $fila['ausentes'];
        $resumen['tardes'] += (int) $fila['tardes'];
        $resumen['justificados'] += (int) $fila['justificados'];
    }
    $totalMarcas = $resumen['presentes'] + $resumen['ausentes'] + $resumen['tardes'] + $resumen['justificados'];
    $resumen['promedio'] = porcentaje_asistencia(
        $resumen['presentes'],
        $resumen['tardes'],
        $resumen['justificados'],
        $totalMarcas
    );
}

$tituloPagina = 'Asistencia';
require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Asistencia</h1>
        <p class="subtitle">
            <?= $esGestor ? 'Toma lista por fecha y consulta los reportes del curso.' : 'Resumen y listado de todas tus asistencias.' ?>
        </p>
    </div>
</div>

<?php if ($esGestor): ?>
<ul class="nav nav-pills gap-2 mb-4 flex-wrap">
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'tomar' ? 'active' : '' ?>" href="<?= URL_ASISTENCIA ?>?vista=tomar<?= $idCurso ? '&curso=' . $idCurso : '' ?><?= $fecha ? '&fecha=' . urlencode($fecha) : '' ?>">
            <i class="bi bi-calendar-check me-1"></i> Tomar asistencia
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'reportes' ? 'active' : '' ?>" href="<?= URL_ASISTENCIA ?>?vista=reportes<?= $idCurso ? '&curso=' . $idCurso : '' ?>">
            <i class="bi bi-bar-chart me-1"></i> Reportes
        </a>
    </li>
</ul>
<?php endif; ?>

<?php if (!$esGestor): ?>
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
    <div class="panel-header">
        <h2>Todas tus asistencias</h2>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Curso</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$detallePropio): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Aún no hay asistencias registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($detallePropio as $fila): ?>
                        <tr>
                            <td><?= formatear_fecha($fila['attendance_date']) ?></td>
                            <td>
                                <strong><?= escapar($fila['course_title']) ?></strong>
                                <div class="small text-muted"><?= escapar($fila['course_code']) ?></div>
                            </td>
                            <td><?= insignia_asistencia($fila['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($vista === 'tomar'): ?>
<div class="panel mb-4">
    <div class="panel-header"><h2>Generar listado</h2></div>
    <div class="panel-body">
        <form class="row g-3 align-items-end" method="get">
            <input type="hidden" name="vista" value="tomar">
            <div class="col-md-6">
                <label class="form-label" for="curso">Curso</label>
                <select name="curso" id="curso" class="form-select" required>
                    <option value="">Selecciona un curso</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $idCurso === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= escapar($c['title']) ?> (<?= escapar($c['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="fecha">Fecha</label>
                <input type="date" name="fecha" id="fecha" class="form-control" value="<?= escapar($fecha) ?>" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-people me-1"></i> Generar listado
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$cursos): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <i class="bi bi-journal-x"></i>
                <p class="mb-0">No hay cursos disponibles para tomar asistencia.</p>
            </div>
        </div>
    </div>
<?php elseif ($curso): ?>
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
        <form method="post" id="form-asistencia">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="guardar_asistencia">
            <input type="hidden" name="curso" value="<?= $idCurso ?>">
            <input type="hidden" name="fecha" value="<?= escapar($fecha) ?>">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="mb-1">Lista del <?= formatear_fecha($fecha) ?></h2>
                        <p class="small text-muted mb-0">
                            <?= escapar($curso['title']) ?> · <?= count($estudiantes) ?> estudiante(s)
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
<?php endif; ?>

<?php else: ?>
<div class="panel mb-4">
    <div class="panel-header"><h2>Filtros del reporte</h2></div>
    <div class="panel-body">
        <form class="row g-3 align-items-end" method="get">
            <input type="hidden" name="vista" value="reportes">
            <div class="col-md-4">
                <label class="form-label" for="curso-reporte">Curso</label>
                <select name="curso" id="curso-reporte" class="form-select" required>
                    <option value="">Selecciona un curso</option>
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $idCurso === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= escapar($c['title']) ?> (<?= escapar($c['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="desde">Desde</label>
                <input type="date" name="desde" id="desde" class="form-control" value="<?= escapar($desde) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="hasta">Hasta</label>
                <input type="date" name="hasta" id="hasta" class="form-control" value="<?= escapar($hasta) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-search me-1"></i> Ver reporte
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$cursos): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <i class="bi bi-journal-x"></i>
                <p class="mb-0">No hay cursos para consultar.</p>
            </div>
        </div>
    </div>
<?php elseif ($curso): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-navy"><i class="bi bi-calendar3"></i></div>
                <div class="stat-value"><?= (int) $resumen['sesiones'] ?></div>
                <div class="stat-label">Sesiones</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-teal"><i class="bi bi-percent"></i></div>
                <div class="stat-value"><?= (int) $resumen['promedio'] ?>%</div>
                <div class="stat-label">Asistencia</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-teal"><i class="bi bi-person-check"></i></div>
                <div class="stat-value"><?= (int) $resumen['presentes'] ?></div>
                <div class="stat-label">Presentes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon icon-rose"><i class="bi bi-person-x"></i></div>
                <div class="stat-value"><?= (int) $resumen['ausentes'] ?></div>
                <div class="stat-label">Ausentes</div>
            </div>
        </div>
    </div>

    <div class="panel mb-4">
            <div class="panel-header">
                <h2>Por estudiante · <?= escapar($curso['title']) ?></h2>
            </div>
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
                                        <a class="btn btn-sm btn-outline-primary" href="<?= URL_ASISTENCIA ?>?vista=tomar&curso=<?= $idCurso ?>&fecha=<?= urlencode($fila['attendance_date']) ?>">
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
