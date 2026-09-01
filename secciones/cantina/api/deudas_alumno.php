<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    exit;
}
require_once '../../../config/db.php';
verificarPermiso('cantina');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT v.id_venta AS id_compra, v.fecha, v.observaciones AS producto, v.total AS monto
    FROM ventas v
    WHERE v.id_alumno = ? AND v.estado_pago IN ('pendiente','parcial')
    ORDER BY v.fecha DESC
");
$stmt->execute([$id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
