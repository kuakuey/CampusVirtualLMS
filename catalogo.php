<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();
requiere_rol('student');

$usuario = usuario_actual();
$tituloPagina = 'Catálogo de cursos';
$buscar = trim($_GET['buscar'] ?? '');
$idCategoria = (int) ($_GET['categoria'] ?? 0);

$categories = bd()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$sql = 'SELECT c.*, u.name AS teacher_name, cat.name AS category_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students,
               EXISTS(SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status = "active") AS enrolled
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.status = "published"';
$params = [$usuario['id']];

if ($q !== '') {
    $sql .= ' AND (c.title LIKE ? OR c.description LIKE ? OR c.code LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
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
    if ($curso && $curso['status'] === 'published') {
        if (esta_matriculado($idCurso)) {
            mensaje_flash('info', 'Ya estás inscrito en este curso.');
        } else {
            $inscripcion = bd()->prepare('INSERT INTO enrollments (course_id, student_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = "active"');
            $inscripcion->execute([$idCurso, $usuario['id']]);
            mensaje_flash('success', 'Te has inscrito en «' . $curso['title'] . '».');
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
        <h1>Catálogo</h1>
        <p class="subtitle">Explora e inscríbete en cursos publicados</p>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2" method="get">
            <div class="col-md-6">
                <input type="text" name="buscar" class="form-control" value="<?= escapar($q) ?>" placeholder="Buscar cursos...">
            </div>
            <div class="col-md-4">
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
                <p><?= escapar(mb_strimwidth($curso['description'] ?? '', 0, 110, '…')) ?></p>
                <div class="small text-muted mb-3">
                    <?= escapar($curso['teacher_name']) ?> · <?= escapar($curso['category_name'] ?? 'General') ?> · <?= (int) $curso['students'] ?> alumnos
                </div>
                <?php if ($curso['enrolled']): ?>
                    <a href="<?= URL_APP ?>/curso.php?id=<?= (int) $curso['id'] ?>" class="btn btn-success w-100"><i class="bi bi-check2 me-1"></i> Ya inscrito · Abrir</a>
                <?php else: ?>
                    <form method="post">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="id_curso_inscripcion" value="<?= (int) $curso['id'] ?>">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Inscribirme</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
