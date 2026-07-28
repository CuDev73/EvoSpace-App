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

    <div class="row g-4">
        <!-- 1. Nueva Venta -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-cart-plus fs-1 text-danger"></i>
                    <h5 class="card-title mt-3">Nueva Venta</h5>
                    <a href="ventas/nueva.php" class="btn btn-danger mt-2"><i class="bi bi-plus-circle"></i> Registrar</a>
                </div>
            </div>
        </div>

        <!-- 2. Compras Alumnos -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-person-lines-fill fs-1 text-warning"></i>
                    <h5 class="card-title mt-3">Compras Alumnos</h5>
                    <p class="card-text text-muted small">Fiado y pagos</p>
                    <a href="compras_alumnos/index.php" class="btn btn-warning mt-2 text-dark"><i class="bi bi-arrow-right"></i> Gestionar</a>
                </div>
            </div>
        </div>

        <!-- 3. Productos -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-box-seam fs-1 text-success"></i>
                    <h5 class="card-title mt-3">Productos</h5>
                    <a href="productos/index.php" class="btn btn-success mt-2"><i class="bi bi-pencil-square"></i> Gestionar</a>
                </div>
            </div>
        </div>

        <!-- 4. Compras a Proveedores -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-cart-dash fs-1 text-secondary"></i>
                    <h5 class="card-title mt-3">Compras a Proveedores</h5>
                    <p class="card-text text-muted small">Registro de compras y gastos</p>
                    <a href="compras_proveedores/index.php" class="btn btn-secondary mt-2"><i class="bi bi-arrow-right"></i> Gestionar</a>
                </div>
            </div>
        </div>

        <!-- 5. Proveedores -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-truck fs-1 text-info"></i>
                    <h5 class="card-title mt-3">Proveedores</h5>
                    <a href="proveedores/index.php" class="btn btn-info mt-2 text-white"><i class="bi bi-arrow-right"></i> Gestionar</a>
                </div>
            </div>
        </div>

        <!-- (Opcional) Historial de Ventas -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-list-ul fs-1 text-primary"></i>
                    <h5 class="card-title mt-3">Historial de Ventas</h5>
                    <a href="ventas/index.php" class="btn btn-primary mt-2"><i class="bi bi-eye"></i> Ver</a>
                </div>
            </div>
        </div>

        <!-- (Opcional) Resumen -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-graph-up-arrow fs-1 text-danger"></i>
                    <h5 class="card-title mt-3">Resumen y Ganancias</h5>
                    <a href="resumen.php" class="btn btn-danger mt-2"><i class="bi bi-bar-chart"></i> Ver</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>