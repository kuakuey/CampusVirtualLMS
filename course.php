<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$course = get_course($id);

if (!$course) {
    flash('danger', 'Curso no encontrado.');
    redirect('courses.php');
}

$isOwner = $user['role'] === 'admin' || ($user['role'] === 'teacher' && (int) $course['teacher_id'] === (int) $user['id']);
$enrolled = is_enrolled($id);

if (!$isOwner && !$enrolled && $user['role'] !== 'admin') {
    if ($course['status'] === 'published' && $user['role'] === 'student') {
        flash('warning', 'Debes inscribirte para acceder al curso.');
        redirect('catalog.php');
    }
    flash('danger', 'No tienes acceso a este curso.');
    redirect('courses.php');
}

$tab = $_GET['tab'] ?? 'lessons';
$allowedTabs = ['lessons', 'assignments', 'forum', 'students', 'announcements'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'lessons';
}
if ($tab === 'students' && !$isOwner) {
    $tab = 'lessons';
}

// --- Acciones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_lesson' && $isOwner) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $video = trim($_POST['video_url'] ?? '');
        $order = (int) ($_POST['sort_order'] ?? 0);
        if ($title !== '') {
            $stmt = db()->prepare('INSERT INTO lessons (course_id, title, content, video_url, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$id, $title, $content, $video ?: null, $order]);
            flash('success', 'Lección creada.');
        }
        redirect("course.php?id=$id&tab=lessons");
    }

    if ($action === 'delete_lesson' && $isOwner) {
        $lessonId = (int) ($_POST['lesson_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM lessons WHERE id = ? AND course_id = ?');
        $stmt->execute([$lessonId, $id]);
        flash('success', 'Lección eliminada.');
        redirect("course.php?id=$id&tab=lessons");
    }

    if ($action === 'add_assignment' && $isOwner) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due = $_POST['due_date'] ?? null;
        $max = (float) ($_POST['max_score'] ?? 100);
        if ($title !== '') {
            $due = $due ? date('Y-m-d H:i:s', strtotime($due)) : null;
            $stmt = db()->prepare('INSERT INTO assignments (course_id, title, description, due_date, max_score) VALUES (?,?,?,?,?)');
            $stmt->execute([$id, $title, $description, $due, $max]);
            flash('success', 'Tarea creada.');
        }
        redirect("course.php?id=$id&tab=assignments");
    }

    if ($action === 'delete_assignment' && $isOwner) {
        $aid = (int) ($_POST['assignment_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM assignments WHERE id = ? AND course_id = ?');
        $stmt->execute([$aid, $id]);
        flash('success', 'Tarea eliminada.');
        redirect("course.php?id=$id&tab=assignments");
    }

    if ($action === 'submit_assignment' && $enrolled) {
        $aid = (int) ($_POST['assignment_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $check = db()->prepare('SELECT id FROM assignments WHERE id = ? AND course_id = ?');
        $check->execute([$aid, $id]);
        if ($check->fetch()) {
            $filePath = null;
            if (!empty($_FILES['file']['name'])) {
                $filePath = upload_file($_FILES['file'], 'submissions');
            }
            $exists = db()->prepare('SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?');
            $exists->execute([$aid, $user['id']]);
            if ($row = $exists->fetch()) {
                $stmt = db()->prepare('UPDATE submissions SET content=?, file_path=COALESCE(?, file_path), submitted_at=NOW() WHERE id=?');
                $stmt->execute([$content, $filePath, $row['id']]);
            } else {
                $stmt = db()->prepare('INSERT INTO submissions (assignment_id, student_id, content, file_path) VALUES (?,?,?,?)');
                $stmt->execute([$aid, $user['id'], $content, $filePath]);
            }
            flash('success', 'Entrega enviada.');
        }
        redirect("course.php?id=$id&tab=assignments");
    }

    if ($action === 'grade_submission' && $isOwner) {
        $sid = (int) ($_POST['submission_id'] ?? 0);
        $score = (float) ($_POST['score'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        $check = db()->prepare(
            'SELECT s.id FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.id = ? AND a.course_id = ?'
        );
        $check->execute([$sid, $id]);
        if ($check->fetch()) {
            $exists = db()->prepare('SELECT id FROM grades WHERE submission_id = ?');
            $exists->execute([$sid]);
            if ($g = $exists->fetch()) {
                $stmt = db()->prepare('UPDATE grades SET score=?, feedback=?, graded_by=?, graded_at=NOW() WHERE id=?');
                $stmt->execute([$score, $feedback, $user['id'], $g['id']]);
            } else {
                $stmt = db()->prepare('INSERT INTO grades (submission_id, score, feedback, graded_by) VALUES (?,?,?,?)');
                $stmt->execute([$sid, $score, $feedback, $user['id']]);
            }
            flash('success', 'Calificación guardada.');
        }
        redirect("course.php?id=$id&tab=assignments");
    }

    if ($action === 'add_topic' && ($isOwner || $enrolled)) {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title !== '' && $body !== '') {
            $stmt = db()->prepare('INSERT INTO forum_topics (course_id, author_id, title, body) VALUES (?,?,?,?)');
            $stmt->execute([$id, $user['id'], $title, $body]);
            flash('success', 'Tema publicado.');
        }
        redirect("course.php?id=$id&tab=forum");
    }

    if ($action === 'add_reply' && ($isOwner || $enrolled)) {
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $check = db()->prepare('SELECT id FROM forum_topics WHERE id = ? AND course_id = ?');
        $check->execute([$topicId, $id]);
        if ($check->fetch() && $body !== '') {
            $stmt = db()->prepare('INSERT INTO forum_replies (topic_id, author_id, body) VALUES (?,?,?)');
            $stmt->execute([$topicId, $user['id'], $body]);
            flash('success', 'Respuesta publicada.');
        }
        redirect("course.php?id=$id&tab=forum&topic=$topicId");
    }

    if ($action === 'add_announcement' && $isOwner) {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title !== '' && $body !== '') {
            $stmt = db()->prepare('INSERT INTO announcements (course_id, author_id, title, body, is_global) VALUES (?,?,?,?,0)');
            $stmt->execute([$id, $user['id'], $title, $body]);
            flash('success', 'Anuncio publicado.');
        }
        redirect("course.php?id=$id&tab=announcements");
    }

    if ($action === 'unenroll_student' && $isOwner) {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $stmt = db()->prepare('UPDATE enrollments SET status = "dropped" WHERE course_id = ? AND student_id = ?');
        $stmt->execute([$id, $studentId]);
        flash('success', 'Estudiante retirado del curso.');
        redirect("course.php?id=$id&tab=students");
    }

    if ($action === 'delete_course' && $isOwner) {
        $stmt = db()->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Curso eliminado.');
        redirect('courses.php');
    }
}

// --- Datos por pestaña ---
$lessons = db()->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
$lessons->execute([$id]);
$lessons = $lessons->fetchAll();

$assignments = db()->prepare('SELECT * FROM assignments WHERE course_id = ? ORDER BY due_date IS NULL, due_date, id');
$assignments->execute([$id]);
$assignments = $assignments->fetchAll();

$mySubmissions = [];
$allSubmissions = [];
if ($user['role'] === 'student') {
    $stmt = db()->prepare(
        'SELECT s.*, g.score, g.feedback FROM submissions s
         LEFT JOIN grades g ON g.submission_id = s.id
         WHERE s.student_id = ? AND s.assignment_id IN (SELECT id FROM assignments WHERE course_id = ?)'
    );
    $stmt->execute([$user['id'], $id]);
    foreach ($stmt->fetchAll() as $row) {
        $mySubmissions[(int) $row['assignment_id']] = $row;
    }
}
if ($isOwner) {
    $stmt = db()->prepare(
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

$topics = db()->prepare(
    'SELECT t.*, u.name AS author_name,
            (SELECT COUNT(*) FROM forum_replies r WHERE r.topic_id = t.id) AS replies
     FROM forum_topics t
     JOIN users u ON u.id = t.author_id
     WHERE t.course_id = ?
     ORDER BY t.created_at DESC'
);
$topics->execute([$id]);
$topics = $topics->fetchAll();

$topicId = (int) ($_GET['topic'] ?? 0);
$currentTopic = null;
$replies = [];
if ($topicId) {
    $stmt = db()->prepare(
        'SELECT t.*, u.name AS author_name FROM forum_topics t JOIN users u ON u.id = t.author_id WHERE t.id = ? AND t.course_id = ?'
    );
    $stmt->execute([$topicId, $id]);
    $currentTopic = $stmt->fetch() ?: null;
    if ($currentTopic) {
        $stmt = db()->prepare(
            'SELECT r.*, u.name AS author_name, u.role AS author_role
             FROM forum_replies r JOIN users u ON u.id = r.author_id
             WHERE r.topic_id = ? ORDER BY r.created_at'
        );
        $stmt->execute([$topicId]);
        $replies = $stmt->fetchAll();
    }
}

$students = [];
if ($isOwner) {
    $stmt = db()->prepare(
        'SELECT e.*, u.name, u.email FROM enrollments e
         JOIN users u ON u.id = e.student_id
         WHERE e.course_id = ? ORDER BY e.enrolled_at DESC'
    );
    $stmt->execute([$id]);
    $students = $stmt->fetchAll();
}

$courseAnnouncements = db()->prepare(
    'SELECT a.*, u.name AS author_name FROM announcements a
     JOIN users u ON u.id = a.author_id
     WHERE a.course_id = ? ORDER BY a.created_at DESC'
);
$courseAnnouncements->execute([$id]);
$courseAnnouncements = $courseAnnouncements->fetchAll();

$pageTitle = $course['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span class="badge text-bg-light border"><?= e($course['code']) ?></span>
            <?= status_badge($course['status']) ?>
            <?php if ($course['category_name']): ?><span class="badge bg-secondary"><?= e($course['category_name']) ?></span><?php endif; ?>
        </div>
        <h1><?= e($course['title']) ?></h1>
        <p class="subtitle mb-0">Docente: <?= e($course['teacher_name']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($isOwner): ?>
            <a href="<?= APP_URL ?>/course_form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i> Editar</a>
            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este curso y todo su contenido?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_course">
                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
            </form>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/courses.php" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<?php if ($course['description']): ?>
<div class="panel mb-4"><div class="panel-body"><?= nl2br(e($course['description'])) ?></div></div>
<?php endif; ?>

<ul class="nav nav-pills gap-2 mb-4 flex-wrap">
    <?php
    $tabs = [
        'lessons' => ['Lecciones', 'bi-book'],
        'assignments' => ['Tareas', 'bi-clipboard-check'],
        'forum' => ['Foro', 'bi-chat-dots'],
        'announcements' => ['Anuncios', 'bi-megaphone'],
    ];
    if ($isOwner) {
        $tabs['students'] = ['Estudiantes', 'bi-people'];
    }
    foreach ($tabs as $key => [$label, $icon]):
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="?id=<?= $id ?>&tab=<?= $key ?>">
            <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'lessons'): ?>
<div class="row g-4">
    <div class="col-lg-<?= $isOwner ? '7' : '12' ?>">
        <div class="panel">
            <div class="panel-header"><h2>Contenido del curso</h2></div>
            <div class="panel-body">
                <?php if (!$lessons): ?>
                    <div class="empty-state"><i class="bi bi-journal"></i><p class="mb-0">Aún no hay lecciones.</p></div>
                <?php else: ?>
                    <?php foreach ($lessons as $i => $lesson): ?>
                        <div class="lesson-item">
                            <div>
                                <span class="badge bg-light text-dark border me-2"><?= $i + 1 ?></span>
                                <strong><?= e($lesson['title']) ?></strong>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= APP_URL ?>/lesson.php?id=<?= (int) $lesson['id'] ?>" class="btn btn-sm btn-primary">Ver</a>
                                <?php if ($isOwner): ?>
                                <form method="post" onsubmit="return confirm('¿Eliminar lección?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_lesson">
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
    <?php if ($isOwner): ?>
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header"><h2>Nueva lección</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_lesson">
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
                        <label class="form-label">Orden</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= count($lessons) + 1 ?>">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Agregar lección</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'assignments'): ?>
<div class="row g-4">
    <div class="col-lg-<?= $isOwner ? '7' : '12' ?>">
        <div class="panel mb-4">
            <div class="panel-header"><h2>Tareas</h2></div>
            <div class="panel-body">
                <?php if (!$assignments): ?>
                    <div class="empty-state"><i class="bi bi-clipboard"></i><p class="mb-0">No hay tareas.</p></div>
                <?php else: ?>
                    <?php foreach ($assignments as $asg): ?>
                        <?php $sub = $mySubmissions[(int) $asg['id']] ?? null; ?>
                        <div class="assignment-item flex-column align-items-stretch">
                            <div class="d-flex justify-content-between gap-2 flex-wrap">
                                <div>
                                    <strong><?= e($asg['title']) ?></strong>
                                    <div class="small text-muted">
                                        Máx. <?= number_format((float) $asg['max_score'], 0) ?> pts
                                        <?php if ($asg['due_date']): ?> · Vence <?= format_date($asg['due_date'], true) ?><?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($isOwner): ?>
                                <form method="post" onsubmit="return confirm('¿Eliminar tarea?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_assignment">
                                    <input type="hidden" name="assignment_id" value="<?= (int) $asg['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php elseif ($sub): ?>
                                    <span class="badge <?= isset($sub['score']) ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= isset($sub['score']) ? 'Nota: ' . $sub['score'] : 'Entregado' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($asg['description']): ?><p class="small mb-2 mt-2"><?= nl2br(e($asg['description'])) ?></p><?php endif; ?>

                            <?php if ($enrolled && !$isOwner): ?>
                                <?php if ($sub && isset($sub['feedback']) && $sub['feedback']): ?>
                                    <div class="alert alert-info py-2 small mb-2">Feedback: <?= e($sub['feedback']) ?></div>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data" class="border-top pt-3 mt-1">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="submit_assignment">
                                    <input type="hidden" name="assignment_id" value="<?= (int) $asg['id'] ?>">
                                    <div class="mb-2">
                                        <label class="form-label small">Tu respuesta</label>
                                        <textarea name="content" class="form-control form-control-sm" rows="3"><?= e($sub['content'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Archivo (opcional)</label>
                                        <input type="file" name="file" class="form-control form-control-sm">
                                        <?php if (!empty($sub['file_path'])): ?>
                                            <small class="text-muted">Archivo actual: <a href="<?= APP_URL ?>/uploads/<?= e($sub['file_path']) ?>" target="_blank">Descargar</a></small>
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

        <?php if ($isOwner && $allSubmissions): ?>
        <div class="panel">
            <div class="panel-header"><h2>Entregas recibidas</h2></div>
            <div class="panel-body">
                <?php foreach ($allSubmissions as $sub): ?>
                <div class="assignment-item flex-column align-items-stretch">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                            <strong><?= e($sub['student_name']) ?></strong> · <?= e($sub['assignment_title']) ?>
                            <div class="small text-muted">Enviado <?= format_date($sub['submitted_at'], true) ?></div>
                        </div>
                        <?php if ($sub['score'] !== null): ?>
                            <span class="badge bg-success"><?= e($sub['score']) ?> / <?= e($sub['max_score']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Sin calificar</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($sub['content']): ?><p class="small mt-2 mb-1"><?= nl2br(e($sub['content'])) ?></p><?php endif; ?>
                    <?php if ($sub['file_path']): ?>
                        <a class="small" href="<?= APP_URL ?>/uploads/<?= e($sub['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> Archivo adjunto</a>
                    <?php endif; ?>
                    <form method="post" class="row g-2 mt-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="grade_submission">
                        <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
                        <div class="col-md-3">
                            <label class="form-label small">Nota (máx <?= e($sub['max_score']) ?>)</label>
                            <input type="number" step="0.01" name="score" class="form-control form-control-sm" value="<?= e($sub['score'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Feedback</label>
                            <input type="text" name="feedback" class="form-control form-control-sm" value="<?= e($sub['feedback'] ?? '') ?>">
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

    <?php if ($isOwner): ?>
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header"><h2>Nueva tarea</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_assignment">
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

<?php elseif ($tab === 'forum'): ?>
<div class="row g-4">
    <div class="col-lg-7">
        <?php if ($currentTopic): ?>
            <div class="panel mb-3">
                <div class="panel-header">
                    <h2><?= e($currentTopic['title']) ?></h2>
                    <a href="?id=<?= $id ?>&tab=forum" class="btn btn-sm btn-outline-secondary">Volver</a>
                </div>
                <div class="panel-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="small text-muted mb-2"><?= e($currentTopic['author_name']) ?> · <?= format_date($currentTopic['created_at'], true) ?></div>
                        <p class="mb-0"><?= nl2br(e($currentTopic['body'])) ?></p>
                    </div>
                    <?php foreach ($replies as $reply): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($reply['author_name']) ?></strong>
                                <small class="text-muted"><?= format_date($reply['created_at'], true) ?></small>
                            </div>
                            <div class="mb-1"><?= role_badge($reply['author_role']) ?></div>
                            <p class="mb-0 small"><?= nl2br(e($reply['body'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <form method="post" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_reply">
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
                            <a href="?id=<?= $id ?>&tab=forum&topic=<?= (int) $topic['id'] ?>" class="forum-item text-decoration-none text-dark">
                                <div>
                                    <strong><?= e($topic['title']) ?></strong>
                                    <div class="small text-muted"><?= e($topic['author_name']) ?> · <?= format_date($topic['created_at']) ?> · <?= (int) $topic['replies'] ?> respuestas</div>
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
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_topic">
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

<?php elseif ($tab === 'announcements'): ?>
<div class="row g-4">
    <div class="col-lg-<?= $isOwner ? '7' : '12' ?>">
        <div class="panel">
            <div class="panel-header"><h2>Anuncios del curso</h2></div>
            <div class="panel-body">
                <?php if (!$courseAnnouncements): ?>
                    <div class="empty-state"><i class="bi bi-megaphone"></i><p class="mb-0">Sin anuncios.</p></div>
                <?php else: ?>
                    <?php foreach ($courseAnnouncements as $ann): ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <strong><?= e($ann['title']) ?></strong>
                            <div class="small text-muted mb-2"><?= e($ann['author_name']) ?> · <?= format_date($ann['created_at'], true) ?></div>
                            <p class="mb-0"><?= nl2br(e($ann['body'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($isOwner): ?>
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header"><h2>Nuevo anuncio</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_announcement">
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
    <?php endif; ?>
</div>

<?php elseif ($tab === 'students' && $isOwner): ?>
<div class="panel">
    <div class="panel-header"><h2>Estudiantes inscritos (<?= count($students) ?>)</h2></div>
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
                    <?php if (!$students): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Sin estudiantes inscritos.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $st): ?>
                    <tr>
                        <td><?= e($st['name']) ?></td>
                        <td><?= e($st['email']) ?></td>
                        <td><?= status_badge($st['status']) ?></td>
                        <td><?= format_date($st['enrolled_at']) ?></td>
                        <td class="text-end">
                            <?php if ($st['status'] === 'active'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Retirar estudiante?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unenroll_student">
                                <input type="hidden" name="student_id" value="<?= (int) $st['student_id'] ?>">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
