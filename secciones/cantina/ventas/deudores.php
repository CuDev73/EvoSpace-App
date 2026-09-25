<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$filtros = ['estado_pago' => 'pendiente'];
if (isset($_GET['fecha_inicio']) && $_GET['fecha_inicio']) {
    $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
}
if (isset($_GET['fecha_fin']) && $_GET['fecha_fin']) {
    $filtros['fecha_fin'] = $_GET['fecha_fin'];
}
if (isset($_GET['tipo_comprador']) && $_GET['tipo_comprador']) {
    $filtros['tipo_comprador'] = $_GET['tipo_comprador'];
}
if (isset($_GET['nombre_comprador']) && $_GET['nombre_comprador']) {
    $filtros['nombre_comprador'] = $_GET['nombre_comprador'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pagado'])) {
    verificarTokenCSRF();
    $id = (int) $_POST['id_venta'];
    $stmt = $pdo->prepare("SELECT total, monto_pagado FROM ventas WHERE id_venta = ?");
    $stmt->execute([$id]);
    $venta = $stmt->fetch(PDO::FETCH_OBJ);
    if ($venta) {
        $total = (float) $venta->total;
        $pagado = (float) $venta->monto_pagado;
        $restante = $total - $pagado;
        $monto = (isset($_POST['monto']) && $_POST['monto'] !== '') ? (float) $_POST['monto'] : $restante;
        $monto = max(0, min($monto, $restante));
        $nuevoPagado = $pagado + $monto;
        $nuevoEstado = $nuevoPagado >= $total ? 'pagado' : 'parcial';
        $stmt = $pdo->prepare("UPDATE ventas SET monto_pagado = ?, estado_pago = ? WHERE id_venta = ?");
        $stmt->execute([$nuevoPagado, $nuevoEstado, $id]);
    }
    header('Location: deudores.php');
    exit;
}

$deudores = obtenerVentas($pdo, $filtros);
$total_deuda = array_sum(array_map(fn($v) => (float) $v->total - (float) $v->monto_pagado, $deudores));

$mostrarVolver = true;
$volverUrl = '../../index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-hourglass-split"></i> Deudores / Fiado</h4>
        <a href="nueva.php" class="btn btn-evo btn-sm"><i class="bi bi-plus-circle"></i> Nueva Venta</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body d-flex align-items-center gap-2">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="small text-muted">Deuda total (fiado)</div>
                        <strong>Gs <?= number_format($total_deuda, 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body d-flex align-items-center gap-2">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="small text-muted">Compras pendientes</div>
                        <strong><?= count($deudores) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-3">
        <div class="card-header">
            <i class="bi bi-funnel"></i> Filtros
        </div>
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
                <div class="col-md-2">
                    <label class="form-label small">Tipo</label>
                    <select name="tipo_comprador" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="alumno" <?= ($_GET['tipo_comprador'] ?? '') == 'alumno' ? 'selected' : '' ?>>Alumno</option>
                        <option value="profesor" <?= ($_GET['tipo_comprador'] ?? '') == 'profesor' ? 'selected' : '' ?>>Profesor</option>
                        <option value="padre" <?= ($_GET['tipo_comprador'] ?? '') == 'padre' ? 'selected' : '' ?>>Tutor/a</option>
                        <option value="otro" <?= ($_GET['tipo_comprador'] ?? '') == 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-evo btn-sm w-100">Filtrar</button>
                </div>
                <div class="col-md-1">
                    <a href="deudores.php" class="btn btn-sm btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-hourglass-split"></i> Deudores / Fiado</span>
            <span class="badge bg-danger"><?= count($deudores) ?> pendientes</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Comprador</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Saldo</th>
                            <th>Método</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deudores)): ?>
                            <tr><td colspan="8" class="text-center text-muted">No hay deudas pendientes, todo al día.</td></tr>
                        <?php else: ?>
                            <?php foreach ($deudores as $v): ?>
                                <tr>
                                    <td><?= $v->id_venta ?></td>
                                    <td><?= date('d/m/Y', strtotime($v->fecha)) ?></td>
                                    <td><?= htmlspecialchars($v->nombre_comprador ?? 'Anónimo') ?></td>
                                    <td class="text-end"><?= number_format($v->total, 0, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format($v->total - $v->monto_pagado, 0, ',', '.') ?></td>
                                    <td><span class="badge bg-warning"><?= $v->metodo_pago ?></span></td>
                                    <td>
                                        <?php if ($v->estado_pago == 'parcial'): ?>
                                            <span class="badge bg-warning text-dark">Parcial</span>
                                            <small class="d-block text-muted">Pág. <?= number_format($v->monto_pagado, 0, ',', '.') ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm" title="Cobrar" data-bs-toggle="modal" data-bs-target="#pagarModal" data-id="<?= $v->id_venta ?>" data-comprador="<?= htmlspecialchars($v->nombre_comprador ?? 'Anónimo', ENT_QUOTES) ?>" data-total="<?= (float) $v->total ?>" data-restante="<?= (float) $v->total - (float) $v->monto_pagado ?>"><i class="bi bi-cash-coin"></i> Cobrar</button>
                                        <button type="button" class="btn btn-outline-evo btn-sm" title="Ver items" data-bs-toggle="modal" data-bs-target="#detalleModal" data-id="<?= $v->id_venta ?>"><i class="bi bi-eye"></i></button>
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

<!-- Modal detalle venta -->
<div class="modal fade" id="detalleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-receipt"></i> Detalle de venta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3" id="detalleHeader"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="detalleComprador">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detalleItems"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end fw-bold mt-2" id="detalleTotal"></div>
                <div id="detalleComprobante" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('detalleModal').addEventListener('show.bs.modal', function (e) {
    const id = e.relatedTarget.dataset.id;
    document.getElementById('detalleHeader').innerHTML = '<div class="col-12 text-muted small">Cargando...</div>';
    fetch('index.php?detalle=' + id)
        .then(r => r.json())
        .then(d => {
            const v = d.venta;
            document.getElementById('detalleHeader').innerHTML =
                '<div class="col-md-6"><span class="badge bg-secondary">Venta #' + v.id_venta + '</span></div>' +
                '<div class="col-md-6 text-md-end"><span class="small text-muted">' + (new Date(v.fecha)).toLocaleString('es-PY') + '</span></div>' +
                '<div class="col-12"><strong>' + (v.nombre_comprador || 'Anónimo') + '</strong></div>' +
                '<div class="col-12"><span class="badge bg-' + (v.metodo_pago == 'Efectivo' ? 'success' : 'warning') + '">' + v.metodo_pago + '</span> ' +
                '<span class="badge bg-' + (v.estado_pago == 'pagado' ? 'success' : 'danger') + '">' + (v.estado_pago == 'pagado' ? 'Pagado' : (v.estado_pago == 'parcial' ? 'Parcial' : 'Pendiente')) + '</span></div>';
            let rows = '';
            d.items.forEach(item => {
                rows += '<tr>' +
                    '<td>' + item.producto_nombre + '</td>' +
                    '<td class="text-center">' + item.cantidad + '</td>' +
                    '<td class="text-end">' + Number(item.precio_unitario).toLocaleString('es-PY').replace(/,/g, '.') + '</td>' +
                    '<td class="text-end">' + Number(item.subtotal).toLocaleString('es-PY').replace(/,/g, '.') + '</td>' +
                    '</tr>';
            });
            document.getElementById('detalleItems').innerHTML = rows;
            document.getElementById('detalleTotal').textContent = 'Total: Gs ' + Number(v.total).toLocaleString('es-PY').replace(/,/g, '.');
            const comp = v.comprobante ? '<div class="alert alert-light border">' +
                '<h6 class="small fw-bold"><i class="bi bi-paperclip"></i> Comprobante adjunto</h6>' +
                (v.comprobante.toLowerCase().endsWith('.pdf') ? '<a href="../../' + v.comprobante + '" target="_blank" class="btn btn-sm btn-outline-evo"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>' : '<img src="../../' + v.comprobante + '" alt="Comprobante" class="img-fluid rounded border" style="max-height:220px;">') +
                '</div>' : '';
            document.getElementById('detalleComprobante').innerHTML = comp;
        })
        .catch(() => {
            document.getElementById('detalleHeader').innerHTML = '<div class="col-12 text-danger small">No se pudo cargar el detalle.</div>';
        });
});
</script>

<!-- Modal pagar -->
<div class="modal fade" id="pagarModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <?= campoCSRF() ?>
                <input type="hidden" name="id_venta" id="modalIdVenta">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-cash-coin"></i> Cobrar venta</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Venta <strong id="modalIdText">#</strong></p>
                    <p class="mb-2"><strong id="modalComprador"></strong> · Total: <strong id="modalTotal"></strong></p>
                    <div class="bg-light border rounded p-2 mb-2 d-flex justify-content-between">
                        <span class="small text-muted">Saldo pendiente</span>
                        <strong id="modalSaldo"></strong>
                    </div>
                    <label class="form-label small">Monto a cobrar</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Gs</span>
                        <input type="number" name="monto" id="montoPago" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="small text-muted mt-2" id="modalVuelto"></div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="marcar_pagado" class="btn btn-evo btn-sm"><i class="bi bi-check-circle"></i> Registrar cobro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('pagarModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    const restante = Number(btn.dataset.restante);
    document.getElementById('modalIdVenta').value = btn.dataset.id;
    document.getElementById('modalIdText').textContent = '#' + btn.dataset.id;
    document.getElementById('modalComprador').textContent = btn.dataset.comprador;
    document.getElementById('modalTotal').textContent = 'Gs ' + Number(btn.dataset.total).toLocaleString('es-PY').replace(/,/g, '.');
    document.getElementById('modalSaldo').textContent = 'Gs ' + restante.toLocaleString('es-PY').replace(/,/g, '.');
    const inp = document.getElementById('montoPago');
    inp.value = restante;
    inp.max = restante;
    actualizarVuelto(restante, restante);
});
function actualizarVuelto(restante, monto) {
    const v = document.getElementById('modalVuelto');
    if (monto < restante) {
        v.textContent = 'Queda saldo pendiente de Gs ' + (restante - monto).toLocaleString('es-PY').replace(/,/g, '.');
        v.className = 'small text-warning mt-2';
    } else if (monto > restante) {
        v.textContent = 'Vuelto: Gs ' + (monto - restante).toLocaleString('es-PY').replace(/,/g, '.');
        v.className = 'small text-success mt-2';
    } else {
        v.textContent = '';
        v.className = 'small text-muted mt-2';
    }
}
document.getElementById('montoPago').addEventListener('input', function () {
    const restante = Number(this.max);
    const monto = Number(this.value) || 0;
    actualizarVuelto(restante, monto);
});
</script>

<?php include '../../../includes/footer.php'; ?>