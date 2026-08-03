<?php
/**
 * AulaVirtual LMS - Configuración
 */
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    die('<h1>PHP incompatible</h1><p>Se requiere PHP 7.4 o superior. Versión actual: <strong>' . htmlspecialchars(PHP_VERSION) . '</strong></p>');
}

define('NOMBRE_APP', 'AulaVirtual');
define('RUTA_BASE', dirname(__DIR__));

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

define('BD_HOST', 'localhost');
define('BD_NOMBRE', 'iglesiacasadeavi_lms');
define('BD_USUARIO', 'iglesiacasadeavi_kuakuey');
define('BD_CLAVE', 'Superadmin29@!');
define('BD_CHARSET', 'utf8mb4');

define('RUTA_SUBIDAS', RUTA_BASE . '/subidas');
define('URL_SUBIDAS', URL_APP . '/subidas');

define('URL_INICIO_SESION', URL_APP . '/iniciar-sesion.php');
define('URL_REGISTRO', URL_APP . '/registrarse.php');
define('URL_CERRAR_SESION', URL_APP . '/cerrar-sesion.php');
define('URL_PANEL', URL_APP . '/panel.php');
define('URL_CURSOS', URL_APP . '/cursos.php');
define('URL_CURSO', URL_APP . '/curso.php');
define('URL_CURSO_FORMULARIO', URL_APP . '/curso-formulario.php');
define('URL_CATALOGO', URL_APP . '/catalogo.php');
define('URL_LECCION', URL_APP . '/leccion.php');
define('URL_LECCION_FORMULARIO', URL_APP . '/leccion-formulario.php');
define('URL_PERFIL', URL_APP . '/perfil.php');
define('URL_INSTALACION', URL_APP . '/instalacion.php');
define('URL_USUARIOS', URL_APP . '/admin/usuarios.php');
define('URL_CATEGORIAS', URL_APP . '/admin/categorias.php');
define('URL_GRUPOS', URL_APP . '/admin/grupos-cursos.php');

date_default_timezone_set('America/Bogota');

// Registra errores en archivo (útil en producción)
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
