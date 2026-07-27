<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$mensaje = '';
$error = '';

if (isset($_GET['eliminar'])) {
    try {
        eliminarCompraProveedor($pdo, (int) $_GET['eliminar']);
        $mensaje = "Compra eliminada correctamente.";
        header("Location: index.php?exito=1");
        exit;
    } catch (Exception $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

include '../../../includes/header.php';
include '../../../includes/navbar.php';

$compras = obtenerComprasProveedores($pdo);
if (isset($_GET['exito'])) {
    $mensaje = "Operación realizada correctamente.";
}
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
        <h4><i class="bi bi-cart-dash"></i> Compras a Proveedores</h4>
        <a href="nueva.php" class="btn btn-danger btn-sm"><i class="bi bi-plus-circle"></i> Nueva Compra</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th class="text-end">Total</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($compras)): ?>
                            <tr><td colspan="6" class="text-center">No hay compras registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($compras as $c): ?>
                                <tr>
                                    <td><?= $c->id_compra ?></td>
                                    <td><?= date('d/m/Y', strtotime($c->fecha)) ?></td>
                                    <td><?= htmlspecialchars($c->proveedor_nombre) ?></td>
                                    <td class="text-end"><?= number_format($c->total, 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($c->observaciones ?? '') ?></td>
                                    <td>
                                        <a href="ver.php?id=<?= $c->id_compra ?>" class="btn btn-primary btn-sm"><i class="bi bi-eye"></i></a>
                                        <a href="index.php?eliminar=<?= $c->id_compra ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta compra? Se devolverá el stock.')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include '../../../includes/footer.php'; ?>