<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();
requiere_rol('student');

$usuario = usuario_actual();
$tituloPagina = 'Catálogo de cursos';
$buscar = trim($_GET['buscar'] ?? '');
$idCategoria = (int) ($_GET['categoria'] ?? 0);

$categories = bd()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$sql = 'SELECT c.*, cat.name AS category_name,
               EXISTS(SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status = "active") AS enrolled
        FROM courses c
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.status NOT IN ("draft", "archived")';
$params = [$usuario['id']];

if ($buscar !== '') {
    $sql .= ' AND (c.title LIKE ? OR c.short_description LIKE ? OR c.description LIKE ? OR c.code LIKE ?)';
    $like = '%' . $buscar . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($idCategoria > 0) {
    $sql .= ' AND c.category_id = ?';
    $params[] = $idCategoria;
}
$sql .= ' ORDER BY c.title';

$stmt = bd()->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_curso_inscripcion'])) {
    verificar_csrf();
    $idCurso = (int) $_POST['id_curso_inscripcion'];
    $curso = obtener_curso($idCurso);
    if ($curso && !in_array($curso['status'] ?? '', ['draft', 'archived'], true)) {
        $resultado = intentar_inscripcion_curso($curso, trim($_POST['clave_inscripcion'] ?? ''));
        mensaje_flash($resultado['tipo'], $resultado['mensaje']);
        if ($resultado['tipo'] === 'success') {
            redirigir('curso.php?id=' . $idCurso);
        }
    } else {
        mensaje_flash('danger', 'Curso no disponible.');
    }
    redirigir('catalogo.php');
}

require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1>Cursos disponibles</h1>
        <p class="subtitle">Todos los cursos publicados. No se muestran borradores ni archivados.</p>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2" method="get">
            <div class="col-md-5">
                <input type="text" name="buscar" class="form-control" value="<?= escapar($buscar) ?>" placeholder="Buscar cursos...">
            </div>
            <div class="col-md-5">
                <select name="categoria" class="form-select">
                    <option value="0">Todas las categorías</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= $idCategoria === (int) $cat['id'] ? 'selected' : '' ?>><?= escapar($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php if (!$cursos): ?>
        <div class="col-12"><div class="panel"><div class="panel-body empty-state"><i class="bi bi-search"></i><p class="mb-0">No hay cursos que coincidan.</p></div></div></div>
    <?php endif; ?>
    <?php foreach ($cursos as $curso): ?>
    <div class="col-md-6 col-xl-4">
        <div class="course-card">
            <div class="course-banner"><span class="code"><?= escapar($curso['code']) ?></span></div>
            <div class="course-body">
                <h3><?= escapar($curso['title']) ?></h3>
                <?php $textoBreve = descripcion_lista_curso($curso); ?>
                <?php if ($textoBreve !== ''): ?>
                    <p><?= escapar($textoBreve) ?></p>
                <?php endif; ?>
                <?php $tipoInscripcion = $curso['enrollment_type'] ?? 'public'; ?>
                <div class="small text-muted mb-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span>Inscripción:</span>
                        <?= insignia_metodo_inscripcion($tipoInscripcion) ?>
                    </div>
                    <?php if (!empty($curso['enrollment_deadline'])): ?>
                    <div>
                        <i class="bi bi-calendar-event me-1"></i>Hasta <?= formatear_fecha($curso['enrollment_deadline']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($curso['enrolled']): ?>
                    <a href="<?= URL_APP ?>/curso.php?id=<?= (int) $curso['id'] ?>" class="btn btn-success w-100"><i class="bi bi-check2 me-1"></i> Ya inscrito · Abrir</a>
                <?php elseif (!inscripcion_abierta($curso)): ?>
                    <p class="small text-muted mb-2">El plazo de inscripción finalizó.</p>
                    <button type="button" class="btn btn-primary w-100" disabled><i class="bi bi-plus-lg me-1"></i> Inscribirse</button>
                <?php elseif ($tipoInscripcion === 'url'): ?>
                    <p class="small text-muted mb-2">Solo puedes inscribirte con el enlace.</p>
                    <button type="button" class="btn btn-primary w-100" disabled><i class="bi bi-plus-lg me-1"></i> Inscribirse</button>
                <?php elseif ($tipoInscripcion === 'password'): ?>
                    <form method="post">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="id_curso_inscripcion" value="<?= (int) $curso['id'] ?>">
                        <div class="mb-2">
                            <input type="password" name="clave_inscripcion" class="form-control form-control-sm" placeholder="Contraseña del curso" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-key me-1"></i> Inscribirse</button>
                    </form>
                <?php else: ?>
                    <form method="post">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="id_curso_inscripcion" value="<?= (int) $curso['id'] ?>">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Inscribirse</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
