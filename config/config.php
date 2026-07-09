<?php
/**
 * AulaVirtual LMS - Configuración
 */
define('APP_NAME', 'AulaVirtual');
define('APP_URL', 'http://localhost/AulaVirtual');
define('BASE_PATH', dirname(__DIR__));

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
