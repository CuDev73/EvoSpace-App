<?php
// config/db.php

$host = 'localhost';
$dbname = 'evospace';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Incluir funciones
require_once __DIR__ . '/../helpers/functions.php';

// Configuración de correo (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'villoan73@gmail.com');
define('SMTP_PASS', 'ffywsdnearepwhfs'); 
define('SMTP_FROM', 'villoan73@gmail.com');
define('SMTP_FROM_NAME', 'EvoSpace - Escuela');