<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
verificarPermiso('cantina');

$hoy = date('Y-m-d');
$semana = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $semana[] = $fecha;
}

$labels = [];
$valores = [];
foreach ($semana as $fecha) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total FROM ventas WHERE DATE(fecha) = ?");
    $stmt->execute([$fecha]);
    $total = $stmt->fetch()['total'];
    $labels[] = date('d/m', strtotime($fecha));
    $valores[] = (float)$total;
}

header('Content-Type: application/json');
echo json_encode(['labels' => $labels, 'valores' => $valores]);