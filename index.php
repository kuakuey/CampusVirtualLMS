<?php
require_once __DIR__ . '/includes/funciones.php';

if (esta_logueado()) {
    redirigir('panel.php');
}
redirigir('iniciar-sesion.php');
