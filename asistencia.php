<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

if (!asegurar_tabla_asistencias()) {
    mensaje_flash('danger', 'No se pudo preparar la tabla de asistencias. Actualiza las tablas en instalación.');
    redirigir('panel.php');
}

$usuario = usuario_actual();
$esGestor = in_array($usuario['role'], ['admin', 'teacher'], true);

if ($esGestor) {
    $idCurso = (int) ($_GET['curso'] ?? 0);
    if ($idCurso) {
        $curso = obtener_curso($idCurso);
        if ($curso && puede_gestionar_asistencia($curso)) {
            $query = [];
            if (!empty($_GET['fecha'])) {
                $query['fecha'] = (string) $_GET['fecha'];
            }
            if (($_GET['vista'] ?? '') === 'reportes') {
                $query['vista'] = 'reportes';
            }
            redirigir(url_asistencia_curso($idCurso, $query));
        }
    }

    $cursos = cursos_para_asistencia($usuario);
    $tituloPagina = 'Asistencia';
    require_once __DIR__ . '/includes/encabezado.php';
    ?>
    <div class="page-header">
        <div>
            <h1>Asistencia</h1>
            <p class="subtitle">Elige un curso para abrir su página de asistencia.</p>
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
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($cursos as $c): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="panel h-100">
                        <div class="panel-body">
                            <span class="badge text-bg-light border mb-2"><?= escapar($c['code']) ?></span>
                            <h2 class="h5 mb-3"><?= escapar($c['title']) ?></h2>
                            <a class="btn btn-primary w-100" href="<?= escapar(url_asistencia_curso((int) $c['id'])) ?>">
                                <i class="bi bi-calendar-check me-1"></i> Abrir asistencia
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    require_once __DIR__ . '/includes/pie.php';
    exit;
}

$detallePropio = asistencias_estudiante((int) $usuario['id']);
$resumen = resumen_asistencias($detallePropio);
$tituloPagina = 'Asistencia';
require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Asistencia</h1>
        <p class="subtitle">Resumen y listado de todas tus asistencias.</p>
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
                                <a href="<?= escapar(url_asistencia_curso((int) $fila['course_id'])) ?>" class="text-decoration-none">
                                    <strong><?= escapar($fila['course_title']) ?></strong>
                                </a>
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

<?php require_once __DIR__ . '/includes/pie.php'; ?>
