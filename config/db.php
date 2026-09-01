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

// ============================================================
// MIGRACIONES AUTOMÁTICAS (una sola vez por columna/tabla)
// Asegura que el esquema mínimo exista aunque la BD se importó
// desde una versión anterior de evospace.sql.
// ============================================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS migraciones_aplicadas (
        nombre VARCHAR(100) PRIMARY KEY,
        aplicada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $migraciones = [
        // fase13_dia_cobro.sql
        'fase13_dia_cobro' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'dia_cobro'",
        // fase5_recargos.sql
        'fase5_recargo_por_dia' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'recargo_por_dia'",
        // fase12_config_correo.sql
        'fase12_config_correo' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion' AND COLUMN_NAME = 'smtp_host'",
    ];

    $hechas = $pdo->query("SELECT nombre FROM migraciones_aplicadas")->fetchAll(PDO::FETCH_COLUMN);

    $acciones = [
        'fase13_dia_cobro'   => "ALTER TABLE usuarios ADD COLUMN dia_cobro TINYINT(4) DEFAULT NULL AFTER activo",
        'fase5_recargo_por_dia'   => "INSERT INTO configuracion (clave, valor) SELECT 'recargo_por_dia', '1000' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'recargo_por_dia')",
        'fase12_config_correo'   => "INSERT INTO configuracion (clave, valor) SELECT 'smtp_host', '' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'smtp_host')",
    ];

    foreach ($migraciones as $nombre => $checkSQL) {
        if (in_array($nombre, $hechas, true)) {
            continue;
        }
        $falta = (int)$pdo->query($checkSQL)->fetchColumn() === 0;
        if ($falta) {
            try {
                $pdo->exec($acciones[$nombre]);
            } catch (PDOException $e) {
                // Ignorar si ya existía o la acción no aplica
            }
        }
        $pdo->prepare("INSERT IGNORE INTO migraciones_aplicadas (nombre) VALUES (?)")->execute([$nombre]);
    }
} catch (PDOException $e) {
    // Si la BD no permite esto (permisos), se ignora silenciosamente.
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