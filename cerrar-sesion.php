<?php
require_once __DIR__ . '/includes/funciones.php';
$_SESSION = [];
session_destroy();
session_start();
mensaje_flash('success', 'Sesión cerrada correctamente.');
redirigir('iniciar-sesion.php');
