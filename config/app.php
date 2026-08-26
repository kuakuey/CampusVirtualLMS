<?php
/**
 * AulaVirtual LMS — Arranque de la aplicación (sin credenciales).
 */
$rutaConfig = __DIR__ . '/config.php';
if (!is_file($rutaConfig)) {
    salir_configuracion(
        'Falta config/config.php',
        'El servidor no tiene el archivo de credenciales. Cópialo desde config.example.php o créalo en el administrador de archivos del hosting con BD_HOST, BD_NOMBRE, BD_USUARIO, BD_CLAVE y BD_CHARSET.'
    );
}
require_once $rutaConfig;

$constantesBd = ['BD_HOST', 'BD_NOMBRE', 'BD_USUARIO', 'BD_CLAVE', 'BD_CHARSET'];
$faltantes = [];
foreach ($constantesBd as $constante) {
    if (!defined($constante)) {
        $faltantes[] = $constante;
    }
}
if ($faltantes) {
    salir_configuracion(
        'config.php está incompleto',
        'El archivo existe, pero no define: ' . implode(', ', $faltantes) . '. Debe contener solo las credenciales de MySQL (define BD_HOST, BD_NOMBRE, BD_USUARIO, BD_CLAVE y BD_CHARSET).'
    );
}

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    salir_configuracion(
        'PHP incompatible',
        'Se requiere PHP 7.4 o superior. Versión actual: ' . PHP_VERSION
    );
}

if (!defined('NOMBRE_APP')) {
    define('NOMBRE_APP', 'AulaVirtual');
}
if (!defined('RUTA_BASE')) {
    define('RUTA_BASE', dirname(__DIR__));
}

if (!defined('URL_APP')) {
    $urlApp = getenv('URL_APP') ?: getenv('APP_URL') ?: '';
    if ($urlApp !== '') {
        define('URL_APP', rtrim($urlApp, '/'));
    } else {
        $esquema = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ) ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $rutaRelativa = '';

        $raizDocumentos = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $raizApp = realpath(RUTA_BASE);
        if ($raizDocumentos && $raizApp) {
            $rutaRelativa = str_replace('\\', '/', str_replace($raizDocumentos, '', $raizApp));
        }

        define('URL_APP', $esquema . '://' . $host . $rutaRelativa);
    }
}

if (!defined('RUTA_SUBIDAS')) {
    define('RUTA_SUBIDAS', RUTA_BASE . '/subidas');
}
if (!defined('URL_SUBIDAS')) {
    define('URL_SUBIDAS', URL_APP . '/subidas');
}

$rutasApp = [
    'URL_INICIO_SESION' => '/iniciar-sesion.php',
    'URL_REGISTRO' => '/registrarse.php',
    'URL_CERRAR_SESION' => '/cerrar-sesion.php',
    'URL_PANEL' => '/panel.php',
    'URL_CURSOS' => '/cursos.php',
    'URL_CURSO' => '/curso.php',
    'URL_CURSO_FORMULARIO' => '/curso-formulario.php',
    'URL_INSCRIPCION_CURSO' => '/inscripcion-curso.php',
    'URL_CATALOGO' => '/catalogo.php',
    'URL_LECCION' => '/leccion.php',
    'URL_LECCION_FORMULARIO' => '/leccion-formulario.php',
    'URL_PERFIL' => '/perfil.php',
    'URL_INSTALACION' => '/instalacion.php',
    'URL_CURSO_ASISTENCIA' => '/curso.php',
    'URL_ASISTENCIA' => '/asistencia.php',
    'URL_USUARIOS' => '/admin/usuarios.php',
    'URL_CATEGORIAS' => '/admin/categorias.php',
];
foreach ($rutasApp as $nombre => $ruta) {
    if (!defined($nombre)) {
        define($nombre, URL_APP . $ruta);
    }
}

date_default_timezone_set('America/Bogota');

$directorioLogs = RUTA_BASE . '/logs';
if (!is_dir($directorioLogs)) {
    @mkdir($directorioLogs, 0755, true);
}
if (is_dir($directorioLogs) && is_writable($directorioLogs)) {
    ini_set('log_errors', '1');
    ini_set('error_log', $directorioLogs . '/errores.log');
}
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function salir_configuracion(string $titulo, string $detalle): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $tituloHtml = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $detalleHtml = htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $tituloHtml . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:42rem;margin:3rem auto;padding:0 1.5rem;color:#1f2937;line-height:1.5}h1{font-size:1.5rem}code{background:#f3f4f6;padding:.1rem .35rem;border-radius:4px}pre{background:#111827;color:#f9fafb;padding:1rem;border-radius:8px;overflow:auto}</style>';
    echo '</head><body>';
    echo '<h1>' . $tituloHtml . '</h1>';
    echo '<p>' . $detalleHtml . '</p>';
    echo '<p>En el hosting (Administrador de archivos → <code>campus-virtual/config/config.php</code>) el archivo debe verse así:</p>';
    echo '<pre>&lt;?php
define(\'BD_HOST\', \'localhost\');
define(\'BD_NOMBRE\', \'iglesiacasadeavi_lms\');
define(\'BD_USUARIO\', \'iglesiacasadeavi_kuakuey\');
define(\'BD_CLAVE\', \'tu_clave\');
define(\'BD_CHARSET\', \'utf8mb4\');</pre>';
    echo '<p>Ese archivo no se sube con git (está en .gitignore). Hay que crearlo una vez en el servidor. Si git lo borró al hacer pull, vuelve a crearlo.</p>';
    echo '</body></html>';
    exit;
}
