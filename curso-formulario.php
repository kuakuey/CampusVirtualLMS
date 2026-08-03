<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();
requiere_rol(['admin', 'teacher']);

$usuario = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);
$curso = $id ? obtener_curso($id) : null;
$tituloPagina = $curso ? 'Editar curso' : 'Nuevo curso';

if ($curso && $usuario['role'] === 'teacher' && (int) $curso['teacher_id'] !== (int) $usuario['id']) {
    mensaje_flash('danger', 'No puedes editar este curso.');
    redirigir('cursos.php');
}

$categories = bd()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$grupos = bd()->query('SELECT * FROM course_groups ORDER BY sort_order, name')->fetchAll();
$teachers = [];
if ($usuario['role'] === 'admin') {
    $teachers = bd()->query('SELECT id, name FROM users WHERE role = "teacher" AND status = 1 ORDER BY name')->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $title = trim($_POST['title'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $idCategoria = (int) ($_POST['category_id'] ?? 0) ?: null;
    $idGrupo = (int) ($_POST['group_id'] ?? 0) ?: null;
    $estado = $_POST['estado'] ?? 'draft';
    $teacherId = $usuario['role'] === 'admin' ? (int) ($_POST['teacher_id'] ?? 0) : (int) $usuario['id'];

    if ($title === '') $errors[] = 'El título es obligatorio.';
    if ($code === '') $errors[] = 'El código es obligatorio.';
    if (!in_array($estado, ['draft', 'published', 'archived'], true)) $estado = 'draft';
    if ($usuario['role'] === 'admin' && $teacherId <= 0) $errors[] = 'Selecciona un docente.';

    if (!$errors) {
        $check = bd()->prepare('SELECT id FROM courses WHERE code = ? AND id != ?');
        $check->execute([$code, $id]);
        if ($check->fetch()) {
            $errors[] = 'Ese código de curso ya existe.';
        }
    }

    if (!$errors) {
        $rutaDocumento = $curso['document_path'] ?? null;
        if (!empty($_POST['quitar_documento'])) {
            eliminar_archivo_subida($rutaDocumento);
            $rutaDocumento = null;
        }
        if (!empty($_FILES['documento']['name'])) {
            eliminar_archivo_subida($rutaDocumento);
            $rutaDocumento = subir_archivo($_FILES['documento'], 'cursos');
            if ($rutaDocumento === null) {
                $errors[] = 'No se pudo subir el documento. Verifica el formato (PDF, Word, Excel, PowerPoint, imágenes o texto).';
            }
        }
    }

    if (!$errors) {
        if ($curso) {
            $stmt = bd()->prepare('UPDATE courses SET category_id=?, group_id=?, teacher_id=?, title=?, code=?, description=?, document_path=?, status=? WHERE id=?');
            $stmt->execute([$idCategoria, $idGrupo, $teacherId, $title, $code, $description, $rutaDocumento, $estado, $id]);
            mensaje_flash('success', 'Curso actualizado.');
        } else {
            $stmt = bd()->prepare('INSERT INTO courses (category_id, group_id, teacher_id, title, code, description, document_path, status) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$idCategoria, $idGrupo, $teacherId, $title, $code, $description, $rutaDocumento, $estado]);
            $id = (int) bd()->lastInsertId();
            mensaje_flash('success', 'Curso creado correctamente.');
        }
        redirigir('curso.php?id=' . $id);
    }
} else {
    $_POST = $curso ?: [
        'title' => '', 'code' => '', 'description' => '', 'category_id' => '', 'group_id' => '', 'status' => 'draft', 'teacher_id' => $usuario['id']
    ];
}

require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><?= escapar($tituloPagina) ?></h1>
        <p class="subtitle">Completa la información del curso</p>
    </div>
    <a href="<?= URL_APP ?>/cursos.php" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="panel" style="max-width: 760px;">
    <div class="panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= escapar($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= campo_csrf() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Título</label>
                    <input type="text" name="title" class="form-control" value="<?= escapar($_POST['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Código</label>
                    <input type="text" name="code" class="form-control" value="<?= escapar($_POST['code'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="4"><?= escapar($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Documento (opcional)</label>
                    <input type="file" name="documento" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp,.txt">
                    <small class="text-muted">PDF, Word, Excel, PowerPoint, imágenes o texto. Los estudiantes podrán previsualizarlo en el curso.</small>
                    <?php if (!empty($curso['document_path'])): ?>
                        <div class="mt-3">
                            <?= renderizar_vista_previa_documento($curso['document_path'], 'Documento actual') ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="quitar_documento" value="1" id="quitar_documento">
                                <label class="form-check-label" for="quitar_documento">Quitar documento actual</label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Grupo de cursos</label>
                    <select name="group_id" class="form-select">
                        <option value="">Sin grupo</option>
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= (int) $grupo['id'] ?>" <?= (int) ($_POST['group_id'] ?? 0) === (int) $grupo['id'] ? 'selected' : '' ?>><?= escapar($grupo['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($usuario['role'] === 'admin' && !$grupos): ?>
                        <small class="text-muted">Crea grupos en <a href="<?= URL_GRUPOS ?>">Administración → Grupos de cursos</a>.</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Categoría</label>
                    <select name="category_id" class="form-select">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (int) ($_POST['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= escapar($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['draft' => 'Borrador', 'published' => 'Publicado', 'archived' => 'Archivado'] as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($_POST['status'] ?? $_POST['estado'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($usuario['role'] === 'admin'): ?>
                <div class="col-md-6">
                    <label class="form-label">Docente</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (int) ($_POST['teacher_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= escapar($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="<?= URL_APP ?>/cursos.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
