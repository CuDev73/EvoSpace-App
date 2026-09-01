<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'padre') {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

$id_padre = $_SESSION['id_usuario'];
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_padre]);
$padre = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener hijos
$stmt = $pdo->prepare("
    SELECT a.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo
    FROM alumnos a
    INNER JOIN cursos c ON a.id_curso = c.id_curso
    WHERE a.id_padre = ? AND a.activo = 1
    ORDER BY a.apellido, a.nombre
");
$stmt->execute([$id_padre]);
$hijos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener cursos de los hijos (para filtrar eventos)
$stmt = $pdo->prepare("SELECT DISTINCT id_curso FROM alumnos WHERE id_padre = ? AND activo = 1");
$stmt->execute([$id_padre]);
$cursosDelPadre = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener eventos únicos para esos cursos (usando DISTINCT)
$eventosParaPadre = [];
if (!empty($cursosDelPadre)) {
    $placeholders = implode(',', array_fill(0, count($cursosDelPadre), '?'));
    // Usamos DISTINCT para evitar duplicados si el evento está asociado a varios cursos del padre
    $sql = "SELECT DISTINCT e.* FROM eventos e
            INNER JOIN evento_curso ec ON e.id_evento = ec.id_evento
            WHERE ec.id_curso IN ($placeholders) AND e.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
            ORDER BY e.fecha ASC, e.hora ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($cursosDelPadre);
    $eventosParaPadre = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener configuración de recargo y día límite
$config = [];
$stmt = $pdo->query("SELECT clave, valor FROM configuracion");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['clave']] = $row['valor'];
}
$porcentajeBeca = (float)($config['porcentaje_beca'] ?? 50.0);
$recargoPorDia = (float)($config['recargo_por_dia'] ?? 1000);
$diaLimite = (int)($config['dia_limite_pago'] ?? 10);
$diasGracia = (int)($config['dias_gracia_pago'] ?? 10);

// Filtro de mes/año
$mesFiltro = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$anioFiltro = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// Función para calcular deuda de un alumno en un mes específico
// $diaVenc es por alumno (fallback a config global).
function calcularDeuda($pdo, $id_alumno, $mes, $anio, $porcentajeBeca, $recargoPorDia, $diaLimite, $diaVenc = null, $diasGracia = 10) {
    // 1. Obtener precio de la cuota del curso del alumno
    $stmt = $pdo->prepare("
        SELECT p.precio 
        FROM alumnos a 
        INNER JOIN precios p ON a.id_curso = p.id_curso
        WHERE a.id_alumno = ? AND p.concepto = 'cuota'
    ");
    $stmt->execute([$id_alumno]);
    $cuotaBase = (float)$stmt->fetchColumn() ?: 0;
    if ($cuotaBase == 0) return ['cuota' => 0, 'recargo' => 0, 'pagado' => 0, 'deuda' => 0];

    // 2. Aplicar beca si corresponde
    $stmt = $pdo->prepare("SELECT becado FROM alumnos WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $becado = $stmt->fetchColumn();
    $cuota = $becado ? round($cuotaBase * ($porcentajeBeca / 100) / 1000) * 1000 : $cuotaBase;

    // 3. Sumar pagos de cuota en el mes filtrado
    $stmt = $pdo->prepare("
        SELECT SUM(total) FROM pagos 
        WHERE id_alumno = ? 
          AND concepto = 'cuota' 
          AND MONTH(fecha) = ? 
          AND YEAR(fecha) = ?
    ");
    $stmt->execute([$id_alumno, $mes, $anio]);
    $pagadoCuota = (float)$stmt->fetchColumn() ?: 0;

    // 4. Recargo según vencimiento del alumno (por defecto usa config global)
    $diaVenc = $diaVenc ?? $diaLimite;
    $vencimiento = $diaVenc + $diasGracia;
    $recargo = 0;
    $hoy = getdate();
    if ($mes == $hoy['mon'] && $anio == $hoy['year'] && $hoy['mday'] > $vencimiento) {
        $diasAtraso = $hoy['mday'] - $vencimiento;
        $recargo = $diasAtraso * $recargoPorDia;
    }

    // 5. Deuda = (cuota + recargo) - pagadoCuota
    $deuda = ($cuota + $recargo) - $pagadoCuota;
    if ($deuda < 0) $deuda = 0;

    return [
        'cuota' => $cuota,
        'recargo' => $recargo,
        'pagado' => $pagadoCuota,
        'deuda' => $deuda
    ];
}

// Deuda de cantina por alumno (reusar funciones de cantina si existen)
if (file_exists(__DIR__ . '/../secciones/cantina/funciones.php')) {
    require_once __DIR__ . '/../secciones/cantina/funciones.php';
}

// Datos enriquecidos por hijo: deuda del mes + cantina + asistencia
$hijosDatos = [];
$deudaTotalMes = 0;
foreach ($hijos as $hijo) {
    $id = (int)$hijo['id_alumno'];

    $deudaInfo = calcularDeuda($pdo, $id, $mesFiltro, $anioFiltro, $porcentajeBeca, $recargoPorDia, $diaLimite, $hijo['dia_vencimiento'] ?? null, $diasGracia);
    $deudaTotalMes += $deudaInfo['deuda'];

    $stmtPago = $pdo->prepare("SELECT SUM(total) AS total_pagado FROM pagos WHERE id_alumno = ?");
    $stmtPago->execute([$id]);
    $totalHistorico = (float)($stmtPago->fetch(PDO::FETCH_ASSOC)['total_pagado'] ?? 0);

    $matriculaAnioActual = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pagos WHERE id_alumno = $id AND concepto = 'matrícula' AND YEAR(fecha) = " . (int)date('Y'))->fetchColumn();

    $deudaCantina = function_exists('obtenerDeudaAlumnoCantina')
        ? obtenerDeudaAlumnoCantina($pdo, $id)
        : 0;

    // Asistencia del mes filtrado
    $stmtAsist = $pdo->prepare("
        SELECT fecha, presente FROM asistencia
        WHERE id_alumno = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?
        ORDER BY fecha DESC
    ");
    $stmtAsist->execute([$id, $mesFiltro, $anioFiltro]);
    $asistenciaMes = $stmtAsist->fetchAll(PDO::FETCH_ASSOC);
    $diasRegistrados = count($asistenciaMes);
    $diasPresentes = count(array_filter($asistenciaMes, fn($r) => (int)$r['presente'] === 1));
    $pctAsistencia = $diasRegistrados > 0 ? round($diasPresentes / $diasRegistrados * 100) : null;

    $estado = 'al_dia';
    $estadoTexto = 'Al día';
    $estadoColor = 'success';
    if ($deudaInfo['deuda'] > 0) {
        $estado = 'moroso';
        $estadoTexto = 'En mora';
        $estadoColor = 'danger';
    } elseif ($deudaInfo['pagado'] > 0 && $deudaInfo['recargo'] == 0) {
        $estadoTexto = 'Pagado completo';
    } elseif ($deudaInfo['pagado'] == 0 && $deudaInfo['deuda'] == 0) {
        $estadoTexto = 'Sin cuota este mes';
        $estadoColor = 'secondary';
    }

    $hijosDatos[] = [
        'hijo' => $hijo,
        'deuda' => $deudaInfo,
        'total_historico' => $totalHistorico,
        'matricula_anio' => $matriculaAnioActual,
        'deuda_cantina' => $deudaCantina,
        'asistencia' => $asistenciaMes,
        'dias_registrados' => $diasRegistrados,
        'dias_presentes' => $diasPresentes,
        'pct_asistencia' => $pctAsistencia,
        'estado' => $estado,
        'estado_texto' => $estadoTexto,
        'estado_color' => $estadoColor,
    ];
}
$hijosAlDia = count(array_filter($hijosDatos, fn($h) => $h['estado'] === 'al_dia' && $h['deuda']['deuda'] == 0));
$hijosEnMora = count($hijosDatos) - $hijosAlDia;
?>

<div class="container mt-3 pt-4">

    <!-- Saludo -->
    <div class="dashboard-greeting mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-person-circle me-2"></i> Bienvenido, <?= htmlspecialchars($padre['usuario']) ?></h4>
            <small>Email: <?= htmlspecialchars($padre['email']) ?></small>
        </div>
        <span class="badge bg-light text-dark fs-6"><?= count($hijos) ?> hijo<?= count($hijos) !== 1 ? 's' : '' ?></span>
    </div>

    <!-- Eventos próximos (ahora sin duplicados) -->
    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white">
            <i class="bi bi-calendar-event"></i> Próximos eventos para tus hijos
        </div>
        <div class="card-body">
            <?php if (empty($eventosParaPadre)): ?>
                <p class="text-muted">No hay eventos programados para los cursos de tus hijos.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($eventosParaPadre as $ev): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold"><?= htmlspecialchars($ev['titulo']) ?></div>
                                <small>
                                    <i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($ev['fecha'])) ?>
                                    <?php if ($ev['hora']): ?> - <i class="bi bi-clock"></i> <?= date('H:i', strtotime($ev['hora'])) ?><?php endif; ?>
                                </small><br>
                                <small><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ev['lugar'] ?? 'Sin lugar') ?></small>
                                <?php if ($ev['descripcion']): ?>
                                    <p class="mt-1 mb-0 small"><?= nl2br(htmlspecialchars($ev['descripcion'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($ev['enlace_ubicacion']): ?>
                                <a href="<?= htmlspecialchars($ev['enlace_ubicacion']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-map"></i> Mapa
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtro de mes/año -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Mes</label>
                    <select name="mes" class="form-select form-select-sm">
                        <?php for ($m=1; $m<=12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $mesFiltro ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$m,1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Año</label>
                    <select name="anio" class="form-select form-select-sm">
                        <?php for ($a=date('Y')-2; $a<=date('Y')+1; $a++): ?>
                            <option value="<?= $a ?>" <?= $a == $anioFiltro ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-evo btn-sm w-100">Filtrar</button>
                </div>
                <div class="col-md-3 text-end">
                    <a href="?mes=<?= date('m') ?>&anio=<?= date('Y') ?>" class="btn btn-outline-secondary btn-sm w-100 w-md-auto">Mes actual</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (count($hijosDatos) > 0): ?>

    <!-- KPIs del panel -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-6">
            <div class="card shadow h-100 text-center border-success">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-0">Hijos al día</h6>
                    <h3 class="fw-bold text-success mb-0"><?= $hijosAlDia ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-12">
            <div class="card shadow h-100 text-center <?= $deudaTotalMes > 0 ? 'border-danger' : 'border-success' ?>">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-0">Deuda total de <?= date('F', mktime(0,0,0,$mesFiltro,1)) ?></h6>
                    <h3 class="fw-bold <?= $deudaTotalMes > 0 ? 'text-danger' : 'text-success' ?> mb-0"><?= number_format($deudaTotalMes, 0, ',', '.') ?> Gs</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs por hijo -->
    <div class="card shadow">
        <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-people-fill"></i> Mis hijos</span>
            <span class="badge bg-light text-dark"><?= date('F', mktime(0,0,0,$mesFiltro,1)) . ' ' . $anioFiltro ?></span>
        </div>
        <div class="card-body">
            <ul class="nav nav-pills gap-1 mb-3" id="tabsHijos" role="tablist">
                <?php foreach ($hijosDatos as $i => $hd): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                id="tab-hijo-<?= $hd['hijo']['id_alumno'] ?>"
                                data-bs-toggle="tab" data-bs-target="#panel-hijo-<?= $hd['hijo']['id_alumno'] ?>"
                                type="button" role="tab">
                            <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars(explode(' ', trim($hd['hijo']['nombre'] . ' ' . $hd['hijo']['apellido']))[0]) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content" id="tabsHijosContent">
                <?php foreach ($hijosDatos as $i => $hd):
                    $hijo = $hd['hijo'];
                    $d = $hd['deuda'];
                ?>
                    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="panel-hijo-<?= $hijo['id_alumno'] ?>" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h5 class="mb-0"><?= htmlspecialchars($hijo['nombre'] . ' ' . $hijo['apellido']) ?>
                                <span class="badge bg-<?= $hijo['becado'] ? 'warning text-dark' : 'secondary' ?> ms-1"><?= $hijo['becado'] ? 'Descuento 50%' : 'Precio completo' ?></span>
                            </h5>
                            <span class="badge bg-<?= $hd['estado_color'] ?> fs-6 px-3"><i class="bi bi-<?= $hd['estado'] === 'moroso' ? 'exclamation-triangle-fill' : 'check-circle-fill' ?> me-1"></i><?= $hd['estado_texto'] ?></span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header bg-light"><i class="bi bi-cash-stack me-1 text-danger"></i> Cuota de <?= date('F', mktime(0,0,0,$mesFiltro,1)) ?></div>
                                    <div class="card-body small">
                                        <p class="mb-1 d-flex justify-content-between"><span class="text-muted">Curso</span><strong><?= htmlspecialchars($hijo['curso_tipo'] . ' - ' . $hijo['curso_nombre']) ?></strong></p>
                                        <p class="mb-1 d-flex justify-content-between"><span class="text-muted">Cuota</span><span><?= number_format($d['cuota'], 0, ',', '.') ?> Gs</span></p>
                                        <?php if ($d['recargo'] > 0): ?>
                                            <p class="mb-1 d-flex justify-content-between text-danger"><span>Recargo</span><span>+ <?= number_format($d['recargo'], 0, ',', '.') ?> Gs</span></p>
                                        <?php endif; ?>
                                        <p class="mb-1 d-flex justify-content-between"><span class="text-muted">Pagado</span><span class="text-success"><?= number_format($d['pagado'], 0, ',', '.') ?> Gs</span></p>
                                        <hr class="my-2">
                                        <p class="mb-0 d-flex justify-content-between fw-bold"><span>Deuda</span><span class="text-<?= $d['deuda'] > 0 ? 'danger' : 'success' ?>"><?= $d['deuda'] > 0 ? number_format($d['deuda'], 0, ',', '.') . ' Gs' : 'Al día' ?></span></p>
                                        <p class="mt-2 mb-0 d-flex justify-content-between">
                                            <span class="text-muted">Matrícula <?= date('Y') ?></span>
                                            <?php if ($hd['matricula_anio'] > 0): ?>
                                                <span class="text-success">Pagada</span>
                                            <?php else: ?>
                                                <span class="text-danger">Pendiente</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="mt-2 mb-0 text-muted">Total pagado histórico: <?= number_format($hd['total_historico'], 0, ',', '.') ?> Gs</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm <?= $hd['deuda_cantina'] > 0 ? 'border-warning' : '' ?>">
                                    <div class="card-header bg-light"><i class="bi bi-cup-hot me-1 text-warning"></i> Cantina</div>
                                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center small">
                                        <?php if ($hd['deuda_cantina'] > 0): ?>
                                            <h3 class="text-warning fw-bold mb-1"><?= number_format($hd['deuda_cantina'], 0, ',', '.') ?> Gs</h3>
                                            <span class="badge bg-warning text-dark">Deuda pendiente de fiado</span>
                                        <?php else: ?>
                                            <h3 class="text-success fw-bold mb-1"><i class="bi bi-check-circle-fill"></i> Al día</h3>
                                            <span class="text-muted">Sin deudas en cantina</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header bg-light"><i class="bi bi-clipboard-check me-1 text-primary"></i> Asistencia del mes</div>
                                    <div class="card-body text-center">
                                        <?php if ($hd['pct_asistencia'] === null): ?>
                                            <p class="text-muted small mb-2">Sin registros de asistencia este mes.</p>
                                        <?php else: ?>
                                            <h3 class="fw-bold <?= $hd['pct_asistencia'] >= 75 ? 'text-success' : ($hd['pct_asistencia'] >= 50 ? 'text-warning' : 'text-danger') ?> mb-1"><?= $hd['pct_asistencia'] ?>%</h3>
                                            <small class="text-muted"><?= $hd['dias_presentes'] ?>/<?= $hd['dias_registrados'] ?> clases presentes</small>
                                            <div class="progress mt-2" style="height:8px;">
                                                <div class="progress-bar bg-<?= $hd['pct_asistencia'] >= 75 ? 'success' : ($hd['pct_asistencia'] >= 50 ? 'warning' : 'danger') ?>" style="width:<?= $hd['pct_asistencia'] ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($hd['asistencia'])): ?>
                                            <div class="mt-3 text-start small">
                                                <strong>Últimos registros:</strong>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    <?php foreach (array_slice($hd['asistencia'], 0, 5) as $a): ?>
                                                        <span class="badge bg-<?= $a['presente'] ? 'success' : 'danger' ?>">
                                                            <?= date('d/m', strtotime($a['fecha'])) ?> · <?= $a['presente'] ? 'P' : 'A' ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <button onclick="verPagos(<?= $hijo['id_alumno'] ?>)" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye-fill"></i> Ver pagos / Recibos
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php else: ?>
        <div class="alert alert-info">No tienes hijos registrados en el sistema.</div>
    <?php endif; ?>
</div>

<!-- Modal para ver pagos y descargar recibos -->
<div class="modal fade" id="modalVerPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="contenidoVerPagos">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function verPagos(idAlumno) {
    const modal = new bootstrap.Modal(document.getElementById('modalVerPagos'));
    modal.show();

    document.getElementById('contenidoVerPagos').innerHTML = `
        <div class="modal-body text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;

    fetch('../secciones/obtener_pagos.php?id_alumno=' + idAlumno)
        .then(response => {
            if (!response.ok) throw new Error('Error al cargar los pagos');
            return response.text();
        })
        .then(html => {
            document.getElementById('contenidoVerPagos').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('contenidoVerPagos').innerHTML = `
                <div class="modal-body">
                    <div class="alert alert-danger">Error: ${error.message}</div>
                </div>
            `;
        });
}
</script>

<?php include '../includes/footer.php'; ?>