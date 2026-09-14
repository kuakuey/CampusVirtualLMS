<?php
require_once __DIR__ . '/includes/funciones.php';

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}
mensaje_flash('success', 'Sesión cerrada correctamente.');
redirigir('iniciar-sesion.php');
