<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$usuario = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);

$stmt = bd()->prepare(
    'SELECT l.*, c.title AS course_title, c.teacher_id, c.id AS course_id
     FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?'
);
$stmt->execute([$id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    mensaje_flash('danger', 'Lección no encontrada.');
    redirigir('cursos.php');
}

$curso = obtener_curso((int) $lesson['course_id']);
if (!$curso || !puede_acceder_curso($curso)) {
    mensaje_flash('danger', 'No tienes acceso a esta lección.');
    redirigir('cursos.php');
}

$siblings = bd()->prepare('SELECT id, title, sort_order FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
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

$tituloPagina = $lesson['title'];
require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="sidebar-course">
            <div class="panel-header"><h3 class="mb-0"><?= escapar($lesson['course_title']) ?></h3></div>
            <div class="list-group list-group-flush">
                <?php foreach ($siblings as $i => $sib): ?>
                    <a href="<?= URL_APP ?>/leccion.php?id=<?= (int) $sib['id'] ?>"
                       class="list-group-item list-group-item-action <?= (int) $sib['id'] === $id ? 'active' : '' ?>">
                        <span class="text-muted me-1"><?= $i + 1 ?>.</span> <?= escapar($sib['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="<?= URL_APP ?>/curso.php?id=<?= (int) $lesson['course_id'] ?>" class="btn btn-outline-secondary w-100 mt-3">
            <i class="bi bi-arrow-left me-1"></i> Volver al curso
        </a>
    </div>
    <div class="col-lg-9">
        <div class="panel">
            <div class="panel-header">
                <h2><?= escapar($lesson['title']) ?></h2>
            </div>
            <div class="panel-body">
                <?php if ($lesson['video_url']): ?>
                    <div class="ratio ratio-16x9 mb-4 rounded overflow-hidden">
                        <?php
                        $video = $lesson['video_url'];
                        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $video, $m)) {
                            $embed = 'https://www.youtube.com/embed/' . $m[1];
                            echo '<iframe src="' . escapar($embed) . '" allowfullscreen></iframe>';
                        } else {
                            echo '<a href="' . escapar($video) . '" target="_blank" class="btn btn-primary">Ver video</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($lesson['attachment'])): ?>
                    <?= renderizar_vista_previa_documento($lesson['attachment'], 'Documento de la lección') ?>
                <?php endif; ?>
                <div class="content-html">
                    <?= $lesson['content'] ?: '<p class="text-muted">Sin contenido.</p>' ?>
                </div>
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

<?php require_once __DIR__ . '/includes/pie.php'; ?>
