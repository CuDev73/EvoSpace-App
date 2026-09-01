<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
verificarTokenCSRF();

$id_venta = isset($_POST['id_venta']) ? (int)$_POST['id_venta'] : 0;
if ($id_venta) {
    try {
        eliminarVenta($pdo, $id_venta);
        header('Location: index.php?eliminado=1');
    } catch (Exception $e) {
        header('Location: index.php?error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: index.php');
}
exit;
?>