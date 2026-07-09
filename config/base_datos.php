<?php
require_once __DIR__ . '/config.php';

class BaseDatos
{
    private static ?PDO $instancia = null;

    public static function obtenerConexion(): PDO
    {
        if (self::$instancia === null) {
            $dsn = 'mysql:host=' . BD_HOST . ';dbname=' . BD_NOMBRE . ';charset=' . BD_CHARSET;
            self::$instancia = new PDO($dsn, BD_USUARIO, BD_CLAVE, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$instancia;
    }
}

function bd(): PDO
{
    return BaseDatos::obtenerConexion();
}
