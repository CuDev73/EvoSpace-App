<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

if (isset($_GET['detalle']) && (int) $_GET['detalle'] > 0) {
    $v = obtenerVenta($pdo, (int) $_GET['detalle']);
    $items = obtenerDetalleVenta($pdo, (int) $_GET['detalle']);
    echo json_encode([
        'venta' => $v,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

// Cobrar venta (total o parcial)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pagado'])) {
    header('Location: index.php');
    exit;
}

$historial = obtenerVentas($pdo, array_merge($filtros, ['estado_pago' => 'pagado']));
$total_cobrado = array_sum(array_map(fn($v) => (float) $v->monto_pagado, $historial));
$total_ventas = count($historial);

$hoy = date('Y-m-d');
$kpiHoy = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) total, COUNT(*) n FROM ventas WHERE estado_pago = 'pagado' AND DATE(fecha) = ?");
$kpiHoy->execute([$hoy]);
$kpiHoy = $kpiHoy->fetch(PDO::FETCH_OBJ);
$mesInicio = date('Y-m-01');
$kpiMes = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) total, COUNT(*) n FROM ventas WHERE estado_pago = 'pagado' AND DATE(fecha) BETWEEN ? AND ?");
$kpiMes->execute([$mesInicio, date('Y-m-d')]);
$kpiMes = $kpiMes->fetch(PDO::FETCH_OBJ);

$mostrarVolver = true;
$volverUrl = '../index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-clock-history"></i> Historial de Ventas</h4>
        <a href="nueva.php" class="btn btn-evo btn-sm"><i class="bi bi-plus-circle"></i> Nueva Venta</a>
    </div>

    <!-- Resumen -->
    <div class="card shadow mb-3">
        <div class="card-header">
            <i class="bi bi-graph-up-arrow"></i> Resumen
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-icon bg-evo-tint"><i class="bi bi-cash-stack"></i></div>
                        <div>
                            <div class="small text-muted">Cobrado hoy</div>
                            <strong>Gs <?= number_format($kpiHoy->total, 0, ',', '.') ?></strong>
                            <div class="small text-muted"><?= $kpiHoy->n ?> ventas</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-icon bg-evo-tint"><i class="bi bi-calendar-month"></i></div>
                        <div>
                            <div class="small text-muted">Cobrado <strong><?= date('M') ?></strong></div>
                            <strong>Gs <?= number_format($kpiMes->total, 0, ',', '.') ?></strong>
                            <div class="small text-muted"><?= $kpiMes->n ?> ventas</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-lg-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="small text-muted">Total cobrado</div>
                            <strong>Gs <?= number_format($total_cobrado, 0, ',', '.') ?></strong>
                            <div class="small text-muted"><?= $total_ventas ?> ventas</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-lg-3 d-flex align-items-center">
                    <a href="deudores.php" class="btn btn-outline-danger btn-sm ms-2"><i class="bi bi-hourglass-split"></i> Ver Deudores</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
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
                    <a href="index.php" class="btn btn-sm btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla: Historial de ventas (pagadas) -->
    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clock-history"></i> Historial de ventas</span>
            <span class="badge bg-success"><?= count($historial) ?> pagadas</span>
        </div>
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
                            <th>Items</th>
                            <th>Comprobante</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historial)): ?>
                            <tr><td colspan="9" class="text-center text-muted">No hay ventas pagadas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($historial as $v): ?>
                                <tr>
                                    <td><?= $v->id_venta ?></td>
                                    <td><?= date('d/m/Y', strtotime($v->fecha)) ?></td>
                                    <td><?= htmlspecialchars($v->nombre_comprador ?? 'Anónimo') ?></td>
                                    <td><span class="badge bg-<?= $v->tipo_comprador == 'alumno' ? 'primary' : ($v->tipo_comprador == 'profesor' ? 'info' : 'secondary') ?>"><?= ucfirst($v->tipo_comprador ?? 'otro') ?></span></td>
                                    <td class="text-end"><?= number_format($v->total, 0, ',', '.') ?></td>
                                    <td><span class="badge bg-success"><?= $v->metodo_pago ?></span></td>
                                    <td class="text-center"><?= $v->total_items ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($v->comprobante)): ?>
                                            <a href="../../<?= htmlspecialchars($v->comprobante) ?>" target="_blank" class="btn btn-outline-evo btn-sm" title="Ver comprobante"><i class="bi bi-paperclip"></i></a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-evo btn-sm" title="Ver items" data-bs-toggle="modal" data-bs-target="#detalleModal" data-id="<?= $v->id_venta ?>"><i class="bi bi-eye"></i></button>
                                        <form method="POST" action="eliminar.php" class="d-inline" onsubmit="return confirmarEliminar(this, '¿Eliminar esta venta?')">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="id_venta" value="<?= $v->id_venta ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
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

<?php include '../../../includes/footer.php'; ?>
