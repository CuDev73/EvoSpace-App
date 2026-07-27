<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$termino = $_GET['q'] ?? '';
if (strlen($termino) < 2) {
    echo json_encode([]);
    exit;
}

$resultados = buscarCompradores($pdo, $termino);
echo json_encode($resultados);
?>