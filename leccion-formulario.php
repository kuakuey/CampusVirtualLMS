<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$usuario = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);
$leccion = $id ? obtener_leccion($id) : null;

if (!$leccion) {
    mensaje_flash('danger', 'Lección no encontrada.');
    redirigir('cursos.php');
}

$curso = obtener_curso((int) $leccion['course_id']);
if (!$curso || !es_propietario_curso($curso, $usuario)) {
    mensaje_flash('danger', 'No tienes permiso para editar esta lección.');
    redirigir('curso.php?id=' . (int) $leccion['course_id']);
}

$idCurso = (int) $leccion['course_id'];
$tituloPagina = 'Editar lección';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video = trim($_POST['video_url'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);

    if ($title === '') {
        $errors[] = 'El título es obligatorio.';
    }

    $adjunto = $leccion['attachment'] ?? null;
    if (!empty($_POST['quitar_documento'])) {
        eliminar_archivo_subida($adjunto);
        $adjunto = null;
    }
    if (!empty($_FILES['documento']['name'])) {
        eliminar_archivo_subida($adjunto);
        $adjunto = subir_archivo($_FILES['documento'], 'lecciones');
        if ($adjunto === null) {
            $errors[] = 'No se pudo subir el documento. Verifica el formato.';
        }
    }

    if (!$errors) {
        $consulta = bd()->prepare(
            'UPDATE lessons SET title=?, content=?, video_url=?, sort_order=?, attachment=? WHERE id=? AND course_id=?'
        );
        $consulta->execute([$title, $content, $video ?: null, $order, $adjunto, $id, $idCurso]);
        mensaje_flash('success', 'Lección actualizada.');
        redirigir('leccion.php?id=' . $id);
    }

    $leccion = array_merge($leccion, [
        'title' => $title,
        'content' => $content,
        'video_url' => $video,
        'sort_order' => $order,
        'attachment' => $adjunto,
    ]);
}

require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Editar lección</h1>
        <p class="subtitle"><?= escapar($curso['title']) ?> · <?= escapar($leccion['title']) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= URL_APP ?>/leccion.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-eye me-1"></i> Ver lección</a>
        <a href="<?= URL_APP ?>/curso.php?id=<?= $idCurso ?>&pestaña=lecciones" class="btn btn-outline-secondary">Volver al curso</a>
    </div>
</div>

<div class="panel" style="max-width: 760px;">
    <div class="panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= escapar($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= campo_csrf() ?>
            <div class="mb-3">
                <label class="form-label">Título</label>
                <input type="text" name="title" class="form-control" value="<?= escapar($leccion['title']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contenido (HTML permitido)</label>
                <textarea name="content" class="form-control" rows="8"><?= escapar($leccion['content'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">URL de video (opcional)</label>
                <input type="url" name="video_url" class="form-control" value="<?= escapar($leccion['video_url'] ?? '') ?>" placeholder="https://...">
            </div>
            <div class="mb-3">
                <label class="form-label">Orden</label>
                <input type="number" name="sort_order" class="form-control" value="<?= (int) ($leccion['sort_order'] ?? 0) ?>" min="0">
            </div>
            <div class="mb-3">
                <label class="form-label">Documento (opcional)</label>
                <input type="file" name="documento" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp,.txt">
                <small class="text-muted">Sube un archivo para reemplazar el documento actual.</small>
                <?php if (!empty($leccion['attachment'])): ?>
                    <div class="mt-3">
                        <?= renderizar_vista_previa_documento($leccion['attachment'], 'Documento actual') ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="quitar_documento" value="1" id="quitar_documento">
                            <label class="form-check-label" for="quitar_documento">Quitar documento actual</label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a href="<?= URL_APP ?>/leccion.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
