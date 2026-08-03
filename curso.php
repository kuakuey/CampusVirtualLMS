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

    if ($accion === 'agregar_leccion' && $esPropietario) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $video = trim($_POST['video_url'] ?? '');
        $order = (int) ($_POST['sort_order'] ?? 0);
        if ($title !== '') {
            $adjunto = !empty($_FILES['documento']['name']) ? subir_archivo($_FILES['documento'], 'lecciones') : null;
            $stmt = bd()->prepare('INSERT INTO lessons (course_id, title, content, video_url, sort_order, attachment) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$id, $title, $content, $video ?: null, $order, $adjunto]);
            mensaje_flash('success', 'Lección creada.');
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
$lecciones = bd()->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
$lecciones->execute([$id]);
$lecciones = $lecciones->fetchAll();

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

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span class="badge text-bg-light border"><?= escapar($curso['code']) ?></span>
            <?= insignia_estado($curso['status']) ?>
            <?php if ($curso['group_name']): ?><span class="badge bg-info text-dark"><?= escapar($curso['group_name']) ?></span><?php endif; ?>
            <?php if ($curso['category_name']): ?><span class="badge bg-secondary"><?= escapar($curso['category_name']) ?></span><?php endif; ?>
        </div>
        <h1><?= escapar($curso['title']) ?></h1>
        <p class="subtitle mb-0">Docente: <?= escapar($curso['teacher_name']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
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

<?php if ($curso['description']): ?>
<div class="panel mb-4"><div class="panel-body"><?= nl2br(escapar($curso['description'])) ?></div></div>
<?php endif; ?>

<?php if (!empty($curso['document_path'])): ?>
<div class="panel mb-4">
    <div class="panel-body">
        <?= renderizar_vista_previa_documento($curso['document_path'], 'Material del curso') ?>
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
                <?php if (!$lecciones): ?>
                    <div class="empty-state"><i class="bi bi-journal"></i><p class="mb-0">Aún no hay lecciones.</p></div>
                <?php else: ?>
                    <?php foreach ($lecciones as $i => $lesson): ?>
                        <div class="lesson-item">
                            <div>
                                <span class="badge bg-light text-dark border me-2"><?= $i + 1 ?></span>
                                <strong><?= escapar($lesson['title']) ?></strong>
                                <?php if (!empty($lesson['attachment'])): ?>
                                    <span class="badge bg-light text-dark border ms-1"><i class="bi bi-paperclip"></i> Documento</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($esPropietario): ?>
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header"><h2>Nueva lección</h2></div>
            <div class="panel-body">
                <form method="post" enctype="multipart/form-data">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="agregar_leccion">
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
                    <div class="mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= count($lecciones) + 1 ?>">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Agregar lección</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

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
    <div class="panel-header"><h2>Estudiantes inscritos (<?= count($estudiantes) ?>)</h2></div>
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
