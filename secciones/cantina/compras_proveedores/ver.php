<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$id_compra = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_compra) {
    header('Location: index.php');
    exit;
}

$compra = obtenerCompraProveedor($pdo, $id_compra);
if (!$compra) {
    header('Location: index.php?error=1');
    exit;
}

$detalles = obtenerDetalleCompraProveedor($pdo, $id_compra);

$mostrarVolver = true;
$volverUrl = 'index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-receipt"></i> Detalle de Compra #<?= $id_compra ?></h4>

    <div class="card shadow mb-3">
        <div class="card-header bg-danger text-white">Información de la compra</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($compra->fecha)) ?></div>
                <div class="col-md-4"><strong>Proveedor:</strong> <?= htmlspecialchars($compra->proveedor_nombre) ?></div>
                <div class="col-md-4"><strong>Total:</strong> Gs <?= number_format($compra->total, 0, ',', '.') ?></div>
                <?php if ($compra->observaciones): ?>
                    <div class="col-md-12"><strong>Observaciones:</strong> <?= htmlspecialchars($compra->observaciones) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header bg-danger text-white">Productos comprados</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Precio compra</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d->producto_nombre) ?></td>
                                <td class="text-center"><?= $d->cantidad ?></td>
                                <td class="text-end">Gs <?= number_format($d->precio_compra, 0, ',', '.') ?></td>
                                <td class="text-end">Gs <?= number_format($d->subtotal, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end">Gs <?= number_format($compra->total, 0, ',', '.') ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>