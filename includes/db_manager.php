<?php
require_once __DIR__ . '/../config/config.php';

class DbManager
{
    private const MIGRATIONS_DIR = BASE_PATH . '/sql/migrations';

    private const APP_TABLES = [
        'users',
        'categories',
        'courses',
        'enrollments',
        'lessons',
        'assignments',
        'submissions',
        'grades',
        'announcements',
        'forum_topics',
        'forum_replies',
    ];

    public static function getServerConnection(): PDO
    {
        $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function getDatabaseConnection(): ?PDO
    {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            return new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException) {
            return null;
        }
    }

    public static function testServerConnection(): array
    {
        try {
            self::getServerConnection();
            return ['ok' => true, 'message' => 'Conexión al servidor MySQL exitosa.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => 'No se pudo conectar al servidor: ' . $e->getMessage()];
        }
    }

    public static function databaseExists(): bool
    {
        try {
            $pdo = self::getServerConnection();
            $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([DB_NAME]);
            return (bool) $stmt->fetch();
        } catch (PDOException) {
            return false;
        }
    }

    public static function createDatabase(): array
    {
        try {
            $pdo = self::getServerConnection();
            $name = str_replace('`', '``', DB_NAME);
            $pdo->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            return ['ok' => true, 'message' => 'Base de datos «' . DB_NAME . '» creada o ya existente.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function ensureMigrationsTable(PDO $pdo): void
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

    public static function getMigrationFiles(): array
    {
        if (!is_dir(self::MIGRATIONS_DIR)) {
            return [];
        }

        $files = glob(self::MIGRATIONS_DIR . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);
        return array_map('basename', $files);
    }

    public static function getAppliedMigrations(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? self::getDatabaseConnection();
        if (!$pdo) {
            return [];
        }

        try {
            self::ensureMigrationsTable($pdo);
            $stmt = $pdo->query('SELECT migration, executed_at FROM schema_migrations ORDER BY migration');
            return $stmt->fetchAll();
        } catch (PDOException) {
            return [];
        }
    }

    public static function getPendingMigrations(?PDO $pdo = null): array
    {
        $all = self::getMigrationFiles();
        $applied = array_column(self::getAppliedMigrations($pdo), 'migration');
        return array_values(array_diff($all, $applied));
    }

    public static function parseSqlFile(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $content = preg_replace('/--.*$/m', '', $content);
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        $parts = preg_split('/;\s*\n|;\s*$/', $content);
        $statements = [];

        foreach ($parts as $part) {
            $sql = trim($part);
            if ($sql !== '') {
                $statements[] = $sql;
            }
        }

        return $statements;
    }

    public static function runMigration(string $filename): array
    {
        $path = self::MIGRATIONS_DIR . '/' . $filename;
        if (!is_file($path)) {
            return ['ok' => false, 'message' => 'Archivo de migración no encontrado.'];
        }

        $pdo = self::getDatabaseConnection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'No hay conexión a la base de datos. Créala primero.'];
        }

        $applied = array_column(self::getAppliedMigrations($pdo), 'migration');
        if (in_array($filename, $applied, true)) {
            return ['ok' => true, 'message' => 'La migración ya estaba aplicada.', 'skipped' => true];
        }

        try {
            self::ensureMigrationsTable($pdo);
            $statements = self::parseSqlFile($path);
            $executed = 0;

            $pdo->beginTransaction();
            foreach ($statements as $sql) {
                $pdo->exec($sql);
                $executed++;
            }

            $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration, batch) VALUES (?, ?)');
            $stmt->execute([$filename, $batch]);
            $pdo->commit();

            return [
                'ok' => true,
                'message' => 'Migración «' . $filename . '» aplicada (' . $executed . ' sentencias).',
                'executed' => $executed,
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function runPendingMigrations(bool $includeSeed = true): array
    {
        $pending = self::getPendingMigrations();
        if (!$includeSeed) {
            $pending = array_values(array_filter($pending, fn ($f) => !str_contains($f, 'seed')));
        }
        if (!$pending) {
            return ['ok' => true, 'message' => 'No hay migraciones pendientes.', 'results' => []];
        }

        $results = [];
        foreach ($pending as $file) {
            $result = self::runMigration($file);
            $results[] = ['file' => $file, 'result' => $result];
            if (!$result['ok']) {
                return [
                    'ok' => false,
                    'message' => 'Error en «' . $file . '»: ' . $result['message'],
                    'results' => $results,
                ];
            }
        }

        return [
            'ok' => true,
            'message' => count($pending) . ' migración(es) aplicada(s) correctamente.',
            'results' => $results,
        ];
    }

    public static function getTablesStatus(): array
    {
        $pdo = self::getDatabaseConnection();
        if (!$pdo) {
            return array_map(fn ($table) => [
                'name' => $table,
                'exists' => false,
                'rows' => null,
            ], self::APP_TABLES);
        }

        $status = [];
        foreach (self::APP_TABLES as $table) {
            $exists = false;
            $rows = null;

            try {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema = ? AND table_name = ?'
                );
                $stmt->execute([DB_NAME, $table]);
                $exists = (int) $stmt->fetchColumn() > 0;

                if ($exists) {
                    $rows = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
                }
            } catch (PDOException) {
                $exists = false;
            }

            $status[] = [
                'name' => $table,
                'exists' => $exists,
                'rows' => $rows,
            ];
        }

        return $status;
    }

    public static function getSummary(): array
    {
        $server = self::testServerConnection();
        $dbExists = self::databaseExists();
        $pdo = self::getDatabaseConnection();
        $applied = self::getAppliedMigrations($pdo);
        $pending = self::getPendingMigrations($pdo);
        $tables = self::getTablesStatus();
        $existingTables = count(array_filter($tables, fn ($t) => $t['exists']));

        return [
            'server' => $server,
            'database_exists' => $dbExists,
            'database_connected' => $pdo !== null,
            'database_name' => DB_NAME,
            'database_host' => DB_HOST,
            'applied_migrations' => $applied,
            'pending_migrations' => $pending,
            'tables' => $tables,
            'tables_ready' => $existingTables === count(self::APP_TABLES),
            'tables_count' => $existingTables,
            'tables_total' => count(self::APP_TABLES),
        ];
    }
}
