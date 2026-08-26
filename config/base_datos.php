<?php
require_once __DIR__ . '/app.php';

class BaseDatos
{
    /** @var PDO|null */
    private static $instancia = null;

    public static function obtenerConexion(): PDO
    {
        if (self::$instancia === null) {
            try {
                $dsn = 'mysql:host=' . BD_HOST . ';dbname=' . BD_NOMBRE . ';charset=' . BD_CHARSET;
                self::$instancia = new PDO($dsn, BD_USUARIO, BD_CLAVE, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log('Error BD AulaVirtual: ' . $e->getMessage());
                http_response_code(503);
                die('<h1>Servicio no disponible</h1><p>No se pudo conectar a la base de datos. Verifica la configuración en <code>config/config.php</code> o usa <a href="' . URL_INSTALACION . '">instalacion.php</a>.</p>');
            }
        }
        return self::$instancia;
    }
}

function bd(): PDO
{
    return BaseDatos::obtenerConexion();
}
