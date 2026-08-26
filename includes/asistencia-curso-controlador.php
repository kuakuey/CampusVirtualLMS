<?php
if (!asegurar_tabla_asistencias()) {
    mensaje_flash('danger', 'No se pudo preparar la tabla de asistencias. Actualiza las tablas en instalación.');
    redirigir('curso.php?id=' . $id);
}

$vista = $_GET['vista'] ?? ($esPropietario ? 'tomar' : 'resumen');
if ($esPropietario && !in_array($vista, ['tomar', 'reportes'], true)) {
    $vista = 'tomar';
}
if (!$esPropietario) {
    $vista = 'resumen';
}

$fecha = fecha_asistencia_valida($_GET['fecha'] ?? $_POST['fecha'] ?? '') ?: date('Y-m-d');
$desde = fecha_asistencia_valida($_GET['desde'] ?? '') ?: date('Y-m-01');
$hasta = fecha_asistencia_valida($_GET['hasta'] ?? '') ?: date('Y-m-d');
if ($desde > $hasta) {
    $tmp = $desde;
    $desde = $hasta;
    $hasta = $tmp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_asistencia' && $esPropietario) {
        $fechaGuardar = fecha_asistencia_valida($_POST['fecha'] ?? '');
        $estados = $_POST['estado'] ?? [];
        if (!$fechaGuardar) {
            mensaje_flash('danger', 'La fecha de asistencia no es válida.');
            redirigir(url_asistencia_curso($id));
        }
        if (!is_array($estados) || !$estados) {
            mensaje_flash('warning', 'No se recibió el listado de estudiantes.');
            redirigir(url_asistencia_curso($id, ['fecha' => $fechaGuardar]));
        }
        $guardados = guardar_asistencias($id, $fechaGuardar, $estados, (int) $usuario['id']);
        mensaje_flash('success', 'Asistencia del ' . formatear_fecha($fechaGuardar) . ' guardada (' . $guardados . ' estudiante(s)).');
        redirigir(url_asistencia_curso($id, ['fecha' => $fechaGuardar]));
    }

    if ($accion === 'eliminar_asistencia' && $esPropietario) {
        $fechaBorrar = fecha_asistencia_valida($_POST['fecha'] ?? '');
        if (!$fechaBorrar) {
            mensaje_flash('danger', 'La fecha de asistencia no es válida.');
            redirigir(url_asistencia_curso($id));
        }
        $borrados = eliminar_asistencias_fecha($id, $fechaBorrar);
        if ($borrados > 0) {
            mensaje_flash('success', 'Se eliminó el listado del ' . formatear_fecha($fechaBorrar) . ' (' . $borrados . ' registro(s)).');
        } else {
            mensaje_flash('warning', 'No había un listado guardado para esa fecha.');
        }
        $vistaDestino = ($_POST['volver_a'] ?? '') === 'reportes' ? 'reportes' : 'tomar';
        $query = ['vista' => $vistaDestino];
        if ($vistaDestino === 'tomar') {
            $query['fecha'] = $fechaBorrar;
        }
        redirigir(url_asistencia_curso($id, $query));
    }

    mensaje_flash('danger', 'No se pudo procesar la acción.');
    redirigir(url_asistencia_curso($id));
}

$estudiantesAsistencia = [];
$asistenciasFecha = [];
$yaRegistrada = false;
$reporteFechas = [];
$detallePropio = [];
$resumen = [
    'sesiones' => 0,
    'presentes' => 0,
    'ausentes' => 0,
    'tardes' => 0,
    'justificados' => 0,
    'promedio' => 0,
];

if ($esPropietario) {
    $estudiantesAsistencia = estudiantes_activos_curso($id);
    $asistenciasFecha = asistencias_por_fecha($id, $fecha);
    $yaRegistrada = count($asistenciasFecha) > 0;
    $reporteFechas = reporte_asistencia_fechas_todas($id);
    $resumen = resumen_desde_fechas($reporteFechas);
} else {
    $detallePropio = asistencias_estudiante_curso((int) $usuario['id'], $id);
    $resumen = resumen_asistencias($detallePropio);
}
