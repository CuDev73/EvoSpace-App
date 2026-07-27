<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    exit('No autorizado');
}

require_once '../config/db.php';

if (!isset($_GET['id_curso'])) {
    http_response_code(400);
    exit('Falta id_curso');
}

$id_curso = (int)$_GET['id_curso'];

// Obtener porcentaje de beca
$stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_beca'");
$porcentaje_beca = (float)$stmt->fetchColumn() ?: 45.45;

$sql = "SELECT concepto, precio FROM precios WHERE id_curso = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_curso]);
$precios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular precio con beca redondeado al millar para cada concepto
foreach ($precios as &$p) {
    if ($p['concepto'] === 'cuota') {
        $precioConBeca = $p['precio'] * ($porcentaje_beca / 100);
        $p['precio_con_beca'] = round($precioConBeca / 1000) * 1000;
    } else {
        $p['precio_con_beca'] = $p['precio'];
    }
}

header('Content-Type: application/json');
echo json_encode($precios);
?>