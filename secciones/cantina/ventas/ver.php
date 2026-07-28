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

// Marcar como pagado
if (isset($_POST['marcar_pagado'])) {
    $stmt = $pdo->prepare("UPDATE ventas SET estado_pago = 'pagado' WHERE id_venta = ?");
    $stmt->execute([$id_venta]);
    header("Location: ver.php?id=$id_venta&pagado=1");
    exit;
}

$detalles = obtenerDetalleVenta($pdo, $id_venta);

$mostrarVolver = true;
$volverUrl = 'index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';
?>

<div class="container mt-3">
    <?php if (isset($_GET['pagado'])): ?>
        <div class="alert alert-success">Venta marcada como pagada correctamente.</div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-receipt"></i> Detalle de Venta #<?= $id_venta ?></h4>
        <?php if ($venta->estado_pago == 'pendiente' || $venta->estado_pago == 'parcial'): ?>
            <form method="POST" style="display:inline;">
                <button type="submit" name="marcar_pagado" class="btn btn-success btn-sm" onclick="return confirm('¿Marcar como pagada?')">
                    <i class="bi bi-check-circle"></i> Marcar como pagado
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card shadow mb-3">
        <div class="card-header bg-danger text-white">Información de la venta</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded">
                        <small class="text-muted d-block">Fecha</small>
                        <strong><?= date('d/m/Y H:i', strtotime($venta->fecha)) ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded">
                        <small class="text-muted d-block">Comprador</small>
                        <strong><?= htmlspecialchars($venta->nombre_comprador ?? 'Anónimo') ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded">
                        <small class="text-muted d-block">Tipo</small>
                        <strong><?= ucfirst($venta->tipo_comprador ?? 'otro') ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded">
                        <small class="text-muted d-block">Método de pago</small>
                        <strong><?= $venta->metodo_pago ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded">
                        <small class="text-muted d-block">Estado</small>
                        <span class="badge bg-<?= $venta->estado_pago == 'pagado' ? 'success' : 'danger' ?> fs-6"><?= ucfirst($venta->estado_pago ?? 'pagado') ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 bg-light rounded">
                        <small class="text-muted d-block">Total</small>
                        <strong class="text-success">Gs <?= number_format($venta->total, 0, ',', '.') ?></strong>
                    </div>
                </div>
                <?php if ($venta->observaciones): ?>
                    <div class="col-12">
                        <div class="p-2 bg-light rounded">
                            <small class="text-muted d-block">Observaciones</small>
                            <strong><?= htmlspecialchars($venta->observaciones) ?></strong>
                        </div>
                    </div>
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
</div>

<?php include '../../../includes/footer.php'; ?>