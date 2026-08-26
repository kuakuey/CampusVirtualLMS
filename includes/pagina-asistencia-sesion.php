<?php
$idModuloReporte = (int) ($_GET['modulo'] ?? 0);
if ($idModuloReporte > 0 && !obtener_subcurso($idModuloReporte, $id)) {
    $idModuloReporte = 0;
}
$idSesion = (int) ($_GET['sesion'] ?? 0);
$reporteSesion = reporte_asistencia_sesiones_curso($id, $idModuloReporte > 0 ? $idModuloReporte : null);
$leccionesReporte = $reporteSesion['lecciones'];
$estudiantesReporte = $reporteSesion['estudiantes'];
$celdasReporte = $reporteSesion['celdas'];
$porSesion = $reporteSesion['por_sesion'];
$totalEstudiantesReporte = $reporteSesion['total_estudiantes'];
$totalSesionesReporte = $reporteSesion['total_sesiones'];
$requeridoReporte = $reporteSesion['requerido'];

$leccionDetalle = null;
foreach ($leccionesReporte as $leccionRep) {
    if ((int) $leccionRep['id'] === $idSesion) {
        $leccionDetalle = $leccionRep;
        break;
    }
}
if (!$leccionDetalle) {
    $idSesion = 0;
}

$queryBase = [];
if ($idModuloReporte > 0) {
    $queryBase['modulo'] = $idModuloReporte;
}
?>

<div class="page-header mb-4">
    <div>
        <h2 class="h4 mb-1">Asistencia por sesión</h2>
        <p class="subtitle mb-0">Cada lección cuenta como una sesión. El estudiante asiste si ve 10 minutos de video o marca la lección como completada.</p>
    </div>
</div>

<?php if ($mostrarSubcursos): ?>
<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2 align-items-end" method="get" action="<?= URL_APP ?>/curso.php">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="pestaña" value="asistencia-sesion">
            <div class="col-md-8">
                <label class="form-label" for="modulo-asistencia-sesion">Módulo</label>
                <select name="modulo" id="modulo-asistencia-sesion" class="form-select">
                    <option value="0">Todos los módulos</option>
                    <?php foreach ($subcursos as $subcurso): ?>
                        <option value="<?= (int) $subcurso['id'] ?>" <?= $idModuloReporte === (int) $subcurso['id'] ? 'selected' : '' ?>>
                            <?= escapar($subcurso['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" type="submit">Filtrar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-camera-video"></i></div>
            <div class="stat-value"><?= $totalSesionesReporte ?></div>
            <div class="stat-label">Sesiones</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= $totalEstudiantesReporte ?></div>
            <div class="stat-label">Estudiantes</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-person-check"></i></div>
            <div class="stat-value"><?= (int) $reporteSesion['asistencias'] ?></div>
            <div class="stat-label">Asistencias registradas</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon icon-rose"><i class="bi bi-percent"></i></div>
            <div class="stat-value"><?= (int) $reporteSesion['porcentaje'] ?>%</div>
            <div class="stat-label">Asistencia promedio</div>
        </div>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-header">
        <h2>Resumen por sesión</h2>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Sesión</th>
                        <?php if ($mostrarSubcursos && $idModuloReporte === 0): ?>
                            <th>Módulo</th>
                        <?php endif; ?>
                        <th class="text-center">Asistieron</th>
                        <th class="text-center">10 minutos</th>
                        <th class="text-center">Completaron</th>
                        <th class="text-center">%</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$leccionesReporte): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay lecciones para mostrar.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($leccionesReporte as $i => $leccionRep): ?>
                        <?php
                        $idLeccionRep = (int) $leccionRep['id'];
                        $conteo = $porSesion[$idLeccionRep] ?? ['asistieron' => 0, 'completaron' => 0, 'diez_min' => 0];
                        $pctSesion = $totalEstudiantesReporte > 0
                            ? (int) round(($conteo['asistieron'] / $totalEstudiantesReporte) * 100)
                            : 0;
                        ?>
                        <tr class="<?= $idSesion === $idLeccionRep ? 'table-active' : '' ?>">
                            <td>
                                <span class="text-muted me-1"><?= $i + 1 ?>.</span>
                                <?= escapar($leccionRep['title']) ?>
                            </td>
                            <?php if ($mostrarSubcursos && $idModuloReporte === 0): ?>
                                <td class="small text-muted"><?= escapar($leccionRep['subcourse_title'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td class="text-center"><?= (int) $conteo['asistieron'] ?> / <?= $totalEstudiantesReporte ?></td>
                            <td class="text-center"><?= (int) $conteo['diez_min'] ?></td>
                            <td class="text-center"><?= (int) $conteo['completaron'] ?></td>
                            <td class="text-center"><?= $pctSesion ?>%</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= escapar(url_asistencia_sesion_curso($id, $queryBase + ['sesion' => $idLeccionRep])) ?>">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($leccionDetalle): ?>
    <?php $idLeccionDetalle = (int) $leccionDetalle['id']; ?>
    <div class="panel mb-4">
        <div class="panel-header">
            <div>
                <h2 class="mb-1"><?= escapar($leccionDetalle['title']) ?></h2>
                <p class="small text-muted mb-0">Detalle de asistencia de esta sesión.</p>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="<?= escapar(url_asistencia_sesion_curso($id, $queryBase)) ?>">Cerrar detalle</a>
        </div>
        <div class="panel-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Estudiante</th>
                            <th>Asistió</th>
                            <th>10 minutos</th>
                            <th>Completada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$estudiantesReporte): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No hay estudiantes inscritos.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($estudiantesReporte as $estRep): ?>
                            <?php
                            $celda = $celdasReporte[(int) $estRep['id']][$idLeccionDetalle] ?? [
                                'asistio' => false,
                                'diez_min' => false,
                                'completada' => false,
                                'seconds_watched' => 0,
                                'reached_required_at' => null,
                                'completed_at' => null,
                            ];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= escapar($estRep['name']) ?></strong>
                                    <div class="small text-muted"><?= escapar($estRep['email']) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($celda['asistio'])): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($celda['diez_min'])): ?>
                                        <span class="badge bg-success">Sí</span>
                                        <?php if (!empty($celda['reached_required_at'])): ?>
                                            <div class="small text-muted mt-1"><?= formatear_fecha($celda['reached_required_at'], true) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ((int) $celda['seconds_watched'] > 0): ?>
                                        <span class="text-muted"><?= formatear_duracion_segundos((int) $celda['seconds_watched']) ?> / <?= formatear_duracion_segundos($requeridoReporte) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($celda['completada'])): ?>
                                        <span class="badge bg-success">Sí</span>
                                        <div class="small text-muted mt-1"><?= formatear_fecha($celda['completed_at'], true) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <div>
            <h2 class="mb-1">Reporte por estudiante</h2>
            <p class="small text-muted mb-0">
                <span class="badge bg-success me-1">C</span> Completó
                <span class="badge bg-info text-dark me-1">10</span> Llegó a 10 minutos
                <span class="text-muted">— no asistió</span>
            </p>
        </div>
    </div>
    <div class="panel-body p-0">
        <?php if (!$estudiantesReporte || !$leccionesReporte): ?>
            <div class="empty-state py-4">
                <i class="bi bi-clipboard-data"></i>
                <p class="mb-0">No hay datos suficientes para armar el reporte.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 session-matrix">
                    <thead class="table-light">
                        <tr>
                            <th class="sticky-col">Estudiante</th>
                            <?php foreach ($leccionesReporte as $i => $leccionRep): ?>
                                <th class="session-col text-center" title="<?= escapar($leccionRep['title']) ?>">
                                    <a class="text-decoration-none" href="<?= escapar(url_asistencia_sesion_curso($id, $queryBase + ['sesion' => (int) $leccionRep['id']])) ?>">
                                        <?= $i + 1 ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantesReporte as $estRep): ?>
                            <tr>
                                <td class="sticky-col">
                                    <strong><?= escapar($estRep['name']) ?></strong>
                                    <div class="small text-muted"><?= escapar($estRep['email']) ?></div>
                                </td>
                                <?php foreach ($leccionesReporte as $leccionRep): ?>
                                    <?php $celda = $celdasReporte[(int) $estRep['id']][(int) $leccionRep['id']] ?? ['asistio' => false, 'completada' => false, 'diez_min' => false]; ?>
                                    <td class="text-center">
                                        <?php if (!empty($celda['completada'])): ?>
                                            <span class="badge bg-success" title="Completó la lección">C</span>
                                        <?php elseif (!empty($celda['diez_min'])): ?>
                                            <span class="badge bg-info text-dark" title="Llegó a 10 minutos">10</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-center fw-semibold">
                                    <?= (int) ($estRep['sesiones_asistidas'] ?? 0) ?> / <?= $totalSesionesReporte ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
