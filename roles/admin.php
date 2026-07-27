<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

// ============================================================
// 1. ESTADÍSTICAS PRINCIPALES
// ============================================================
$mesActual = date('m');
$anioActual = date('Y');

// Alumnos
$totalAlumnos = (int) $pdo->query("SELECT COUNT(*) FROM alumnos WHERE activo = 1")->fetchColumn();
$totalBecados = (int) $pdo->query("SELECT COUNT(*) FROM alumnos WHERE becado = 1 AND activo = 1")->fetchColumn();

// Profesores y padres
$totalProfesores = (int) $pdo->query("SELECT COUNT(*) FROM profesores p INNER JOIN usuarios u ON p.id_usuario = u.id_usuario WHERE p.activo = 1 AND u.activo = 1")->fetchColumn();
$totalPadres = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') AND activo = 1")->fetchColumn();

// Finanzas (mes actual)
$stmt = $pdo->prepare("SELECT SUM(total) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesActual, $anioActual]);
$recaudadoMes = (float) $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesActual, $anioActual]);
$totalPagosMes = (int) $stmt->fetchColumn() ?: 0;

// Salarios
$stmt = $pdo->query("SELECT SUM(p.salario_base) FROM profesores p INNER JOIN usuarios u ON p.id_usuario = u.id_usuario WHERE p.activo = 1 AND u.activo = 1");
$totalSalarioBase = (float) $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(monto_abono) FROM abonos WHERE MONTH(fecha_abono) = ? AND YEAR(fecha_abono) = ?");
$stmt->execute([$mesActual, $anioActual]);
$totalAbonosMes = (float) $stmt->fetchColumn() ?: 0;
$salarioPendiente = max(0, $totalSalarioBase - $totalAbonosMes);

// Deuda total de alumnos
function calcularDeudaTotal($pdo, $mes, $anio) {
    $deudaTotal = 0;
    $porcentajeBeca = (float) $pdo->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_beca'")->fetchColumn() ?: 45.45;
    $alumnos = $pdo->query("SELECT id_alumno, becado, id_curso FROM alumnos WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($alumnos as $al) {
        $stmt = $pdo->prepare("SELECT precio FROM precios WHERE id_curso = ? AND concepto = 'cuota'");
        $stmt->execute([$al['id_curso']]);
        $cuotaBase = (float) $stmt->fetchColumn() ?: 0;
        if ($cuotaBase == 0) continue;
        $cuota = $al['becado'] ? $cuotaBase * ($porcentajeBeca / 100) : $cuotaBase;
        $stmt = $pdo->prepare("SELECT SUM(total) FROM pagos WHERE id_alumno = ? AND concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
        $stmt->execute([$al['id_alumno'], $mes, $anio]);
        $pagado = (float) $stmt->fetchColumn() ?: 0;
        $deuda = $cuota - $pagado;
        if ($deuda > 0) $deudaTotal += $deuda;
    }
    return $deudaTotal;
}
$deudaTotalAlumnos = calcularDeudaTotal($pdo, $mesActual, $anioActual);

// Eventos próximos
$hoy = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE fecha >= ?");
$stmt->execute([$hoy]);
$totalEventosProximos = (int) $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT * FROM eventos WHERE fecha >= ? ORDER BY fecha ASC, hora ASC LIMIT 1");
$stmt->execute([$hoy]);
$proximoEvento = $stmt->fetch(PDO::FETCH_ASSOC);

// Ventas cantina
$stmt = $pdo->prepare("SELECT SUM(total) FROM ventas WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesActual, $anioActual]);
$totalVentasCantina = (float) $stmt->fetchColumn() ?: 0;

// Top 5 deudores
$topDeudores = [];
$porcentajeBeca = (float) $pdo->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_beca'")->fetchColumn() ?: 45.45;
$alumnos = $pdo->query("SELECT a.id_alumno, a.nombre, a.apellido, a.becado, a.id_curso, c.nombre as curso_nombre FROM alumnos a INNER JOIN cursos c ON a.id_curso = c.id_curso WHERE a.activo = 1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($alumnos as $al) {
    $stmt = $pdo->prepare("SELECT precio FROM precios WHERE id_curso = ? AND concepto = 'cuota'");
    $stmt->execute([$al['id_curso']]);
    $cuotaBase = (float) $stmt->fetchColumn() ?: 0;
    if ($cuotaBase == 0) continue;
    $cuota = $al['becado'] ? $cuotaBase * ($porcentajeBeca / 100) : $cuotaBase;
    $stmt = $pdo->prepare("SELECT SUM(total) FROM pagos WHERE id_alumno = ? AND concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$al['id_alumno'], $mesActual, $anioActual]);
    $pagado = (float) $stmt->fetchColumn() ?: 0;
    $deuda = $cuota - $pagado;
    if ($deuda > 0) {
        $topDeudores[] = [
            'nombre' => $al['nombre'] . ' ' . $al['apellido'],
            'curso' => $al['curso_nombre'],
            'deuda' => $deuda
        ];
    }
}
usort($topDeudores, fn($a, $b) => $b['deuda'] - $a['deuda']);
$topDeudores = array_slice($topDeudores, 0, 5);

// Últimos pagos
$ultimosPagos = $pdo->query("SELECT p.*, a.nombre, a.apellido FROM pagos p INNER JOIN alumnos a ON p.id_alumno = a.id_alumno ORDER BY p.fecha DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Distribución de alumnos por tipo de curso
$distribucionCursos = $pdo->query("SELECT c.tipo, COUNT(a.id_alumno) as total FROM alumnos a INNER JOIN cursos c ON a.id_curso = c.id_curso WHERE a.activo = 1 GROUP BY c.tipo")->fetchAll(PDO::FETCH_ASSOC);
$labelsCursos = array_column($distribucionCursos, 'tipo');
$dataCursos = array_column($distribucionCursos, 'total');

// Deuda por tipo de curso (para gráfico)
$deudaPorTipo = [];
foreach ($distribucionCursos as $row) {
    $tipo = $row['tipo'];
    $stmt = $pdo->prepare("SELECT a.id_alumno, a.becado FROM alumnos a INNER JOIN cursos c ON a.id_curso = c.id_curso WHERE c.tipo = ? AND a.activo = 1");
    $stmt->execute([$tipo]);
    $alumnosTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $deudaTipo = 0;
    foreach ($alumnosTipo as $al) {
        $stmt = $pdo->prepare("SELECT precio FROM precios WHERE id_curso = (SELECT id_curso FROM alumnos WHERE id_alumno = ?) AND concepto = 'cuota'");
        $stmt->execute([$al['id_alumno']]);
        $cuotaBase = (float) $stmt->fetchColumn() ?: 0;
        if ($cuotaBase == 0) continue;
        $cuota = $al['becado'] ? $cuotaBase * 0.4545 : $cuotaBase;
        $stmt = $pdo->prepare("SELECT SUM(total) FROM pagos WHERE id_alumno = ? AND concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
        $stmt->execute([$al['id_alumno'], $mesActual, $anioActual]);
        $pagado = (float) $stmt->fetchColumn() ?: 0;
        $deuda = $cuota - $pagado;
        if ($deuda > 0) $deudaTipo += $deuda;
    }
    $deudaPorTipo[] = $deudaTipo;
}

// Evolución de pagos vs salarios (últimos 6 meses)
$labelsMeses = [];
$dataPagosMensual = [];
$dataSalariosMensual = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('m', strtotime("-$i months"));
    $anio = date('Y', strtotime("-$i months"));
    $labelsMeses[] = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT SUM(total) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$mes, $anio]);
    $dataPagosMensual[] = (float) $stmt->fetchColumn() ?: 0;
    // Para salarios, usamos el valor actual (simplificado, en producción se podría guardar histórico)
    $dataSalariosMensual[] = $totalSalarioBase;
}
?>

<div class="container mt-3">

    <!-- ========================================================== -->
    <!-- TARJETAS DE KPIs (6 columnas) -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0">
                <div class="card-body text-center">
                    <i class="bi bi-people-fill fs-1 text-danger"></i>
                    <h2 class="fw-bold mt-2 mb-0"><?= $totalAlumnos ?></h2>
                    <p class="text-muted small">Alumnos activos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0">
                <div class="card-body text-center">
                    <i class="bi bi-person-badge-fill fs-1 text-primary"></i>
                    <h2 class="fw-bold mt-2 mb-0"><?= $totalProfesores ?></h2>
                    <p class="text-muted small">Profesores</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0">
                <div class="card-body text-center">
                    <i class="bi bi-person-fill fs-1 text-success"></i>
                    <h2 class="fw-bold mt-2 mb-0"><?= $totalPadres ?></h2>
                    <p class="text-muted small">Padres</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0">
                <div class="card-body text-center">
                    <i class="bi bi-gift-fill fs-1 text-warning"></i>
                    <h2 class="fw-bold mt-2 mb-0"><?= $totalBecados ?></h2>
                    <p class="text-muted small">Becados</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-event-fill fs-1 text-info"></i>
                    <h2 class="fw-bold mt-2 mb-0"><?= $totalEventosProximos ?></h2>
                    <p class="text-muted small">Eventos próximos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0">
                <div class="card-body text-center">
                    <i class="bi bi-cash-stack fs-1 text-success"></i>
                    <h2 class="fw-bold mt-2 mb-0"><?= number_format($totalPagosMes) ?></h2>
                    <p class="text-muted small">Pagos (mes)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- TARJETAS FINANCIERAS (6 columnas) -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0 bg-success text-white">
                <div class="card-body text-center">
                    <i class="bi bi-coin fs-1"></i>
                    <h5 class="fw-bold mt-2 mb-0"><?= number_format($recaudadoMes) ?> Gs</h5>
                    <p class="small opacity-75">Recaudado</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0 bg-danger text-white">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle fs-1"></i>
                    <h5 class="fw-bold mt-2 mb-0"><?= number_format($deudaTotalAlumnos) ?> Gs</h5>
                    <p class="small opacity-75">Deuda alumnos</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0 bg-primary text-white">
                <div class="card-body text-center">
                    <i class="bi bi-cash-stack fs-1"></i>
                    <h5 class="fw-bold mt-2 mb-0"><?= number_format($totalSalarioBase) ?> Gs</h5>
                    <p class="small opacity-75">Salario base</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0 bg-warning text-dark">
                <div class="card-body text-center">
                    <i class="bi bi-cash fs-1"></i>
                    <h5 class="fw-bold mt-2 mb-0"><?= number_format($totalAbonosMes) ?> Gs</h5>
                    <p class="small opacity-75">Abonos pagados</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0 bg-danger text-white">
                <div class="card-body text-center">
                    <i class="bi bi-hourglass-split fs-1"></i>
                    <h5 class="fw-bold mt-2 mb-0"><?= number_format($salarioPendiente) ?> Gs</h5>
                    <p class="small opacity-75">Salario pendiente</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card shadow h-100 border-0 bg-info text-white">
                <div class="card-body text-center">
                    <i class="bi bi-cup-straw fs-1"></i>
                    <h5 class="fw-bold mt-2 mb-0"><?= number_format($totalVentasCantina) ?> Gs</h5>
                    <p class="small opacity-75">Ventas cantina</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- GRÁFICOS Y TABLAS -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-danger text-white">Recaudación vs Salarios (últimos 6 meses)</div>
                <div class="card-body">
                    <canvas id="evolucionChart" height="150"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-danger text-white">Deuda acumulada por tipo de curso</div>
                <div class="card-body">
                    <canvas id="deudaTipoChart" height="150"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> Alumnos con mayor deuda (Top 5)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Alumno</th><th>Curso</th><th class="text-end">Deuda (Gs)</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topDeudores)): ?>
                                    <tr><td colspan="3" class="text-center">Sin deudas registradas.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($topDeudores as $d): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($d['nombre']) ?></td>
                                            <td><?= htmlspecialchars($d['curso']) ?></td>
                                            <td class="text-end text-danger fw-bold"><?= number_format($d['deuda']) ?> Gs</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-clock-history"></i> Últimos pagos registrados
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Alumno</th><th>Concepto</th><th class="text-end">Monto</th><th>Fecha</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimosPagos)): ?>
                                    <tr><td colspan="4" class="text-center">Sin pagos recientes.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ultimosPagos as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['nombre'] . ' ' . $p['apellido']) ?></td>
                                            <td><?= ucfirst($p['concepto']) ?></td>
                                            <td class="text-end"><?= number_format($p['total']) ?> Gs</td>
                                            <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- PRÓXIMO EVENTO (destacado) -->
    <!-- ========================================================== -->
    <div class="card shadow mb-4 border-0">
        <div class="card-header bg-danger text-white fs-5">
            <i class="bi bi-calendar-event-fill me-2"></i>
            <?= $proximoEvento ? 'Próximo evento' : 'No hay eventos programados' ?>
        </div>
        <div class="card-body">
            <?php if ($proximoEvento): ?>
                <h4 class="fw-bold"><?= htmlspecialchars($proximoEvento['titulo']) ?></h4>
                <p><i class="bi bi-geo-alt me-2"></i> <?= htmlspecialchars($proximoEvento['lugar'] ?? 'Sin lugar') ?></p>
                <p><?= nl2br(htmlspecialchars($proximoEvento['descripcion'] ?? '')) ?></p>
                <div class="text-end fw-bold text-danger">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?= date('d/m/Y', strtotime($proximoEvento['fecha'])) ?>
                    <?php if ($proximoEvento['hora']): ?>
                        - <i class="bi bi-clock me-1"></i> <?= date('H:i', strtotime($proximoEvento['hora'])) ?>
                    <?php endif; ?>
                </div>
                <?php if ($proximoEvento['enlace_ubicacion']): ?>
                    <a href="<?= htmlspecialchars($proximoEvento['enlace_ubicacion']) ?>" target="_blank" class="btn btn-outline-primary btn-sm mt-2">
                        <i class="bi bi-map"></i> Ver en mapa
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted">No hay eventos programados para los próximos días.</p>
                <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-danger">Crear primer evento</a>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ========================================================== -->
<!-- SCRIPTS PARA GRÁFICOS (Chart.js) -->
<!-- ========================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Evolución de recaudación vs salarios
    const ctxEvol = document.getElementById('evolucionChart').getContext('2d');
    new Chart(ctxEvol, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsMeses) ?>,
            datasets: [
                {
                    label: 'Recaudado',
                    data: <?= json_encode($dataPagosMensual) ?>,
                    backgroundColor: 'rgba(46, 204, 113, 0.7)',
                    borderColor: '#2ecc71',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'Salarios base',
                    data: <?= json_encode($dataSalariosMensual) ?>,
                    backgroundColor: 'rgba(200, 16, 21, 0.7)',
                    borderColor: '#c81015',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'Gs ' + v.toLocaleString() }
                }
            }
        }
    });

    // 2. Deuda por tipo de curso (barras horizontales)
    const ctxDeuda = document.getElementById('deudaTipoChart').getContext('2d');
    new Chart(ctxDeuda, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsCursos) ?>,
            datasets: [{
                label: 'Deuda (Gs)',
                data: <?= json_encode($deudaPorTipo) ?>,
                backgroundColor: ['#e74c3c', '#f1c40f', '#3498db'],
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    ticks: { callback: v => 'Gs ' + v.toLocaleString() }
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>