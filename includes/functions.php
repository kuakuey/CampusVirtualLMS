<?php
require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    $url = str_starts_with($path, 'http') ? $path : APP_URL . '/' . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Debes iniciar sesión para continuar.');
        redirect('login.php');
    }
}

function require_role(string|array $roles): void
{
    require_login();
    $roles = (array) $roles;
    if (!in_array(current_user()['role'], $roles, true)) {
        flash('danger', 'No tienes permiso para acceder a esta sección.');
        redirect('dashboard.php');
    }
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrador',
        'teacher' => 'Docente',
        'student' => 'Estudiante',
        default => $role,
    };
}

function role_badge(string $role): string
{
    $class = match ($role) {
        'admin' => 'bg-danger',
        'teacher' => 'bg-primary',
        'student' => 'bg-success',
        default => 'bg-secondary',
    };
    return '<span class="badge ' . $class . '">' . e(role_label($role)) . '</span>';
}

function status_badge(string $status): string
{
    $map = [
        'draft' => ['bg-secondary', 'Borrador'],
        'published' => ['bg-success', 'Publicado'],
        'archived' => ['bg-dark', 'Archivado'],
        'active' => ['bg-success', 'Activo'],
        'completed' => ['bg-info', 'Completado'],
        'dropped' => ['bg-warning text-dark', 'Retirado'],
    ];
    [$class, $label] = $map[$status] ?? ['bg-secondary', $status];
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function format_date(?string $datetime, bool $withTime = false): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $withTime ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        flash('danger', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

function is_enrolled(int $courseId, ?int $studentId = null): bool
{
    $studentId = $studentId ?? (current_user()['id'] ?? 0);
    $stmt = db()->prepare('SELECT id FROM enrollments WHERE course_id = ? AND student_id = ? AND status = "active"');
    $stmt->execute([$courseId, $studentId]);
    return (bool) $stmt->fetch();
}

function can_access_course(array $course): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'admin') {
        return true;
    }
    if ($user['role'] === 'teacher' && (int) $course['teacher_id'] === (int) $user['id']) {
        return true;
    }
    return is_enrolled((int) $course['id'], (int) $user['id']);
}

function get_course(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT c.*, cat.name AS category_name, u.name AS teacher_name
         FROM courses c
         LEFT JOIN categories cat ON cat.id = c.category_id
         JOIN users u ON u.id = c.teacher_id
         WHERE c.id = ?'
    );
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    return $course ?: null;
}

function count_query(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters ?: '?';
}

function upload_file(array $file, string $subdir = 'files'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'txt'];
    if (!in_array($ext, $allowed, true)) {
        return null;
    }
    $filename = uniqid('f_', true) . '.' . $ext;
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return $subdir . '/' . $filename;
}
