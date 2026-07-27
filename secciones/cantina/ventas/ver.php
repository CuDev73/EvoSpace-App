<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_venta) {
    header('Location: index.php');
    exit;
}

$venta = obtenerVenta($pdo, $id_venta);
if (!$venta) {
    header('Location: index.php?error=1');
    exit;
}

$detalles = obtenerDetalleVenta($pdo, $id_venta);

include '../../../includes/header.php';
include '../../../includes/navbar.php';
?>

<div class="container mt-3">
    <!-- Botones de Volver -->
    <div class="row g-2 mb-3">
        <div class="col-md-6">
            <button onclick="history.back()" class="btn btn-secondary w-100">
                <i class="bi bi-arrow-left"></i> Volver atrás
            </button>
        </div>
        <div class="col-md-6">
            <a href="../index.php" class="btn btn-secondary w-100">
                <i class="bi bi-house"></i> Volver al panel de Cantina
            </a>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-receipt"></i> Detalle de Venta #<?= $id_venta ?></h4>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver al historial</a>
    </div>

    <div class="card shadow mb-3">
        <div class="card-header bg-danger text-white">Información de la venta</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($venta->fecha)) ?></div>
                <div class="col-md-3"><strong>Comprador:</strong> <?= htmlspecialchars($venta->nombre_comprador ?? 'Anónimo') ?></div>
                <div class="col-md-3"><strong>Tipo:</strong> <?= ucfirst($venta->tipo_comprador ?? 'otro') ?></div>
                <div class="col-md-3"><strong>Método de pago:</strong> <?= $venta->metodo_pago ?></div>
                <div class="col-md-3"><strong>Estado:</strong> <span class="badge bg-<?= $venta->estado_pago == 'pagado' ? 'success' : 'danger' ?>"><?= ucfirst($venta->estado_pago ?? 'pagado') ?></span></div>
                <div class="col-md-3"><strong>Total:</strong> Gs <?= number_format($venta->total, 0, ',', '.') ?></div>
                <?php if ($venta->observaciones): ?>
                    <div class="col-md-6"><strong>Observaciones:</strong> <?= htmlspecialchars($venta->observaciones) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header bg-danger text-white">Productos</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Precio unitario</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d->producto_nombre) ?></td>
                                <td class="text-center"><?= $d->cantidad ?></td>
                                <td class="text-end">Gs <?= number_format($d->precio_unitario, 0, ',', '.') ?></td>
                                <td class="text-end">Gs <?= number_format($d->subtotal, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end">Gs <?= number_format($venta->total, 0, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Botones de Volver (abajo) -->
    <div class="row g-2 mt-4">
        <div class="col-md-6">
            <button onclick="history.back()" class="btn btn-secondary w-100">
                <i class="bi bi-arrow-left"></i> Volver atrás
            </button>
        </div>
        <div class="col-md-6">
            <a href="../index.php" class="btn btn-secondary w-100">
                <i class="bi bi-house"></i> Volver al panel de Cantina
            </a>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>