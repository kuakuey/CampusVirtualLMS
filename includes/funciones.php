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

function obtener_leccion(int $id): ?array
{
    $consulta = bd()->prepare(
        'SELECT l.*, c.title AS course_title, c.teacher_id, c.id AS course_id
         FROM lessons l
         JOIN courses c ON c.id = l.course_id
         WHERE l.id = ?'
    );
    $consulta->execute([$id]);
    $leccion = $consulta->fetch();
    return $leccion ?: null;
}

function es_propietario_curso(array $curso, ?array $usuario = null): bool
{
    $usuario = $usuario ?? usuario_actual();
    if (!$usuario) {
        return false;
    }
    return $usuario['role'] === 'admin'
        || ($usuario['role'] === 'teacher' && (int) $curso['teacher_id'] === (int) $usuario['id']);
}

function id_video_youtube(?string $url): ?string
{
    if (!$url || !preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]+)/', $url, $coincidencias)) {
        return null;
    }
    return $coincidencias[1];
}

function es_video_html5(?string $url): bool
{
    if (!$url) {
        return false;
    }
    $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    return in_array($extension, ['mp4', 'webm', 'ogg', 'mov'], true);
}

function tipo_video_leccion(?string $url): string
{
    if (id_video_youtube($url)) {
        return 'youtube';
    }
    if (es_video_html5($url)) {
        return 'html5';
    }
    return $url ? 'externo' : 'ninguno';
}

function leccion_esta_completada(int $idLeccion, int $idEstudiante): bool
{
    $consulta = bd()->prepare('SELECT id FROM lesson_completions WHERE lesson_id = ? AND student_id = ? LIMIT 1');
    $consulta->execute([$idLeccion, $idEstudiante]);
    return (bool) $consulta->fetch();
}

function obtener_ids_lecciones_completadas(int $idCurso, int $idEstudiante): array
{
    $consulta = bd()->prepare(
        'SELECT lc.lesson_id FROM lesson_completions lc
         JOIN lessons l ON l.id = lc.lesson_id
         WHERE l.course_id = ? AND lc.student_id = ?'
    );
    $consulta->execute([$idCurso, $idEstudiante]);
    return array_map('intval', array_column($consulta->fetchAll(), 'lesson_id'));
}

function porcentaje_progreso_curso(int $idCurso, int $idEstudiante): int
{
    $total = contar_consulta('SELECT COUNT(*) FROM lessons WHERE course_id = ?', [$idCurso]);
    if ($total === 0) {
        return 0;
    }
    $completadas = contar_consulta(
        'SELECT COUNT(*) FROM lesson_completions lc
         JOIN lessons l ON l.id = lc.lesson_id
         WHERE l.course_id = ? AND lc.student_id = ?',
        [$idCurso, $idEstudiante]
    );
    return (int) round(($completadas / $total) * 100);
}

function marcar_leccion_completada(int $idLeccion, int $idEstudiante): bool
{
    $consulta = bd()->prepare(
        'INSERT INTO lesson_completions (lesson_id, student_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP'
    );
    return $consulta->execute([$idLeccion, $idEstudiante]);
}

function generar_token_inscripcion(): string
{
    return bin2hex(random_bytes(16));
}

function generar_codigo_curso_unico(): string
{
    do {
        $codigo = 'CUR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $consulta = bd()->prepare('SELECT id FROM courses WHERE code = ? LIMIT 1');
        $consulta->execute([$codigo]);
    } while ($consulta->fetch());
    return $codigo;
}

function resolver_docente_nuevo_curso(?array $usuario = null): int
{
    $usuario = $usuario ?? usuario_actual();
    if ($usuario['role'] === 'teacher') {
        return (int) $usuario['id'];
    }
    $consulta = bd()->query('SELECT id FROM users WHERE role = "teacher" AND status = 1 ORDER BY name LIMIT 1');
    $docente = $consulta->fetch();
    return $docente ? (int) $docente['id'] : (int) $usuario['id'];
}

function crear_curso_rapido(?array $usuario = null): int
{
    $usuario = $usuario ?? usuario_actual();
    $docenteId = resolver_docente_nuevo_curso($usuario);
    $codigo = generar_codigo_curso_unico();
    $token = generar_token_inscripcion();
    $consulta = bd()->prepare(
        'INSERT INTO courses (teacher_id, title, code, status, enrollment_type, enrollment_token)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $consulta->execute([$docenteId, 'Nuevo curso', $codigo, 'draft', 'public', $token]);
    return (int) bd()->lastInsertId();
}

function obtener_curso_por_token_inscripcion(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    $consulta = bd()->prepare(
        'SELECT c.*, cat.name AS category_name, g.name AS group_name, u.name AS teacher_name
         FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
         LEFT JOIN course_groups g ON g.id = c.group_id
         JOIN users u ON u.id = c.teacher_id
         WHERE c.enrollment_token = ? LIMIT 1'
    );
    $consulta->execute([$token]);
    $curso = $consulta->fetch();
    return $curso ?: null;
}

function asegurar_token_inscripcion_curso(int $idCurso): string
{
    $consulta = bd()->prepare('SELECT enrollment_token FROM courses WHERE id = ?');
    $consulta->execute([$idCurso]);
    $token = $consulta->fetchColumn();
    if ($token) {
        return $token;
    }
    $token = generar_token_inscripcion();
    $actualizar = bd()->prepare('UPDATE courses SET enrollment_token = ? WHERE id = ?');
    $actualizar->execute([$token, $idCurso]);
    return $token;
}

function url_inscripcion_curso(array $curso): string
{
    $token = $curso['enrollment_token'] ?? '';
    if ($token === '') {
        $token = asegurar_token_inscripcion_curso((int) $curso['id']);
    }
    return URL_INSCRIPCION_CURSO . '?token=' . urlencode($token);
}

function inscribir_estudiante_en_curso(int $idCurso, int $idEstudiante): bool
{
    $consulta = bd()->prepare(
        'INSERT INTO enrollments (course_id, student_id, status) VALUES (?, ?, "active")
         ON DUPLICATE KEY UPDATE status = "active"'
    );
    return $consulta->execute([$idCurso, $idEstudiante]);
}

function etiqueta_metodo_inscripcion(string $tipo): string
{
    switch ($tipo) {
        case 'public': return 'Público';
        case 'password': return 'Con contraseña';
        case 'url': return 'Por URL';
        default: return $tipo;
    }
}

function insignia_metodo_inscripcion(string $tipo): string
{
    switch ($tipo) {
        case 'public': $clase = 'bg-success'; break;
        case 'password': $clase = 'bg-warning text-dark'; break;
        case 'url': $clase = 'bg-info text-dark'; break;
        default: $clase = 'bg-secondary';
    }
    return '<span class="badge ' . $clase . '">' . escapar(etiqueta_metodo_inscripcion($tipo)) . '</span>';
}

function reiniciar_tiempo_video_leccion(int $idLeccion): void
{
    if (!isset($_SESSION['tiempo_video_leccion'])) {
        $_SESSION['tiempo_video_leccion'] = [];
    }
    $_SESSION['tiempo_video_leccion'][$idLeccion] = 0;
}

function obtener_tiempo_video_leccion(int $idLeccion): int
{
    return (int) ($_SESSION['tiempo_video_leccion'][$idLeccion] ?? 0);
}

function registrar_tiempo_video_leccion(int $idLeccion, int $segundos): int
{
    if ($segundos < 1 || $segundos > 120) {
        return obtener_tiempo_video_leccion($idLeccion);
    }
    if (!isset($_SESSION['tiempo_video_leccion'])) {
        $_SESSION['tiempo_video_leccion'] = [];
    }
    $_SESSION['tiempo_video_leccion'][$idLeccion] = min(
        86400,
        obtener_tiempo_video_leccion($idLeccion) + $segundos
    );
    return $_SESSION['tiempo_video_leccion'][$idLeccion];
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
    $permitidas = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'zip'];
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

function eliminar_archivo_subida(?string $rutaRelativa): void
{
    if (!$rutaRelativa) {
        return;
    }
    $ruta = RUTA_SUBIDAS . '/' . ltrim($rutaRelativa, '/');
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

function url_documento_publico(string $rutaRelativa): string
{
    return URL_SUBIDAS . '/' . ltrim($rutaRelativa, '/');
}

function limpiar_archivos_curso(int $idCurso): void
{
    $curso = obtener_curso($idCurso);
    if ($curso && !empty($curso['document_path'])) {
        eliminar_archivo_subida($curso['document_path']);
    }
    $consulta = bd()->prepare('SELECT attachment FROM lessons WHERE course_id = ? AND attachment IS NOT NULL AND attachment != ""');
    $consulta->execute([$idCurso]);
    foreach ($consulta->fetchAll() as $fila) {
        eliminar_archivo_subida($fila['attachment']);
    }
}

function renderizar_vista_previa_documento(?string $rutaRelativa, string $titulo = 'Documento adjunto'): string
{
    if (!$rutaRelativa) {
        return '';
    }

    $url = url_documento_publico($rutaRelativa);
    $extension = strtolower(pathinfo($rutaRelativa, PATHINFO_EXTENSION));
    $nombre = basename($rutaRelativa);

    $html = '<div class="doc-preview-panel mb-4">';
    $html .= '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">';
    $html .= '<h3 class="h6 mb-0"><i class="bi bi-file-earmark-text me-1"></i>' . escapar($titulo) . '</h3>';
    $html .= '<a href="' . escapar($url) . '" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Descargar</a>';
    $html .= '</div>';

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $html .= '<img src="' . escapar($url) . '" class="doc-preview-image img-fluid rounded border" alt="' . escapar($nombre) . '">';
    } elseif ($extension === 'pdf') {
        $html .= '<div class="doc-preview-frame ratio ratio-4x3 rounded overflow-hidden border"><iframe src="' . escapar($url) . '#navpanes=0" title="' . escapar($titulo) . '"></iframe></div>';
    } elseif ($extension === 'txt') {
        $rutaLocal = RUTA_SUBIDAS . '/' . ltrim($rutaRelativa, '/');
        $texto = (is_file($rutaLocal) && filesize($rutaLocal) <= 512000) ? file_get_contents($rutaLocal) : false;
        if ($texto !== false && $texto !== '') {
            $html .= '<pre class="doc-preview-text p-3 bg-light border rounded mb-0">' . escapar($texto) . '</pre>';
        } else {
            $html .= '<p class="text-muted small mb-0">Archivo de texto disponible para descarga.</p>';
        }
    } elseif (in_array($extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'], true)) {
        $embed = 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($url);
        $html .= '<div class="doc-preview-frame ratio ratio-4x3 rounded overflow-hidden border"><iframe src="' . escapar($embed) . '" title="' . escapar($titulo) . '"></iframe></div>';
        $html .= '<p class="small text-muted mt-2 mb-0">Si la vista previa no carga, usa el botón Descargar.</p>';
    } else {
        $html .= '<div class="alert alert-light border mb-0"><i class="bi bi-file-earmark me-1"></i>' . escapar($nombre) . ' — descarga el archivo para abrirlo.</div>';
    }

    $html .= '</div>';
    return $html;
}
