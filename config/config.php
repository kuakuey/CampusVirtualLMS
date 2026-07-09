<?php
/**
 * AulaVirtual LMS - Configuración
 */
define('APP_NAME', 'AulaVirtual');
define('BASE_PATH', dirname(__DIR__));

// URL base de la app. Opciones:
// 1) Variable de entorno APP_URL (recomendado en producción)
// 2) Detección automática según dominio y ruta
// 3) Descomenta y define manualmente si lo necesitas:
// define('APP_URL', 'https://tudominio.com');
if (!defined('APP_URL')) {
    $appUrl = getenv('APP_URL') ?: '';
    if ($appUrl !== '') {
        define('APP_URL', rtrim($appUrl, '/'));
    } else {
        $scheme = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ) ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $relativePath = '';

        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $appRoot = realpath(BASE_PATH);
        if ($docRoot && $appRoot) {
            $relativePath = str_replace('\\', '/', str_replace($docRoot, '', $appRoot));
        }

        define('APP_URL', $scheme . '://' . $host . $relativePath);
    }
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'aulavirtual');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', APP_URL . '/uploads');

date_default_timezone_set('America/Bogota');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
