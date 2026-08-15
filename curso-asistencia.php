<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$id = (int) ($_GET['id'] ?? 0);
$query = [];
foreach (['vista', 'fecha', 'desde', 'hasta'] as $campo) {
    if (!empty($_GET[$campo])) {
        $query[$campo] = (string) $_GET[$campo];
    }
}
redirigir($id > 0 ? url_asistencia_curso($id, $query) : 'cursos.php');
