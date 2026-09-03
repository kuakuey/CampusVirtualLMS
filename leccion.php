<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$usuario = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);
$lesson = $id ? obtener_leccion($id) : null;

if (!$lesson) {
    mensaje_flash('danger', 'Lección no encontrada.');
    redirigir('cursos.php');
}

$curso = obtener_curso((int) $lesson['course_id']);
if (!$curso || !puede_acceder_curso($curso)) {
    mensaje_flash('danger', 'No tienes acceso a esta lección.');
    redirigir('cursos.php');
}

$esPropietario = es_propietario_curso($curso, $usuario);
$idCurso = (int) $lesson['course_id'];
$puedeVerSeguimiento = $esPropietario
    || ($usuario['role'] === 'teacher' && es_docente_modulo_curso($idCurso, (int) $usuario['id']));
$mostrarProgreso = $usuario['role'] === 'student' && esta_matriculado($idCurso);
$leccionCompletada = $mostrarProgreso && leccion_esta_completada($id, (int) $usuario['id']);
$progresoCurso = $mostrarProgreso ? porcentaje_progreso_curso($idCurso, (int) $usuario['id']) : 0;
$idsCompletadas = $mostrarProgreso ? obtener_ids_lecciones_completadas($idCurso, (int) $usuario['id']) : [];
$tipoVideo = tipo_video_leccion($lesson['video_url'] ?? null);
$youtubeId = id_video_youtube($lesson['video_url'] ?? null);
$tiempoRequerido = segundos_requeridos_video_leccion();
$videoConSeguimiento = $mostrarProgreso && !$leccionCompletada && in_array($tipoVideo, ['youtube', 'html5'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_POST['token_csrf'] ?? '';
    if (!hash_equals(token_csrf(), $token)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Token de seguridad inválido.']);
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar_tiempo_video' && $mostrarProgreso && !$leccionCompletada) {
        $total = registrar_tiempo_video_leccion($id, (int) ($_POST['segundos'] ?? 0));
        echo json_encode(['ok' => true, 'total' => $total, 'requerido' => $tiempoRequerido]);
        exit;
    }

    if ($accion === 'marcar_leccion_completada' && $mostrarProgreso) {
        if ($leccionCompletada) {
            echo json_encode(['ok' => true]);
            exit;
        }
        if (empty($lesson['video_url']) || !in_array($tipoVideo, ['youtube', 'html5'], true)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Esta lección requiere un video compatible para marcarla como completada.']);
            exit;
        }
        if (obtener_tiempo_video_leccion($id) < $tiempoRequerido) {
            echo json_encode(['ok' => false, 'mensaje' => 'Debes ver el video al menos 10 minutos en esta sesión.']);
            exit;
        }
        marcar_leccion_completada($id, (int) $usuario['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'mensaje' => 'Acción no permitida.']);
    exit;
}

if ($mostrarProgreso) {
    reiniciar_tiempo_video_leccion($id);
}

$siblings = obtener_lecciones_curso($idCurso);
$subcursosSidebar = obtener_subcursos_curso($idCurso);
$mostrarSubcursosSidebar = count($subcursosSidebar) > 1;
$leccionesPorSubcursoSidebar = [];
foreach ($siblings as $sib) {
    $sid = (int) ($sib['subcourse_id'] ?? 0);
    if ($sid > 0) {
        $leccionesPorSubcursoSidebar[$sid][] = $sib;
    }
}

$prev = $next = null;
$idModuloLeccion = (int) ($lesson['subcourse_id'] ?? 0);
$leccionesModuloActual = $mostrarSubcursosSidebar
    ? ($leccionesPorSubcursoSidebar[$idModuloLeccion] ?? [])
    : $siblings;
foreach ($leccionesModuloActual as $i => $sib) {
    if ((int) $sib['id'] === $id) {
        $prev = $leccionesModuloActual[$i - 1] ?? null;
        $next = $leccionesModuloActual[$i + 1] ?? null;
        break;
    }
}
$urlVolverCurso = URL_APP . '/curso.php?id=' . $idCurso . '&pestaña=lecciones'
    . ($mostrarSubcursosSidebar && $idModuloLeccion > 0 ? '&modulo=' . $idModuloLeccion : '');

$datosLeccionActual = datos_leccion_lista($lesson);
$tituloPagina = $datosLeccionActual['titulo'];
if ($datosLeccionActual['fecha'] !== '') {
    $tituloPagina .= ' · ' . $datosLeccionActual['fecha'];
}
require_once __DIR__ . '/includes/encabezado.php';
?>

<button type="button" class="lesson-sidebar-fab d-lg-none" id="btn-abrir-lecciones" aria-label="Ver lecciones del curso">
    <i class="bi bi-list-ul"></i>
    <span>Lecciones</span>
</button>
<div class="lesson-sidebar-backdrop d-lg-none" id="lesson-sidebar-backdrop" aria-hidden="true"></div>

<div class="row g-4 lesson-layout">
    <div class="col-lg-3 lesson-sidebar-col">
        <div class="lesson-sidebar-drawer" id="lesson-sidebar-drawer">
            <div class="lesson-sidebar-drawer-header d-lg-none">
                <span class="fw-semibold">Lecciones</span>
                <button type="button" class="btn btn-sm btn-light border" id="btn-cerrar-lecciones" aria-label="Cerrar menú">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="sidebar-course">
            <div class="panel-header lesson-sidebar-meta d-none d-lg-block">
                <h3 class="mb-0"><?= escapar($lesson['course_title']) ?></h3>
                <?php if ($mostrarProgreso): ?>
                    <div class="mt-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Progreso del curso</span>
                            <strong><?= $progresoCurso ?>%</strong>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: <?= $progresoCurso ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($mostrarSubcursosSidebar): ?>
            <div class="px-2 pt-2 pb-2 border-bottom lesson-materia-field">
                <label class="form-label small fw-semibold mb-1" for="selector-materia-leccion">Materia</label>
                <select id="selector-materia-leccion" class="form-select form-select-sm course-materia-select">
                    <?php foreach ($subcursosSidebar as $subcursoSidebar): ?>
                        <?php
                        $idSub = (int) $subcursoSidebar['id'];
                        $leccionesTab = $leccionesPorSubcursoSidebar[$idSub] ?? [];
                        $urlMateria = $leccionesTab
                            ? URL_APP . '/leccion.php?id=' . (int) $leccionesTab[0]['id']
                            : URL_APP . '/curso.php?id=' . $idCurso . '&pestaña=lecciones&modulo=' . $idSub;
                        ?>
                        <option value="<?= $idSub ?>" data-url="<?= escapar($urlMateria) ?>" <?= $idModuloLeccion === $idSub ? 'selected' : '' ?>>
                            <?= escapar($subcursoSidebar['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="list-group list-group-flush">
                <?php if ($mostrarSubcursosSidebar): ?>
                    <?php if (!$leccionesModuloActual): ?>
                        <div class="list-group-item small text-muted">Sin lecciones en este módulo.</div>
                    <?php else: ?>
                        <?php foreach ($leccionesModuloActual as $i => $sib): ?>
                            <?php
                            $completada = in_array((int) $sib['id'], $idsCompletadas, true);
                            $datosLeccion = datos_leccion_lista($sib);
                            ?>
                            <a href="<?= URL_APP ?>/leccion.php?id=<?= (int) $sib['id'] ?>"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= (int) $sib['id'] === $id ? 'active' : '' ?>">
                                <span class="min-w-0 d-flex align-items-center gap-2 flex-wrap">
                                    <span class="text-muted me-1"><?= $i + 1 ?>.</span>
                                    <span class="text-truncate"><?= escapar($datosLeccion['titulo']) ?></span>
                                    <?php if ($datosLeccion['fecha'] !== ''): ?>
                                        <span class="opacity-75 flex-shrink-0">-</span>
                                        <span class="small opacity-75 flex-shrink-0"><i class="bi bi-calendar-event me-1"></i><?= escapar($datosLeccion['fecha']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($mostrarProgreso): ?>
                                    <?php if ($completada): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-lg"></i></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0%</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php foreach ($siblings as $i => $sib): ?>
                        <?php
                        $completada = in_array((int) $sib['id'], $idsCompletadas, true);
                        $datosLeccion = datos_leccion_lista($sib);
                        ?>
                        <a href="<?= URL_APP ?>/leccion.php?id=<?= (int) $sib['id'] ?>"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= (int) $sib['id'] === $id ? 'active' : '' ?>">
                            <span class="min-w-0 d-flex align-items-center gap-2 flex-wrap">
                                <span class="text-muted me-1"><?= $i + 1 ?>.</span>
                                <span class="text-truncate"><?= escapar($datosLeccion['titulo']) ?></span>
                                <?php if ($datosLeccion['fecha'] !== ''): ?>
                                    <span class="opacity-75 flex-shrink-0">-</span>
                                    <span class="small opacity-75 flex-shrink-0"><i class="bi bi-calendar-event me-1"></i><?= escapar($datosLeccion['fecha']) ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if ($mostrarProgreso): ?>
                                <?php if ($completada): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-lg"></i></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">0%</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            </div>
            <a href="<?= escapar($urlVolverCurso) ?>" class="btn btn-outline-secondary w-100 mt-3 lesson-sidebar-back d-none d-lg-block">
                <i class="bi bi-arrow-left me-1"></i> Volver al curso
            </a>
        </div>
    </div>
    <div class="col-lg-9 col-12 lesson-main-col">
        <div class="panel">
            <?php if ($esPropietario || $puedeVerSeguimiento): ?>
            <div class="panel-header">
                <div>
                    <h2 class="mb-0"><?= escapar($datosLeccionActual['titulo']) ?></h2>
                    <?php if ($datosLeccionActual['fecha'] !== ''): ?>
                        <p class="small text-muted mb-0 mt-1"><i class="bi bi-calendar-event me-1"></i><?= escapar($datosLeccionActual['fecha']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($puedeVerSeguimiento): ?>
                    <a href="<?= escapar(url_asistencia_sesion_curso($idCurso, ['sesion' => $id])) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-camera-video me-1"></i> Asistencia de esta sesión
                    </a>
                    <?php endif; ?>
                    <?php if ($esPropietario): ?>
                    <a href="<?= URL_APP ?>/leccion-formulario.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i> Editar</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="panel-header">
                <div>
                    <h2 class="mb-0"><?= escapar($datosLeccionActual['titulo']) ?></h2>
                    <?php if ($datosLeccionActual['fecha'] !== ''): ?>
                        <p class="small text-muted mb-0 mt-1"><i class="bi bi-calendar-event me-1"></i><?= escapar($datosLeccionActual['fecha']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="panel-body">
                <?php if ($mostrarProgreso && !$leccionCompletada && in_array($tipoVideo, ['youtube', 'html5'], true)): ?>
                    <div id="lesson-progress" class="lesson-progress-box mb-4"
                         data-lesson-id="<?= $id ?>"
                         data-video-type="<?= escapar($tipoVideo) ?>"
                         data-youtube-id="<?= escapar($youtubeId ?? '') ?>"
                         data-requerido="<?= $tiempoRequerido ?>"
                         data-completada="0"
                         data-csrf="<?= escapar(token_csrf()) ?>"
                         data-url="<?= escapar(URL_APP . '/leccion.php?id=' . $id) ?>">
                        <div class="lesson-progress-row row align-items-center g-3 lesson-progress-stack">
                            <div class="col-12 col-md min-w-0">
                                <div class="d-flex align-items-baseline flex-wrap gap-2">
                                    <span class="lesson-progress-label mb-0">Tiempo de video</span>
                                    <span class="lesson-progress-value" id="video-tiempo-texto">0:00 / 10:00</span>
                                </div>
                                <div class="progress lesson-progress-bar mt-2">
                                    <div id="video-tiempo-barra" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-auto lesson-progress-action">
                                <button type="button" id="btn-marcar-completada" class="btn btn-success text-nowrap" disabled>
                                    <i class="bi bi-check2-circle me-1"></i> Marcar como completada
                                </button>
                            </div>
                        </div>
                    </div>
                <?php elseif ($mostrarProgreso && $leccionCompletada): ?>
                    <div id="lesson-progress" class="lesson-progress-box mb-4 lesson-progress-done"
                         data-completada="1"
                         data-video-type="<?= escapar($tipoVideo) ?>">
                        <div class="lesson-progress-row row align-items-center g-3 lesson-progress-stack">
                            <div class="col-12 col-md min-w-0">
                                <div class="d-flex align-items-baseline flex-wrap gap-2">
                                    <span class="lesson-progress-label mb-0">Tiempo de video</span>
                                    <span class="lesson-progress-value text-success"><i class="bi bi-check-circle-fill me-1"></i>Lección completada</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-auto lesson-progress-action">
                                <button type="button" class="btn btn-success text-nowrap" disabled>
                                    <i class="bi bi-check2-circle me-1"></i> Completada
                                </button>
                            </div>
                        </div>
                    </div>
                <?php elseif ($mostrarProgreso && $tipoVideo === 'externo'): ?>
                    <div class="alert alert-warning small mb-4">
                        El video de esta lección no permite seguimiento automático. Usa un enlace de YouTube o un archivo de video directo (MP4) para habilitar el progreso.
                    </div>
                <?php elseif ($mostrarProgreso && $tipoVideo === 'ninguno'): ?>
                    <div class="alert alert-info small mb-4">
                        Esta lección no tiene video. Solo las lecciones con video pueden marcarse como completadas.
                    </div>
                <?php endif; ?>

                <?php if ($lesson['video_url']): ?>
                    <div class="mb-4 rounded overflow-hidden">
                        <?php if ($tipoVideo === 'youtube'): ?>
                            <div class="ratio ratio-16x9 bg-dark">
                                <?php if ($videoConSeguimiento): ?>
                                    <div id="yt-player"></div>
                                <?php else: ?>
                                    <iframe src="<?= escapar('https://www.youtube.com/embed/' . $youtubeId) ?>" allowfullscreen title="Video de la lección"></iframe>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($tipoVideo === 'html5'): ?>
                            <video <?= $videoConSeguimiento ? 'id="lesson-video"' : '' ?> class="w-100 rounded border" controls playsinline preload="metadata">
                                <source src="<?= escapar($lesson['video_url']) ?>">
                                Tu navegador no soporta la reproducción de video.
                            </video>
                        <?php else: ?>
                            <a href="<?= escapar($lesson['video_url']) ?>" target="_blank" class="btn btn-primary"><i class="bi bi-play-btn me-1"></i> Ver video</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($lesson['attachment'])): ?>
                    <?= renderizar_vista_previa_documento($lesson['attachment'], 'Documento de la lección') ?>
                <?php endif; ?>

                <?php if (trim($lesson['content'] ?? '') !== ''): ?>
                <div class="content-html">
                    <?= $lesson['content'] ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-3">
            <?php if ($prev): ?>
                <a href="<?= URL_APP ?>/leccion.php?id=<?= (int) $prev['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-chevron-left"></i> <?= escapar($prev['title']) ?></a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($next): ?>
                <a href="<?= URL_APP ?>/leccion.php?id=<?= (int) $next['id'] ?>" class="btn btn-primary"><?= escapar($next['title']) ?> <i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($videoConSeguimiento): ?>
    <?php if ($tipoVideo === 'youtube'): ?>
    <script src="https://www.youtube.com/iframe_api"></script>
    <?php endif; ?>
    <script src="<?= URL_APP ?>/assets/js/leccion-progreso.js"></script>
<?php endif; ?>
<script src="<?= URL_APP ?>/assets/js/leccion-sidebar.js"></script>
<?php if ($mostrarSubcursosSidebar): ?>
<script src="<?= URL_APP ?>/assets/js/materia-select.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
