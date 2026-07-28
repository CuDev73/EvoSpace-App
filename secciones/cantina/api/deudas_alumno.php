<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    exit;
}
require_once '../../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT c.id_compra, c.fecha, c.producto, c.monto
    FROM compras_alumnos c
    WHERE c.id_alumno = ? AND c.pagado = 0
    ORDER BY c.fecha DESC
");
$stmt->execute([$id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
