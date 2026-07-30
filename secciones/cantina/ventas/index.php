<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$filtros = [];
if (isset($_GET['fecha_inicio']) && $_GET['fecha_inicio']) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
}
if (isset($_GET['fecha_fin']) && $_GET['fecha_fin']) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
}
if (isset($_GET['tipo_comprador']) && $_GET['tipo_comprador']) {
    $filtros['tipo_comprador'] = $_GET['tipo_comprador'];
}
if (isset($_GET['estado_pago']) && $_GET['estado_pago']) {
    $filtros['estado_pago'] = $_GET['estado_pago'];
}
if (isset($_GET['nombre_comprador']) && $_GET['nombre_comprador']) {
    $filtros['nombre_comprador'] = $_GET['nombre_comprador'];
}

// Marcar como pagado inline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pagado'])) {
    $id = (int)$_POST['id_venta'];
    $stmt = $pdo->prepare("UPDATE ventas SET estado_pago = 'pagado' WHERE id_venta = ?");
    $stmt->execute([$id]);
    header('Location: index.php');
    exit;
}

$ventas = obtenerVentas($pdo, $filtros);
$total_ventas = array_sum(array_column($ventas, 'total'));

$mostrarVolver = true;
$volverUrl = '../index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-clock-history"></i> Historial de Ventas</h4>
        <a href="nueva.php" class="btn btn-danger btn-sm"><i class="bi bi-plus-circle"></i> Nueva Venta</a>
    </div>

    <!-- Filtros -->
    <div class="card shadow mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= $_GET['fecha_inicio'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Fecha fin</label>
                    <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= $_GET['fecha_fin'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Buscar por nombre</label>
                    <input type="text" name="nombre_comprador" class="form-control form-control-sm" placeholder="Nombre del comprador..." value="<?= htmlspecialchars($_GET['nombre_comprador'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label small">Tipo</label>
                    <select name="tipo_comprador" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="alumno" <?= ($_GET['tipo_comprador'] ?? '') == 'alumno' ? 'selected' : '' ?>>Alumno</option>
                        <option value="profesor" <?= ($_GET['tipo_comprador'] ?? '') == 'profesor' ? 'selected' : '' ?>>Profesor</option>
                        <option value="padre" <?= ($_GET['tipo_comprador'] ?? '') == 'padre' ? 'selected' : '' ?>>Padre</option>
                        <option value="otro" <?= ($_GET['tipo_comprador'] ?? '') == 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small">Estado</label>
                    <select name="estado_pago" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="pagado" <?= ($_GET['estado_pago'] ?? '') == 'pagado' ? 'selected' : '' ?>>Pagado</option>
                        <option value="pendiente" <?= ($_GET['estado_pago'] ?? '') == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-danger btn-sm w-100">Filtrar</button>
                </div>
                <div class="col-md-1">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Total -->
    <div class="alert alert-info">
        <strong>Total recaudado:</strong> Gs <?= number_format($total_ventas, 0, ',', '.') ?>
        <span class="ms-3"><strong>Ventas:</strong> <?= count($ventas) ?></span>
    </div>

    <!-- Tabla -->
    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Comprador</th>
                            <th>Tipo</th>
                            <th class="text-end">Total</th>
                            <th>Método</th>
                            <th>Estado</th>
                            <th>Items</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                            <tr><td colspan="9" class="text-center">No hay ventas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $v): ?>
                                <tr>
                                    <td><?= $v->id_venta ?></td>
                                    <td><?= date('d/m/Y', strtotime($v->fecha)) ?></td>
                                    <td><?= htmlspecialchars($v->nombre_comprador ?? 'Anónimo') ?></td>
                                    <td><span class="badge bg-<?= $v->tipo_comprador == 'alumno' ? 'primary' : ($v->tipo_comprador == 'profesor' ? 'info' : 'secondary') ?>"><?= ucfirst($v->tipo_comprador ?? 'otro') ?></span></td>
                                    <td class="text-end"><?= number_format($v->total, 0, ',', '.') ?></td>
                                    <td><span class="badge bg-<?= $v->metodo_pago == 'Efectivo' ? 'success' : ($v->metodo_pago == 'Transferencia' ? 'info' : 'warning') ?>"><?= $v->metodo_pago ?></span></td>
                                    <td><span class="badge bg-<?= $v->estado_pago == 'pagado' ? 'success' : ($v->estado_pago == 'pendiente' ? 'danger' : 'warning') ?>"><?= ucfirst($v->estado_pago ?? 'pagado') ?></span></td>
                                    <td class="text-center"><?= $v->total_items ?></td>
                                    <td>
                                        <?php if ($v->estado_pago == 'pendiente' || $v->estado_pago == 'parcial'): ?>
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pagarModal" data-id="<?= $v->id_venta ?>" data-comprador="<?= htmlspecialchars($v->nombre_comprador ?? 'Anónimo', ENT_QUOTES) ?>" data-total="<?= number_format($v->total, 0, ',', '.') ?>"><i class="bi bi-check-circle"></i></button>
                                        <?php else: ?>
                                            <span class="text-success"><i class="bi bi-check-circle-fill"></i></span>
                                        <?php endif; ?>
                                        <a href="eliminar.php?id=<?= $v->id_venta ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta venta?')"><i class="bi bi-trash"></i></a>
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

<!-- Modal pagar -->
<div class="modal fade" id="pagarModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id_venta" id="modalIdVenta">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title"><i class="bi bi-check-circle"></i> Confirmar pago</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-1">¿Marcar como pagada la venta <strong id="modalIdText">#</strong>?</p>
                    <p class="mb-0"><strong id="modalComprador"></strong></p>
                    <h4 class="text-success mt-2" id="modalTotal"></h4>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="marcar_pagado" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Confirmar pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('pagarModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('modalIdVenta').value = btn.dataset.id;
    document.getElementById('modalIdText').textContent = '#' + btn.dataset.id;
    document.getElementById('modalComprador').textContent = btn.dataset.comprador;
    document.getElementById('modalTotal').textContent = 'Gs ' + btn.dataset.total;
});
</script>

<?php include '../../../includes/footer.php'; ?>
