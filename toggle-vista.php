<?php
require_once __DIR__ . '/includes/funciones.php';
requiere_sesion();

$real = usuario_real();
if (!$real || !in_array($real['role'], ['admin', 'teacher'], true)) {
    mensaje_flash('danger', 'No tienes permiso para cambiar de vista.');
    redirigir('panel.php');
}

verificar_csrf();

if (esta_en_vista_estudiante()) {
    desactivar_vista_estudiante();
    mensaje_flash('info', 'Volviste a la vista de ' . etiqueta_rol($real['role']) . '.');
} else {
    activar_vista_estudiante();
    mensaje_flash('info', 'Estás viendo el sistema como Estudiante. Pulsa el botón para volver.');
}

redirigir($_POST['volver'] ?? 'panel.php');
