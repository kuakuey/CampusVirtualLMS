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
        'SELECT c.*, cat.name AS category_name, u.name AS teacher_name
         FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
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
        'SELECT l.*, c.title AS course_title, c.teacher_id, c.id AS course_id,
                s.title AS subcourse_title, s.sort_order AS subcourse_sort
         FROM lessons l
         JOIN courses c ON c.id = l.course_id
         LEFT JOIN subcourses s ON s.id = l.subcourse_id
         WHERE l.id = ?'
    );
    $consulta->execute([$id]);
    $leccion = $consulta->fetch();
    return $leccion ?: null;
}

function obtener_subcursos_curso(int $idCurso): array
{
    $consulta = bd()->prepare('SELECT * FROM subcourses WHERE course_id = ? ORDER BY sort_order, id');
    $consulta->execute([$idCurso]);
    return $consulta->fetchAll();
}

function contar_subcursos_curso(int $idCurso): int
{
    return contar_consulta('SELECT COUNT(*) FROM subcourses WHERE course_id = ?', [$idCurso]);
}

function asegurar_subcurso_default(int $idCurso): int
{
    $consulta = bd()->prepare('SELECT id FROM subcourses WHERE course_id = ? ORDER BY sort_order, id LIMIT 1');
    $consulta->execute([$idCurso]);
    $id = $consulta->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $insert = bd()->prepare('INSERT INTO subcourses (course_id, title, sort_order) VALUES (?, ?, 1)');
    $insert->execute([$idCurso, 'Contenido']);
    return (int) bd()->lastInsertId();
}

function obtener_subcurso(int $id, ?int $idCurso = null): ?array
{
    $sql = 'SELECT * FROM subcourses WHERE id = ?';
    $params = [$id];
    if ($idCurso !== null) {
        $sql .= ' AND course_id = ?';
        $params[] = $idCurso;
    }
    $consulta = bd()->prepare($sql);
    $consulta->execute($params);
    $subcurso = $consulta->fetch();
    return $subcurso ?: null;
}

function obtener_lecciones_curso(int $idCurso): array
{
    $consulta = bd()->prepare(
        'SELECT l.*, s.title AS subcourse_title, s.sort_order AS subcourse_sort
         FROM lessons l
         LEFT JOIN subcourses s ON s.id = l.subcourse_id
         WHERE l.course_id = ?
         ORDER BY COALESCE(s.sort_order, 999), s.id, l.sort_order, l.id'
    );
    $consulta->execute([$idCurso]);
    return $consulta->fetchAll();
}

function obtener_siguiente_orden_leccion(int $idSubcurso): int
{
    $consulta = bd()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM lessons WHERE subcourse_id = ?');
    $consulta->execute([$idSubcurso]);
    return (int) $consulta->fetchColumn() + 1;
}

function reordenar_lecciones_curso(array $idsOrdenados, int $idCurso, int $idSubcurso): bool
{
    $idsOrdenados = array_values(array_filter(array_map('intval', $idsOrdenados)));
    if (!$idsOrdenados) {
        return true;
    }

    $placeholders = implode(',', array_fill(0, count($idsOrdenados), '?'));
    $consulta = bd()->prepare(
        "SELECT id FROM lessons WHERE course_id = ? AND subcourse_id = ? AND id IN ($placeholders)"
    );
    $consulta->execute(array_merge([$idCurso, $idSubcurso], $idsOrdenados));
    if (count($consulta->fetchAll()) !== count($idsOrdenados)) {
        return false;
    }

    $actualizar = bd()->prepare('UPDATE lessons SET sort_order = ? WHERE id = ? AND course_id = ?');
    foreach ($idsOrdenados as $indice => $idLeccion) {
        $actualizar->execute([$indice + 1, $idLeccion, $idCurso]);
    }
    return true;
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
        $codigo = 'CDA-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $consulta = bd()->prepare('SELECT id FROM courses WHERE code = ? LIMIT 1');
        $consulta->execute([$codigo]);
    } while ($consulta->fetch());
    return $codigo;
}

function obtener_curso_por_token_inscripcion(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    $consulta = bd()->prepare(
        'SELECT c.*, cat.name AS category_name, u.name AS teacher_name
         FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
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

function inscripcion_abierta(array $curso): bool
{
    $fecha = $curso['enrollment_deadline'] ?? null;
    if (!$fecha) {
        return true;
    }
    return strtotime($fecha) >= strtotime(date('Y-m-d'));
}

function fecha_para_input(?string $fecha): string
{
    if (!$fecha) {
        return '';
    }
    $marca = strtotime($fecha);
    return $marca ? date('Y-m-d', $marca) : '';
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

function etiqueta_asistencia(string $estado): string
{
    switch ($estado) {
        case 'present': return 'Presente';
        case 'absent': return 'Ausente';
        case 'late': return 'Tarde';
        case 'excused': return 'Justificado';
        default: return $estado;
    }
}

function insignia_asistencia(string $estado): string
{
    switch ($estado) {
        case 'present': $clase = 'bg-success'; break;
        case 'absent': $clase = 'bg-danger'; break;
        case 'late': $clase = 'bg-warning text-dark'; break;
        case 'excused': $clase = 'bg-info text-dark'; break;
        default: $clase = 'bg-secondary';
    }
    return '<span class="badge ' . $clase . '">' . escapar(etiqueta_asistencia($estado)) . '</span>';
}

function estados_asistencia(): array
{
    return ['present', 'absent', 'late', 'excused'];
}

function puede_gestionar_asistencia(array $curso, ?array $usuario = null): bool
{
    return es_propietario_curso($curso, $usuario);
}

function cursos_para_asistencia(array $usuario): array
{
    if ($usuario['role'] === 'admin') {
        $consulta = bd()->query('SELECT id, title, code FROM courses ORDER BY title');
        return $consulta->fetchAll();
    }
    if ($usuario['role'] === 'teacher') {
        $consulta = bd()->prepare('SELECT id, title, code FROM courses WHERE teacher_id = ? ORDER BY title');
        $consulta->execute([$usuario['id']]);
        return $consulta->fetchAll();
    }
    $consulta = bd()->prepare(
        'SELECT c.id, c.title, c.code
         FROM courses c
         JOIN enrollments e ON e.course_id = c.id
         WHERE e.student_id = ? AND e.status = "active"
         ORDER BY c.title'
    );
    $consulta->execute([$usuario['id']]);
    return $consulta->fetchAll();
}

function estudiantes_activos_curso(int $idCurso): array
{
    $consulta = bd()->prepare(
        'SELECT u.id, u.name, u.email, u.avatar
         FROM enrollments e
         JOIN users u ON u.id = e.student_id
         WHERE e.course_id = ? AND e.status = "active"
         ORDER BY u.name'
    );
    $consulta->execute([$idCurso]);
    return $consulta->fetchAll();
}

function asistencias_por_fecha(int $idCurso, string $fecha): array
{
    $consulta = bd()->prepare(
        'SELECT student_id, status FROM attendances WHERE course_id = ? AND attendance_date = ?'
    );
    $consulta->execute([$idCurso, $fecha]);
    $mapa = [];
    foreach ($consulta->fetchAll() as $fila) {
        $mapa[(int) $fila['student_id']] = $fila['status'];
    }
    return $mapa;
}

function guardar_asistencias(int $idCurso, string $fecha, array $estados, int $registradoPor): int
{
    $permitidos = estudiantes_activos_curso($idCurso);
    $idsValidos = array_map('intval', array_column($permitidos, 'id'));
    $guardar = bd()->prepare(
        'INSERT INTO attendances (course_id, student_id, attendance_date, status, recorded_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), recorded_by = VALUES(recorded_by)'
    );
    $guardados = 0;
    foreach ($estados as $idEstudiante => $estado) {
        $idEstudiante = (int) $idEstudiante;
        if (!in_array($idEstudiante, $idsValidos, true) || !in_array($estado, estados_asistencia(), true)) {
            continue;
        }
        $guardar->execute([$idCurso, $idEstudiante, $fecha, $estado, $registradoPor]);
        $guardados++;
    }
    return $guardados;
}

function reporte_asistencia_estudiantes(int $idCurso, string $desde, string $hasta): array
{
    $consulta = bd()->prepare(
        'SELECT u.id, u.name, u.email, u.avatar,
                COUNT(a.id) AS total,
                SUM(a.status = "present") AS presentes,
                SUM(a.status = "absent") AS ausentes,
                SUM(a.status = "late") AS tardes,
                SUM(a.status = "excused") AS justificados
         FROM enrollments e
         JOIN users u ON u.id = e.student_id
         LEFT JOIN attendances a ON a.student_id = u.id AND a.course_id = e.course_id
              AND a.attendance_date BETWEEN ? AND ?
         WHERE e.course_id = ? AND e.status = "active"
         GROUP BY u.id, u.name, u.email, u.avatar
         ORDER BY u.name'
    );
    $consulta->execute([$desde, $hasta, $idCurso]);
    return $consulta->fetchAll();
}

function reporte_asistencia_fechas(int $idCurso, string $desde, string $hasta): array
{
    $consulta = bd()->prepare(
        'SELECT attendance_date,
                COUNT(*) AS total,
                SUM(status = "present") AS presentes,
                SUM(status = "absent") AS ausentes,
                SUM(status = "late") AS tardes,
                SUM(status = "excused") AS justificados
         FROM attendances
         WHERE course_id = ? AND attendance_date BETWEEN ? AND ?
         GROUP BY attendance_date
         ORDER BY attendance_date DESC'
    );
    $consulta->execute([$idCurso, $desde, $hasta]);
    return $consulta->fetchAll();
}

function reporte_asistencia_propia(int $idEstudiante, int $idCurso, string $desde, string $hasta): array
{
    $consulta = bd()->prepare(
        'SELECT attendance_date, status
         FROM attendances
         WHERE student_id = ? AND course_id = ? AND attendance_date BETWEEN ? AND ?
         ORDER BY attendance_date DESC'
    );
    $consulta->execute([$idEstudiante, $idCurso, $desde, $hasta]);
    return $consulta->fetchAll();
}

function asistencias_estudiante(int $idEstudiante): array
{
    $consulta = bd()->prepare(
        'SELECT a.attendance_date, a.status, c.title AS course_title, c.code AS course_code, c.id AS course_id
         FROM attendances a
         JOIN courses c ON c.id = a.course_id
         JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = a.student_id AND e.status = "active"
         WHERE a.student_id = ?
         ORDER BY a.attendance_date DESC, c.title'
    );
    $consulta->execute([$idEstudiante]);
    return $consulta->fetchAll();
}

function resumen_asistencias(array $registros): array
{
    $resumen = [
        'sesiones' => count($registros),
        'presentes' => 0,
        'ausentes' => 0,
        'tardes' => 0,
        'justificados' => 0,
        'promedio' => 0,
    ];
    foreach ($registros as $fila) {
        if ($fila['status'] === 'present') {
            $resumen['presentes']++;
        } elseif ($fila['status'] === 'absent') {
            $resumen['ausentes']++;
        } elseif ($fila['status'] === 'late') {
            $resumen['tardes']++;
        } else {
            $resumen['justificados']++;
        }
    }
    $resumen['promedio'] = porcentaje_asistencia(
        $resumen['presentes'],
        $resumen['tardes'],
        $resumen['justificados'],
        $resumen['sesiones']
    );
    return $resumen;
}

function porcentaje_asistencia(int $presentes, int $tardes, int $justificados, int $total): int
{
    if ($total <= 0) {
        return 0;
    }
    return (int) round((($presentes + $tardes + $justificados) / $total) * 100);
}

function fecha_asistencia_valida(?string $fecha): ?string
{
    if (!$fecha) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        return null;
    }
    return $fecha;
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

function url_avatar_usuario(?string $avatar): ?string
{
    if (!$avatar) {
        return null;
    }
    $ruta = RUTA_SUBIDAS . '/' . ltrim($avatar, '/');
    if (!is_file($ruta)) {
        return null;
    }
    return url_documento_publico($avatar);
}

function renderizar_avatar_usuario(array $usuario, int $size = 40, string $clasesExtra = ''): string
{
    $nombre = $usuario['name'] ?? '';
    $url = url_avatar_usuario($usuario['avatar'] ?? null);
    $fontSize = max(0.65, round($size / 28, 2));
    $style = 'width:' . $size . 'px;height:' . $size . 'px;font-size:' . $fontSize . 'rem;';
    $clases = trim('user-avatar ' . $clasesExtra);

    if ($url) {
        return '<span class="' . escapar($clases) . ' user-avatar-photo" style="' . escapar($style) . '">'
            . '<img src="' . escapar($url) . '" alt="' . escapar($nombre) . '">'
            . '</span>';
    }

    return '<span class="' . escapar($clases) . '" style="' . escapar($style) . '">' . escapar(iniciales($nombre)) . '</span>';
}

function sincronizar_sesion_usuario(int $idUsuario): void
{
    if (!esta_logueado() || (int) ($_SESSION['usuario']['id'] ?? 0) !== $idUsuario) {
        return;
    }
    $stmt = bd()->prepare('SELECT id, name, email, role, avatar, bio, status, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$idUsuario]);
    $usuario = $stmt->fetch();
    if ($usuario) {
        $_SESSION['usuario'] = $usuario;
    }
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
