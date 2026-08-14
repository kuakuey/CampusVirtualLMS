<?php
require_once __DIR__ . '/../config/config.php';

class GestorBd
{
    private const RUTA_MIGRACIONES = RUTA_BASE . '/sql/migrations';

    private const TABLAS_APP = [
        'users', 'categories', 'course_groups', 'courses', 'subcourses', 'enrollments', 'lessons',
        'assignments', 'submissions', 'grades',
        'forum_topics', 'forum_replies', 'lesson_completions', 'attendances',
    ];

    public static function obtenerConexionServidor(): PDO
    {
        $dsn = 'mysql:host=' . BD_HOST . ';charset=' . BD_CHARSET;
        return new PDO($dsn, BD_USUARIO, BD_CLAVE, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function obtenerConexionBaseDatos(): ?PDO
    {
        try {
            $dsn = 'mysql:host=' . BD_HOST . ';dbname=' . BD_NOMBRE . ';charset=' . BD_CHARSET;
            return new PDO($dsn, BD_USUARIO, BD_CLAVE, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function probarConexionServidor(): array
    {
        try {
            self::obtenerConexionServidor();
            return ['exito' => true, 'mensaje' => 'Conexión al servidor MySQL exitosa.'];
        } catch (PDOException $e) {
            return ['exito' => false, 'mensaje' => 'No se pudo conectar al servidor: ' . $e->getMessage()];
        }
    }

    public static function existeBaseDatos(): bool
    {
        try {
            $pdo = self::obtenerConexionServidor();
            $consulta = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $consulta->execute([BD_NOMBRE]);
            return (bool) $consulta->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function crearBaseDatos(): array
    {
        try {
            $pdo = self::obtenerConexionServidor();
            $nombre = str_replace('`', '``', BD_NOMBRE);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $nombre . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            return ['exito' => true, 'mensaje' => 'Base de datos «' . BD_NOMBRE . '» creada o ya existente.'];
        } catch (PDOException $e) {
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public static function asegurarTablaMigraciones(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(150) NOT NULL UNIQUE,
                batch INT NOT NULL DEFAULT 1,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
    }

    public static function obtenerArchivosMigracion(): array
    {
        if (!is_dir(self::RUTA_MIGRACIONES)) {
            return [];
        }
        $archivos = glob(self::RUTA_MIGRACIONES . '/*.sql') ?: [];
        sort($archivos, SORT_NATURAL);
        return array_map('basename', $archivos);
    }

    public static function obtenerMigracionesAplicadas(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? self::obtenerConexionBaseDatos();
        if (!$pdo) {
            return [];
        }
        try {
            self::asegurarTablaMigraciones($pdo);
            return $pdo->query('SELECT migration, executed_at FROM schema_migrations ORDER BY migration')->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public static function obtenerMigracionesPendientes(?PDO $pdo = null): array
    {
        $todas = self::obtenerArchivosMigracion();
        $aplicadas = array_column(self::obtenerMigracionesAplicadas($pdo), 'migration');
        return array_values(array_diff($todas, $aplicadas));
    }

    public static function analizarArchivoSql(string $ruta): array
    {
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            return [];
        }
        $contenido = preg_replace('/--.*$/m', '', $contenido);
        $contenido = preg_replace('/\/\*.*?\*\//s', '', $contenido);
        $partes = preg_split('/;\s*\n|;\s*$/', $contenido);
        $sentencias = [];
        foreach ($partes as $parte) {
            $sql = trim($parte);
            if ($sql !== '') {
                $sentencias[] = $sql;
            }
        }
        return $sentencias;
    }

    public static function ejecutarMigracion(string $archivo): array
    {
        $ruta = self::RUTA_MIGRACIONES . '/' . $archivo;
        if (!is_file($ruta)) {
            return ['exito' => false, 'mensaje' => 'Archivo de migración no encontrado.'];
        }

        $pdo = self::obtenerConexionBaseDatos();
        if (!$pdo) {
            return ['exito' => false, 'mensaje' => 'No hay conexión a la base de datos. Créala primero.'];
        }

        $aplicadas = array_column(self::obtenerMigracionesAplicadas($pdo), 'migration');
        if (in_array($archivo, $aplicadas, true)) {
            return ['exito' => true, 'mensaje' => 'La migración ya estaba aplicada.', 'omitida' => true];
        }

        try {
            self::asegurarTablaMigraciones($pdo);
            $sentencias = self::analizarArchivoSql($ruta);
            $ejecutadas = 0;
            $pdo->beginTransaction();
            foreach ($sentencias as $sql) {
                $pdo->exec($sql);
                $ejecutadas++;
            }
            $lote = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();
            $consulta = $pdo->prepare('INSERT INTO schema_migrations (migration, batch) VALUES (?, ?)');
            $consulta->execute([$archivo, $lote]);
            $pdo->commit();
            return ['exito' => true, 'mensaje' => 'Migración «' . $archivo . '» aplicada (' . $ejecutadas . ' sentencias).', 'ejecutadas' => $ejecutadas];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public static function ejecutarMigracionesPendientes(bool $soloEstructura = false): array
    {
        $pendientes = self::obtenerMigracionesPendientes();
        if ($soloEstructura) {
            $pendientes = array_values(array_filter($pendientes, function ($f) {
                return strpos($f, 'datos_iniciales') === false;
            }));
        }
        if (!$pendientes) {
            return ['exito' => true, 'mensaje' => 'No hay migraciones pendientes.', 'resultados' => []];
        }

        $resultados = [];
        foreach ($pendientes as $archivo) {
            $resultado = self::ejecutarMigracion($archivo);
            $resultados[] = ['archivo' => $archivo, 'resultado' => $resultado];
            if (!$resultado['exito']) {
                return ['exito' => false, 'mensaje' => 'Error en «' . $archivo . '»: ' . $resultado['mensaje'], 'resultados' => $resultados];
            }
        }
        return ['exito' => true, 'mensaje' => count($pendientes) . ' migración(es) aplicada(s) correctamente.', 'resultados' => $resultados];
    }

    public static function obtenerEstadoTablas(): array
    {
        $pdo = self::obtenerConexionBaseDatos();
        if (!$pdo) {
            return array_map(function ($tabla) {
                return ['nombre' => $tabla, 'existe' => false, 'registros' => null];
            }, self::TABLAS_APP);
        }

        $estado = [];
        foreach (self::TABLAS_APP as $tabla) {
            $existe = false;
            $registros = null;
            try {
                $consulta = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
                $consulta->execute([BD_NOMBRE, $tabla]);
                $existe = (int) $consulta->fetchColumn() > 0;
                if ($existe) {
                    $registros = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $tabla) . '`')->fetchColumn();
                }
            } catch (PDOException $e) {
                $existe = false;
            }
            $estado[] = ['nombre' => $tabla, 'existe' => $existe, 'registros' => $registros];
        }
        return $estado;
    }

    public static function obtenerResumen(): array
    {
        $servidor = self::probarConexionServidor();
        $existeBd = self::existeBaseDatos();
        $pdo = self::obtenerConexionBaseDatos();
        $aplicadas = self::obtenerMigracionesAplicadas($pdo);
        $pendientes = self::obtenerMigracionesPendientes($pdo);
        $tablas = self::obtenerEstadoTablas();
        $tablasExistentes = count(array_filter($tablas, function ($t) {
            return $t['existe'];
        }));

        return [
            'servidor' => $servidor,
            'existe_bd' => $existeBd,
            'bd_conectada' => $pdo !== null,
            'nombre_bd' => BD_NOMBRE,
            'host_bd' => BD_HOST,
            'migraciones_aplicadas' => $aplicadas,
            'migraciones_pendientes' => $pendientes,
            'tablas' => $tablas,
            'tablas_listas' => $tablasExistentes === count(self::TABLAS_APP),
            'cantidad_tablas' => $tablasExistentes,
            'total_tablas' => count(self::TABLAS_APP),
        ];
    }
}
