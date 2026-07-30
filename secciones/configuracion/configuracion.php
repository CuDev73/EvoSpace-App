<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../config/db.php';

$limiteHoras = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'limite_horas_profesionales'")->fetchColumn() ?: 200;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limite_horas'])) {
    $nuevo = (float)$_POST['limite_horas'];
    if ($nuevo > 0) {
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'limite_horas_profesionales'");
        $stmt->execute([$nuevo]);
        header('Location: configuracion.php?ok=1');
        exit;
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
verificarPermiso('configuracion');
?>

<div class="container mt-3">
    <div class="page-header">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-gear-fill me-2"></i> Panel de Configuración</h3>
            <small>Administra la configuración del sistema</small>
        </div>
    </div>

    <div class="row g-4">
        <!-- Tarjeta: Configurar Pagos -->
        <div class="col-md-4">
            <div class="card shadow-hover h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-coin fs-1 text-danger"></i>
                    <h5 class="card-title mt-3">Pagos</h5>
                    <p class="card-text text-muted small">Editar precios por curso y conceptos</p>
                    <a href="configurar_pagos.php" class="btn btn-evo mt-2">
                        <i class="bi bi-arrow-right-circle"></i> Configurar
                    </a>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Configurar Recibo -->
        <div class="col-md-4">
            <div class="card shadow-hover h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-receipt fs-1 text-success"></i>
                    <h5 class="card-title mt-3">Recibo</h5>
                    <p class="card-text text-muted small">Nombre, RUC, logo, mensaje del recibo PDF</p>
                    <a href="configurar_recibo.php" class="btn btn-evo mt-2">
                        <i class="bi bi-arrow-right-circle"></i> Configurar
                    </a>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Horas Profesionales -->
        <div class="col-md-4">
            <div class="card shadow-hover h-100 text-center">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-clock-history fs-1 text-info"></i>
                    <h5 class="card-title mt-3">Horas Profesionales</h5>
                    <p class="card-text text-muted small">Límite de horas para nivel Superior</p>
                    <a href="#" class="btn btn-evo mt-2" data-bs-toggle="modal" data-bs-target="#modalHoras">
                        <i class="bi bi-arrow-right-circle"></i> Configurar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="container mt-2"><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Límite actualizado.</div></div>
<?php endif; ?>
<div class="modal fade" id="modalHoras" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history"></i> Límite de horas profesionales</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <label class="form-label">Horas requeridas para completar el nivel Superior</label>
                    <input type="number" step="1" name="limite_horas" class="form-control"
                           value="<?= (int)$limiteHoras ?>" min="1" required>
                    <small class="text-muted">Este límite se usa para calcular el progreso en la ficha del alumno.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>