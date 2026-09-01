<?php
// config/db.php

$configDir = __DIR__;
$envFile = $configDir . '/.env';
$env = [];
if (file_exists($envFile)) {
    $lineas = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') {
            continue;
        }
        $pos = strpos($linea, '=');
        if ($pos === false) {
            continue;
        }
        $clave = trim(substr($linea, 0, $pos));
        $valor = trim(substr($linea, $pos + 1));
        $env[$clave] = trim($valor, '"');
    }
}

$host    = $env['DB_HOST'] ?? 'localhost';
$dbname  = $env['DB_NAME'] ?? 'evospace';
$user    = $env['DB_USER'] ?? 'root';
$pass    = $env['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos.");
}

// Incluir funciones
require_once __DIR__ . '/../helpers/functions.php';

// Configuración de correo (SMTP) desde .env
define('SMTP_HOST', $env['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', (int)($env['SMTP_PORT'] ?? 587));
define('SMTP_USER', $env['SMTP_USER'] ?? '');
define('SMTP_PASS', $env['SMTP_PASS'] ?? '');
define('SMTP_FROM', $env['SMTP_FROM'] ?? '');
define('SMTP_FROM_NAME', $env['SMTP_FROM_NAME'] ?? 'EvoSpace - Escuela');