<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$usuario = usuario_actual();
$tituloPagina = 'Cursos';
$buscar = trim($_GET['buscar'] ?? '');
$estado = $_GET['estado'] ?? '';
$idGrupo = (int) ($_GET['grupo'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_curso') {
    verificar_csrf();
    $idCurso = (int) ($_POST['id_curso'] ?? 0);
    $cursoEliminar = $idCurso ? obtener_curso($idCurso) : null;
    $puedeEliminar = $cursoEliminar && (
        $usuario['role'] === 'admin'
        || ($usuario['role'] === 'teacher' && (int) $cursoEliminar['teacher_id'] === (int) $usuario['id'])
    );
    if ($puedeEliminar) {
        $consulta = bd()->prepare('DELETE FROM courses WHERE id = ?');
        $consulta->execute([$idCurso]);
        mensaje_flash('success', 'Curso «' . $cursoEliminar['title'] . '» eliminado.');
    } else {
        mensaje_flash('danger', 'No tienes permiso para eliminar este curso.');
    }
    redirigir('cursos.php');
}

$grupos = bd()->query('SELECT id, name FROM course_groups ORDER BY sort_order, name')->fetchAll();

$sql = 'SELECT c.*, u.name AS teacher_name, cat.name AS category_name, g.name AS group_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lessons_count
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        LEFT JOIN course_groups g ON g.id = c.group_id
        WHERE 1=1';
$params = [];

if ($usuario['role'] === 'teacher') {
    $sql .= ' AND c.teacher_id = ?';
    $params[] = $usuario['id'];
} elseif ($usuario['role'] === 'student') {
    $sql .= ' AND EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ? AND e.status = "active")';
    $params[] = $usuario['id'];
}

if ($buscar !== '') {
    $sql .= ' AND (c.title LIKE ? OR c.code LIKE ? OR c.description LIKE ?)';
    $like = '%' . $buscar . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($estado !== '' && in_array($estado, ['draft', 'published', 'archived'], true)) {
    $sql .= ' AND c.status = ?';
    $params[] = $estado;
}

if ($idGrupo > 0) {
    $sql .= ' AND c.group_id = ?';
    $params[] = $idGrupo;
}

$sql .= ' ORDER BY g.sort_order, g.name, c.created_at DESC';
$stmt = bd()->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll();

require_once __DIR__ . '/includes/encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><?= $usuario['role'] === 'student' ? 'Mis cursos' : 'Cursos' ?></h1>
        <p class="subtitle"><?= count($cursos) ?> curso(s) encontrado(s)</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($usuario['role'] === 'student'): ?>
            <a href="<?= URL_APP ?>/catalogo.php" class="btn btn-outline-primary"><i class="bi bi-grid me-1"></i> Catálogo</a>
        <?php endif; ?>
        <?php if (in_array($usuario['role'], ['admin', 'teacher'], true)): ?>
            <a href="<?= URL_APP ?>/curso-formulario.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nuevo curso</a>
        <?php endif; ?>
        <?php if ($usuario['role'] === 'admin'): ?>
            <a href="<?= URL_GRUPOS ?>" class="btn btn-outline-secondary"><i class="bi bi-collection me-1"></i> Grupos</a>
        <?php endif; ?>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="buscar" class="form-control" value="<?= escapar($buscar) ?>" placeholder="Título, código o descripción">
            </div>
            <?php if ($usuario['role'] !== 'student'): ?>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="published" <?= $estado === 'published' ? 'selected' : '' ?>>Publicado</option>
                    <option value="draft" <?= $estado === 'draft' ? 'selected' : '' ?>>Borrador</option>
                    <option value="archived" <?= $estado === 'archived' ? 'selected' : '' ?>>Archivado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Grupo</label>
                <select name="grupo" class="form-select">
                    <option value="0">Todos los grupos</option>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= (int) $grupo['id'] ?>" <?= $idGrupo === (int) $grupo['id'] ? 'selected' : '' ?>><?= escapar($grupo['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Filtrar</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$cursos): ?>
    <div class="panel"><div class="panel-body empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">No se encontraron cursos.</p></div></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($cursos as $curso): ?>
    <?php $esPropietario = $usuario['role'] === 'admin' || ($usuario['role'] === 'teacher' && (int) $curso['teacher_id'] === (int) $usuario['id']); ?>
    <div class="col-md-6 col-xl-4">
        <div class="course-card">
            <div class="course-banner">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <span class="code"><?= escapar($curso['code']) ?></span>
                    <?= insignia_estado($curso['status']) ?>
                </div>
            </div>
            <div class="course-body">
                <h3><?= escapar($curso['title']) ?></h3>
                <p><?= escapar(mb_strimwidth($curso['description'] ?? '', 0, 100, '…')) ?></p>
                <div class="small text-muted mb-3">
                    <div><i class="bi bi-person me-1"></i><?= escapar($curso['teacher_name']) ?></div>
                    <?php if ($curso['group_name']): ?>
                        <div><i class="bi bi-collection me-1"></i><?= escapar($curso['group_name']) ?></div>
                    <?php endif; ?>
                    <div><i class="bi bi-tag me-1"></i><?= escapar($curso['category_name'] ?? 'Sin categoría') ?></div>
                    <div><i class="bi bi-people me-1"></i><?= (int) $curso['students'] ?> alumnos · <?= (int) $curso['lessons_count'] ?> lecciones</div>
                </div>
                <div class="d-flex gap-2 mt-auto">
                    <a href="<?= URL_APP ?>/curso.php?id=<?= (int) $curso['id'] ?>" class="btn btn-primary flex-fill">Abrir</a>
                    <?php if ($esPropietario): ?>
                        <a href="<?= URL_APP ?>/curso-formulario.php?id=<?= (int) $curso['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este curso y todo su contenido?');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="eliminar_curso">
                            <input type="hidden" name="id_curso" value="<?= (int) $curso['id'] ?>">
                            <button class="btn btn-outline-danger" type="submit" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
