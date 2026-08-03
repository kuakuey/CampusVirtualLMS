<?php
require_once __DIR__ . '/../config/base_datos.php';

function escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function redirigir(string $ruta): void
{
    $url = (strpos($ruta, 'http') === 0) ? $ruta : URL_APP . '/' . ltrim($ruta, '/');
    header('Location: ' . $url);
    exit;
}

function mensaje_flash(string $tipo, string $mensaje): void
{
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtener_mensaje(): ?array
{
    if (!isset($_SESSION['mensaje'])) {
        return null;
    }
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
    return $mensaje;
}

function esta_logueado(): bool
{
    return isset($_SESSION['usuario']);
}

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function requiere_sesion(): void
{
    if (!esta_logueado()) {
        mensaje_flash('warning', 'Debes iniciar sesión para continuar.');
        redirigir('iniciar-sesion.php');
    }
}

function requiere_rol($roles): void
{
    requiere_sesion();
    $roles = (array) $roles;
    if (!in_array(usuario_actual()['role'], $roles, true)) {
        mensaje_flash('danger', 'No tienes permiso para acceder a esta sección.');
        redirigir('panel.php');
    }
}

function etiqueta_rol(string $rol): string
{
    switch ($rol) {
        case 'admin': return 'Administrador';
        case 'teacher': return 'Docente';
        case 'student': return 'Estudiante';
        default: return $rol;
    }
}

function insignia_rol(string $rol): string
{
    switch ($rol) {
        case 'admin': $clase = 'bg-danger'; break;
        case 'teacher': $clase = 'bg-primary'; break;
        case 'student': $clase = 'bg-success'; break;
        default: $clase = 'bg-secondary';
    }
    return '<span class="badge ' . $clase . '">' . escapar(etiqueta_rol($rol)) . '</span>';
}

function insignia_estado(string $estado): string
{
    $mapa = [
        'draft' => ['bg-secondary', 'Borrador'],
        'published' => ['bg-success', 'Publicado'],
        'archived' => ['bg-dark', 'Archivado'],
        'active' => ['bg-success', 'Activo'],
        'completed' => ['bg-info', 'Completado'],
        'dropped' => ['bg-warning text-dark', 'Retirado'],
    ];
    [$clase, $etiqueta] = $mapa[$estado] ?? ['bg-secondary', $estado];
    return '<span class="badge ' . $clase . '">' . escapar($etiqueta) . '</span>';
}

function formatear_fecha(?string $fechaHora, bool $conHora = false): string
{
    if (!$fechaHora) {
        return '—';
    }
    $marca = strtotime($fechaHora);
    return $conHora ? date('d/m/Y H:i', $marca) : date('d/m/Y', $marca);
}

function token_csrf(): string
{
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="token_csrf" value="' . escapar(token_csrf()) . '">';
}

function verificar_csrf(): void
{
    $token = $_POST['token_csrf'] ?? '';
    if (!hash_equals(token_csrf(), $token)) {
        mensaje_flash('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirigir($_SERVER['HTTP_REFERER'] ?? 'panel.php');
    }
}

function esta_matriculado(int $idCurso, ?int $idEstudiante = null): bool
{
    $idEstudiante = $idEstudiante ?? (usuario_actual()['id'] ?? 0);
    $consulta = bd()->prepare('SELECT id FROM enrollments WHERE course_id = ? AND student_id = ? AND status = "active"');
    $consulta->execute([$idCurso, $idEstudiante]);
    return (bool) $consulta->fetch();
}

function puede_acceder_curso(array $curso): bool
{
    $usuario = usuario_actual();
    if (!$usuario) {
        return false;
    }
    if ($usuario['role'] === 'admin') {
        return true;
    }
    if ($usuario['role'] === 'teacher' && (int) $curso['teacher_id'] === (int) $usuario['id']) {
        return true;
    }
    return esta_matriculado((int) $curso['id'], (int) $usuario['id']);
}

function obtener_curso(int $id): ?array
{
    $consulta = bd()->prepare(
        'SELECT c.*, cat.name AS category_name, g.name AS group_name, u.name AS teacher_name
         FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
         LEFT JOIN course_groups g ON g.id = c.group_id
         JOIN users u ON u.id = c.teacher_id
         WHERE c.id = ?'
    );
    $consulta->execute([$id]);
    $curso = $consulta->fetch();
    return $curso ?: null;
}

function contar_consulta(string $sql, array $parametros = []): int
{
    $consulta = bd()->prepare($sql);
    $consulta->execute($parametros);
    return (int) $consulta->fetchColumn();
}

function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $letras = '';
    foreach (array_slice($partes, 0, 2) as $parte) {
        $letras .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    return $letras ?: '?';
}

function subir_archivo(array $archivo, string $subcarpeta = 'archivos'): ?string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $directorio = RUTA_SUBIDAS . '/' . $subcarpeta;
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $permitidas = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'txt'];
    if (!in_array($extension, $permitidas, true)) {
        return null;
    }
    $nombreArchivo = uniqid('f_', true) . '.' . $extension;
    $destino = $directorio . '/' . $nombreArchivo;
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        return null;
    }
    return $subcarpeta . '/' . $nombreArchivo;
}
