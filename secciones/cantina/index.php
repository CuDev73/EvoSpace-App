<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../../includes/header.php';
include '../../includes/navbar.php';
require_once '../../config/db.php';
require_once 'funciones.php';
verificarPermiso('cantina');
?>

<div class="container mt-3">

    <div class="row g-4 justify-content-center">
        <!-- 1. Nueva Venta -->
        <div class="col-sm-6 col-md-4 col-lg-3">
            <a href="ventas/nueva.php" class="text-decoration-none text-reset">
                <div class="card shadow h-100 text-center">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                        <i class="bi bi-cart-plus fs-1 text-danger"></i>
                        <h5 class="card-title mt-3">Nueva Venta</h5>
                        <span class="small text-muted">Registrar una venta</span>
                        <span class="btn btn-danger mt-3"><i class="bi bi-plus-circle"></i> Registrar</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- 2. Productos -->
        <div class="col-sm-6 col-md-4 col-lg-3">
            <a href="productos/index.php" class="text-decoration-none text-reset">
                <div class="card shadow h-100 text-center">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                        <i class="bi bi-box-seam fs-1 text-success"></i>
                        <h5 class="card-title mt-3">Productos</h5>
                        <span class="small text-muted">Gestionar stock y precios</span>
                        <span class="btn btn-success mt-3"><i class="bi bi-pencil-square"></i> Gestionar</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- 4. Historial de Ventas -->
        <div class="col-sm-6 col-md-4 col-lg-3">
            <a href="ventas/index.php" class="text-decoration-none text-reset">
                <div class="card shadow h-100 text-center">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                        <i class="bi bi-list-ul fs-1 text-primary"></i>
                        <h5 class="card-title mt-3">Historial de Ventas</h5>
                        <span class="small text-muted">Ver todas las ventas</span>
                        <span class="btn btn-primary mt-3"><i class="bi bi-eye"></i> Ver</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- 5. Resumen -->
        <div class="col-sm-6 col-md-4 col-lg-3">
            <a href="resumen.php" class="text-decoration-none text-reset">
                <div class="card shadow h-100 text-center">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                        <i class="bi bi-graph-up-arrow fs-1 text-danger"></i>
                        <h5 class="card-title mt-3">Resumen y Ganancias</h5>
                        <span class="small text-muted">Reportes de la cantina</span>
                        <span class="btn btn-danger mt-3"><i class="bi bi-bar-chart"></i> Ver</span>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>