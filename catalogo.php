<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();
requiere_rol('student');

$usuario = usuario_actual();
$tituloPagina = 'Catálogo de cursos';
$buscar = trim($_GET['buscar'] ?? '');
$idCategoria = (int) ($_GET['categoria'] ?? 0);
$idGrupo = (int) ($_GET['grupo'] ?? 0);

$categories = bd()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$grupos = bd()->query('SELECT * FROM course_groups ORDER BY sort_order, name')->fetchAll();

$sql = 'SELECT c.*, u.name AS teacher_name, cat.name AS category_name, g.name AS group_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students,
               EXISTS(SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status = "active") AS enrolled
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        LEFT JOIN course_groups g ON g.id = c.group_id
        WHERE c.status = "published" AND c.enrollment_type IN ("public", "password")';
$params = [$usuario['id']];

if ($buscar !== '') {
    $sql .= ' AND (c.title LIKE ? OR c.description LIKE ? OR c.code LIKE ?)';
    $like = '%' . $buscar . '%';
    array_push($params, $like, $like, $like);
}
if ($idCategoria > 0) {
    $sql .= ' AND c.category_id = ?';
    $params[] = $idCategoria;
}
if ($idGrupo > 0) {
    $sql .= ' AND c.group_id = ?';
    $params[] = $idGrupo;
}
$sql .= ' ORDER BY g.sort_order, g.name, c.title';

$stmt = bd()->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_curso_inscripcion'])) {
    verificar_csrf();
    $idCurso = (int) $_POST['id_curso_inscripcion'];
    $curso = obtener_curso($idCurso);
    if ($curso && $curso['status'] === 'published' && in_array($curso['enrollment_type'] ?? 'public', ['public', 'password'], true)) {
        if (esta_matriculado($idCurso)) {
            mensaje_flash('info', 'Ya estás inscrito en este curso.');
        } elseif (($curso['enrollment_type'] ?? 'public') === 'password') {
            $clave = trim($_POST['clave_inscripcion'] ?? '');
            if ($clave === '' || empty($curso['enrollment_password']) || !password_verify($clave, $curso['enrollment_password'])) {
                mensaje_flash('danger', 'Contraseña de inscripción incorrecta.');
            } else {
                inscribir_estudiante_en_curso($idCurso, (int) $usuario['id']);
                mensaje_flash('success', 'Te has inscrito en «' . $curso['title'] . '».');
            }
        } else {
            inscribir_estudiante_en_curso($idCurso, (int) $usuario['id']);
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
            <div class="col-md-4">
                <input type="text" name="buscar" class="form-control" value="<?= escapar($buscar) ?>" placeholder="Buscar cursos...">
            </div>
            <div class="col-md-3">
                <select name="grupo" class="form-select">
                    <option value="0">Todos los grupos</option>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= (int) $grupo['id'] ?>" <?= $idGrupo === (int) $grupo['id'] ? 'selected' : '' ?>><?= escapar($grupo['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
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
                    <?= escapar($curso['teacher_name']) ?>
                    <?php if ($curso['group_name']): ?> · <?= escapar($curso['group_name']) ?><?php endif; ?>
                    · <?= escapar($curso['category_name'] ?? 'General') ?> · <?= (int) $curso['students'] ?> alumnos
                </div>
                <?php if ($curso['enrolled']): ?>
                    <a href="<?= URL_APP ?>/curso.php?id=<?= (int) $curso['id'] ?>" class="btn btn-success w-100"><i class="bi bi-check2 me-1"></i> Ya inscrito · Abrir</a>
                <?php elseif (($curso['enrollment_type'] ?? 'public') === 'password'): ?>
                    <form method="post">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="id_curso_inscripcion" value="<?= (int) $curso['id'] ?>">
                        <div class="mb-2">
                            <input type="password" name="clave_inscripcion" class="form-control form-control-sm" placeholder="Contraseña del curso" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-key me-1"></i> Inscribirme</button>
                    </form>
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
