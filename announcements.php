<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['admin', 'teacher']);

$user = current_user();
$pageTitle = 'Anuncios';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $courseId = (int) ($_POST['course_id'] ?? 0) ?: null;
        $isGlobal = $user['role'] === 'admin' && !empty($_POST['is_global']) ? 1 : 0;

        if ($isGlobal) {
            $courseId = null;
        }

        if ($courseId) {
            $course = get_course($courseId);
            if (!$course || ($user['role'] === 'teacher' && (int) $course['teacher_id'] !== (int) $user['id'])) {
                flash('danger', 'Curso no válido.');
                redirect('announcements.php');
            }
        }

        if ($title !== '' && $body !== '' && ($isGlobal || $courseId)) {
            $stmt = db()->prepare('INSERT INTO announcements (course_id, author_id, title, body, is_global) VALUES (?,?,?,?,?)');
            $stmt->execute([$courseId, $user['id'], $title, $body, $isGlobal]);
            flash('success', 'Anuncio publicado.');
        } else {
            flash('danger', 'Completa título, mensaje y destino.');
        }
        redirect('announcements.php');
    }

    if ($action === 'delete') {
        $aid = (int) ($_POST['announcement_id'] ?? 0);
        if ($user['role'] === 'admin') {
            $stmt = db()->prepare('DELETE FROM announcements WHERE id = ?');
            $stmt->execute([$aid]);
        } else {
            $stmt = db()->prepare('DELETE FROM announcements WHERE id = ? AND author_id = ?');
            $stmt->execute([$aid, $user['id']]);
        }
        flash('success', 'Anuncio eliminado.');
        redirect('announcements.php');
    }
}

if ($user['role'] === 'admin') {
    $courses = db()->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
    $announcements = db()->query(
        'SELECT a.*, u.name AS author_name, c.title AS course_title
         FROM announcements a
         JOIN users u ON u.id = a.author_id
         LEFT JOIN courses c ON c.id = a.course_id
         ORDER BY a.created_at DESC'
    )->fetchAll();
} else {
    $stmt = db()->prepare('SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title');
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
    $stmt = db()->prepare(
        'SELECT a.*, u.name AS author_name, c.title AS course_title
         FROM announcements a
         JOIN users u ON u.id = a.author_id
         LEFT JOIN courses c ON c.id = a.course_id
         WHERE a.author_id = ? OR a.course_id IN (SELECT id FROM courses WHERE teacher_id = ?)
         ORDER BY a.created_at DESC'
    );
    $stmt->execute([$user['id'], $user['id']]);
    $announcements = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Anuncios</h1>
        <p class="subtitle">Comunica novedades a tus estudiantes</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header"><h2>Nuevo anuncio</h2></div>
            <div class="panel-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje</label>
                        <textarea name="body" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Curso</label>
                        <select name="course_id" class="form-select" <?= $user['role'] === 'admin' ? '' : 'required' ?>>
                            <?php if ($user['role'] === 'admin'): ?><option value="">— Solo global —</option><?php endif; ?>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($user['role'] === 'admin'): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_global" value="1" id="is_global">
                        <label class="form-check-label" for="is_global">Anuncio global (toda la plataforma)</label>
                    </div>
                    <?php endif; ?>
                    <button class="btn btn-primary w-100" type="submit">Publicar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header"><h2>Publicados</h2></div>
            <div class="panel-body">
                <?php if (!$announcements): ?>
                    <div class="empty-state"><i class="bi bi-megaphone"></i><p class="mb-0">No hay anuncios.</p></div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="d-flex justify-content-between gap-3 mb-3 pb-3 border-bottom">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <strong><?= e($ann['title']) ?></strong>
                                    <?php if ($ann['is_global']): ?><span class="badge bg-info">Global</span>
                                    <?php elseif ($ann['course_title']): ?><span class="badge bg-secondary"><?= e($ann['course_title']) ?></span><?php endif; ?>
                                </div>
                                <p class="mb-1"><?= nl2br(e($ann['body'])) ?></p>
                                <small class="text-muted"><?= e($ann['author_name']) ?> · <?= format_date($ann['created_at'], true) ?></small>
                            </div>
                            <form method="post" onsubmit="return confirm('¿Eliminar anuncio?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="announcement_id" value="<?= (int) $ann['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
