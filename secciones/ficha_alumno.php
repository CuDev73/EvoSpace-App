<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../config/db.php';

$mensaje = '';
$tipoMensaje = 'info';

$id_alumno = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_alumno) { echo '<div class="container mt-3"><div class="alert alert-danger">Alumno no especificado.</div></div>'; include '../includes/footer.php'; exit; }

$stmtAlumno = $pdo->prepare("
    SELECT a.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo, c.orden AS curso_orden, u.usuario AS padre_nombre, u.email AS padre_email
    FROM alumnos a
    JOIN cursos c ON a.id_curso = c.id_curso
    LEFT JOIN usuarios u ON a.id_padre = u.id_usuario
    WHERE a.id_alumno = ?
");
$stmtAlumno->execute([$id_alumno]);
$alumno = $stmtAlumno->fetch(PDO::FETCH_ASSOC);
if (!$alumno) { echo '<div class="container mt-3"><div class="alert alert-danger">Alumno no encontrado.</div></div>'; include '../includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'avanzar') {
    $siguiente = $pdo->prepare("SELECT id_curso, nombre, tipo FROM cursos WHERE tipo = ? AND orden = ? AND activo = 1");
    $siguiente->execute([$alumno['curso_tipo'], $alumno['curso_orden'] + 1]);
    $prox = $siguiente->fetch();
    if ($prox) {
        $stmt = $pdo->prepare("UPDATE alumnos SET id_curso = ? WHERE id_alumno = ?");
        $stmt->execute([$prox['id_curso'], $id_alumno]);
        header('Location: ficha_alumno.php?id=' . $id_alumno . '&ok=avanzado');
        exit;
    } else {
        header('Location: ficha_alumno.php?id=' . $id_alumno . '&err=ultimo');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'agregar_pago') {
    $fecha = $_POST['fecha'];
    $concepto = trim($_POST['concepto']);
    $cantidad = (int)$_POST['cantidad'];
    $monto = (float)$_POST['monto'];
    $recargo = (float)$_POST['recargo'];
    $total = (float)$_POST['total'];
    $metodo_pago = $_POST['metodo_pago'];
    $descripcion = trim($_POST['descripcion'] ?? '');

    $imagen = null;
    if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $nombre = 'pago_' . $id_alumno . '_' . uniqid() . '.' . $ext;
            $destino = __DIR__ . '/../uploads/pagos/' . $nombre;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $imagen = 'uploads/pagos/' . $nombre;
            }
        }
    }

    $sql = "INSERT INTO pagos (id_alumno, fecha, concepto, cantidad, monto, descuento, recargo, total, metodo_pago, descripcion, imagen)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([$id_alumno, $fecha, $concepto, $cantidad, $monto, $recargo, $total, $metodo_pago, $descripcion ?: null, $imagen]);
        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Pago registrado.';
        $tipoMensaje = 'success';
    } catch (PDOException $e) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
        $tipoMensaje = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'agregar_horas') {
    $horas = (float)$_POST['horas'];
    $descripcion = trim($_POST['descripcion_horas'] ?? '');
    $fecha_horas = $_POST['fecha_horas'] ?? date('Y-m-d');
    if ($horas > 0) {
        $stmt = $pdo->prepare("INSERT INTO horas_profesionales_log (id_alumno, horas, descripcion, fecha) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_alumno, $horas, $descripcion ?: null, $fecha_horas]);
        $stmt = $pdo->prepare("UPDATE alumnos SET horas_profesionales = (SELECT COALESCE(SUM(horas), 0) FROM horas_profesionales_log WHERE id_alumno = ?) WHERE id_alumno = ?");
        $stmt->execute([$id_alumno, $id_alumno]);
        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> ' . number_format($horas, 1) . ' horas profesionales registradas.';
        $tipoMensaje = 'success';
    } else {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Las horas deben ser mayores a 0.';
        $tipoMensaje = 'danger';
    }
}

include '../includes/header.php';
include '../includes/navbar.php';

$horarios = $pdo->prepare("
    SELECT h.*, u.usuario AS profe_nombre
    FROM horarios h
    LEFT JOIN profesores p ON h.id_profesor = p.id_profesor
    LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
    WHERE h.id_curso = ? ORDER BY h.hora_inicio
");
$horarios->execute([$alumno['id_curso']]);
$horarios = $horarios->fetchAll(PDO::FETCH_ASSOC);

$pagos = $pdo->prepare("SELECT * FROM pagos WHERE id_alumno = ? ORDER BY fecha DESC");
$pagos->execute([$id_alumno]);
$pagos = $pagos->fetchAll(PDO::FETCH_ASSOC);

$asistencia = $pdo->prepare("SELECT a.*, c.nombre AS curso_nombre FROM asistencia a JOIN cursos c ON a.id_curso = c.id_curso WHERE a.id_alumno = ? ORDER BY a.fecha DESC LIMIT 30");
$asistencia->execute([$id_alumno]);
$asistencia = $asistencia->fetchAll(PDO::FETCH_ASSOC);

$total_pagado = array_sum(array_column($pagos, 'total'));
$ultimo_pago = $pagos[0] ?? null;

$config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
$porcentajeBeca = (float)($config['porcentaje_beca'] ?? 45.45);
$recargoPorDia = (float)($config['recargo_por_dia'] ?? 1000);
$diaLimite = (int)($config['dia_limite_pago'] ?? 10);

$precio_cuota = $pdo->prepare("SELECT precio FROM precios WHERE id_curso = ? AND concepto = 'cuota'");
$precio_cuota->execute([$alumno['id_curso']]);
$cuota_base = (float)$precio_cuota->fetchColumn() ?: 0;
$cuota_valor = $alumno['becado'] ? round($cuota_base * ($porcentajeBeca / 100) / 1000) * 1000 : round($cuota_base / 1000) * 1000;

$precios_curso = $pdo->prepare("SELECT concepto, precio FROM precios WHERE id_curso = ?");
$precios_curso->execute([$alumno['id_curso']]);
$precios_json = json_encode($precios_curso->fetchAll(PDO::FETCH_KEY_PAIR));

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE id_alumno = ? AND concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
$stmt->execute([$id_alumno, date('m'), date('Y')]);
$pagado_este_mes = (float)$stmt->fetchColumn();
$dia_hoy = (int)date('j');
$recargo = ($dia_hoy > $diaLimite) ? ($dia_hoy - $diaLimite) * $recargoPorDia : 0;
$deuda = max(0, ($cuota_valor + $recargo) - $pagado_este_mes);

$limiteHoras = (float)($config['limite_horas_profesionales'] ?? 200);
$stmtHoras = $pdo->prepare("SELECT COALESCE(SUM(horas), 0) FROM horas_profesionales_log WHERE id_alumno = ?");
$stmtHoras->execute([$id_alumno]);
$totalHoras = (float)$stmtHoras->fetchColumn();
$stmtHorasLog = $pdo->prepare("SELECT * FROM horas_profesionales_log WHERE id_alumno = ? ORDER BY fecha DESC");
$stmtHorasLog->execute([$id_alumno]);
$horasLog = $stmtHorasLog->fetchAll(PDO::FETCH_ASSOC);

$dias = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

$siguiente_curso = $pdo->prepare("SELECT id_curso, nombre, tipo FROM cursos WHERE tipo = ? AND orden = ? AND activo = 1");
$siguiente_curso->execute([$alumno['curso_tipo'], $alumno['curso_orden'] + 1]);
$siguiente_curso = $siguiente_curso->fetch(PDO::FETCH_ASSOC);
?>
<div class="container mt-3 pb-4">
    <a href="alumnos.php" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left"></i> Volver a alumnos</a>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show"><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success alert-dismissible fade show">Alumno avanzado al curso siguiente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif (isset($_GET['err']) && $_GET['err'] === 'ultimo'): ?>
        <div class="alert alert-warning alert-dismissible fade show">Este alumno ya está en el último curso de este nivel.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></h5>
            <div>
                <?php if ($alumno['becado']): ?><span class="badge bg-warning text-dark me-1">Descuento</span><?php endif; ?>
                <span class="badge bg-<?= $alumno['activo'] ? 'success' : 'secondary' ?>"><?= $alumno['activo'] ? 'Activo' : 'Inactivo' ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <small class="text-muted">Curso</small>
                    <p class="fw-bold mb-0"><?= htmlspecialchars($alumno['curso_tipo'] . ' - ' . $alumno['curso_nombre']) ?></p>
                    <?php if ($siguiente_curso): ?>
                        <form method="POST" class="mt-1" onsubmit="return confirm('¿Avanzar a <?= htmlspecialchars($siguiente_curso['nombre']) ?>?')">
                            <input type="hidden" name="accion" value="avanzar">
                            <button class="btn btn-sm btn-outline-success py-0"><i class="bi bi-arrow-right-circle"></i> Avanzar a <?= htmlspecialchars($siguiente_curso['nombre']) ?></button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-secondary mt-1">Último curso</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Cédula</small>
                    <p class="mb-0"><?= htmlspecialchars($alumno['ci']) ?></p>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Teléfono</small>
                    <p class="mb-0"><?= htmlspecialchars($alumno['telefono'] ?: '-') ?></p>
                </div>
                <div class="col-md-2">
                    <small class="text-muted">Año ingreso</small>
                    <p class="mb-0"><?= $alumno['anio_ingreso'] ?></p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">Padre/Madre</small>
                    <p class="mb-0"><?= htmlspecialchars($alumno['padre_nombre'] ?: 'Sin asignar') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php if ($alumno['curso_tipo'] === 'Superior'): ?>
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <small class="text-muted">Horas profesionales</small>
                            <h4 class="mb-0"><?= number_format($totalHoras, 1) ?> / <?= number_format($limiteHoras, 0) ?> h</h4>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarHoras">
                            <i class="bi bi-plus-circle"></i> Agregar horas
                        </button>
                    </div>
                    <div class="progress" style="height:8px;">
                        <?php $pct = min(100, round($totalHoras / $limiteHoras * 100)); ?>
                        <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : ($pct >= 75 ? 'warning' : 'primary') ?>"
                             style="width:<?= $pct ?>%"><?= $pct ?>%</div>
                    </div>
                    <?php if (!empty($horasLog)): ?>
                        <div class="mt-2 small">
                            <?php foreach (array_slice($horasLog, 0, 5) as $h): ?>
                                <span class="badge bg-light text-dark border me-1 mb-1">
                                    +<?= number_format($h['horas'], 1) ?>h
                                    <?= date('d/m/Y', strtotime($h['fecha'])) ?>
                                    <?php if ($h['descripcion']): ?>- <?= htmlspecialchars($h['descripcion']) ?><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (count($horasLog) > 5): ?>
                                <span class="text-muted">+<?= count($horasLog) - 5 ?> más</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm text-center">
                <div class="card-body py-2">
                    <small class="text-muted">Total pagado</small>
                    <h4 class="mb-0 text-success"><?= number_format($total_pagado, 0, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <?php
        $deudaCantina = 0;
        if (file_exists(__DIR__ . '/cantina/funciones.php')) {
            require_once __DIR__ . '/cantina/funciones.php';
            $deudaCantina = obtenerDeudaAlumnoCantina($pdo, $id_alumno);
        }
        ?>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm text-center <?= $deudaCantina > 0 ? 'border-warning' : '' ?>">
                <div class="card-body py-2">
                    <small class="text-muted">Cantina</small>
                    <h4 class="mb-0 text-<?= $deudaCantina > 0 ? 'warning' : 'secondary' ?>"><?= $deudaCantina > 0 ? number_format($deudaCantina, 0, ',', '.') : 'Al día' ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm text-center">
                <div class="card-body py-2">
                    <small class="text-muted">Cuota este mes</small>
                    <h4 class="mb-0"><?= number_format($cuota_valor, 0, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm text-center">
                <div class="card-body py-2">
                    <small class="text-muted">Deuda actual</small>
                    <h4 class="mb-0 text-<?= $deuda > 0 ? 'danger' : 'success' ?>"><?= $deuda > 0 ? number_format($deuda, 0, ',', '.') : 'Al día' ?></h4>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($horarios)): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white"><i class="bi bi-calendar-week me-1"></i> Horarios</div>
        <div class="card-body py-2">
            <div class="row g-2">
                <?php foreach ($horarios as $h): 
                    $diasArray = explode(',', $h['dia_semana']); ?>
                    <div class="col-auto">
                        <span class="badge bg-light text-dark border p-2">
                            <?php foreach ($diasArray as $d): ?>
                                <span class="badge bg-secondary me-1"><?= $dias[(int)trim($d)] ?? '?' ?></span>
                            <?php endforeach; ?>
                            <?= substr($h['hora_inicio'], 0, 5) ?>-<?= substr($h['hora_fin'], 0, 5) ?>
                            <?php if ($h['profe_nombre']): ?> | <?= htmlspecialchars($h['profe_nombre']) ?><?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($asistencia): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white"><i class="bi bi-clipboard-check me-1"></i> Asistencia (últimos 30 registros)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Fecha</th><th>Curso</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php $presentes = 0; foreach ($asistencia as $a): $presentes += $a['presente']; ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
                                <td><?= htmlspecialchars($a['curso_nombre']) ?></td>
                                <td><span class="badge bg-<?= $a['presente'] ? 'success' : 'danger' ?>"><?= $a['presente'] ? 'Presente' : 'Ausente' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light">
            <?php $total_asistencia = count($asistencia); ?>
            Asistencia: <?= $presentes ?>/<?= $total_asistencia ?> (<?= $total_asistencia > 0 ? round($presentes/$total_asistencia*100) : 0 ?>%)
        </div>
    </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-cash-stack me-1"></i> Historial de pagos</span>
            <div>
                <button class="btn btn-sm btn-light me-1" data-bs-toggle="modal" data-bs-target="#modalNuevoPago"><i class="bi bi-plus-circle"></i> Nuevo pago</button>
                <span class="badge bg-light text-dark">Total: <?= number_format($total_pagado, 0, ',', '.') ?> Gs</span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pagos)): ?>
                <div class="p-3 text-muted">Sin pagos registrados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light"><tr><th>Fecha</th><th>Concepto</th><th>Monto</th><th>Recargo</th><th>Total</th><th>Método</th><th>Nota</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($pagos as $p): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($p['concepto'])) ?></td>
                                    <td><?= number_format($p['monto'], 0, ',', '.') ?></td>
                                    <td><?= $p['recargo'] > 0 ? number_format($p['recargo'], 0, ',', '.') : '-' ?></td>
                                    <td class="fw-bold"><?= number_format($p['total'], 0, ',', '.') ?> Gs</td>
                                    <td><?= $p['metodo_pago'] ?></td>
                                    <td>
                                        <?php if (!empty($p['descripcion'])): ?>
                                            <span class="small text-muted" title="<?= htmlspecialchars($p['descripcion'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= mb_strimwidth(htmlspecialchars($p['descripcion'], ENT_QUOTES, 'UTF-8'), 0, 25, '...') ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['imagen'])): ?>
                                            <a href="/evospace/<?= $p['imagen'] ?>" target="_blank" class="text-decoration-none ms-1" title="Ver comprobante"><i class="bi bi-paperclip"></i></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="recibo.php?id_pago=<?= $p['id_pago'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Descargar recibo"><i class="bi bi-file-pdf"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Modal Nuevo Pago -->
<div class="modal fade" id="modalNuevoPago" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Nuevo Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ficha_alumno.php?id=<?= $id_alumno ?>" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar_pago">
                    <input type="hidden" name="id_alumno" value="<?= $id_alumno ?>">
                    <div id="infoRecargo" class="alert alert-warning py-1 px-2 mb-2 small" style="display:none;"></div>
                    <div id="infoEntradas" class="alert alert-info py-1 px-2 mb-2 small" style="display:none;"><i class="bi bi-info-circle"></i> Entradas = rifas/boletos entregados al alumno para vender</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha *</label>
                            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Concepto *</label>
                            <select name="concepto" class="form-select" required>
                                <option value="cuota">Cuota</option>
                                <option value="matrícula">Matrícula</option>
                                <option value="vestuarios">Vestuarios</option>
                                <option value="entradas">Entradas (rifas)</option>
                                <option value="folleto">Folleto</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Monto</label>
                            <input type="number" name="monto" class="form-control" value="<?= (int)$cuota_valor ?>" required data-moneda>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Recargo</label>
                            <input type="number" step="0.01" name="recargo" id="inputRecargo" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total *</label>
                            <input type="number" name="total" class="form-control" required data-moneda>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Método *</label>
                            <select name="metodo_pago" class="form-select" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Fiado">Fiado</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción / Nota</label>
                            <textarea name="descripcion" class="form-control" rows="2" placeholder="Detalles del pago, referencia, observaciones..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Comprobante / Imagen</label>
                            <input type="file" name="imagen" class="form-control form-control-sm" accept="image/*">
                            <small class="text-muted">JPG, PNG, GIF o WebP</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check-lg"></i> Registrar pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Horas Profesionales -->
<div class="modal fade" id="modalAgregarHoras" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Agregar Horas Profesionales</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ficha_alumno.php?id=<?= $id_alumno ?>">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar_horas">
                    <div class="mb-3">
                        <label class="form-label">Horas *</label>
                        <input type="number" step="0.5" name="horas" class="form-control"
                               value="1" min="0.5" max="999" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha_horas" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion_horas" class="form-control" rows="2"
                                  placeholder="Ej: Ensayo de obra, presentación, taller..."></textarea>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle"></i>
                        Límite: <?= number_format($limiteHoras, 0) ?>h &middot;
                        Actual: <?= number_format($totalHoras, 1) ?>h &middot;
                        Restante: <?= number_format(max(0, $limiteHoras - $totalHoras), 1) ?>h
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Agregar horas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const diaLimite = <?= $diaLimite ?>;
const recargoPorDia = <?= $recargoPorDia ?>;
const precios = <?= $precios_json ?>;
const porcBeca = <?= $porcentajeBeca ?>;
const alumnoBecado = <?= $alumno['becado'] ? 1 : 0 ?>;

document.querySelector('#modalNuevoPago input[name="fecha"]')?.addEventListener('change', actualizarRecargo);
document.querySelector('#modalNuevoPago select[name="concepto"]')?.addEventListener('change', function() {
    actualizarMonto();
    actualizarRecargo();
});
document.querySelector('#modalNuevoPago input[name="monto"]')?.addEventListener('input', calcularTotalPago);
document.querySelector('#modalNuevoPago input[name="recargo"]')?.addEventListener('input', calcularTotalPago);
document.querySelector('#modalNuevoPago input[name="cantidad"]')?.addEventListener('input', calcularTotalPago);
document.querySelector('#modalNuevoPago')?.addEventListener('shown.bs.modal', function() {
    actualizarMonto();
    actualizarRecargo();
});

function actualizarMonto() {
    const concepto = document.querySelector('#modalNuevoPago select[name="concepto"]')?.value;
    const montoInput = document.querySelector('#modalNuevoPago input[name="monto"]');
    let precio = parseFloat(precios[concepto]) || 0;
    if (concepto === 'cuota') {
        precio = alumnoBecado ? Math.round(precio * (porcBeca / 100) / 1000) * 1000 : Math.round(precio / 1000) * 1000;
    }
    montoInput.value = precio;
    calcularTotalPago();
}

function actualizarRecargo() {
    const concepto = document.querySelector('#modalNuevoPago select[name="concepto"]')?.value;
    const fecha = document.querySelector('#modalNuevoPago input[name="fecha"]')?.value;
    const infoRecargo = document.getElementById('infoRecargo');
    const infoEntradas = document.getElementById('infoEntradas');
    const recargoInput = document.getElementById('inputRecargo');

    infoEntradas.style.display = concepto === 'entradas' ? 'block' : 'none';

    if (concepto === 'cuota' && fecha) {
        const dia = parseInt(fecha.split('-')[2]);
        if (dia > diaLimite) {
            const diasAtraso = dia - diaLimite;
            const recargo = diasAtraso * recargoPorDia;
            recargoInput.value = recargo;
            infoRecargo.style.display = 'block';
            infoRecargo.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + diasAtraso + ' día(s) de atraso → recargo de Gs ' + recargo.toLocaleString('es');
            calcularTotalPago();
            return;
        }
    }
    if (concepto !== 'cuota') recargoInput.value = 0;
    infoRecargo.style.display = 'none';
    calcularTotalPago();
}

function calcularTotalPago() {
    const m = parseFloat(document.querySelector('#modalNuevoPago input[name="monto"]')?.value || 0);
    const r = parseFloat(document.querySelector('#modalNuevoPago input[name="recargo"]')?.value || 0);
    const c = parseInt(document.querySelector('#modalNuevoPago input[name="cantidad"]')?.value || 1);
    document.querySelector('#modalNuevoPago input[name="total"]').value = (m * c + r).toFixed(2);
}
actualizarMonto();
actualizarRecargo();
</script>

<?php include '../includes/footer.php'; ?>
