<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

if (recordatorioDeudaPendiente($pdo)) {
    $enviados = enviarRecordatorioDeudasTutores($pdo);
    $pdo->exec("UPDATE configuracion SET valor = '" . date('Y-m') . "' WHERE clave = 'recordatorio_deuda_ultimo'");
}

$hoy = date('Y-m-d');
$mesActual = date('m');
$anioActual = date('Y');
$hora = (int)date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 18 ? 'Buenas tardes' : 'Buenas noches');
$nombreUsuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'EvoSpace';

$diasES = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mesesES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$diaSemana = (int)date('N');
$diaNum = (int)date('j');
$mesNum = (int)date('n') - 1;
$fechaFormateada = $diasES[$diaSemana] . ', ' . $diaNum . ' de ' . $mesesES[$mesNum] . ' de ' . date('Y');

// ============================================================
// INDICADORES PRINCIPALES
// ============================================================
$totalAlumnos = (int)$pdo->query("SELECT COUNT(*) FROM alumnos WHERE activo = 1")->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesActual, $anioActual]);
$recaudadoMes = (float)$stmt->fetchColumn();

$mesAnterior = (int)date('m', strtotime('first day of last month'));
$anioAnterior = (int)date('Y', strtotime('first day of last month'));
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesAnterior, $anioAnterior]);
$recaudadoMesAnterior = (float)$stmt->fetchColumn();
$variacionRecaudado = $recaudadoMesAnterior > 0
    ? round((($recaudadoMes - $recaudadoMesAnterior) / $recaudadoMesAnterior) * 100)
    : null;

// ============================================================
// ASISTENCIA HOY
// ============================================================
$asistenciaHoy = [];
$stmtCursos = $pdo->query("SELECT id_curso, nombre, tipo FROM cursos WHERE activo = 1 ORDER BY tipo, orden");
while ($cursoRow = $stmtCursos->fetch(PDO::FETCH_OBJ)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presentes FROM asistencia WHERE id_curso = ? AND fecha = ?");
    $stmt->execute([$cursoRow->id_curso, $hoy]);
    $stats = $stmt->fetch(PDO::FETCH_OBJ);
    $total = (int)$stats->total;
    $presentes = (int)$stats->presentes;
    if ($total > 0) {
        $asistenciaHoy[] = [
            'curso' => $cursoRow->nombre,
            'tipo' => $cursoRow->tipo,
            'total' => $total,
            'presentes' => $presentes,
            'porcentaje' => round(($presentes / $total) * 100)
        ];
    }
}

// ============================================================
// GRÁFICO RECAUDACIÓN (6 meses)
// ============================================================
$labelsMeses = [];
$dataRecaudacion = [];
$primerDiaMes = (new DateTime('first day of this month'));
for ($i = 5; $i >= 0; $i--) {
    $fechaMes = (clone $primerDiaMes)->modify("-$i months");
    $mes = $fechaMes->format('m');
    $anio = $fechaMes->format('Y');
    $labelsMeses[] = $fechaMes->format('M');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$mes, $anio]);
    $dataRecaudacion[] = (float)$stmt->fetchColumn();
}

// ============================================================
// PENDIENTES
// ============================================================
$deudores = (int)$pdo->query("SELECT COUNT(DISTINCT id_alumno) FROM ventas WHERE id_alumno IS NOT NULL AND estado_pago IN ('pendiente','parcial')")->fetchColumn();
$deudaTotal = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE estado_pago IN ('pendiente','parcial')")->fetchColumn();

$profesoresPendientes = 0;
$stmt = $pdo->query("SELECT p.id_profesor, u.id_usuario, p.salario_base FROM profesores p INNER JOIN usuarios u ON p.id_usuario = u.id_usuario WHERE p.activo = 1 AND u.activo = 1");
foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $prof) {
    $stmtAbono = $pdo->prepare("SELECT COALESCE(SUM(monto_abono), 0) FROM abonos WHERE profesor = (SELECT usuario FROM usuarios WHERE id_usuario = ?) AND MONTH(fecha_abono) = ? AND YEAR(fecha_abono) = ?");
    $stmtAbono->execute([$prof->id_usuario, $mesActual, $anioActual]);
    if ($prof->salario_base > (float)$stmtAbono->fetchColumn()) $profesoresPendientes++;
}

// ============================================================
// BALANCE DEL MES
// ============================================================
$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_abono), 0) FROM abonos WHERE MONTH(fecha_abono) = ? AND YEAR(fecha_abono) = ?");
$stmt->execute([$mesActual, $anioActual]);
$gastosMes = (float)$stmt->fetchColumn();
$gananciaMes = $recaudadoMes - $gastosMes;

// ============================================================
// CUMPLIMIENTO DE PAGOS
// ============================================================
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_alumno) FROM pagos WHERE concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesActual, $anioActual]);
$totalAlumnosConCuota = (int)$stmt->fetchColumn();
$porcentajeCumplimiento = $totalAlumnos > 0 ? round(($totalAlumnosConCuota / $totalAlumnos) * 100, 0) : 0;

// ============================================================
// MOROSIDAD DE CUOTA (alumnos activos sin pago de cuota del mes)
// ============================================================
$morososCuota = [];
$deudaCuotaTotal = 0.0;
$pctBeca = (float)$pdo->query("SELECT COALESCE(MAX(valor), 50) FROM configuracion WHERE clave = 'porcentaje_beca'")->fetchColumn();
$stmtAlActivos = $pdo->query("
    SELECT a.id_alumno, a.nombre, a.apellido, a.becado, a.id_curso, c.tipo, c.nombre AS curso_nombre
    FROM alumnos a
    JOIN cursos c ON a.id_curso = c.id_curso
    WHERE a.activo = 1
    ORDER BY c.tipo, c.orden, a.apellido
");
foreach ($stmtAlActivos as $al) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagos WHERE id_alumno = ? AND concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$al['id_alumno'], $mesActual, $anioActual]);
    // Sumar la cuota impaga del mes para los morosos (mismo criterio que padre.php)
    $stmtCuota = $pdo->prepare("SELECT COALESCE(p.precio, 0) FROM precios p WHERE p.id_curso = ? AND p.concepto = 'cuota'");
    $stmtCuota->execute([$al['id_curso']]);
    $cuotaBase = (float)$stmtCuota->fetchColumn();
    $cuota = $al['becado'] ? round($cuotaBase * ($pctBeca / 100) / 1000) * 1000 : $cuotaBase;
    if ((int)$stmt->fetchColumn() === 0) {
        $morososCuota[] = $al;
        $deudaCuotaTotal += $cuota;
    }
}
$totalMorososCuota = count($morososCuota);

// ============================================================
// MATRÍCULAS PENDIENTES DEL AÑO
// ============================================================
$stmtMat = $pdo->prepare("
    SELECT COUNT(*) FROM alumnos a
    WHERE a.activo = 1
      AND NOT EXISTS (
          SELECT 1 FROM pagos p
          WHERE p.id_alumno = a.id_alumno AND p.concepto = 'matrícula' AND YEAR(p.fecha) = ?
      )
");
$stmtMat->execute([$anioActual]);
$matriculasPendientes = (int)$stmtMat->fetchColumn();

// ============================================================
// PRÓXIMOS EVENTOS (recordatorios 7 días)
// ============================================================
$proximosEventos = $pdo->query("
    SELECT e.*, DATEDIFF(e.fecha, CURDATE()) AS dias_restantes
    FROM eventos e
    WHERE e.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY e.fecha
")->fetchAll(PDO::FETCH_OBJ);
?>
<div class="container mt-3">

    <!-- ========================================================== -->
    <!-- 1. ENCABEZADO -->
    <!-- ========================================================== -->
    <div class="dashboard-greeting">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-person-circle me-2"></i><?= $saludo ?>, <?= htmlspecialchars($nombreUsuario) ?></h4>
            <small><?= $fechaFormateada ?></small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark fs-6 px-3 py-2">
                <i class="bi bi-building me-1"></i> Instituto Evolución Arte
            </span>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 2. INDICADORES PRINCIPALES -->
    <!-- ========================================================== -->
    <?php $nombresMeses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-evo-tint"><i class="bi bi-people-fill"></i></div>
                    <div class="stat-number"><?= $totalAlumnos ?></div>
                    <div class="stat-label">Alumnos activos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-success bg-opacity-10"><i class="bi bi-cash-coin text-success"></i></div>
                    <div class="stat-number"><?= number_format($recaudadoMes, 0, ',', '.') ?></div>
                    <div class="stat-label">Recaudado este mes</div>
                    <?php if ($variacionRecaudado !== null): ?>
                        <small class="badge bg-<?= $variacionRecaudado >= 0 ? 'success' : 'danger' ?>" style="font-size: 0.7rem;">
                            <?= $variacionRecaudado >= 0 ? '▲ +' : '▼ ' ?><?= number_format(abs($variacionRecaudado), 0, ',', '.') ?>% vs <?= strtolower($nombresMeses[$mesAnterior]) ?>
                        </small>
                    <?php else: ?>
                        <small style="opacity: 0.7;">Mes anterior sin datos</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-warning bg-opacity-10"><i class="bi bi-graph-up-arrow text-warning"></i></div>
                    <div class="stat-number text-<?= $gananciaMes < 0 ? 'danger' : 'success' ?>"><?= number_format($gananciaMes, 0, ',', '.') ?></div>
                    <div class="stat-label">Balance del mes</div>
                    <small class="text-muted">Ingresos vs. sueldos</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-warning bg-opacity-10"><i class="bi bi-clipboard-check-fill text-warning"></i></div>
                    <div class="stat-number"><?= $porcentajeCumplimiento ?>%</div>
                    <div class="stat-label">Cumplimiento de cuota</div>
                    <small style="opacity: 0.7;"><?= $totalAlumnosConCuota ?> de <?= $totalAlumnos ?> alumnos</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 3. ALERTAS -->
    <!-- ========================================================== -->
    <div class="card shadow mb-4 <?= ($totalMorososCuota > 0 || $deudores > 0 || $profesoresPendientes > 0) ? 'border-danger' : '' ?>">
        <div class="card-header bg-evo text-white">
            <i class="bi bi-bell-fill me-1"></i> Alertas
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-md-4 col-6">
                    <a href="/evospace/secciones/alumnos.php?filtro=morosos" class="text-decoration-none">
                        <div class="fs-3 fw-bold text-danger"><?= $totalMorososCuota ?></div>
                        <div class="small text-muted">Morosos de cuota (<?= number_format($deudaCuotaTotal, 0, ',', '.') ?> Gs)</div>
                        <span class="btn btn-sm btn-outline-evo mt-2">Ver y cobrar</span>
                    </a>
                </div>
                <div class="col-md-4 col-6">
                    <a href="/evospace/secciones/cantina/ventas/index.php?estado_pago=pendiente" class="text-decoration-none">
                        <div class="fs-3 fw-bold text-danger"><?= $deudores ?></div>
                        <div class="small text-muted">Deuda de cantina (<?= number_format($deudaTotal, 0, ',', '.') ?> Gs)</div>
                        <span class="btn btn-sm btn-outline-evo mt-2">Gestionar</span>
                    </a>
                </div>
                <div class="col-md-4 col-6">
                    <a href="/evospace/secciones/profesores.php" class="text-decoration-none">
                        <div class="fs-3 fw-bold text-danger"><?= $profesoresPendientes ?></div>
                        <div class="small text-muted">Profesores con salario pendiente</div>
                        <span class="btn btn-sm btn-outline-evo mt-2">Gestionar</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 4. ASISTENCIA DEL DÍA (si hay registros) -->
    <!-- ========================================================== -->
    <?php if (!empty($asistenciaHoy)): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-evo-accent text-white">
            <i class="bi bi-clipboard-check me-1"></i> Asistencia de hoy
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php foreach ($asistenciaHoy as $a): ?>
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fw-bold small"><?= htmlspecialchars($a['tipo'] . ' - ' . $a['curso']) ?></div>
                            <div class="fs-2 fw-bold text-<?= $a['porcentaje'] >= 80 ? 'success' : ($a['porcentaje'] >= 50 ? 'warning' : 'danger') ?>">
                                <?= $a['porcentaje'] ?>%
                            </div>
                            <div class="small text-muted"><?= $a['presentes'] ?>/<?= $a['total'] ?> presentes</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================================== -->
    <!-- 5. ACCIONES RÁPIDAS -->
    <!-- ========================================================== -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="/evospace/secciones/inscripciones.php" class="btn btn-success shadow-sm flex-fill">
            <i class="bi bi-person-plus-fill"></i> Inscribir alumno
        </a>
        <a href="/evospace/secciones/asistencia/index.php" class="btn btn-evo shadow-sm flex-fill">
            <i class="bi bi-clipboard-check"></i> Tomar asistencia
        </a>
<a href="/evospace/secciones/cantina/ventas/nueva.php" class="btn btn-evo shadow-sm flex-fill">
            <i class="bi bi-cart-plus"></i> Venta rápida
        </a>
        <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-info shadow-sm flex-fill text-white">
            <i class="bi bi-calendar-event"></i> Crear evento
        </a>
        <a href="/evospace/secciones/profesores.php" class="btn btn-secondary shadow-sm flex-fill text-white">
            <i class="bi bi-person-badge"></i> Profesores
        </a>
    </div>

    <!-- ========================================================== -->
    <!-- 5. ZONA PRINCIPAL: GRÁFICO -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card shadow h-100">
                <div class="card-header bg-evo-accent text-white">
                    <i class="bi bi-graph-up-arrow me-1"></i> Recaudación mensual (últimos 6 meses)
                </div>
                <div class="card-body">
                    <canvas id="recaudacionChart" height="160"></canvas>
                </div>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted">Ingresos</small>
                            <h6 class="text-success"><?= number_format($recaudadoMes, 0, ',', '.') ?> Gs</h6>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Gastos (abonos)</small>
                            <h6 class="text-danger"><?= number_format($gastosMes, 0, ',', '.') ?> Gs</h6>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Balance del mes</small>
                            <h6 class="text-<?= $gananciaMes < 0 ? 'danger' : 'success' ?>"><?= number_format($gananciaMes, 0, ',', '.') ?> Gs</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 6. PRÓXIMOS EVENTOS (recordatorios)                         -->
    <!-- ========================================================== -->
    <div class="card shadow mb-4">
        <div class="card-header bg-evo-accent text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar-event-fill me-1"></i> Próximos eventos (7 días)</span>
            <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-sm btn-evo fw-bold"><i class="bi bi-plus-circle"></i> Gestionar</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($proximosEventos)): ?>
                <div class="p-3 text-muted">No hay eventos programados para los próximos 7 días.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Evento</th><th class="text-center">Fecha</th><th class="text-center">Faltan</th><th class="text-center">Recordatorio</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proximosEventos as $ev): $dias = (int) $ev->dias_restantes; ?>
                                <tr>
                                    <td>
                                        <span class="d-inline-block rounded me-1" style="width:10px;height:10px;background:<?= htmlspecialchars($ev->color ?? '#c81015') ?>;"></span>
                                        <?= htmlspecialchars($ev->titulo) ?>
                                        <?php if (!empty($ev->ultimo_recordatorio)): ?>
                                            <small class="text-muted d-block">Rec. enviado: <?= date('d/m/Y', strtotime($ev->ultimo_recordatorio)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center small"><?= date('d/m/Y', strtotime($ev->fecha)) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $dias <= 3 ? 'danger' : 'warning' ?>">
                                            <?= $dias == 0 ? '¡Hoy!' : $dias . ' día' . ($dias == 1 ? '' : 's') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="/evospace/secciones/eventos/eventos.php" class="d-inline" onsubmit="return confirm('¿Enviar recordatorio a los tutores?')">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="accion" value="recordatorio_evento">
                                            <input type="hidden" name="id_evento" value="<?= (int) $ev->id_evento ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Enviar recordatorio a tutores"><i class="bi bi-bell-fill"></i> Enviar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 7. MATRÍCULAS PENDIENTES + DISTRIBUCIÓN                    -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-evo-accent text-white"><i class="bi bi-mortarboard-fill me-1"></i> Matrículas <?= $anioActual ?></div>
                <div class="card-body text-center">
                    <h2 class="fw-bold <?= $matriculasPendientes > 0 ? 'text-danger' : 'text-success' ?>"><?= $matriculasPendientes ?></h2>
                    <p class="text-muted mb-2">alumnos activos sin matrícula pagada este año</p>
                    <?php $pctMat = $totalAlumnos > 0 ? round(($totalAlumnos - $matriculasPendientes) / $totalAlumnos * 100) : 0; ?>
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar bg-<?= $matriculasPendientes > 0 ? 'danger' : 'success' ?>" style="width: <?= $pctMat ?>%;"></div>
                    </div>
                    <small class="text-muted"><?= $totalAlumnos - $matriculasPendientes ?>/<?= $totalAlumnos ?> con matrícula al día (<?= $pctMat ?>%)</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-evo-accent text-white">
                    <i class="bi bi-bar-chart-fill"></i> Distribución por nivel
                </div>
                <div class="card-body">
                    <?php
                    $niveles = $pdo->query("SELECT c.tipo, COUNT(a.id_alumno) as total FROM cursos c INNER JOIN alumnos a ON c.id_curso = a.id_curso WHERE a.activo = 1 GROUP BY c.tipo ORDER BY c.tipo")->fetchAll(PDO::FETCH_OBJ);
                    $totalNiveles = array_sum(array_column($niveles, 'total'));
                    ?>
                    <?php if (!empty($niveles)): ?>
                        <?php foreach ($niveles as $n): $pct = $totalNiveles > 0 ? round(($n->total / $totalNiveles) * 100) : 0; ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-bold"><?= htmlspecialchars($n->tipo) ?></span>
                                    <span><?= $n->total ?> alumnos (<?= $pct ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center text-muted small mt-2">Total: <?= $totalNiveles ?> alumnos</div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Sin datos.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('recaudacionChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsMeses) ?>,
            datasets: [{
                label: 'Recaudado (Gs)',
                data: <?= json_encode($dataRecaudacion) ?>,
                backgroundColor: 'rgba(200, 16, 21, 0.6)',
                borderColor: '#c81015',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'Gs ' + value.toLocaleString(); }
                    }
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
