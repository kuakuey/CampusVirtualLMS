<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();
requiere_rol(['admin', 'teacher']);

$usuario = usuario_actual();
$id = (int) ($_GET['id'] ?? 0);
$esNuevo = !$id;
$curso = $id ? obtener_curso($id) : null;

if (!$esNuevo && !$curso) {
    mensaje_flash('danger', 'Curso no encontrado.');
    redirigir('cursos.php');
}

if ($curso && $usuario['role'] === 'teacher' && (int) $curso['teacher_id'] !== (int) $usuario['id']) {
    mensaje_flash('danger', 'No puedes editar este curso.');
    redirigir('cursos.php');
}

$tituloPagina = $esNuevo ? 'Nuevo curso' : 'Editar curso';

$categories = bd()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$grupos = bd()->query('SELECT * FROM course_groups ORDER BY sort_order, name')->fetchAll();
$teachers = [];
if ($usuario['role'] === 'admin') {
    $teachers = bd()->query('SELECT id, name FROM users WHERE role = "teacher" AND status = 1 ORDER BY name')->fetchAll();
}

$errors = [];
$codigoGenerado = generar_codigo_curso_unico();
$urlInscripcion = $curso ? url_inscripcion_curso($curso) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? 'guardar';

    if (!$esNuevo && $accion === 'regenerar_enlace') {
        $token = generar_token_inscripcion();
        $consulta = bd()->prepare('UPDATE courses SET enrollment_token = ? WHERE id = ?');
        $consulta->execute([$token, $id]);
        mensaje_flash('success', 'Enlace de inscripción regenerado.');
        redirigir('curso-formulario.php?id=' . $id);
    }

    $title = trim($_POST['title'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $idCategoria = (int) ($_POST['category_id'] ?? 0) ?: null;
    $idGrupo = (int) ($_POST['group_id'] ?? 0) ?: null;
    $estado = $_POST['estado'] ?? 'draft';
    $tipoInscripcion = $_POST['enrollment_type'] ?? 'public';
    $claveInscripcion = trim($_POST['enrollment_password'] ?? '');
    $teacherId = $usuario['role'] === 'admin' ? (int) ($_POST['teacher_id'] ?? 0) : (int) $usuario['id'];

    if ($title === '') $errors[] = 'El título es obligatorio.';
    if ($code === '') $errors[] = 'El código es obligatorio.';
    if (!preg_match('/^CDA-[A-Z0-9]+$/', $code)) $errors[] = 'El código debe empezar por CDA-.';
    if (!in_array($estado, ['draft', 'published', 'archived'], true)) $estado = 'draft';
    if (!in_array($tipoInscripcion, ['public', 'password', 'url'], true)) $tipoInscripcion = 'public';
    if ($usuario['role'] === 'admin' && $teacherId <= 0) $errors[] = 'Selecciona un docente.';
    if ($tipoInscripcion === 'password' && $claveInscripcion === '' && ($esNuevo || empty($curso['enrollment_password']))) {
        $errors[] = 'Define una contraseña de inscripción.';
    }

    if (!$errors) {
        $check = bd()->prepare('SELECT id FROM courses WHERE code = ? AND id != ?');
        $check->execute([$code, $id]);
        if ($check->fetch()) {
            $errors[] = 'Ese código de curso ya existe.';
        }
    }

    if (!$errors) {
        $claveHash = $esNuevo ? null : ($curso['enrollment_password'] ?? null);
        if ($tipoInscripcion === 'password') {
            if ($claveInscripcion !== '') {
                $claveHash = password_hash($claveInscripcion, PASSWORD_DEFAULT);
            }
        } else {
            $claveHash = null;
        }

        $token = generar_token_inscripcion();

        if ($esNuevo) {
            $stmt = bd()->prepare(
                'INSERT INTO courses (category_id, group_id, teacher_id, title, code, description, status, enrollment_type, enrollment_password, enrollment_token)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$idCategoria, $idGrupo, $teacherId, $title, $code, $description, $estado, $tipoInscripcion, $claveHash, $token]);
            $id = (int) bd()->lastInsertId();
            mensaje_flash('success', 'Curso creado correctamente.');
        } else {
            if ($tipoInscripcion === 'url') {
                $token = asegurar_token_inscripcion_curso($id);
            } else {
                $token = $curso['enrollment_token'] ?? null;
            }
            $stmt = bd()->prepare(
                'UPDATE courses SET category_id=?, group_id=?, teacher_id=?, title=?, code=?, description=?, status=?, enrollment_type=?, enrollment_password=?, enrollment_token=? WHERE id=?'
            );
            $stmt->execute([$idCategoria, $idGrupo, $teacherId, $title, $code, $description, $estado, $tipoInscripcion, $claveHash, $token, $id]);
            mensaje_flash('success', 'Curso actualizado.');
        }
        redirigir('curso.php?id=' . $id);
    }

    $codigoGenerado = $code;
    if (!$esNuevo) {
        $curso = array_merge($curso, [
            'title' => $title,
            'code' => $code,
            'description' => $description,
            'category_id' => $idCategoria,
            'group_id' => $idGrupo,
            'status' => $estado,
            'enrollment_type' => $tipoInscripcion,
            'teacher_id' => $teacherId,
        ]);
        $urlInscripcion = url_inscripcion_curso($curso);
    }
} elseif ($esNuevo) {
    $_POST = [
        'title' => '',
        'code' => $codigoGenerado,
        'description' => '',
        'category_id' => '',
        'group_id' => '',
        'status' => 'draft',
        'enrollment_type' => 'public',
        'teacher_id' => $usuario['role'] === 'admin' ? '' : $usuario['id'],
    ];
} else {
    $_POST = $curso;
}

require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><?= escapar($tituloPagina) ?></h1>
        <p class="subtitle"><?= $esNuevo ? 'Completa los datos para crear el curso' : 'Configura la información y el método de inscripción' ?></p>
    </div>
    <a href="<?= URL_APP ?>/<?= $esNuevo ? 'cursos.php' : 'curso.php?id=' . $id ?>" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="panel" style="max-width: 760px;">
    <div class="panel-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= escapar($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="guardar">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Título</label>
                    <input type="text" name="title" class="form-control" value="<?= escapar($_POST['title'] ?? '') ?>" required autofocus>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Código</label>
                    <input type="text" name="code" class="form-control bg-light" value="<?= escapar($_POST['code'] ?? $codigoGenerado) ?>" readonly required>
                    <small class="text-muted">Generado automáticamente</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="4"><?= escapar($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Método de inscripción</label>
                    <select name="enrollment_type" class="form-select" id="enrollment_type">
                        <option value="public" <?= ($_POST['enrollment_type'] ?? 'public') === 'public' ? 'selected' : '' ?>>Público — cualquier estudiante puede inscribirse desde el catálogo</option>
                        <option value="password" <?= ($_POST['enrollment_type'] ?? '') === 'password' ? 'selected' : '' ?>>Con contraseña — requiere clave para inscribirse</option>
                        <option value="url" <?= ($_POST['enrollment_type'] ?? '') === 'url' ? 'selected' : '' ?>>Por URL — inscripción automática al abrir el enlace</option>
                    </select>
                </div>

                <div class="col-12" id="campo-clave-inscripcion" style="display:none;">
                    <label class="form-label">Contraseña de inscripción</label>
                    <input type="text" name="enrollment_password" class="form-control" placeholder="<?= !$esNuevo && !empty($curso['enrollment_password']) ? 'Dejar vacío para mantener la actual' : 'Contraseña requerida' ?>">
                </div>

                <?php if (!$esNuevo): ?>
                <div class="col-12" id="campo-url-inscripcion" style="display:none;">
                    <label class="form-label">Enlace de inscripción automática</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="url-inscripcion" value="<?= escapar($urlInscripcion) ?>" readonly>
                        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('url-inscripcion').value)"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <small class="text-muted">Comparte este enlace. Al abrirlo, el estudiante se inscribe automáticamente.</small>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <label class="form-label">Grupo de cursos</label>
                    <select name="group_id" class="form-select">
                        <option value="">Sin grupo</option>
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= (int) $grupo['id'] ?>" <?= (int) ($_POST['group_id'] ?? 0) === (int) $grupo['id'] ? 'selected' : '' ?>><?= escapar($grupo['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary"><?= $esNuevo ? 'Crear curso' : 'Guardar' ?></button>
                    <a href="<?= URL_APP ?>/<?= $esNuevo ? 'cursos.php' : 'curso.php?id=' . $id ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
        <?php if (!$esNuevo && ($curso['enrollment_type'] ?? '') === 'url'): ?>
        <form method="post" class="mt-3" onsubmit="return confirm('¿Generar un nuevo enlace? El anterior dejará de funcionar.');">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="regenerar_enlace">
            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-repeat me-1"></i> Regenerar enlace de inscripción</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const select = document.getElementById('enrollment_type');
    const campoClave = document.getElementById('campo-clave-inscripcion');
    const campoUrl = document.getElementById('campo-url-inscripcion');
    function actualizarCampos() {
        const v = select.value;
        if (campoClave) campoClave.style.display = v === 'password' ? '' : 'none';
        if (campoUrl) campoUrl.style.display = v === 'url' ? '' : 'none';
    }
    select.addEventListener('change', actualizarCampos);
    actualizarCampos();
})();
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
