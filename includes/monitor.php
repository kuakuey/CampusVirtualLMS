<?php

function iniciar_monitor(): void
{
    static $listo = false;
    if ($listo) {
        return;
    }
    $listo = true;

    set_exception_handler(function ($excepcion) {
        if (!$excepcion instanceof Throwable) {
            return;
        }
        registrar_caida('excepcion', $excepcion->getMessage(), $excepcion->getFile(), (int) $excepcion->getLine());
        error_log('Excepción AulaVirtual: ' . $excepcion->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<h1>Algo salió mal</h1><p>El campus no pudo completar esta acción. El fallo quedó registrado.</p>';
        exit;
    });

    register_shutdown_function(function () {
        $error = error_get_last();
        if (!$error) {
            return;
        }
        $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatales, true)) {
            return;
        }
        registrar_caida('fatal', (string) $error['message'], (string) ($error['file'] ?? ''), (int) ($error['line'] ?? 0));
    });
}

function ruta_log_caidas(): string
{
    return RUTA_BASE . '/logs/caidas.log';
}

function mensaje_caida_seguro(string $mensaje): string
{
    $ocultar = [];
    foreach (['BD_CLAVE', 'BD_USUARIO', 'BD_HOST', 'BD_NOMBRE'] as $constante) {
        if (defined($constante)) {
            $valor = (string) constant($constante);
            if (strlen($valor) >= 4) {
                $ocultar[] = $valor;
            }
        }
    }
    if ($ocultar) {
        $mensaje = str_replace($ocultar, '[oculto]', $mensaje);
    }
    $mensaje = preg_replace('/(password|clave|token)\s*[=:]\s*\S+/i', '$1=[oculto]', $mensaje) ?? $mensaje;
    $mensaje = preg_replace('/\s+/', ' ', trim($mensaje)) ?? $mensaje;
    if (function_exists('mb_substr')) {
        return mb_substr($mensaje, 0, 400);
    }
    return substr($mensaje, 0, 400);
}

function registrar_caida(string $tipo, string $mensaje, string $archivo = '', int $linea = 0): void
{
    static $yaEscrito = [];
    $mensaje = mensaje_caida_seguro($mensaje);
    if ($mensaje === '') {
        $mensaje = 'Fallo sin detalle';
    }
    $firma = $tipo . '|' . $mensaje;
    if (isset($yaEscrito[$firma])) {
        return;
    }
    $yaEscrito[$firma] = true;

    $directorio = RUTA_BASE . '/logs';
    if (!is_dir($directorio)) {
        @mkdir($directorio, 0755, true);
    }
    if (!is_dir($directorio) || !is_writable($directorio)) {
        return;
    }

    $ruta = ruta_log_caidas();
    $ahora = time();
    $marca = $directorio . '/.ultima_caida';
    $previa = json_decode((string) @file_get_contents($marca), true);
    if (is_array($previa) && ($previa['firma'] ?? '') === $firma && $ahora - (int) ($previa['en'] ?? 0) < 60) {
        return;
    }
    @file_put_contents($marca, json_encode(['firma' => $firma, 'en' => $ahora]));

    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $pagina = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $pagina) ?? '';
    $entrada = [
        'fecha' => date('Y-m-d H:i:s'),
        'tipo' => $tipo,
        'mensaje' => $mensaje,
        'pagina' => substr($pagina, 0, 80),
        'archivo' => substr(basename($archivo), 0, 80),
        'linea' => $linea,
        'metodo' => substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 8),
        'memoria' => memory_get_usage(true),
    ];
    $lineaLog = json_encode($entrada, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($ruta, $lineaLog, FILE_APPEND | LOCK_EX);

    if (is_file($ruta) && filesize($ruta) > 512000) {
        $lineas = @file($ruta, FILE_IGNORE_NEW_LINES);
        if (is_array($lineas) && count($lineas) > 200) {
            $lineas = array_slice($lineas, -200);
            @file_put_contents($ruta, implode("\n", $lineas) . "\n", LOCK_EX);
        }
    }
}

function leer_caidas(int $limite = 80): array
{
    $ruta = ruta_log_caidas();
    if (!is_file($ruta)) {
        return [];
    }
    $lineas = @file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lineas)) {
        return [];
    }
    $lineas = array_slice($lineas, -1 * max(1, $limite));
    $salida = [];
    foreach (array_reverse($lineas) as $linea) {
        $dato = json_decode($linea, true);
        if (is_array($dato) && isset($dato['fecha'], $dato['mensaje'])) {
            $salida[] = $dato;
        }
    }
    return $salida;
}

function vaciar_caidas(): void
{
    $ruta = ruta_log_caidas();
    if (is_file($ruta)) {
        @file_put_contents($ruta, '', LOCK_EX);
    }
}

function registrar_presencia(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (!function_exists('usuario_real') || !function_exists('bd')) {
        return;
    }
    $usuario = usuario_real();
    if (!$usuario) {
        return;
    }
    $ahora = time();
    $sinTabla = (int) ($_SESSION['presencia_sin_tabla'] ?? 0);
    if ($sinTabla > 0 && $ahora - $sinTabla < 600) {
        return;
    }
    $anterior = (int) ($_SESSION['presencia_en'] ?? 0);
    if ($anterior > 0 && $ahora - $anterior < 90) {
        return;
    }

    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $pagina = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $pagina) ?? '';
    $pagina = substr($pagina, 0, 80);
    try {
        $consulta = bd()->prepare(
            'INSERT INTO user_presence (user_id, last_seen_at, pagina)
             VALUES (?, NOW(), ?)
             ON DUPLICATE KEY UPDATE last_seen_at = NOW(), pagina = VALUES(pagina)'
        );
        $consulta->execute([(int) $usuario['id'], $pagina]);
        $_SESSION['presencia_en'] = $ahora;
        unset($_SESSION['presencia_sin_tabla']);
    } catch (PDOException $e) {
        $codigo = (string) $e->getCode();
        if ($codigo === '42S02' || strpos($e->getMessage(), 'user_presence') !== false) {
            $_SESSION['presencia_sin_tabla'] = $ahora;
        }
    }
}

function bytes_desde_ini(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '' || $valor === '-1') {
        return 0;
    }
    $unidad = strtolower(substr($valor, -1));
    $numero = (float) $valor;
    if ($unidad === 'g') {
        return (int) ($numero * 1073741824);
    }
    if ($unidad === 'm') {
        return (int) ($numero * 1048576);
    }
    if ($unidad === 'k') {
        return (int) ($numero * 1024);
    }
    return (int) $numero;
}

function medir_campus(): array
{
    $resultado = [
        'presencia_lista' => false,
        'en_linea' => 0,
        'en_leccion' => 0,
        'por_rol' => [],
        'personas' => [],
        'base' => ['ok' => false, 'ms' => null, 'estado' => [], 'error' => ''],
        'php' => [
            'version' => PHP_VERSION,
            'memoria' => memory_get_usage(true),
            'pico' => memory_get_peak_usage(true),
            'limite' => bytes_desde_ini((string) ini_get('memory_limit')),
            'max_tiempo' => (int) ini_get('max_execution_time'),
        ],
        'disco' => ['libre' => null, 'total' => null],
        'carga' => function_exists('sys_getloadavg') ? @sys_getloadavg() : null,
        'opcache' => null,
    ];

    $libre = @disk_free_space(RUTA_BASE);
    $totalDisco = @disk_total_space(RUTA_BASE);
    if ($libre !== false) {
        $resultado['disco']['libre'] = (int) $libre;
    }
    if ($totalDisco !== false) {
        $resultado['disco']['total'] = (int) $totalDisco;
    }

    if (function_exists('opcache_get_status')) {
        $opcache = @opcache_get_status(false);
        if (is_array($opcache) && !empty($opcache['opcache_enabled'])) {
            $aciertos = (int) ($opcache['opcache_statistics']['hits'] ?? 0);
            $fallos = (int) ($opcache['opcache_statistics']['misses'] ?? 0);
            $resultado['opcache'] = [
                'aciertos' => $aciertos,
                'fallos' => $fallos,
            ];
        }
    }

    try {
        $inicio = microtime(true);
        bd()->query('SELECT 1');
        $resultado['base']['ok'] = true;
        $resultado['base']['ms'] = (microtime(true) - $inicio) * 1000;
        try {
            $nombres = "'Threads_connected','Threads_running','Slow_queries','Questions','Uptime','Max_used_connections','Aborted_connects'";
            $filas = bd()->query('SHOW GLOBAL STATUS WHERE Variable_name IN (' . $nombres . ')')->fetchAll();
            foreach ($filas as $fila) {
                $resultado['base']['estado'][$fila['Variable_name']] = $fila['Value'];
            }
        } catch (PDOException $e) {
            $resultado['base']['error'] = 'La base respondió, pero no permitió leer su estado general.';
        }
    } catch (PDOException $e) {
        $resultado['base']['ok'] = false;
        $resultado['base']['error'] = 'No se pudo consultar la base de datos.';
    }

    try {
        $conteo = bd()->query(
            'SELECT u.role, COUNT(*) AS total
             FROM user_presence p
             JOIN users u ON u.id = p.user_id
             WHERE p.last_seen_at >= (NOW() - INTERVAL 5 MINUTE)
             GROUP BY u.role'
        )->fetchAll();
        $resultado['presencia_lista'] = true;
        foreach ($conteo as $fila) {
            $resultado['por_rol'][$fila['role']] = (int) $fila['total'];
            $resultado['en_linea'] += (int) $fila['total'];
        }
        $personas = bd()->query(
            'SELECT u.name, u.role, p.pagina,
                    TIMESTAMPDIFF(SECOND, p.last_seen_at, NOW()) AS hace_segundos
             FROM user_presence p
             JOIN users u ON u.id = p.user_id
             WHERE p.last_seen_at >= (NOW() - INTERVAL 5 MINUTE)
             ORDER BY p.last_seen_at DESC
             LIMIT 200'
        )->fetchAll();
        $resultado['personas'] = $personas;
        foreach ($personas as $persona) {
            if (($persona['pagina'] ?? '') === 'leccion.php') {
                $resultado['en_leccion']++;
            }
        }
        if ($resultado['en_linea'] > count($personas)) {
            $enLeccion = bd()->query(
                'SELECT COUNT(*) FROM user_presence
                 WHERE pagina = "leccion.php" AND last_seen_at >= (NOW() - INTERVAL 5 MINUTE)'
            )->fetchColumn();
            $resultado['en_leccion'] = (int) $enLeccion;
        }
    } catch (PDOException $e) {
        $resultado['presencia_lista'] = false;
    }

    return $resultado;
}
