<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../includes/header.php';   // ← primero carga functions.php
include '../includes/navbar.php';
require_once '../config/db.php';

verificarPermiso('cantina');   // ← ahora funciona
?>

<div class="container mt-3">
    <div class="row g-4">
        <!-- Tarjeta: Nueva Venta -->
        <div class="col-md-4">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-cart-plus fs-1 text-danger"></i>
                    <h5 class="card-title mt-3">Nueva Venta</h5>
                    <p class="card-text text-muted small">Registrar una venta de productos</p>
                    <a href="cantina/ventas.php?accion=nueva" class="btn btn-danger mt-2">
                        <i class="bi bi-plus-circle"></i> Ir a ventas
                    </a>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Gestionar Productos -->
        <div class="col-md-4">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-box-seam fs-1 text-danger"></i>
                    <h5 class="card-title mt-3">Productos</h5>
                    <p class="card-text text-muted small">Agregar, editar o desactivar productos</p>
                    <a href="cantina/productos.php" class="btn btn-danger mt-2">
                        <i class="bi bi-pencil-square"></i> Gestionar
                    </a>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Resumen de Caja -->
        <div class="col-md-4">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-cash-stack fs-1 text-danger"></i>
                    <h5 class="card-title mt-3">Resumen de Caja</h5>
                    <p class="card-text text-muted small">Ver resumen diario y semanal</p>
                    <a href="cantina/resumen.php" class="btn btn-danger mt-2">
                        <i class="bi bi-bar-chart"></i> Ver resumen
                    </a>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Historial de Ventas -->
        <div class="col-md-4">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-clock-history fs-1 text-danger"></i>
                    <h5 class="card-title mt-3">Historial</h5>
                    <p class="card-text text-muted small">Listado completo de ventas</p>
                    <a href="cantina/ventas.php" class="btn btn-danger mt-2">
                        <i class="bi bi-list-ul"></i> Ver todas
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>