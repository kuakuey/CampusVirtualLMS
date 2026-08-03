<?php
require_once __DIR__ . '/includes/funciones.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    if (esta_logueado()) {
        mensaje_flash('danger', 'Enlace de inscripción inválido.');
        redirigir(usuario_actual()['role'] === 'student' ? 'catalogo.php' : 'panel.php');
    }
    redirigir('iniciar-sesion.php');
}

if (!esta_logueado()) {
    mensaje_flash('info', 'Inicia sesión para inscribirte en el curso.');
    redirigir('iniciar-sesion.php?redirect=' . urlencode(URL_INSCRIPCION_CURSO . '?token=' . urlencode($token)));
}

$usuario = usuario_actual();
$curso = obtener_curso_por_token_inscripcion($token);

if (!$curso || ($curso['enrollment_type'] ?? '') !== 'url') {
    mensaje_flash('danger', 'Enlace de inscripción no válido o expirado.');
    redirigir($usuario['role'] === 'student' ? 'catalogo.php' : 'panel.php');
}

if ($curso['status'] !== 'published') {
    mensaje_flash('warning', 'Este curso aún no está publicado.');
    redirigir($usuario['role'] === 'student' ? 'catalogo.php' : 'panel.php');
}

if (!inscripcion_abierta($curso)) {
    mensaje_flash('danger', 'El plazo de inscripción para este curso ha finalizado.');
    redirigir($usuario['role'] === 'student' ? 'catalogo.php' : 'panel.php');
}

if ($usuario['role'] !== 'student') {
    mensaje_flash('info', 'Como docente o administrador ya tienes acceso al curso.');
    redirigir('curso.php?id=' . (int) $curso['id']);
}

if (esta_matriculado((int) $curso['id'], (int) $usuario['id'])) {
    mensaje_flash('info', 'Ya estás inscrito en este curso.');
} else {
    inscribir_estudiante_en_curso((int) $curso['id'], (int) $usuario['id']);
    mensaje_flash('success', '¡Te has inscrito en «' . $curso['title'] . '»!');
}

redirigir('curso.php?id=' . (int) $curso['id']);
