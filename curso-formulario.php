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
    $shortDescription = trim($_POST['short_description'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $idCategoria = (int) ($_POST['category_id'] ?? 0) ?: null;
    $estado = $_POST['estado'] ?? 'draft';
    $tipoInscripcion = $_POST['enrollment_type'] ?? 'public';
    $claveInscripcion = trim($_POST['enrollment_password'] ?? '');
    $fechaLimiteInscripcion = trim($_POST['enrollment_deadline'] ?? '');
    $teacherId = $esNuevo ? (int) $usuario['id'] : (int) $curso['teacher_id'];

    if ($title === '') $errors[] = 'El título es obligatorio.';
    if ($code === '') $errors[] = 'El código es obligatorio.';
    if (!preg_match('/^CDA-[A-Z0-9]+$/', $code)) $errors[] = 'El código debe empezar por CDA-.';
    if (mb_strlen($shortDescription) > 255) $errors[] = 'La descripción breve no puede superar 255 caracteres.';
    if (!in_array($estado, ['draft', 'published', 'archived'], true)) $estado = 'draft';
    if (!in_array($tipoInscripcion, ['public', 'password', 'url'], true)) $tipoInscripcion = 'public';
    if ($tipoInscripcion === 'password' && $claveInscripcion === '' && ($esNuevo || empty($curso['enrollment_password']))) {
        $errors[] = 'Define una contraseña de inscripción.';
    }
    if ($fechaLimiteInscripcion !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaLimiteInscripcion)) {
        $errors[] = 'La fecha límite de inscripción no es válida.';
    }
    $fechaLimiteInscripcion = $fechaLimiteInscripcion !== '' ? $fechaLimiteInscripcion : null;

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
                'INSERT INTO courses (category_id, teacher_id, title, code, short_description, description, status, enrollment_type, enrollment_password, enrollment_token, enrollment_deadline)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$idCategoria, $teacherId, $title, $code, $shortDescription !== '' ? $shortDescription : null, $description, $estado, $tipoInscripcion, $claveHash, $token, $fechaLimiteInscripcion]);
            $id = (int) bd()->lastInsertId();
            mensaje_flash('success', 'Curso creado correctamente.');
        } else {
            if ($tipoInscripcion === 'url') {
                $token = asegurar_token_inscripcion_curso($id);
            } else {
                $token = $curso['enrollment_token'] ?? null;
            }
            $stmt = bd()->prepare(
                'UPDATE courses SET category_id=?, teacher_id=?, title=?, code=?, short_description=?, description=?, status=?, enrollment_type=?, enrollment_password=?, enrollment_token=?, enrollment_deadline=? WHERE id=?'
            );
            $stmt->execute([$idCategoria, $teacherId, $title, $code, $shortDescription !== '' ? $shortDescription : null, $description, $estado, $tipoInscripcion, $claveHash, $token, $fechaLimiteInscripcion, $id]);
            mensaje_flash('success', 'Curso actualizado.');
        }
        redirigir('curso.php?id=' . $id);
    }

    $codigoGenerado = $code;
    if (!$esNuevo) {
        $curso = array_merge($curso, [
            'title' => $title,
            'code' => $code,
            'short_description' => $shortDescription,
            'description' => $description,
            'category_id' => $idCategoria,
            'status' => $estado,
            'enrollment_type' => $tipoInscripcion,
            'enrollment_deadline' => $fechaLimiteInscripcion,
            'teacher_id' => $teacherId,
        ]);
        $urlInscripcion = url_inscripcion_curso($curso);
    }
} elseif ($esNuevo) {
    $_POST = [
        'title' => '',
        'code' => $codigoGenerado,
        'short_description' => '',
        'description' => '',
        'category_id' => '',
        'status' => 'draft',
        'enrollment_type' => 'public',
        'enrollment_deadline' => '',
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
                    <label class="form-label">Descripción breve</label>
                    <textarea name="short_description" class="form-control" rows="2" maxlength="255" placeholder="Se muestra en el listado y el catálogo de cursos"><?= escapar($_POST['short_description'] ?? '') ?></textarea>
                    <small class="text-muted">Hasta 255 caracteres. Aparece en las tarjetas de cursos.</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción larga</label>
                    <textarea name="description" class="form-control" rows="6" placeholder="Se muestra al abrir el curso"><?= escapar($_POST['description'] ?? '') ?></textarea>
                    <small class="text-muted">Puedes usar HTML para formato, listas, enlaces e imágenes. Se ve al entrar al curso.</small>
                </div>

                <div class="col-12">
                    <label class="form-label">Método de inscripción</label>
                    <select name="enrollment_type" class="form-select" id="enrollment_type">
                        <option value="public" <?= ($_POST['enrollment_type'] ?? 'public') === 'public' ? 'selected' : '' ?>>Público — visible y cualquiera puede inscribirse</option>
                        <option value="password" <?= ($_POST['enrollment_type'] ?? '') === 'password' ? 'selected' : '' ?>>Con contraseña — visible; se inscribe con clave</option>
                        <option value="url" <?= ($_POST['enrollment_type'] ?? '') === 'url' ? 'selected' : '' ?>>Por URL (privado) — visible sin acceso; inscripción solo con el enlace</option>
                    </select>
                    <small class="text-muted">Todos los cursos publicados se ven en el catálogo. URL y contraseña no dan acceso al contenido hasta inscribirse.</small>
                </div>

                <div class="col-12" id="campo-clave-inscripcion" style="display:none;">
                    <label class="form-label">Contraseña de inscripción</label>
                    <input type="text" name="enrollment_password" class="form-control" placeholder="<?= !$esNuevo && !empty($curso['enrollment_password']) ? 'Dejar vacío para mantener la actual' : 'Contraseña requerida' ?>">
                </div>

                <div class="col-md-6" id="campo-fecha-limite-inscripcion">
                    <label class="form-label">Fecha límite de inscripción</label>
                    <input type="date" name="enrollment_deadline" class="form-control" value="<?= escapar(fecha_para_input($_POST['enrollment_deadline'] ?? null)) ?>">
                    <small class="text-muted">Opcional. Después de esta fecha no habrá nuevas inscripciones.</small>
                </div>

                <?php if (!$esNuevo): ?>
                <div class="col-12" id="campo-url-inscripcion" style="display:none;">
                    <label class="form-label">Enlace de inscripción automática</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="url-inscripcion" value="<?= escapar($urlInscripcion) ?>" readonly>
                        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('url-inscripcion').value)"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <small class="text-muted">Comparte este enlace. Al abrirlo, el estudiante se inscribe automáticamente. El curso se ve en el catálogo, pero sin acceso al contenido.</small>
                </div>
                <?php endif; ?>

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
