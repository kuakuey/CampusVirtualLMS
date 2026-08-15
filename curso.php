<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$usuario = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);
$curso = obtener_curso($id);

if (!$curso) {
    mensaje_flash('danger', 'Curso no encontrado.');
    redirigir('cursos.php');
}

$esPropietario = $usuario['role'] === 'admin' || ($usuario['role'] === 'teacher' && (int) $curso['teacher_id'] === (int) $usuario['id']);
$matriculado = esta_matriculado($id);

if (!$esPropietario && !$matriculado && $usuario['role'] !== 'admin') {
    if ($curso['status'] === 'published' && $usuario['role'] === 'student') {
        mensaje_flash('warning', 'Debes inscribirte para acceder al curso.');
        redirigir('catalogo.php');
    }
    mensaje_flash('danger', 'No tienes acceso a este curso.');
    redirigir('cursos.php');
}

$pestaña = $_GET['pestaña'] ?? 'lecciones';
if ($pestaña === 'asistencia') {
    redirigir('curso-asistencia.php?id=' . $id);
}
$pestañasPermitidas = ['lecciones', 'tareas', 'foro', 'estudiantes'];
if (!in_array($pestaña, $pestañasPermitidas, true)) {
    $pestaña = 'lecciones';
}
if ($pestaña === 'estudiantes' && !$esPropietario) {
    $pestaña = 'lecciones';
}

// --- Acciones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'reordenar_lecciones' && $esPropietario) {
        header('Content-Type: application/json; charset=utf-8');
        $token = $_POST['token_csrf'] ?? '';
        if (!hash_equals(token_csrf(), $token)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Token de seguridad inválido.']);
            exit;
        }
        $idSubcurso = (int) ($_POST['subcourse_id'] ?? 0);
        $orden = json_decode($_POST['orden'] ?? '[]', true);
        if (!is_array($orden)) {
            $orden = [];
        }
        $exito = $idSubcurso > 0 && reordenar_lecciones_curso($orden, $id, $idSubcurso);
        echo json_encode($exito
            ? ['ok' => true]
            : ['ok' => false, 'mensaje' => 'No se pudo guardar el orden.']);
        exit;
    }

    if ($accion === 'agregar_leccion' && $esPropietario) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $video = trim($_POST['video_url'] ?? '');
        $idSubcurso = (int) ($_POST['subcourse_id'] ?? 0);
        if ($title !== '') {
            if ($idSubcurso <= 0 || !obtener_subcurso($idSubcurso, $id)) {
                $idSubcurso = asegurar_subcurso_default($id);
            }
            $order = obtener_siguiente_orden_leccion($idSubcurso);
            $adjunto = !empty($_FILES['documento']['name']) ? subir_archivo($_FILES['documento'], 'lecciones') : null;
            $stmt = bd()->prepare('INSERT INTO lessons (course_id, subcourse_id, title, content, video_url, sort_order, attachment) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$id, $idSubcurso, $title, $content, $video ?: null, $order, $adjunto]);
            mensaje_flash('success', 'Lección creada.');
        }
        $redirectModulo = $idSubcurso > 0 ? "&modulo=$idSubcurso" : '';
        redirigir("curso.php?id=$id&pestaña=lecciones$redirectModulo");
    }

    if ($accion === 'agregar_subcurso' && $esPropietario) {
        $title = trim($_POST['title'] ?? '');
        $order = (int) ($_POST['sort_order'] ?? 0);
        if ($title !== '') {
            if ($order <= 0) {
                $order = contar_subcursos_curso($id) + 1;
            }
            $stmt = bd()->prepare('INSERT INTO subcourses (course_id, title, sort_order) VALUES (?,?,?)');
            $stmt->execute([$id, $title, $order]);
            $idNuevoModulo = (int) bd()->lastInsertId();
            mensaje_flash('success', 'Subcurso creado.');
            redirigir("curso.php?id=$id&pestaña=lecciones&modulo=$idNuevoModulo");
        }
        redirigir("curso.php?id=$id&pestaña=lecciones");
    }

    if ($accion === 'editar_subcurso' && $esPropietario) {
        $idSubcurso = (int) ($_POST['subcourse_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $order = (int) ($_POST['sort_order'] ?? 0);
        if ($title !== '' && obtener_subcurso($idSubcurso, $id)) {
            $stmt = bd()->prepare('UPDATE subcourses SET title=?, sort_order=? WHERE id=? AND course_id=?');
            $stmt->execute([$title, $order, $idSubcurso, $id]);
            mensaje_flash('success', 'Subcurso actualizado.');
        }
        redirigir("curso.php?id=$id&pestaña=lecciones&modulo=$idSubcurso");
    }

    if ($accion === 'eliminar_subcurso' && $esPropietario) {
        $idSubcurso = (int) ($_POST['subcourse_id'] ?? 0);
        if (obtener_subcurso($idSubcurso, $id)) {
            $consulta = bd()->prepare('SELECT attachment FROM lessons WHERE subcourse_id = ? AND attachment IS NOT NULL AND attachment != ""');
            $consulta->execute([$idSubcurso]);
            foreach ($consulta->fetchAll() as $fila) {
                eliminar_archivo_subida($fila['attachment'] ?? null);
            }
            $stmt = bd()->prepare('DELETE FROM subcourses WHERE id = ? AND course_id = ?');
            $stmt->execute([$idSubcurso, $id]);
            mensaje_flash('success', 'Subcurso eliminado.');
        }
        redirigir("curso.php?id=$id&pestaña=lecciones");
    }

    if ($accion === 'eliminar_leccion' && $esPropietario) {
        $lessonId = (int) ($_POST['lesson_id'] ?? 0);
        $consulta = bd()->prepare('SELECT attachment FROM lessons WHERE id = ? AND course_id = ?');
        $consulta->execute([$lessonId, $id]);
        if ($fila = $consulta->fetch()) {
            eliminar_archivo_subida($fila['attachment'] ?? null);
        }
        $stmt = bd()->prepare('DELETE FROM lessons WHERE id = ? AND course_id = ?');
        $stmt->execute([$lessonId, $id]);
        mensaje_flash('success', 'Lección eliminada.');
        redirigir("curso.php?id=$id&pestaña=lecciones");
    }

    if ($accion === 'agregar_tarea' && $esPropietario) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due = $_POST['due_date'] ?? null;
        $max = (float) ($_POST['max_score'] ?? 100);
        if ($title !== '') {
            $due = $due ? date('Y-m-d H:i:s', strtotime($due)) : null;
            $stmt = bd()->prepare('INSERT INTO assignments (course_id, title, description, due_date, max_score) VALUES (?,?,?,?,?)');
            $stmt->execute([$id, $title, $description, $due, $max]);
            mensaje_flash('success', 'Tarea creada.');
        }
        redirigir("curso.php?id=$id&pestaña=tareas");
    }

    if ($accion === 'eliminar_tarea' && $esPropietario) {
        $aid = (int) ($_POST['assignment_id'] ?? 0);
        $stmt = bd()->prepare('DELETE FROM assignments WHERE id = ? AND course_id = ?');
        $stmt->execute([$aid, $id]);
        mensaje_flash('success', 'Tarea eliminada.');
        redirigir("curso.php?id=$id&pestaña=tareas");
    }

    if ($accion === 'entregar_tarea' && $matriculado) {
        $aid = (int) ($_POST['assignment_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $check = bd()->prepare('SELECT id FROM assignments WHERE id = ? AND course_id = ?');
        $check->execute([$aid, $id]);
        if ($check->fetch()) {
            $filePath = null;
            if (!empty($_FILES['file']['name'])) {
                $filePath = subir_archivo($_FILES['file'], 'entregas');
            }
            $exists = bd()->prepare('SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?');
            $exists->execute([$aid, $usuario['id']]);
            if ($row = $exists->fetch()) {
                $stmt = bd()->prepare('UPDATE submissions SET content=?, file_path=COALESCE(?, file_path), submitted_at=NOW() WHERE id=?');
                $stmt->execute([$content, $filePath, $row['id']]);
            } else {
                $stmt = bd()->prepare('INSERT INTO submissions (assignment_id, student_id, content, file_path) VALUES (?,?,?,?)');
                $stmt->execute([$aid, $usuario['id'], $content, $filePath]);
            }
            mensaje_flash('success', 'Entrega enviada.');
        }
        redirigir("curso.php?id=$id&pestaña=tareas");
    }

    if ($accion === 'calificar_entrega' && $esPropietario) {
        $sid = (int) ($_POST['submission_id'] ?? 0);
        $score = (float) ($_POST['score'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        $check = bd()->prepare(
            'SELECT s.id FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.id = ? AND a.course_id = ?'
        );
        $check->execute([$sid, $id]);
        if ($check->fetch()) {
            $exists = bd()->prepare('SELECT id FROM grades WHERE submission_id = ?');
            $exists->execute([$sid]);
            if ($g = $exists->fetch()) {
                $stmt = bd()->prepare('UPDATE grades SET score=?, feedback=?, graded_by=?, graded_at=NOW() WHERE id=?');
                $stmt->execute([$score, $feedback, $usuario['id'], $g['id']]);
            } else {
                $stmt = bd()->prepare('INSERT INTO grades (submission_id, score, feedback, graded_by) VALUES (?,?,?,?)');
                $stmt->execute([$sid, $score, $feedback, $usuario['id']]);
            }
            mensaje_flash('success', 'Calificación guardada.');
        }
        redirigir("curso.php?id=$id&pestaña=tareas");
    }

    if ($accion === 'agregar_tema' && ($esPropietario || $matriculado)) {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title !== '' && $body !== '') {
            $stmt = bd()->prepare('INSERT INTO forum_topics (course_id, author_id, title, body) VALUES (?,?,?,?)');
            $stmt->execute([$id, $usuario['id'], $title, $body]);
            mensaje_flash('success', 'Tema publicado.');
        }
        redirigir("curso.php?id=$id&pestaña=foro");
    }

    if ($accion === 'agregar_respuesta' && ($esPropietario || $matriculado)) {
        $idTema = (int) ($_POST['topic_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $check = bd()->prepare('SELECT id FROM forum_topics WHERE id = ? AND course_id = ?');
        $check->execute([$idTema, $id]);
        if ($check->fetch() && $body !== '') {
            $stmt = bd()->prepare('INSERT INTO forum_replies (topic_id, author_id, body) VALUES (?,?,?)');
            $stmt->execute([$idTema, $usuario['id'], $body]);
            mensaje_flash('success', 'Respuesta publicada.');
        }
        redirigir("curso.php?id=$id&pestaña=foro&tema=$idTema");
    }

    if ($accion === 'retirar_estudiante' && $esPropietario) {
        $idEstudiante = (int) ($_POST['id_estudiante'] ?? 0);
        $consulta = bd()->prepare('UPDATE enrollments SET status = "dropped" WHERE course_id = ? AND student_id = ?');
        $consulta->execute([$id, $idEstudiante]);
        mensaje_flash('success', 'Estudiante retirado del curso.');
        redirigir("curso.php?id=$id&pestaña=estudiantes");
    }

    if ($accion === 'eliminar_curso' && $esPropietario) {
        limpiar_archivos_curso($id);
        $stmt = bd()->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        mensaje_flash('success', 'Curso eliminado.');
        redirigir('cursos.php');
    }
}

// --- Datos por pestaña ---
$subcursos = obtener_subcursos_curso($id);
$lecciones = obtener_lecciones_curso($id);
$mostrarSubcursos = count($subcursos) > 1;
$leccionesPorSubcurso = [];
foreach ($lecciones as $leccion) {
    $idSubcurso = (int) ($leccion['subcourse_id'] ?? 0);
    if ($idSubcurso > 0) {
        $leccionesPorSubcurso[$idSubcurso][] = $leccion;
    }
}
$idModuloActivo = (int) ($_GET['modulo'] ?? 0);
if ($mostrarSubcursos) {
    if ($idModuloActivo <= 0 || !obtener_subcurso($idModuloActivo, $id)) {
        $idModuloActivo = (int) ($subcursos[0]['id'] ?? 0);
    }
}
$subcursoActivo = $mostrarSubcursos ? obtener_subcurso($idModuloActivo, $id) : null;
$leccionesModuloActivo = $mostrarSubcursos ? ($leccionesPorSubcurso[$idModuloActivo] ?? []) : $lecciones;
$listaLeccionesVista = $mostrarSubcursos ? $leccionesModuloActivo : $lecciones;
$idSubcursoLista = $mostrarSubcursos
    ? $idModuloActivo
    : (int) ($listaLeccionesVista[0]['subcourse_id'] ?? 0);
if ($idSubcursoLista <= 0 && $listaLeccionesVista) {
    $idSubcursoLista = asegurar_subcurso_default($id);
}

$mostrarProgresoEstudiante = $usuario['role'] === 'student' && $matriculado;
$progresoCurso = $mostrarProgresoEstudiante ? porcentaje_progreso_curso($id, (int) $usuario['id']) : 0;
$idsLeccionesCompletadas = $mostrarProgresoEstudiante ? obtener_ids_lecciones_completadas($id, (int) $usuario['id']) : [];

$tareas = bd()->prepare('SELECT * FROM assignments WHERE course_id = ? ORDER BY due_date IS NULL, due_date, id');
$tareas->execute([$id]);
$tareas = $tareas->fetchAll();

$mySubmissions = [];
$allSubmissions = [];
if ($usuario['role'] === 'student') {
    $stmt = bd()->prepare(
        'SELECT s.*, g.score, g.feedback FROM submissions s
         LEFT JOIN grades g ON g.submission_id = s.id
         WHERE s.student_id = ? AND s.assignment_id IN (SELECT id FROM assignments WHERE course_id = ?)'
    );
    $stmt->execute([$usuario['id'], $id]);
    foreach ($stmt->fetchAll() as $row) {
        $mySubmissions[(int) $row['assignment_id']] = $row;
    }
}
if ($esPropietario) {
    $stmt = bd()->prepare(
        'SELECT s.*, u.name AS student_name, a.title AS assignment_title, a.max_score, g.score, g.feedback, g.id AS grade_id
         FROM submissions s
         JOIN users u ON u.id = s.student_id
         JOIN assignments a ON a.id = s.assignment_id
         LEFT JOIN grades g ON g.submission_id = s.id
         WHERE a.course_id = ?
         ORDER BY s.submitted_at DESC'
    );
    $stmt->execute([$id]);
    $allSubmissions = $stmt->fetchAll();
}

$topics = bd()->prepare(
    'SELECT t.*, u.name AS author_name,
            (SELECT COUNT(*) FROM forum_replies r WHERE r.topic_id = t.id) AS replies
     FROM forum_topics t
     JOIN users u ON u.id = t.author_id
     WHERE t.course_id = ?
     ORDER BY t.created_at DESC'
);
$topics->execute([$id]);
$topics = $topics->fetchAll();

$idTema = (int) ($_GET['tema'] ?? 0);
$currentTopic = null;
$replies = [];
if ($idTema) {
    $stmt = bd()->prepare(
        'SELECT t.*, u.name AS author_name FROM forum_topics t JOIN users u ON u.id = t.author_id WHERE t.id = ? AND t.course_id = ?'
    );
    $stmt->execute([$idTema, $id]);
    $currentTopic = $stmt->fetch() ?: null;
    if ($currentTopic) {
        $stmt = bd()->prepare(
            'SELECT r.*, u.name AS author_name, u.role AS author_role
             FROM forum_replies r JOIN users u ON u.id = r.author_id
             WHERE r.topic_id = ? ORDER BY r.created_at'
        );
        $stmt->execute([$idTema]);
        $replies = $stmt->fetchAll();
    }
}

$estudiantes = [];
if ($esPropietario) {
    $stmt = bd()->prepare(
        'SELECT e.*, u.name, u.email FROM enrollments e
         JOIN users u ON u.id = e.student_id
         WHERE e.course_id = ? ORDER BY e.enrolled_at DESC'
    );
    $stmt->execute([$id]);
    $estudiantes = $stmt->fetchAll();
}

$tituloPagina = $curso['title'];
require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="panel mb-4 course-intro-panel">
    <div class="panel-body">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge text-bg-light border"><?= escapar($curso['code']) ?></span>
                    <?php if ($curso['category_name']): ?><span class="badge bg-secondary"><?= escapar($curso['category_name']) ?></span><?php endif; ?>
                </div>
                <h1 class="h2 mb-2"><?= escapar($curso['title']) ?></h1>
                <p class="text-muted mb-0">Docente: <?= escapar($curso['teacher_name']) ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap flex-shrink-0">
                <?php if ($esPropietario || $matriculado): ?>
                    <a href="<?= URL_CURSO_ASISTENCIA ?>?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-calendar-check me-1"></i> Asistencia</a>
                <?php endif; ?>
                <?php if ($esPropietario): ?>
                    <a href="<?= URL_APP ?>/curso-formulario.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i> Editar</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este curso y todo su contenido?');">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="eliminar_curso">
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Eliminar</button>
                    </form>
                <?php endif; ?>
                <a href="<?= URL_APP ?>/cursos.php" class="btn btn-outline-secondary">Volver</a>
            </div>
        </div>

        <?php if ($mostrarProgresoEstudiante && $lecciones): ?>
        <hr class="my-3">
        <div class="course-progress-block">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <span class="fw-semibold"><i class="bi bi-graph-up me-1"></i> Tu progreso en el curso</span>
                <span class="badge bg-success fs-6"><?= $progresoCurso ?>%</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-success" style="width: <?= $progresoCurso ?>%"></div>
            </div>
            <p class="small text-muted mb-0 mt-2">
                <?= count($idsLeccionesCompletadas) ?> de <?= count($lecciones) ?> lecciones completadas.
                Debes ver cada video al menos 10 minutos para marcarla como completada.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (trim($curso['description'] ?? '') !== ''): ?>
<div class="panel mb-4">
    <div class="panel-header"><h2 class="mb-0">Descripción</h2></div>
    <div class="panel-body content-html">
        <?= $curso['description'] ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($curso['document_path'])): ?>
<div class="panel mb-4">
    <div class="panel-body">
        <?= renderizar_vista_previa_documento($curso['document_path'], 'Material del curso') ?>
    </div>
</div>
<?php endif; ?>

<?php if ($esPropietario): ?>
<div class="panel mb-4">
    <div class="panel-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-semibold">Inscripción:</span>
            <?= insignia_metodo_inscripcion($curso['enrollment_type'] ?? 'public') ?>
            <a href="<?= URL_APP ?>/curso-formulario.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary ms-auto">Configurar</a>
        </div>
        <?php if (($curso['enrollment_type'] ?? '') === 'url'): ?>
            <?php $urlInscripcion = url_inscripcion_curso($curso); ?>
            <label class="form-label small mb-1">Enlace de inscripción automática</label>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" value="<?= escapar($urlInscripcion) ?>" readonly id="url-inscripcion-curso">
                <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('url-inscripcion-curso').value)"><i class="bi bi-clipboard"></i> Copiar</button>
            </div>
        <?php elseif (($curso['enrollment_type'] ?? '') === 'password'): ?>
            <p class="small text-muted mb-0">Los estudiantes deben ingresar la contraseña en el catálogo para inscribirse.</p>
        <?php else: ?>
            <p class="small text-muted mb-0">Visible en el catálogo. Cualquier estudiante puede inscribirse.</p>
        <?php endif; ?>
        <?php if (!empty($curso['enrollment_deadline'])): ?>
            <p class="small text-muted mb-0 mt-2">
                <i class="bi bi-calendar-event me-1"></i>
                Inscripción disponible hasta el <?= formatear_fecha($curso['enrollment_deadline']) ?>
                <?php if (!inscripcion_abierta($curso)): ?>
                    <span class="badge bg-secondary ms-1">Plazo vencido</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<ul class="nav nav-pills gap-2 mb-4 flex-wrap">
    <?php
    $pestañas = [
        'lecciones' => ['Lecciones', 'bi-book'],
        'tareas' => ['Tareas', 'bi-clipboard-check'],
        'foro' => ['Foro', 'bi-chat-dots'],
    ];
    if ($esPropietario) {
        $pestañas['estudiantes'] = ['Estudiantes', 'bi-people'];
    }
    foreach ($pestañas as $key => [$label, $icon]):
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $pestaña === $key ? 'active' : '' ?>" href="?id=<?= $id ?>&pestaña=<?= $key ?>">
            <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($pestaña === 'lecciones'): ?>
<div class="row g-4">
    <div class="col-lg-<?= $esPropietario ? '7' : '12' ?>">
        <div class="panel">
            <div class="panel-header"><h2>Contenido del curso</h2></div>
            <div class="panel-body">
                <?php if (!$lecciones && !$subcursos): ?>
                    <div class="empty-state"><i class="bi bi-journal"></i><p class="mb-0">Aún no hay contenido.<?= $esPropietario ? ' Agrega un módulo o una lección.' : '' ?></p></div>
                <?php else: ?>
                    <?php if ($mostrarSubcursos): ?>
                    <div class="mb-3 course-materia-field">
                        <label class="form-label fw-semibold mb-1" for="selector-materia">Materia</label>
                        <select id="selector-materia" class="form-select course-materia-select" data-course-id="<?= $id ?>">
                            <?php foreach ($subcursos as $subcurso): ?>
                                <?php $idSub = (int) $subcurso['id']; ?>
                                <option value="<?= $idSub ?>" <?= $idModuloActivo === $idSub ? 'selected' : '' ?>>
                                    <?= escapar($subcurso['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($esPropietario && $subcursoActivo): ?>
                    <div class="d-flex justify-content-end gap-2 flex-wrap mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editar-modulo-activo">
                            <i class="bi bi-pencil me-1"></i> Editar módulo
                        </button>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este módulo y todas sus lecciones?');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="eliminar_subcurso">
                            <input type="hidden" name="subcourse_id" value="<?= $idModuloActivo ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Eliminar módulo</button>
                        </form>
                    </div>
                    <div class="collapse mb-3" id="editar-modulo-activo">
                        <form method="post" class="row g-2 align-items-end border rounded p-3 bg-light">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="editar_subcurso">
                            <input type="hidden" name="subcourse_id" value="<?= $idModuloActivo ?>">
                            <div class="col-md-7">
                                <label class="form-label small mb-1">Título del módulo</label>
                                <input type="text" name="title" class="form-control form-control-sm" value="<?= escapar($subcursoActivo['title']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Orden</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int) $subcursoActivo['sort_order'] ?>" min="1">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-primary w-100">Guardar</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$listaLeccionesVista && $mostrarSubcursos): ?>
                        <div class="empty-state py-4"><i class="bi bi-journal"></i><p class="mb-0">Este módulo aún no tiene lecciones.</p></div>
                    <?php elseif ($listaLeccionesVista): ?>
                        <?php if ($esPropietario): ?>
                        <p class="small text-muted mb-2"><i class="bi bi-arrows-move me-1"></i>Arrastra las lecciones para cambiar el orden.</p>
                        <?php endif; ?>
                        <div id="lista-lecciones-ordenables"
                             class="lesson-sortable-list"
                             data-sortable="<?= $esPropietario ? '1' : '0' ?>"
                             data-course-id="<?= $id ?>"
                             data-subcourse-id="<?= $idSubcursoLista ?>"
                             data-csrf="<?= escapar(token_csrf()) ?>"
                             data-url="<?= escapar(URL_APP . '/curso.php?id=' . $id) ?>">
                            <?php foreach ($listaLeccionesVista as $i => $lesson): ?>
                                <?php $leccionCompletada = in_array((int) $lesson['id'], $idsLeccionesCompletadas, true); ?>
                                <div class="lesson-item" data-lesson-id="<?= (int) $lesson['id'] ?>">
                                    <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                                        <?php if ($esPropietario): ?>
                                        <span class="lesson-drag-handle" title="Arrastrar para reordenar"><i class="bi bi-grip-vertical"></i></span>
                                        <?php endif; ?>
                                        <span class="badge bg-light text-dark border lesson-order-badge"><?= $i + 1 ?></span>
                                        <strong class="text-truncate"><?= escapar($lesson['title']) ?></strong>
                                        <?php if ($mostrarProgresoEstudiante): ?>
                                            <span class="badge <?= $leccionCompletada ? 'bg-success' : 'bg-secondary' ?> ms-1 flex-shrink-0">
                                                <?= $leccionCompletada ? '100%' : '0%' ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($lesson['attachment'])): ?>
                                            <span class="badge bg-light text-dark border ms-1 flex-shrink-0"><i class="bi bi-paperclip"></i> Documento</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2 flex-shrink-0">
                                        <a href="<?= URL_APP ?>/leccion.php?id=<?= (int) $lesson['id'] ?>" class="btn btn-sm btn-primary">Ver</a>
                                        <?php if ($esPropietario): ?>
                                        <a href="<?= URL_APP ?>/leccion-formulario.php?id=<?= (int) $lesson['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <form method="post" onsubmit="return confirm('¿Eliminar lección?');">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="accion" value="eliminar_leccion">
                                            <input type="hidden" name="lesson_id" value="<?= (int) $lesson['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($esPropietario): ?>
    <div class="col-lg-5">
        <div class="panel mb-4">
            <div class="panel-header"><h2>Nuevo módulo</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="agregar_subcurso">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" placeholder="Ej. Módulo 1, Semana 2..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= count($subcursos) + 1 ?>" min="1">
                    </div>
                    <button class="btn btn-outline-primary w-100" type="submit">Agregar módulo</button>
                </form>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><h2>Nueva lección</h2></div>
            <div class="panel-body">
                <form method="post" enctype="multipart/form-data">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="agregar_leccion">
                    <?php if ($mostrarSubcursos): ?>
                    <div class="mb-3">
                        <label class="form-label">Materia</label>
                        <select name="subcourse_id" class="form-select" required>
                            <?php foreach ($subcursos as $subcurso): ?>
                                <option value="<?= (int) $subcurso['id'] ?>" <?= $idModuloActivo === (int) $subcurso['id'] ? 'selected' : '' ?>>
                                    <?= escapar($subcurso['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenido (HTML permitido)</label>
                        <textarea name="content" class="form-control" rows="5"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL de video (opcional)</label>
                        <input type="url" name="video_url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Documento (opcional)</label>
                        <input type="file" name="documento" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp,.txt">
                        <small class="text-muted">PDF, Word, Excel, PowerPoint, imágenes o texto. Se podrá previsualizar al abrir la lección.</small>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Agregar lección</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($esPropietario): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="<?= URL_APP ?>/assets/js/lecciones-orden.js"></script>
<?php endif; ?>
<?php if ($mostrarSubcursos): ?>
<script src="<?= URL_APP ?>/assets/js/materia-select.js"></script>
<?php endif; ?>

<?php elseif ($pestaña === 'tareas'): ?>
<div class="row g-4">
    <div class="col-lg-<?= $esPropietario ? '7' : '12' ?>">
        <div class="panel mb-4">
            <div class="panel-header"><h2>Tareas</h2></div>
            <div class="panel-body">
                <?php if (!$tareas): ?>
                    <div class="empty-state"><i class="bi bi-clipboard"></i><p class="mb-0">No hay tareas.</p></div>
                <?php else: ?>
                    <?php foreach ($tareas as $asg): ?>
                        <?php $sub = $mySubmissions[(int) $asg['id']] ?? null; ?>
                        <div class="assignment-item flex-column align-items-stretch">
                            <div class="d-flex justify-content-between gap-2 flex-wrap">
                                <div>
                                    <strong><?= escapar($asg['title']) ?></strong>
                                    <div class="small text-muted">
                                        Máx. <?= number_format((float) $asg['max_score'], 0) ?> pts
                                        <?php if ($asg['due_date']): ?> · Vence <?= formatear_fecha($asg['due_date'], true) ?><?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($esPropietario): ?>
                                <form method="post" onsubmit="return confirm('¿Eliminar tarea?');">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="eliminar_tarea">
                                    <input type="hidden" name="assignment_id" value="<?= (int) $asg['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php elseif ($sub): ?>
                                    <span class="badge <?= isset($sub['score']) ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= isset($sub['score']) ? 'Nota: ' . $sub['score'] : 'Entregado' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($asg['description']): ?><p class="small mb-2 mt-2"><?= nl2br(escapar($asg['description'])) ?></p><?php endif; ?>

                            <?php if ($matriculado && !$esPropietario): ?>
                                <?php if ($sub && isset($sub['feedback']) && $sub['feedback']): ?>
                                    <div class="alert alert-info py-2 small mb-2">Feedback: <?= escapar($sub['feedback']) ?></div>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data" class="border-top pt-3 mt-1">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="entregar_tarea">
                                    <input type="hidden" name="assignment_id" value="<?= (int) $asg['id'] ?>">
                                    <div class="mb-2">
                                        <label class="form-label small">Tu respuesta</label>
                                        <textarea name="content" class="form-control form-control-sm" rows="3"><?= escapar($sub['content'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Archivo (opcional)</label>
                                        <input type="file" name="file" class="form-control form-control-sm">
                                        <?php if (!empty($sub['file_path'])): ?>
                                            <small class="text-muted">Archivo actual: <a href="<?= URL_APP ?>/subidas/<?= escapar($sub['file_path']) ?>" target="_blank">Descargar</a></small>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-primary" type="submit"><?= $sub ? 'Actualizar entrega' : 'Enviar entrega' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($esPropietario && $allSubmissions): ?>
        <div class="panel">
            <div class="panel-header"><h2>Entregas recibidas</h2></div>
            <div class="panel-body">
                <?php foreach ($allSubmissions as $sub): ?>
                <div class="assignment-item flex-column align-items-stretch">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <strong><?= escapar($sub['student_name']) ?></strong> · <?= escapar($sub['assignment_title']) ?>
                            <div class="small text-muted">Enviado <?= formatear_fecha($sub['submitted_at'], true) ?></div>
                        </div>
                        <?php if ($sub['score'] !== null): ?>
                            <span class="badge bg-success"><?= escapar($sub['score']) ?> / <?= escapar($sub['max_score']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Sin calificar</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($sub['content']): ?><p class="small mt-2 mb-1"><?= nl2br(escapar($sub['content'])) ?></p><?php endif; ?>
                    <?php if ($sub['file_path']): ?>
                        <a class="small" href="<?= URL_APP ?>/subidas/<?= escapar($sub['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> Archivo adjunto</a>
                    <?php endif; ?>
                    <form method="post" class="row g-2 mt-2 align-items-end">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="calificar_entrega">
                        <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
                        <div class="col-md-3">
                            <label class="form-label small">Nota (máx <?= escapar($sub['max_score']) ?>)</label>
                            <input type="number" step="0.01" name="score" class="form-control form-control-sm" value="<?= escapar($sub['score'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Feedback</label>
                            <input type="text" name="feedback" class="form-control form-control-sm" value="<?= escapar($sub['feedback'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-primary w-100" type="submit">Calificar</button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($esPropietario): ?>
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header"><h2>Nueva tarea</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="agregar_tarea">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha límite</label>
                        <input type="datetime-local" name="due_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Puntaje máximo</label>
                        <input type="number" name="max_score" class="form-control" value="100" step="0.01">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Crear tarea</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($pestaña === 'foro'): ?>
<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($currentTopic): ?>
            <div class="panel mb-3">
                <div class="panel-header">
                    <h2><?= escapar($currentTopic['title']) ?></h2>
                    <a href="?id=<?= $id ?>&pestaña=foro" class="btn btn-sm btn-outline-secondary">Volver</a>
                </div>
                <div class="panel-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="small text-muted mb-2"><?= escapar($currentTopic['author_name']) ?> · <?= formatear_fecha($currentTopic['created_at'], true) ?></div>
                        <p class="mb-0"><?= nl2br(escapar($currentTopic['body'])) ?></p>
                    </div>
                    <?php foreach ($replies as $reply): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <strong><?= escapar($reply['author_name']) ?></strong>
                                <small class="text-muted"><?= formatear_fecha($reply['created_at'], true) ?></small>
                            </div>
                            <div class="mb-1"><?= insignia_rol($reply['author_role']) ?></div>
                            <p class="mb-0 small"><?= nl2br(escapar($reply['body'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <form method="post" class="mt-3">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="agregar_respuesta">
                        <input type="hidden" name="topic_id" value="<?= (int) $currentTopic['id'] ?>">
                        <label class="form-label">Tu respuesta</label>
                        <textarea name="body" class="form-control mb-2" rows="3" required></textarea>
                        <button class="btn btn-primary" type="submit">Responder</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="panel">
                <div class="panel-header"><h2>Temas del foro</h2></div>
                <div class="panel-body">
                    <?php if (!$topics): ?>
                        <div class="empty-state"><i class="bi bi-chat"></i><p class="mb-0">No hay temas aún.</p></div>
                    <?php else: ?>
                        <?php foreach ($topics as $topic): ?>
                            <a href="?id=<?= $id ?>&pestaña=foro&tema=<?= (int) $topic['id'] ?>" class="forum-item text-decoration-none text-dark">
                                <div>
                                    <strong><?= escapar($topic['title']) ?></strong>
                                    <div class="small text-muted"><?= escapar($topic['author_name']) ?> · <?= formatear_fecha($topic['created_at']) ?> · <?= (int) $topic['replies'] ?> respuestas</div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header"><h2>Nuevo tema</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="agregar_tema">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje</label>
                        <textarea name="body" class="form-control" rows="4" required></textarea>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Publicar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($pestaña === 'estudiantes' && $esPropietario): ?>
<div class="panel">
    <div class="panel-header">
        <h2>Estudiantes inscritos (<?= count($estudiantes) ?>)</h2>
        <a href="<?= URL_CURSO_ASISTENCIA ?>?id=<?= $id ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-calendar-check me-1"></i> Tomar asistencia
        </a>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Inscrito</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$estudiantes): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Sin estudiantes inscritos.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($estudiantes as $st): ?>
                    <tr>
                        <td><?= escapar($st['name']) ?></td>
                        <td><?= escapar($st['email']) ?></td>
                        <td><?= insignia_estado($st['status']) ?></td>
                        <td><?= formatear_fecha($st['enrolled_at']) ?></td>
                        <td class="text-end">
                            <?php if ($st['status'] === 'active'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Retirar estudiante?');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="retirar_estudiante">
                                <input type="hidden" name="id_estudiante" value="<?= (int) $st['student_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Retirar</button>
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
