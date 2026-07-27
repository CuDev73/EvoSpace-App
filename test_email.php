<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

$resultado = enviarCorreo(
    'villoan73@gmail.com', // o el correo de un padre para probar
    'Prueba EvoSpace',
    '<h1 style="color:#c81015;">✅ Funciona!</h1><p>El sistema de correos está configurado correctamente.</p>'
);

var_dump($resultado);
?>