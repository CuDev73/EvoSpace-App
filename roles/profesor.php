<?php
session_start();

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] !== 'profesor' && $_SESSION['rol'] !== 'admin')) {
    header('Location: /evospace/index.php');
    exit;
}

require_once '../config/db.php';
require_once '../helpers/functions.php';
require_once '../helpers/asistencia.php';

// ============================================================
// AJAX: listar alumnos + asistencia del día
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'alumnos') {
    header('Content-Type: application/json');
    $id_curso = (int)($_GET['id_curso'] ?? 0);
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    if (!$id_curso) { echo json_encode([]); exit; }

    echo json_encode(obtenerAlumnosConAsistencia($pdo, $id_curso, $fecha));
    exit;
}

// ============================================================
// AJAX: guardar asistencia
// ============================================================
if (isset($_POST['ajax']) && $_POST['ajax'] === 'guardar_asistencia') {
    verificarTokenCSRF();
    header('Content-Type: application/json');
    $id_curso = (int)($_POST['id_curso'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $estados = $_POST['estado'] ?? [];

    if (!$id_curso) { echo json_encode(['ok' => false, 'error' => 'Falta curso']); exit; }

    $prof = $pdo->prepare("SELECT id_profesor FROM profesores WHERE id_usuario = ?");
    $prof->execute([(int)$_SESSION['id_usuario']]);
    $id_profesor = (int)$prof->fetchColumn();

    if (!$id_profesor || !cursoPerteneceAProfesor($pdo, $id_curso, $id_profesor)) {
        echo json_encode(['ok' => false, 'error' => 'No tenés permiso para este curso']);
        exit;
    }

    try {
        guardarAsistenciaDiaria($pdo, $id_curso, $fecha, $estados, $id_profesor);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// AJAX: resumen mensual
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'mensual') {
    header('Content-Type: application/json');
    $id_curso = (int)($_GET['id_curso'] ?? 0);
    $mes = (int)($_GET['mes'] ?? date('m'));
    $anio = (int)($_GET['anio'] ?? date('Y'));
    if (!$id_curso) { echo json_encode([]); exit; }

    $alumnos = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE id_curso = ? AND activo = 1 ORDER BY apellido, nombre");
    $alumnos->execute([$id_curso]);
    $alumnosArr = $alumnos->fetchAll(PDO::FETCH_ASSOC);

    $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

    $asistencias = [];
    if (!empty($alumnosArr)) {
        $ids = array_column($alumnosArr, 'id_alumno');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id_alumno, DAY(fecha) as dia, presente FROM asistencia WHERE id_curso = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ? AND id_alumno IN ($ph)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$id_curso, $mes, $anio], $ids));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $asistencias[$row['id_alumno']][$row['dia']] = (int)$row['presente'];
        }
    }

    echo json_encode([
        'mes' => $mes,
        'anio' => $anio,
        'dias' => range(1, $diasEnMes),
        'alumnos' => $alumnosArr,
        'asistencias' => $asistencias
    ]);
    exit;
}

// ============================================================
// AJAX: guardar asistencia mensual
// ============================================================
if (isset($_POST['ajax']) && $_POST['ajax'] === 'guardar_mensual') {
    verificarTokenCSRF();
    header('Content-Type: application/json');
    $id_curso = (int)($_POST['id_curso'] ?? 0);
    $mes = (int)($_POST['mes'] ?? date('m'));
    $anio = (int)($_POST['anio'] ?? date('Y'));
    $estados = $_POST['estado'] ?? [];

    if (!$id_curso) { echo json_encode(['ok' => false, 'error' => 'Falta curso']); exit; }

    $prof = $pdo->prepare("SELECT id_profesor FROM profesores WHERE id_usuario = ?");
    $prof->execute([(int)$_SESSION['id_usuario']]);
    $id_profesor = (int)$prof->fetchColumn();

    if (!$id_profesor || !cursoPerteneceAProfesor($pdo, $id_curso, $id_profesor)) {
        echo json_encode(['ok' => false, 'error' => 'No tenés permiso para este curso']);
        exit;
    }

    try {
        guardarAsistenciaMensual($pdo, $id_curso, $mes, $anio, $estados, $id_profesor);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';

$id_usuario = (int) $_SESSION['id_usuario'];
$hora = (int)date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 18 ? 'Buenas tardes' : 'Buenas noches');

$profesor = $pdo->prepare("SELECT * FROM profesores WHERE id_usuario = ?");
$profesor->execute([$id_usuario]);
$profesor = $profesor->fetch();
$idProfesor = $profesor ? (int)$profesor['id_profesor'] : 0;

$cursos = $pdo->prepare("
    SELECT DISTINCT c.id_curso, c.nombre, c.tipo, c.orden, COUNT(a.id_alumno) as total_alumnos
    FROM cursos c
    INNER JOIN alumnos a ON c.id_curso = a.id_curso
    INNER JOIN horarios h ON h.id_curso = c.id_curso
    WHERE c.activo = 1 AND a.activo = 1 AND h.id_profesor = ?
    GROUP BY c.id_curso
    ORDER BY c.tipo, c.orden
");
$cursos->execute([$idProfesor]);
$cursos = $cursos->fetchAll();

$hoy = date('Y-m-d');
$tipos = array_unique(array_column($cursos, 'tipo'));
$totalAlumnos = array_sum(array_column($cursos, 'total_alumnos'));
?>
<div class="container mt-3 d-flex flex-column">
    <div class="order-2 order-md-0">
    <div class="dashboard-greeting">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-person-badge me-2"></i><?= $saludo ?>, <?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '') ?></h4>
            <small>Panel del Profesor</small>
        </div>
        <?php if ($profesor && $profesor['salario_base'] > 0): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalMisPagos">
                <i class="bi bi-cash-stack"></i> Ver salario
            </button>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-danger bg-opacity-10"><i class="bi bi-book-fill text-danger"></i></div>
                    <div class="stat-number"><?= count($cursos) ?></div>
                    <div class="stat-label">Cursos a cargo</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-info bg-opacity-10"><i class="bi bi-people-fill text-info"></i></div>
                    <div class="stat-number"><?= $totalAlumnos ?></div>
                    <div class="stat-label">Alumnos totales</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100 border-0 shadow-hover">
                <div class="card-body text-center">
                    <div class="stat-icon bg-success bg-opacity-10"><i class="bi bi-calendar3 text-success"></i></div>
                    <div class="stat-number"><?= date('d/m/Y') ?></div>
                    <div class="stat-label">Hoy</div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /order saludo+kpis -->

    <!-- ========================================================== -->
    <!-- MIS PAGOS                                                   -->
    <!-- ========================================================== -->
    <?php
    $stmtAbonos = $pdo->prepare("
        SELECT a.* FROM abonos a
        WHERE a.profesor = ?
        ORDER BY a.fecha_abono DESC
    ");
    $stmtAbonos->execute([$_SESSION['usuario']]);
    $misAbonos = $stmtAbonos->fetchAll();
    $totalAbonado = array_sum(array_column($misAbonos, 'monto_abono'));
    $salarioBase = $profesor ? (float)$profesor['salario_base'] : 0;

    // Pendiente del mes actual
    $stmtPendiente = $pdo->prepare("
        SELECT COALESCE(SUM(monto_abono), 0) FROM abonos
        WHERE profesor = ? AND MONTH(fecha_abono) = ? AND YEAR(fecha_abono) = ?
    ");
    $stmtPendiente->execute([$_SESSION['usuario'], date('m'), date('Y')]);
    $abonadoMes = (float)$stmtPendiente->fetchColumn();
    $pendiente = max(0, $salarioBase - $abonadoMes);
    ?>

<!-- Modal Mis Pagos -->
<?php if ($salarioBase > 0): ?>
<div class="modal fade" id="modalMisPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-cash-stack"></i> Mis pagos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                        <small class="text-muted">Salario base</small>
                        <h5 class="mb-0">Gs <?= number_format($salarioBase, 0, ',', '.') ?></h5>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Abonado este mes</small>
                        <h5 class="mb-0 text-success">Gs <?= number_format($abonadoMes, 0, ',', '.') ?></h5>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Pendiente</small>
                        <h5 class="mb-0 <?= $pendiente > 0 ? 'text-danger' : 'text-success' ?>">Gs <?= number_format($pendiente, 0, ',', '.') ?></h5>
                    </div>
                </div>

                <?php if (!empty($misAbonos)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Monto</th>
                                    <th>Descripción</th>
                                    <th>Recibo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($misAbonos as $a): ?>
                                    <tr>
                                        <td><?= $a['id_abono'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($a['fecha_abono'])) ?></td>
                                        <td class="text-end">Gs <?= number_format($a['monto_abono'], 0, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($a['descripcion'] ?? '-') ?></td>
                                        <td>
                                            <a href="/evospace/secciones/recibo_profesor.php?id_abono=<?= $a['id_abono'] ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-file-pdf"></i> Recibo
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">No hay pagos registrados aún.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

    <div class="order-1 order-md-1">
    <?php if (count($tipos) > 1): ?>
        <div class="mb-3 p-3 bg-light rounded border">
            <strong class="small"><i class="bi bi-list-ul"></i> Ir a:</strong>
            <?php foreach ($tipos as $tipo): ?>
                <a href="#tipo-<?= urlencode($tipo) ?>" class="btn btn-outline-danger btn-sm ms-1"><?= htmlspecialchars($tipo) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cursos)): ?>
        <div class="alert alert-warning text-center py-4">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No tenés cursos con alumnos asignados.
        </div>
    <?php else: ?>
        <?php
        $grupo_actual = '';
        foreach ($cursos as $curso):
            if ($curso['tipo'] != $grupo_actual):
                if ($grupo_actual != ''): ?>
                    <hr class="my-4 border-2 border-danger">
                <?php endif; ?>
                <div id="tipo-<?= urlencode($curso['tipo']) ?>" class="scroll-mt-3"></div>
                <h4 class="mt-3 mb-3 text-secondary"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($curso['tipo']) ?></h4>
                <div class="row g-3">
            <?php
                $grupo_actual = $curso['tipo'];
            endif;
        ?>
            <div class="col-md-4 col-lg-3">
                <div class="card shadow h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?= htmlspecialchars($curso['nombre']) ?></h5>
                            <span class="badge bg-secondary"><?= $curso['total_alumnos'] ?> al.</span>
                        </div>
                        <?php
                        $stmtHoy = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presentes FROM asistencia WHERE id_curso = ? AND fecha = ?");
                        $stmtHoy->execute([$curso['id_curso'], $hoy]);
                        $hoyStats = $stmtHoy->fetch();
                        ?>
                        <?php if ($hoyStats && $hoyStats['total'] > 0): ?>
                            <p class="small mb-2">
                                <span class="text-success fw-bold"><?= (int)$hoyStats['presentes'] ?> presentes</span>
                                <span class="text-muted">/ <?= (int)$hoyStats['total'] ?> hoy</span>
                                <span class="badge bg-<?= (int)$hoyStats['presentes'] >= (int)$hoyStats['total'] * 0.8 ? 'success' : 'warning' ?> ms-1">
                                    <?= (int)$hoyStats['total'] > 0 ? round(((int)$hoyStats['presentes'] / (int)$hoyStats['total']) * 100) : 0 ?>%
                                </span>
                            </p>
                        <?php else: ?>
                            <p class="small text-muted mb-2">Sin registro hoy</p>
                        <?php endif; ?>
                        <div class="d-flex gap-1 mt-auto flex-wrap">
                            <button class="btn btn-success btn-sm flex-fill" onclick="abrirAsistencia(<?= $curso['id_curso'] ?>, '<?= htmlspecialchars($curso['nombre'], ENT_QUOTES) ?>')">
                                <i class="bi bi-clipboard-check"></i> Asistencia
                            </button>
                            <button class="btn btn-danger btn-sm flex-fill" onclick="abrirMensual(<?= $curso['id_curso'] ?>, '<?= htmlspecialchars($curso['nombre'], ENT_QUOTES) ?>')">
                                <i class="bi bi-calendar-month"></i> Mensual
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div><!-- /order cuesos -->
</div>

<!-- ========================================================== -->
<!-- MODAL: ASISTENCIA DEL DÍA                                    -->
<!-- ========================================================== -->
<div class="modal fade" id="modalAsistencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formAsistencia" onsubmit="guardarAsistencia(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF()) ?>">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> <span id="asistenciaTitulo"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="asistenciaBody">
                    <div class="text-center py-4"><div class="spinner-border text-success"></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="marcarTodosAsistencia(true)">Todos presentes</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="marcarTodosAsistencia(false)">Todos ausentes</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- MODAL: ASISTENCIA MENSUAL                                   -->
<!-- ========================================================== -->
<div class="modal fade" id="modalMensual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formMensual" onsubmit="guardarMensual(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generarTokenCSRF()) ?>">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-calendar-month"></i> <span id="mensualTitulo"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="mensualBody">
                    <div class="text-center py-4"><div class="spinner-border text-danger"></div></div>
                </div>
                <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                    <div class="d-flex gap-2 align-items-center">
                        <select id="mensualMes" class="form-select form-select-sm" style="width:auto" onchange="cargarMensual()"></select>
                        <select id="mensualAnio" class="form-select form-select-sm" style="width:100px" onchange="cargarMensual()"></select>
                        <a id="mensualExcel" href="#" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="marcarTodosMensual(true)">Todos presentes</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="marcarTodosMensual(false)">Todos ausentes</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-save"></i> Guardar mes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let asistenciaCursoId = 0;
let mensualCursoId = 0;

// ----------------------------------------------------------
// ASISTENCIA DEL DÍA
// ----------------------------------------------------------
function abrirAsistencia(idCurso, nombreCurso) {
    asistenciaCursoId = idCurso;
    document.getElementById('asistenciaTitulo').textContent = nombreCurso + ' - ' + new Date().toLocaleDateString('es-PY');
    document.getElementById('asistenciaBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';
    var modal = new bootstrap.Modal(document.getElementById('modalAsistencia'));
    modal.show();

    fetch('profesor.php?ajax=alumnos&id_curso=' + idCurso + '&fecha=' + new Date().toISOString().slice(0,10))
        .then(r => r.json())
        .then(data => {
            renderAsistencia(data);
        });
}

function renderAsistencia(data) {
    var html = '<div class="table-responsive"><table class="table table-hover table-sm mb-0"><thead class="table-light"><tr><th>#</th><th>Alumno</th><th class="text-center">Presente</th><th>Observaciones</th></tr></thead><tbody>';
    data.alumnos.forEach(function(a, i) {
        html += '<tr>' +
            '<td>' + (i+1) + '</td>' +
            '<td>' + escHtml(a.apellido + ' ' + a.nombre) + '</td>' +
            '<td class="text-center"><div class="form-check form-switch d-inline-block">' +
                '<input class="form-check-input" type="checkbox" name="estado[' + a.id_alumno + '][presente]" value="1" ' + (a.presente ? 'checked' : '') + ' onchange="actualizarBadge(this)">' +
                '<span class="badge bg-' + (a.presente ? 'success' : 'danger') + ' ms-1 badge-asistencia">' + (a.presente ? 'Presente' : 'Ausente') + '</span>' +
            '</div></td>' +
            '<td><input type="text" name="estado[' + a.id_alumno + '][observaciones]" class="form-control form-control-sm" placeholder="Observación" value="' + escHtml(a.observaciones) + '"></td>' +
        '</tr>';
    });
    html += '</tbody></table></div>';
    document.getElementById('asistenciaBody').innerHTML = html;
}

function guardarAsistencia(e) {
    e.preventDefault();
    var form = document.getElementById('formAsistencia');
    var data = new FormData(form);
    data.set('ajax', 'guardar_asistencia');
    data.set('id_curso', asistenciaCursoId);
    data.set('fecha', new Date().toISOString().slice(0,10));

    document.querySelector('#modalAsistencia .modal-footer .btn-success').disabled = true;
    document.querySelector('#modalAsistencia .modal-footer .btn-success').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

    fetch('profesor.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalAsistencia')).hide();
                location.reload();
            } else {
                alert('Error: ' + (res.error || 'desconocido'));
            }
        });
}

function actualizarBadge(cb) {
    var badge = cb.closest('tr').querySelector('.badge-asistencia');
    if (badge) {
        badge.textContent = cb.checked ? 'Presente' : 'Ausente';
        badge.className = 'badge bg-' + (cb.checked ? 'success' : 'danger') + ' ms-1 badge-asistencia';
    }
}

// ----------------------------------------------------------
// ASISTENCIA MENSUAL
// ----------------------------------------------------------
function abrirMensual(idCurso, nombreCurso) {
    mensualCursoId = idCurso;
    document.getElementById('mensualTitulo').textContent = nombreCurso;
    document.getElementById('mensualBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>';

    var m = new Date().getMonth() + 1;
    var a = new Date().getFullYear();

    var selMes = document.getElementById('mensualMes');
    selMes.innerHTML = '';
    var meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    meses.forEach(function(n, i) {
        var op = document.createElement('option');
        op.value = i + 1;
        op.textContent = n;
        if (i + 1 == m) op.selected = true;
        selMes.appendChild(op);
    });

    var selAnio = document.getElementById('mensualAnio');
    selAnio.innerHTML = '';
    for (var y = a - 2; y <= a + 1; y++) {
        var op = document.createElement('option');
        op.value = y;
        op.textContent = y;
        if (y == a) op.selected = true;
        selAnio.appendChild(op);
    }

    document.getElementById('mensualExcel').href = '/evospace/secciones/asistencia/exportar_excel_mensual.php?id_curso=' + idCurso + '&mes=' + m + '&anio=' + a;

    var modal = new bootstrap.Modal(document.getElementById('modalMensual'));
    modal.show();
    cargarMensual();
}

function cargarMensual() {
    var mes = document.getElementById('mensualMes').value;
    var anio = document.getElementById('mensualAnio').value;
    document.getElementById('mensualExcel').href = '/evospace/secciones/asistencia/exportar_excel_mensual.php?id_curso=' + mensualCursoId + '&mes=' + mes + '&anio=' + anio;

    document.getElementById('mensualBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>';

    fetch('profesor.php?ajax=mensual&id_curso=' + mensualCursoId + '&mes=' + mes + '&anio=' + anio)
        .then(r => r.json())
        .then(data => {
            renderMensual(data);
        });
}

function renderMensual(data) {
    var dias = data.dias;
    var html = '<div style="overflow-x:auto"><table class="table table-bordered table-sm table-hover mb-0">';
    html += '<thead class="table-light text-center"><tr><th style="min-width:120px;position:sticky;left:0;background:#fff;z-index:1">Alumno</th>';
    dias.forEach(function(d) {
        var diaSemana = new Date(data.anio, data.mes - 1, d).toLocaleDateString('es-PY', { weekday: 'short' });
        html += '<th style="min-width:35px">' + d + '<br><small class="text-muted">' + diaSemana + '</small></th>';
    });
    html += '</tr></thead><tbody>';

    data.alumnos.forEach(function(a) {
        var asistencias = data.asistencias[a.id_alumno] || {};
        html += '<tr><td style="position:sticky;left:0;background:#fff;z-index:1">' + escHtml(a.apellido + ' ' + a.nombre) + '</td>';
        dias.forEach(function(d) {
            var checked = asistencias[d] ? 'checked' : '';
            html += '<td class="text-center"><div class="form-check form-switch d-flex justify-content-center">' +
                '<input class="form-check-input" type="checkbox" name="estado[' + a.id_alumno + '][' + d + ']" value="1" ' + checked + '>' +
            '</div></td>';
        });
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    document.getElementById('mensualBody').innerHTML = html;
}

function guardarMensual(e) {
    e.preventDefault();
    var form = document.getElementById('formMensual');
    var data = new FormData(form);
    data.set('ajax', 'guardar_mensual');
    data.set('id_curso', mensualCursoId);
    data.set('mes', document.getElementById('mensualMes').value);
    data.set('anio', document.getElementById('mensualAnio').value);

    document.querySelector('#modalMensual .modal-footer .btn-danger').disabled = true;
    document.querySelector('#modalMensual .modal-footer .btn-danger').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

    fetch('profesor.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                cargarMensual();
                document.querySelector('#modalMensual .modal-footer .btn-danger').disabled = false;
                document.querySelector('#modalMensual .modal-footer .btn-danger').innerHTML = '<i class="bi bi-save"></i> Guardar mes';
            } else {
                alert('Error: ' + (res.error || 'desconocido'));
                document.querySelector('#modalMensual .modal-footer .btn-danger').disabled = false;
                document.querySelector('#modalMensual .modal-footer .btn-danger').innerHTML = '<i class="bi bi-save"></i> Guardar mes';
            }
        });
}

function marcarTodosMensual(estado) {
    var msg = estado ? 'Marcar todos los alumnos como presentes?' : 'Marcar todos los alumnos como ausentes?';
    if (!confirm(msg)) return;
    document.querySelectorAll('#formMensual input[type="checkbox"]').forEach(function(cb) {
        cb.checked = estado;
    });
}

function marcarTodosAsistencia(estado) {
    var msg = estado ? 'Marcar todos como presentes?' : 'Marcar todos como ausentes?';
    if (!confirm(msg)) return;
    document.querySelectorAll('#formAsistencia input[name$="[presente]"]').forEach(function(cb) {
        cb.checked = estado;
        actualizarBadge(cb);
    });
}

function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>

<?php include '../includes/footer.php'; ?>
