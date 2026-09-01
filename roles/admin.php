<?php
session_start();
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'auxiliar')) {
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

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$recaudadoHoy = (float)$stmt->fetchColumn();

$totalPendientesCantina = (int)$pdo->query("SELECT COUNT(DISTINCT id_alumno) FROM ventas WHERE id_alumno IS NOT NULL AND estado_pago IN ('pendiente','parcial')")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE fecha >= ?");
$stmt->execute([$hoy]);
$totalEventosProximos = (int)$stmt->fetchColumn();

$totalProfesores = (int)$pdo->query("SELECT COUNT(*) FROM profesores p INNER JOIN usuarios u ON p.id_usuario = u.id_usuario WHERE p.activo = 1 AND u.activo = 1")->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$ventasCantinaHoy = (float)$stmt->fetchColumn();

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
// PRÓXIMOS EVENTOS
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM eventos WHERE fecha >= ? ORDER BY fecha ASC, hora ASC LIMIT 5");
$stmt->execute([$hoy]);
$proximosEventos = $stmt->fetchAll(PDO::FETCH_OBJ);

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
// ÚLTIMOS PAGOS
// ============================================================
$ultimosPagos = $pdo->query("SELECT p.*, a.nombre, a.apellido FROM pagos p INNER JOIN alumnos a ON p.id_alumno = a.id_alumno ORDER BY p.fecha DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

// ============================================================
// BALANCE DEL MES
// ============================================================
$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_abono), 0) FROM abonos WHERE MONTH(fecha_abono) = ? AND YEAR(fecha_abono) = ?");
$stmt->execute([$mesActual, $anioActual]);
$gastosMes = (float)$stmt->fetchColumn();
$gananciaMes = max(0, $recaudadoMes - $gastosMes);

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
$stmtAlActivos = $pdo->query("
    SELECT a.id_alumno, a.nombre, a.apellido, c.tipo, c.nombre AS curso_nombre
    FROM alumnos a
    JOIN cursos c ON a.id_curso = c.id_curso
    WHERE a.activo = 1
    ORDER BY c.tipo, c.orden, a.apellido
");
foreach ($stmtAlActivos as $al) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagos WHERE id_alumno = ? AND concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$al['id_alumno'], $mesActual, $anioActual]);
    if ((int)$stmt->fetchColumn() === 0) {
        $morososCuota[] = $al;
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
                <i class="bi bi-building me-1"></i> Academia Evolucionarte
            </span>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 2. INDICADORES PRINCIPALES -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-danger bg-opacity-10"><i class="bi bi-people-fill text-danger"></i></div>
                    <div class="stat-number"><?= $totalAlumnos ?></div>
                    <div class="stat-label">Alumnos activos</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card h-100 border-0 shadow-hover" style="background: linear-gradient(135deg, #198754, #146c43); color: #fff;">
                <div class="card-body text-center">
                    <div class="stat-icon" style="background: rgba(255,255,255,0.15); color: #fff;"><i class="bi bi-cash-coin"></i></div>
                    <div class="stat-number"><?= number_format($recaudadoMes, 0, ',', '.') ?></div>
                    <div class="stat-label">Recaudado este mes</div>
                    <small style="opacity: 0.7;">Hoy: <?= number_format($recaudadoHoy, 0, ',', '.') ?> Gs</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-warning bg-opacity-10"><i class="bi bi-graph-up-arrow text-warning"></i></div>
                    <div class="stat-number"><?= number_format($gananciaMes, 0, ',', '.') ?></div>
                    <div class="stat-label">Ganancia del mes</div>
                    <small class="text-muted">Ing: <?= number_format($recaudadoMes, 0, ',', '.') ?> Gs</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card h-100 border-0 shadow-hover" style="background: linear-gradient(135deg, #dc3545, #b02a37); color: #fff;">
                <div class="card-body text-center">
                    <div class="stat-icon" style="background: rgba(255,255,255,0.15); color: #fff;"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-number"><?= $deudores ?></div>
                    <div class="stat-label">Alumnos con deuda</div>
                    <small style="opacity: 0.7;">Total: <?= number_format($deudaTotal, 0, ',', '.') ?> Gs</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card h-100 border-0 shadow-hover" style="background: linear-gradient(135deg, #0d6efd, #0a58ca); color: #fff;">
                <div class="card-body text-center">
                    <div class="stat-icon" style="background: rgba(255,255,255,0.15); color: #fff;"><i class="bi bi-person-badge-fill"></i></div>
                    <div class="stat-number"><?= $totalProfesores ?></div>
                    <div class="stat-label">Profesores</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card h-100 border-0 shadow-hover" style="background: linear-gradient(135deg, #ffc107, #e0a800); color: #212529;">
                <div class="card-body text-center">
                    <div class="stat-icon" style="background: rgba(0,0,0,0.08); color: #212529;"><i class="bi bi-cup-straw"></i></div>
                    <div class="stat-number"><?= number_format($ventasCantinaHoy, 0, ',', '.') ?></div>
                    <div class="stat-label">Cantina hoy</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 3. ASISTENCIA DEL DÍA (si hay registros) -->
    <!-- ========================================================== -->
    <?php if (!empty($asistenciaHoy)): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white">
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
    <!-- 4. ACCIONES RÁPIDAS -->
    <!-- ========================================================== -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="/evospace/secciones/inscripciones.php" class="btn btn-success shadow-sm flex-fill">
            <i class="bi bi-person-plus-fill"></i> Inscribir alumno
        </a>
        <a href="/evospace/secciones/asistencia/index.php" class="btn btn-danger shadow-sm flex-fill">
            <i class="bi bi-clipboard-check"></i> Tomar asistencia
        </a>
        <a href="/evospace/secciones/cantina/ventas/nueva.php" class="btn btn-warning shadow-sm flex-fill text-dark">
            <i class="bi bi-cart-plus"></i> Venta cantina
        </a>
        <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-info shadow-sm flex-fill text-white">
            <i class="bi bi-calendar-event"></i> Crear evento
        </a>
        <a href="/evospace/secciones/profesores.php" class="btn btn-secondary shadow-sm flex-fill text-white">
            <i class="bi bi-person-badge"></i> Profesores
        </a>
    </div>

    <!-- ========================================================== -->
    <!-- 5. ZONA PRINCIPAL: GRÁFICO + EVENTOS -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card shadow h-100">
                <div class="card-header bg-evo text-white">
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
                            <small class="text-muted">Ganancia neta</small>
                            <h6 class="text-primary"><?= number_format($gananciaMes, 0, ',', '.') ?> Gs</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card shadow h-100">
                <div class="card-header bg-evo text-white">
                    <i class="bi bi-calendar-event me-1"></i> Próximos eventos
                </div>
                <div class="card-body p-2">
                    <?php if (empty($proximosEventos)): ?>
                        <p class="text-muted mb-0 p-2">No hay eventos próximos.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($proximosEventos as $ev): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="badge bg-danger me-2" style="background-color: <?= htmlspecialchars($ev->color ?? '#c81015') ?>; width: 12px; height: 12px; display: inline-block;"></span>
                                        <?= htmlspecialchars($ev->titulo) ?>
                                    </span>
                                    <span class="small text-muted"><?= date('d/m/Y', strtotime($ev->fecha)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-end">
                    <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-sm btn-outline-danger">Ver todos</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 6. ATENCIÓN / PENDIENTES -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-danger mb-3 h-100">
                <div class="card-header bg-evo text-white">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Requiere atención
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="fs-3 fw-bold text-danger"><?= $deudores ?></div>
                            <div class="small text-muted">Alumnos con deuda en cantina</div>
                            <a href="/evospace/secciones/cantina/ventas/index.php?estado_pago=pendiente" class="btn btn-sm btn-outline-danger mt-2">Gestionar</a>
                        </div>
                        <div class="col-6">
                            <div class="fs-3 fw-bold text-danger"><?= $profesoresPendientes ?></div>
                            <div class="small text-muted">Profesores con salario pendiente</div>
                            <a href="/evospace/secciones/profesores.php" class="btn btn-sm btn-outline-danger mt-2">Gestionar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-evo text-white">
                    <i class="bi bi-check-circle"></i> Cumplimiento de pagos - <?= date('F', mktime(0, 0, 0, $mesActual, 1)) ?>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold"><?= $porcentajeCumplimiento ?>% de los alumnos pagaron la cuota</span>
                        <span><?= $totalAlumnosConCuota ?> de <?= $totalAlumnos ?></span>
                    </div>
                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentajeCumplimiento ?>%;" aria-valuenow="<?= $porcentajeCumplimiento ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 6b. MOROSIDAD DE CUOTA + MATRÍCULAS PENDIENTES              -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card <?= $totalMorososCuota > 0 ? 'border-danger' : '' ?> shadow h-100">
                <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-credit-card-2-front me-1"></i> Morosos de cuota - <?= date('F', mktime(0, 0, 0, $mesActual, 1)) ?></span>
                    <span class="badge bg-<?= $totalMorososCuota > 0 ? 'danger' : 'success' ?>"><?= $totalMorososCuota ?> sin pagar</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($morososCuota)): ?>
                        <div class="p-3 text-muted">Todos los alumnos activos pagaron la cuota este mes.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light"><tr><th>Alumno</th><th>Nivel / Curso</th><th class="text-end">Acción</th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($morososCuota, 0, 8) as $m): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($m['apellido'] . ', ' . $m['nombre']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($m['tipo'] . ' - ' . $m['curso_nombre']) ?></span></td>
                                            <td class="text-end">
                                                <a href="/evospace/secciones/ficha_alumno.php?id=<?= (int)$m['id_alumno'] ?>" class="btn btn-sm btn-outline-danger">Cobrar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalMorososCuota > 8): ?>
                            <div class="p-2 text-center small text-muted">y <?= $totalMorososCuota - 8 ?> más...</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card shadow h-100">
                <div class="card-header bg-evo text-white"><i class="bi bi-mortarboard-fill me-1"></i> Matrículas <?= $anioActual ?></div>
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
    </div>

    <!-- ========================================================== -->
    <!-- 6c. PRÓXIMOS EVENTOS (recordatorios)                       -->
    <!-- ========================================================== -->
    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar-event-fill me-1"></i> Próximos eventos (7 días)</span>
            <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-sm btn-light text-danger fw-bold"><i class="bi bi-plus-circle"></i> Gestionar</a>
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
    <!-- 7. TABLAS: Últimos pagos -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-evo text-white d-flex justify-content-between">
                    <span><i class="bi bi-clock-history"></i> Últimos pagos</span>

                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Alumno</th><th>Concepto</th><th class="text-end">Monto</th><th class="text-center">Fecha</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimosPagos)): ?>
                                    <tr><td colspan="4" class="text-center py-3">Sin pagos recientes.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ultimosPagos as $pago): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($pago->nombre . ' ' . $pago->apellido) ?></td>
                                            <td><?= ucfirst($pago->concepto) ?></td>
                                            <td class="text-end"><?= number_format($pago->total, 0, ',', '.') ?> Gs</td>
                                            <td class="text-center small"><?= date('d/m/Y', strtotime($pago->fecha)) ?></td>
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
                <div class="card-header bg-evo text-white">
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
