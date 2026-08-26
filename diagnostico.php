<?php
/**
 * Diagnóstico del servidor — elimina este archivo después de revisar.
 */
header('Content-Type: text/html; charset=utf-8');

$pruebas = [];

$pruebas[] = ['PHP', 'Versión ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>=')];

$extensiones = ['pdo', 'pdo_mysql', 'mbstring', 'session'];
foreach ($extensiones as $ext) {
    $pruebas[] = ['Extensión ' . $ext, extension_loaded($ext) ? 'Instalada' : 'Falta', extension_loaded($ext)];
}

$configOk = file_exists(__DIR__ . '/config/config.php');
$pruebas[] = ['Config', $configOk ? 'config.php encontrado' : 'No existe config.php', $configOk];

if ($configOk) {
    require_once __DIR__ . '/config/app.php';

    $pruebas[] = ['URL_APP', defined('URL_APP') ? URL_APP : 'No definida', defined('URL_APP')];
    $pruebas[] = ['Carpeta subidas', is_dir(RUTA_SUBIDAS) ? 'OK' : 'No existe (se creará sola)', true];

    try {
        require_once __DIR__ . '/includes/gestor_bd.php';
        $servidor = GestorBd::probarConexionServidor();
        $pruebas[] = ['MySQL servidor', $servidor['mensaje'], $servidor['exito']];

        $bd = GestorBd::obtenerConexionBaseDatos();
        $pruebas[] = ['Base de datos', $bd ? 'Conectada a ' . BD_NOMBRE : 'No se pudo acceder a ' . BD_NOMBRE, $bd !== null];
    } catch (Throwable $e) {
        $pruebas[] = ['Error', $e->getMessage(), false];
    }
}

$todoOk = !in_array(false, array_column($pruebas, 2), true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico · AulaVirtual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width:720px">
    <h1 class="h3 mb-3">Diagnóstico del servidor</h1>
    <div class="alert alert-<?= $todoOk ? 'success' : 'warning' ?>">
        <?= $todoOk ? 'Todo parece correcto. Prueba ' : 'Hay problemas. Revisa ' ?>
        <a href="instalacion.php">instalacion.php</a> o
        <a href="iniciar-sesion.php">iniciar-sesion.php</a>.
    </div>
    <table class="table table-bordered bg-white">
        <thead><tr><th>Prueba</th><th>Resultado</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($pruebas as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p[0]) ?></td>
                <td><?= htmlspecialchars($p[1]) ?></td>
                <td><span class="badge bg-<?= $p[2] ? 'success' : 'danger' ?>"><?= $p[2] ? 'OK' : 'Error' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="text-muted small">Elimina <code>diagnostico.php</code> cuando termines de revisar.</p>
</div>
</body>
</html>
