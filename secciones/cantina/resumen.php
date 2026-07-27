<?php
session_start();
require_once '../../config/db.php';
require_once 'funciones.php';
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
verificarPermiso('cantina');

include '../../includes/header.php';
include '../../includes/navbar.php';

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

$ganancias = obtenerGanancias($pdo, $fecha_inicio, $fecha_fin);
$productos_ganancias = obtenerGananciasPorProducto($pdo, $fecha_inicio, $fecha_fin);
$total_ventas = $pdo->query("SELECT COUNT(*) FROM ventas WHERE estado_pago = 'pagado' AND fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetchColumn();
$total_pendientes = $pdo->query("SELECT COUNT(*) FROM ventas WHERE estado_pago = 'pendiente' AND fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetchColumn();
$total_compras_fiado = $pdo->query("SELECT COALESCE(SUM(monto), 0) FROM compras_alumnos WHERE pagado = 0")->fetchColumn();
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
            <a href="index.php" class="btn btn-secondary w-100">
                <i class="bi bi-house"></i> Volver al panel de Cantina
            </a>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-graph-up-arrow"></i> Resumen de Ganancias</h4>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <!-- Filtros -->
    <div class="card shadow mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= $fecha_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Fecha fin</label>
                    <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= $fecha_fin ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-danger btn-sm w-100">Filtrar</button>
                </div>
                <div class="col-md-3">
                    <a href="resumen.php" class="btn btn-secondary btn-sm w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tarjetas KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Ventas</h6>
                    <h3 class="fw-bold"><?= number_format($ganancias->total_ventas ?? 0, 0, ',', '.') ?> Gs</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Costo de Productos</h6>
                    <h3 class="fw-bold text-danger"><?= number_format($ganancias->total_costos ?? 0, 0, ',', '.') ?> Gs</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Ganancia Neta</h6>
                    <h3 class="fw-bold text-success"><?= number_format($ganancias->ganancia_total ?? 0, 0, ',', '.') ?> Gs</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted">Ventas Pendientes</h6>
                    <h3 class="fw-bold text-warning"><?= $total_pendientes ?></h3>
                    <small>Fiado: <?= number_format($total_compras_fiado, 0, ',', '.') ?> Gs</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Ganancias por producto -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white">Ganancias por Producto</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cantidad Vendida</th>
                            <th class="text-end">Ingreso</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Ganancia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos_ganancias)): ?>
                            <tr><td colspan="5" class="text-center">No hay datos para el período seleccionado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($productos_ganancias as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p->producto) ?></td>
                                    <td class="text-center"><?= $p->total_vendido ?></td>
                                    <td class="text-end"><?= number_format($p->ingreso, 0, ',', '.') ?> Gs</td>
                                    <td class="text-end"><?= number_format($p->costo, 0, ',', '.') ?> Gs</td>
                                    <td class="text-end fw-bold <?= $p->ganancia > 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($p->ganancia, 0, ',', '.') ?> Gs</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
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
            <a href="index.php" class="btn btn-secondary w-100">
                <i class="bi bi-house"></i> Volver al panel de Cantina
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>