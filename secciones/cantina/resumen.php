<?php
session_start();
require_once '../../config/db.php';
require_once 'funciones.php';
require_once '../../vendor/autoload.php';
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
verificarPermiso('cantina');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$mostrarVolver = true;
$volverUrl = 'index.php';

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

$ganancias = obtenerGanancias($pdo, $fecha_inicio, $fecha_fin);
$productos_ganancias = obtenerGananciasPorProducto($pdo, $fecha_inicio, $fecha_fin);

$stmtCant = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE estado_pago = ? AND fecha BETWEEN ? AND ?");
$stmtCant->execute(['pagado', $fecha_inicio, $fecha_fin]);
$total_ventas = $stmtCant->fetchColumn();
$stmtCant->execute(['pendiente', $fecha_inicio, $fecha_fin]);
$total_pendientes = $stmtCant->fetchColumn();

$stmtFiado = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE estado_pago IN ('pendiente','parcial')");
$stmtFiado->execute();
$total_compras_fiado = $stmtFiado->fetchColumn();

// --- EXPORTAR A EXCEL (PHPSpreadsheet) ---
if (isset($_GET['exportar_excel'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Ganancias');

    // Estilos
    $bold = ['font' => ['bold' => true]];
    $titleStyle = ['font' => ['bold' => true, 'size' => 14]];
    $headerStyle = ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'CCCCCC']]];
    $numberFormat = ['numberFormat' => ['formatCode' => '#,##0']];

    // Título
    $sheet->setCellValue('A1', 'Resumen de Ganancias');
    $sheet->getStyle('A1')->applyFromArray($titleStyle);
    $sheet->setCellValue('A2', 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)));
    $sheet->setCellValue('A3', 'Generado: ' . date('d/m/Y H:i'));

    // KPIs
    $row = 5;
    $sheet->setCellValue('A' . $row, 'INDICADORES PRINCIPALES');
    $sheet->getStyle('A' . $row)->applyFromArray($bold);
    $row++;
    $sheet->setCellValue('A' . $row, 'Indicador');
    $sheet->setCellValue('B' . $row, 'Valor (Gs)');
    $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray($headerStyle);
    $row++;
    $kpis = [
        ['Total Ventas', $ganancias->total_ventas ?? 0],
        ['Costo de Productos', $ganancias->total_costos ?? 0],
        ['Ganancia Neta', $ganancias->ganancia_total ?? 0],
        ['Ventas Pagadas (cant.)', $total_ventas],
        ['Ventas Pendientes (cant.)', $total_pendientes],
        ['Deuda Fiado (alumnos)', $total_compras_fiado],
    ];
    foreach ($kpis as $kpi) {
        $sheet->setCellValue('A' . $row, $kpi[0]);
        $sheet->setCellValue('B' . $row, $kpi[1]);
        $sheet->getStyle('B' . $row)->applyFromArray($numberFormat);
        $row++;
    }

    // Productos
    $row += 2;
    $sheet->setCellValue('A' . $row, 'GANANCIAS POR PRODUCTO');
    $sheet->getStyle('A' . $row)->applyFromArray($bold);
    $row++;
    $headers = ['Producto', 'Cantidad Vendida', 'Ingreso (Gs)', 'Costo (Gs)', 'Ganancia (Gs)', 'Margen %'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $row, $h);
        $col++;
    }
    $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($headerStyle);
    $row++;

    $totalCant = 0;
    $totalIngreso = 0;
    $totalCosto = 0;
    $totalGanancia = 0;
    foreach ($productos_ganancias as $p) {
        $margen = $p->ingreso > 0 ? round(($p->ganancia / $p->ingreso) * 100, 1) : 0;
        $sheet->setCellValue('A' . $row, $p->producto);
        $sheet->setCellValue('B' . $row, $p->total_vendido);
        $sheet->setCellValue('C' . $row, $p->ingreso);
        $sheet->setCellValue('D' . $row, $p->costo);
        $sheet->setCellValue('E' . $row, $p->ganancia);
        $sheet->setCellValue('F' . $row, $margen);
        $sheet->getStyle('C' . $row . ':E' . $row)->applyFromArray($numberFormat);
        $totalCant += $p->total_vendido;
        $totalIngreso += $p->ingreso;
        $totalCosto += $p->costo;
        $totalGanancia += $p->ganancia;
        $row++;
    }
    $margenTotal = $totalIngreso > 0 ? round(($totalGanancia / $totalIngreso) * 100, 1) : 0;
    $sheet->setCellValue('A' . $row, 'TOTALES');
    $sheet->setCellValue('B' . $row, $totalCant);
    $sheet->setCellValue('C' . $row, $totalIngreso);
    $sheet->setCellValue('D' . $row, $totalCosto);
    $sheet->setCellValue('E' . $row, $totalGanancia);
    $sheet->setCellValue('F' . $row, $margenTotal);
    $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($bold);
    $sheet->getStyle('C' . $row . ':E' . $row)->applyFromArray($numberFormat);

    // Autoancho
    foreach (range('A', 'F') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="ganancias_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-graph-up-arrow"></i> Resumen de Ganancias</h4>
        <a href="resumen.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ? '&' : '' ?>exportar_excel=1" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Exportar a Excel</a>
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
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <span>Ganancias por Producto</span>
        </div>
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
                            <th class="text-end">Margen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos_ganancias)): ?>
                            <tr><td colspan="6" class="text-center">No hay datos para el período seleccionado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($productos_ganancias as $p): 
                                $margen = $p->ingreso > 0 ? round(($p->ganancia / $p->ingreso) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($p->producto) ?></td>
                                    <td class="text-center"><?= $p->total_vendido ?></td>
                                    <td class="text-end"><?= number_format($p->ingreso, 0, ',', '.') ?> Gs</td>
                                    <td class="text-end"><?= number_format($p->costo, 0, ',', '.') ?> Gs</td>
                                    <td class="text-end fw-bold <?= $p->ganancia > 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($p->ganancia, 0, ',', '.') ?> Gs</td>
                                    <td class="text-end"><?= $margen ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($productos_ganancias)): 
                        $totalCant = array_sum(array_column($productos_ganancias, 'total_vendido'));
                        $totalIngreso = array_sum(array_column($productos_ganancias, 'ingreso'));
                        $totalCosto = array_sum(array_column($productos_ganancias, 'costo'));
                        $totalGanancia = array_sum(array_column($productos_ganancias, 'ganancia'));
                        $margenTotal = $totalIngreso > 0 ? round(($totalGanancia / $totalIngreso) * 100, 1) : 0;
                    ?>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td>TOTALES</td>
                                <td class="text-center"><?= $totalCant ?></td>
                                <td class="text-end"><?= number_format($totalIngreso, 0, ',', '.') ?> Gs</td>
                                <td class="text-end"><?= number_format($totalCosto, 0, ',', '.') ?> Gs</td>
                                <td class="text-end text-success"><?= number_format($totalGanancia, 0, ',', '.') ?> Gs</td>
                                <td class="text-end"><?= $margenTotal ?>%</td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>
