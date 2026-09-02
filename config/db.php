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

    // Columnas que páginas clave necesitan y podrían faltar en BD viejas.
    $columnasNecesarias = [
        ['usuarios', 'dia_cobro', 'TINYINT(4) DEFAULT NULL'],
        ['alumnos', 'becado', 'TINYINT(1) DEFAULT 0'],
        ['alumnos', 'dia_vencimiento', 'INT(11) DEFAULT NULL'],
        ['alumnos', 'dias_gracia', 'INT(11) DEFAULT NULL'],
        ['alumnos', 'horas_profesionales', 'DECIMAL(6,2) DEFAULT 0.00'],
        ['entradas_alumno', 'cantidad_total', 'INT(11) NOT NULL DEFAULT 0'],
        ['pagos', 'id_evento', 'INT(11) DEFAULT NULL'],
        ['pagos', 'concepto', 'VARCHAR(200) DEFAULT NULL'],
    ];

    // Tablas que algunas vistas usan y podrían no existir en BD viejas.
    $tablasNecesarias = [
        'horas_profesionales_log' => "CREATE TABLE IF NOT EXISTS horas_profesionales_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_alumno INT NOT NULL,
            fecha DATE NOT NULL,
            horas DECIMAL(6,2) NOT NULL DEFAULT 0,
            detalle VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    $hechas = $pdo->query("SELECT nombre FROM migraciones_aplicadas")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columnasNecesarias as [$tabla, $columna, $tipo]) {
        $nombre = "col_{$tabla}_{$columna}";
        if (in_array($nombre, $hechas, true)) {
            continue;
        }
        try {
            try {
                $existe = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $existe->execute([$tabla, $columna]);
                if ((int)$existe->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `$columna` $tipo");
                }
            } catch (PDOException $e) {
                // La tabla base puede no existir aún; se ignora.
            }
        } catch (PDOException $e) {
            // Ignorar errores de permisos u otros.
        }
        $pdo->prepare("INSERT IGNORE INTO migraciones_aplicadas (nombre) VALUES (?)")->execute([$nombre]);
    }

    foreach ($tablasNecesarias as $nombre => $sql) {
        if (in_array($nombre, $hechas, true)) {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignorar errores de permisos u otros.
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