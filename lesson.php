<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT l.*, c.title AS course_title, c.teacher_id, c.id AS course_id
     FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?'
);
$stmt->execute([$id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    flash('danger', 'Lección no encontrada.');
    redirect('courses.php');
}

$course = get_course((int) $lesson['course_id']);
if (!$course || !can_access_course($course)) {
    flash('danger', 'No tienes acceso a esta lección.');
    redirect('courses.php');
}

$siblings = db()->prepare('SELECT id, title, sort_order FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
$siblings->execute([(int) $lesson['course_id']]);
$siblings = $siblings->fetchAll();

$prev = $next = null;
foreach ($siblings as $i => $sib) {
    if ((int) $sib['id'] === $id) {
        $prev = $siblings[$i - 1] ?? null;
        $next = $siblings[$i + 1] ?? null;
        break;
    }
}

$pageTitle = $lesson['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="sidebar-course">
            <div class="panel-header"><h3 class="mb-0"><?= e($lesson['course_title']) ?></h3></div>
            <div class="list-group list-group-flush">
                <?php foreach ($siblings as $i => $sib): ?>
                    <a href="<?= APP_URL ?>/lesson.php?id=<?= (int) $sib['id'] ?>"
                       class="list-group-item list-group-item-action <?= (int) $sib['id'] === $id ? 'active' : '' ?>">
                        <span class="text-muted me-1"><?= $i + 1 ?>.</span> <?= e($sib['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="<?= APP_URL ?>/course.php?id=<?= (int) $lesson['course_id'] ?>" class="btn btn-outline-secondary w-100 mt-3">
            <i class="bi bi-arrow-left me-1"></i> Volver al curso
        </a>
    </div>
    <div class="col-lg-9">
        <div class="panel">
            <div class="panel-header">
                <h2><?= e($lesson['title']) ?></h2>
            </div>
            <div class="panel-body">
                <?php if ($lesson['video_url']): ?>
                    <div class="ratio ratio-16x9 mb-4 rounded overflow-hidden">
                        <?php
                        $video = $lesson['video_url'];
                        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $video, $m)) {
                            $embed = 'https://www.youtube.com/embed/' . $m[1];
                            echo '<iframe src="' . e($embed) . '" allowfullscreen></iframe>';
                        } else {
                            echo '<a href="' . e($video) . '" target="_blank" class="btn btn-primary">Ver video</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                <div class="content-html">
                    <?= $lesson['content'] ?: '<p class="text-muted">Sin contenido.</p>' ?>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-3">
            <?php if ($prev): ?>
                <a href="<?= APP_URL ?>/lesson.php?id=<?= (int) $prev['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-chevron-left"></i> <?= e($prev['title']) ?></a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($next): ?>
                <a href="<?= APP_URL ?>/lesson.php?id=<?= (int) $next['id'] ?>" class="btn btn-primary"><?= e($next['title']) ?> <i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
