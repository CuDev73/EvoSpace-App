<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

// ============================================================
// DATOS DEL DÍA Y CONFIGURACIÓN
// ============================================================
$hoy = date('Y-m-d');
$mesActual = date('m');
$anioActual = date('Y');
$nombreUsuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'EvoSpace';
$fechaFormateada = date('l, d \d\e F \d\e Y');

// ============================================================
// 1. INDICADORES PRINCIPALES (6 tarjetas)
// ============================================================

// Total alumnos activos
$totalAlumnos = (int) $pdo->query("SELECT COUNT(*) FROM alumnos WHERE activo = 1")->fetchColumn();

// Recaudado este mes (todo el mes actual)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesActual, $anioActual]);
$recaudadoMes = (float) $stmt->fetchColumn();

// Recaudado hoy (solo para mostrar como dato secundario)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$recaudadoHoy = (float) $stmt->fetchColumn();

// Pagos pendientes (ventas en estado pendiente)
$totalPendientes = (int) $pdo->query("SELECT COUNT(*) FROM ventas WHERE estado_pago = 'pendiente'")->fetchColumn();

// Eventos próximos (fecha >= hoy)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE fecha >= ?");
$stmt->execute([$hoy]);
$totalEventosProximos = (int) $stmt->fetchColumn();

// Total profesores activos
$totalProfesores = (int) $pdo->query("SELECT COUNT(*) FROM profesores p INNER JOIN usuarios u ON p.id_usuario = u.id_usuario WHERE p.activo = 1 AND u.activo = 1")->fetchColumn();

// Ventas cantina hoy
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$ventasCantinaHoy = (float) $stmt->fetchColumn();

// ============================================================
// 2. ACCIONES RÁPIDAS (6 botones)
// ============================================================

// ============================================================
// 3. GRÁFICO RECAUDACIÓN MENSUAL (últimos 6 meses)
// ============================================================
$labelsMeses = [];
$dataRecaudacion = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('m', strtotime("-$i months"));
    $anio = date('Y', strtotime("-$i months"));
    $labelsMeses[] = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$mes, $anio]);
    $dataRecaudacion[] = (float) $stmt->fetchColumn();
}

// ============================================================
// 4. PRÓXIMOS EVENTOS (hasta 3)
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM eventos WHERE fecha >= ? ORDER BY fecha ASC, hora ASC LIMIT 3");
$stmt->execute([$hoy]);
$proximosEventos = $stmt->fetchAll(PDO::FETCH_OBJ);

// ============================================================
// 5. NOTIFICACIONES RECIENTES (últimas 5)
// ============================================================
$stmt = $pdo->prepare("SELECT n.*, u.usuario FROM notificaciones n LEFT JOIN usuarios u ON n.id_usuario = u.id_usuario ORDER BY n.fecha DESC LIMIT 5");
$stmt->execute();
$notificaciones = $stmt->fetchAll(PDO::FETCH_OBJ);

// ============================================================
// 6. PENDIENTES IMPORTANTES
// ============================================================

// Alumnos con deuda (compras_alumnos no pagadas)
$deudores = (int) $pdo->query("SELECT COUNT(DISTINCT id_alumno) FROM compras_alumnos WHERE pagado = 0")->fetchColumn();

// Profesores con salario pendiente (salario_base > abonos)
$profesoresPendientes = 0;
$stmt = $pdo->query("SELECT p.id_profesor, u.id_usuario, p.salario_base 
                     FROM profesores p 
                     INNER JOIN usuarios u ON p.id_usuario = u.id_usuario 
                     WHERE p.activo = 1 AND u.activo = 1");
$profesores = $stmt->fetchAll(PDO::FETCH_OBJ);
foreach ($profesores as $prof) {
    $stmtAbono = $pdo->prepare("SELECT COALESCE(SUM(monto_abono), 0) FROM abonos WHERE profesor = (SELECT usuario FROM usuarios WHERE id_usuario = ?) AND MONTH(fecha_abono) = ? AND YEAR(fecha_abono) = ?");
    $stmtAbono->execute([$prof->id_usuario, $mesActual, $anioActual]);
    $abonado = (float) $stmtAbono->fetchColumn();
    if ($prof->salario_base > $abonado) $profesoresPendientes++;
}

// Eventos sin confirmar (por definir, usamos un criterio)
$eventosSinConfirmar = 0; // Si hay campo confirmado en la tabla eventos, agregar.

// Facturas pendientes (si tienes tabla facturas)
$facturasPendientes = 0; // Dummy

// Notificaciones no leídas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE leida = 0");
$stmt->execute();
$notificacionesNoLeidas = (int) $stmt->fetchColumn();

// ============================================================
// 7. ÚLTIMOS PAGOS (5) - CON FECHA COMPLETA
// ============================================================
$ultimosPagos = $pdo->query("SELECT p.*, a.nombre, a.apellido FROM pagos p INNER JOIN alumnos a ON p.id_alumno = a.id_alumno ORDER BY p.fecha DESC LIMIT 5")->fetchAll(PDO::FETCH_OBJ);

// ============================================================
// 8. MAYORES DEUDORES (5)
// ============================================================
$mayoresDeudores = [];
$alumnos = $pdo->query("SELECT a.id_alumno, a.nombre, a.apellido, a.id_curso FROM alumnos a WHERE activo = 1")->fetchAll(PDO::FETCH_OBJ);
foreach ($alumnos as $al) {
    $stmtDeuda = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM compras_alumnos WHERE id_alumno = ? AND pagado = 0");
    $stmtDeuda->execute([$al->id_alumno]);
    $deuda = (float) $stmtDeuda->fetchColumn();
    if ($deuda > 0) {
        $mayoresDeudores[] = (object) ['nombre' => $al->nombre . ' ' . $al->apellido, 'deuda' => $deuda];
    }
}
usort($mayoresDeudores, function($a, $b) { return $b->deuda - $a->deuda; });
$mayoresDeudores = array_slice($mayoresDeudores, 0, 5);

// ============================================================
// 9. META MENSUAL Y AVANCE
// ============================================================
$metaMensual = 10000000; // Ejemplo: 10.000.000 Gs
$ingresosMes = $recaudadoMes;
$porcentajeMeta = $metaMensual > 0 ? round(($ingresosMes / $metaMensual) * 100, 0) : 0;
$porcentajeMeta = min($porcentajeMeta, 100); // No pasar de 100%

// ============================================================
// 10. BALANCE DEL MES
// ============================================================
$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_abono), 0) FROM abonos WHERE MONTH(fecha_abono) = ? AND YEAR(fecha_abono) = ?");
$stmt->execute([$mesActual, $anioActual]);
$gastosMes = (float) $stmt->fetchColumn();
$gananciaMes = max(0, $ingresosMes - $gastosMes);

// ============================================================
// 11. CUMPLIMIENTO DE PAGOS (alumnos que pagaron cuota este mes)
// ============================================================
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_alumno) FROM pagos WHERE concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$mesActual, $anioActual]);
$totalAlumnosConCuota = (int) $stmt->fetchColumn();
$porcentajeCumplimiento = $totalAlumnos > 0 ? round(($totalAlumnosConCuota / $totalAlumnos) * 100, 0) : 0;

// ============================================================
// 12. VARIABLE PARA EL RECAUDADO DE HOY (para mostrar)
// ============================================================
// Ya está calculada arriba: $recaudadoHoy
?>

<div class="container mt-3">

    <!-- ========================================================== -->
    <!-- 1. ENCABEZADO -->
    <!-- ========================================================== -->
    <div class="dashboard-greeting">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-person-circle me-2"></i>Buenos días, <?= htmlspecialchars($nombreUsuario) ?></h4>
            <small><?= $fechaFormateada ?></small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark">
                <i class="bi bi-bell-fill text-danger me-1"></i><?= $notificacionesNoLeidas ?>
            </span>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 2. INDICADORES PRINCIPALES (6 tarjetas) -->
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
            <div class="card stat-card h-100 border-0 shadow-hover" style="background: linear-gradient(135deg, #dc3545, #b02a37); color: #fff;">
                <div class="card-body text-center">
                    <div class="stat-icon" style="background: rgba(255,255,255,0.15); color: #fff;"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-number"><?= $totalPendientes ?></div>
                    <div class="stat-label">Deuda cantina</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-info bg-opacity-10"><i class="bi bi-calendar-event-fill text-info"></i></div>
                    <div class="stat-number"><?= $totalEventosProximos ?></div>
                    <div class="stat-label">Eventos próximos</div>
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
    <!-- 3. ACCIONES RÁPIDAS -->
    <!-- ========================================================== -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <a href="/evospace/secciones/pagos.php" class="btn btn-success w-100 shadow-sm">
                <i class="bi bi-plus-circle"></i> Registrar pago
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="/evospace/secciones/alumnos.php" class="btn btn-primary w-100 shadow-sm">
                <i class="bi bi-plus-circle"></i> Nuevo alumno
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="/evospace/secciones/cantina/ventas/nueva.php" class="btn btn-warning w-100 shadow-sm text-dark">
                <i class="bi bi-plus-circle"></i> Venta cantina
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="/evospace/roles/profesor.php" class="btn btn-danger w-100 shadow-sm">
                <i class="bi bi-plus-circle"></i> Asistencia
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-info w-100 shadow-sm text-white">
                <i class="bi bi-plus-circle"></i> Crear evento
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="/evospace/secciones/profesores.php" class="btn btn-secondary w-100 shadow-sm text-white">
                <i class="bi bi-plus-circle"></i> Agregar profesor
            </a>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 4. ZONA PRINCIPAL: GRÁFICO + PRÓXIMOS EVENTOS + NOTIFICACIONES -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow h-100">
                <div class="card-header bg-evo text-white">
                    <i class="bi bi-graph-up-arrow me-1"></i> Recaudación mensual
                </div>
                <div class="card-body">
                    <canvas id="recaudacionChart" height="150"></canvas>
                </div>
                <!-- Meta mensual -->
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <span>Meta mensual: <?= number_format($metaMensual, 0, ',', '.') ?> Gs</span>
                        <span>Avance: <?= $porcentajeMeta ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentajeMeta ?>%;" aria-valuenow="<?= $porcentajeMeta ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <!-- Balance del mes -->
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted">Ingresos</small>
                            <h6 class="text-success"><?= number_format($ingresosMes, 0, ',', '.') ?> Gs</h6>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Gastos</small>
                            <h6 class="text-danger"><?= number_format($gastosMes, 0, ',', '.') ?> Gs</h6>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Ganancia</small>
                            <h6 class="text-primary"><?= number_format($gananciaMes, 0, ',', '.') ?> Gs</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <!-- Próximos eventos -->
            <div class="card shadow mb-3">
                <div class="card-header bg-evo text-white">
                    <i class="bi bi-calendar-event me-1"></i> Próximos eventos
                </div>
                <div class="card-body p-2">
                    <?php if (empty($proximosEventos)): ?>
                        <p class="text-muted mb-0">No hay eventos próximos.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($proximosEventos as $ev): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="badge bg-danger me-2" style="background-color: <?= $ev->color ?>; width: 12px; height: 12px; display: inline-block;"></span>
                                        <?= htmlspecialchars($ev->titulo) ?>
                                    </span>
                                    <span class="small text-muted"><?= date('d/m/Y', strtotime($ev->fecha)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notificaciones recientes -->
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-bell me-1"></i> Notificaciones recientes
                </div>
                <div class="card-body p-2">
                    <?php if (empty($notificaciones)): ?>
                        <p class="text-muted mb-0">No hay notificaciones recientes.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($notificaciones as $not): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <?php if ($not->leida): ?>
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                        <?php else: ?>
                                            <i class="bi bi-circle-fill text-danger me-1" style="font-size: 0.6rem;"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($not->mensaje) ?>
                                    </span>
                                    <span class="small text-muted"><?= date('d/m/Y H:i', strtotime($not->fecha)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 5. PENDIENTES IMPORTANTES -->
    <!-- ========================================================== -->
    <div class="card border-danger mb-4">
        <div class="card-header bg-evo text-white">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Atención
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="fs-3 fw-bold text-danger"><?= $deudores ?></div>
                    <div class="small text-muted">Alumnos con deuda</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-3 fw-bold text-danger"><?= $profesoresPendientes ?></div>
                    <div class="small text-muted">Profesores pendientes</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-3 fw-bold text-danger"><?= $eventosSinConfirmar ?></div>
                    <div class="small text-muted">Eventos sin confirmar</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-3 fw-bold text-danger"><?= $facturasPendientes ?></div>
                    <div class="small text-muted">Facturas pendientes</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- 6. TABLAS: Últimos pagos y Mayores deudores -->
    <!-- ========================================================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-evo text-white">
                    <i class="bi bi-clock-history"></i> Últimos pagos
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Alumno</th><th>Concepto</th><th class="text-end">Monto</th><th class="text-center">Fecha</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimosPagos)): ?>
                                    <tr><td colspan="4" class="text-center">Sin pagos recientes.</td></tr>
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
            <div class="card shadow">
                <div class="card-header bg-evo text-white">
                    <i class="bi bi-exclamation-triangle"></i> Mayores deudores
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>Alumno</th><th class="text-end">Deuda</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mayoresDeudores)): ?>
                                    <tr><td colspan="2" class="text-center">Sin deudas registradas.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($mayoresDeudores as $deudor): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($deudor->nombre) ?></td>
                                            <td class="text-end text-danger fw-bold"><?= number_format($deudor->deuda, 0, ',', '.') ?> Gs</td>
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
    <!-- 7. CUMPLIMIENTO DE PAGOS -->
    <!-- ========================================================== -->
    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white">
            <i class="bi bi-check-circle"></i> Cumplimiento de pagos
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <span><?= $porcentajeCumplimiento ?>% de los alumnos pagaron este mes.</span>
                <span><?= $totalAlumnosConCuota ?> de <?= $totalAlumnos ?></span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentajeCumplimiento ?>%;" aria-valuenow="<?= $porcentajeCumplimiento ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
    </div>

</div>

<!-- ========================================================== -->
<!-- SCRIPTS PARA GRÁFICOS (Chart.js) -->
<!-- ========================================================== -->
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