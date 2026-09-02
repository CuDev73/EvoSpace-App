<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../config/db.php';
require_once '../helpers/functions.php';

$mensaje = '';
$tipoMensaje = 'info';

$id_alumno = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_alumno) { echo '<div class="container mt-3"><div class="alert alert-danger">Alumno no especificado.</div></div>'; include '../includes/footer.php'; exit; }

$stmtAlumno = $pdo->prepare("
    SELECT a.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo, c.orden AS curso_orden, u.usuario AS padre_nombre, u.email AS padre_email, u.dia_cobro AS padre_dia_cobro
    FROM alumnos a
    JOIN cursos c ON a.id_curso = c.id_curso
    LEFT JOIN usuarios u ON a.id_padre = u.id_usuario
    WHERE a.id_alumno = ?
");
$stmtAlumno->execute([$id_alumno]);
$alumno = $stmtAlumno->fetch(PDO::FETCH_ASSOC);
if (!$alumno) { echo '<div class="container mt-3"><div class="alert alert-danger">Alumno no encontrado.</div></div>'; include '../includes/footer.php'; exit; }

if (!verificarAccesoAlumno($pdo, $id_alumno)) { denegarAcceso(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'avanzar') {
    verificarTokenCSRF();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'enviar_recordatorio') {
    verificarTokenCSRF();
    $enviado = enviarRecordatorioDeudaAlumno($pdo, $id_alumno);
    if ($enviado) {
        $mensaje = '<i class="bi bi-check-circle-fill"></i> Recordatorio de deudas enviado al tutor/a.';
        $tipoMensaje = 'success';
    } else {
        $mensaje = '<i class="bi bi-info-circle-fill"></i> No se envió: el alumno no tiene deudas pendientes, no tiene tutor/a con email, o hubo un error de envío.';
        $tipoMensaje = 'warning';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'agregar_pago') {
    verificarTokenCSRF();
    $fecha = $_POST['fecha'];
    $concepto = trim($_POST['concepto']);
    $cantidad = (int)$_POST['cantidad'];
    $monto = (float)$_POST['monto'];
    $recargo = (float)$_POST['recargo'];
    $total = (float)$_POST['total'];
    $metodo_pago = $_POST['metodo_pago'];
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_evento = !empty($_POST['id_evento']) ? (int)$_POST['id_evento'] : null;
    $id_lote = !empty($_POST['id_lote']) ? (int)$_POST['id_lote'] : null;

    // Para entradas/rifas: tomar el evento del lote elegido
    if ($concepto === 'entradas' && $id_lote) {
        $stmtLote = $pdo->prepare("SELECT id_evento, cantidad, precio FROM entradas_curso WHERE id_entrada_curso = ? AND estado = 'activa'");
        $stmtLote->execute([$id_lote]);
        $lotePago = $stmtLote->fetch(PDO::FETCH_ASSOC);
        if ($lotePago) {
            $id_evento = $lotePago['id_evento'] ? (int)$lotePago['id_evento'] : null;
            if ($monto <= 0) $monto = (float)$lotePago['precio'];
        }
    }

    // Validación: matrícula única por año
    $error = null;
    if ($concepto === 'matrícula') {
        $stmtMat = $pdo->prepare("SELECT id_pago FROM pagos WHERE id_alumno = ? AND concepto = 'matrícula' AND YEAR(fecha) = ?");
        $stmtMat->execute([$id_alumno, substr($fecha, 0, 4)]);
        if ($stmtMat->fetchColumn()) {
            $error = 'Este alumno ya pagó la matrícula de ' . substr($fecha, 0, 4) . '. La matrícula es única por año.';
        }
    }

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

    if ($error) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> ' . $error;
        $tipoMensaje = 'danger';
    } else {
        $sql = "INSERT INTO pagos (id_alumno, id_evento, fecha, concepto, cantidad, monto, descuento, recargo, total, metodo_pago, descripcion, imagen)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$id_alumno, $id_evento, $fecha, $concepto, $cantidad, $monto, $recargo, $total, $metodo_pago, $descripcion ?: null, $imagen]);

            // Venta de rifas: descontar la cantidad vendida al alumno
            if ($concepto === 'entradas' && $id_lote) {
                $stmtDes = $pdo->prepare("UPDATE entradas_alumno SET cantidad = GREATEST(cantidad - ?, 0) WHERE id_entrada_curso = ? AND id_alumno = ?");
                $stmtDes->execute([$cantidad, $id_lote, $id_alumno]);
            }

            $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Pago registrado.';
            $tipoMensaje = 'success';
        } catch (PDOException $e) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
            $tipoMensaje = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'agregar_horas') {
    verificarTokenCSRF();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'guardar') {
    verificarTokenCSRF();
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $id_curso = (int)$_POST['id_curso'];
    $anio_ingreso = (int)$_POST['anio_ingreso'];
    $horas_profesionales = (float)($_POST['horas_profesionales'] ?? 0);
    $ci = trim($_POST['ci']);
    $telefono = trim($_POST['telefono']);
    $id_padre = !empty($_POST['id_padre']) ? (int)$_POST['id_padre'] : NULL;
    $becado = isset($_POST['becado']) ? 1 : 0;
    $dia_vencimiento = isset($_POST['dia_vencimiento']) && $_POST['dia_vencimiento'] !== '' ? (int)$_POST['dia_vencimiento'] : null;
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Verificar duplicados de CI (excepto este alumno)
    $stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE ci = ? AND id_alumno != ?");
    $stmt->execute([$ci, $id_alumno]);
    $dup = $stmt->fetch();
    if ($dup) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Ya existe un alumno con CI \'<strong>' . htmlspecialchars($ci) . '</strong>\' (' . htmlspecialchars($dup['nombre'] . ' ' . $dup['apellido']) . ').';
        $tipoMensaje = 'danger';
    } else {
        try {
            $sql = "UPDATE alumnos SET nombre=?, apellido=?, id_curso=?, anio_ingreso=?, horas_profesionales=?, ci=?, telefono=?, id_padre=?, becado=?, dia_vencimiento=?, activo=? WHERE id_alumno=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $apellido, $id_curso, $anio_ingreso, $horas_profesionales, $ci, $telefono, $id_padre, $becado, $dia_vencimiento, $activo, $id_alumno]);
            header('Location: ficha_alumno.php?id=' . $id_alumno . '&ok=editado');
            exit;
        } catch (PDOException $e) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
            $tipoMensaje = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'eliminar') {
    verificarTokenCSRF();
    $stmt = $pdo->prepare("DELETE FROM alumnos WHERE id_alumno = ?");
    if ($stmt->execute([$id_alumno])) {
        header('Location: alumnos.php?eliminado=' . $id_alumno);
        exit;
    } else {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al eliminar el alumno.';
        $tipoMensaje = 'danger';
    }
}

$mostrarVolver = true;
$volverUrl = 'alumnos.php';
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

$pagos = $pdo->prepare("
    SELECT p.*, e.titulo AS evento_titulo
    FROM pagos p
    LEFT JOIN eventos e ON p.id_evento = e.id_evento
    WHERE p.id_alumno = ?
    ORDER BY p.fecha DESC
");
$pagos->execute([$id_alumno]);
$pagos = $pagos->fetchAll(PDO::FETCH_ASSOC);

$stmtMat = $pdo->prepare("SELECT YEAR(fecha) AS anio, SUM(total) AS total FROM pagos WHERE id_alumno = ? AND concepto = 'matrícula' GROUP BY YEAR(fecha) ORDER BY anio DESC");
$stmtMat->execute([$id_alumno]);
$matricula_anual = $stmtMat->fetchAll(PDO::FETCH_KEY_PAIR);

$entradas_info = [];
if ($pdo->query("SHOW TABLES LIKE 'entradas_curso'")->fetchColumn()) {
    $stmtEn = $pdo->prepare("
        SELECT ec.id_entrada_curso, ec.cantidad AS lote_total, ec.precio, ec.id_evento, ec.fecha_asignacion,
               e.titulo AS evento_titulo,
               COALESCE((SELECT SUM(ea2.cantidad) FROM entradas_alumno ea2 WHERE ea2.id_entrada_curso = ec.id_entrada_curso), 0) AS lote_entregado,
               COALESCE((SELECT SUM(ea3.cantidad) FROM entradas_alumno ea3 WHERE ea3.id_entrada_curso = ec.id_entrada_curso AND ea3.id_alumno = a.id_alumno), 0) AS entregadas_alumno,
               COALESCE((SELECT MAX(ea4.cantidad_total) FROM entradas_alumno ea4 WHERE ea4.id_entrada_curso = ec.id_entrada_curso AND ea4.id_alumno = a.id_alumno), 0) AS asignadas_alumno
        FROM alumnos a
        JOIN entradas_curso ec ON ec.id_curso = a.id_curso AND ec.estado = 'activa'
        LEFT JOIN eventos e ON ec.id_evento = e.id_evento
        WHERE a.id_alumno = ?
        ORDER BY ec.fecha_asignacion DESC
    ");
    $stmtEn->execute([$id_alumno]);
    $entradas_info = $stmtEn->fetchAll(PDO::FETCH_ASSOC);
}

$eventos = $pdo->query("SELECT id_evento, titulo, fecha FROM eventos ORDER BY fecha DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

$asistencia = $pdo->prepare("SELECT a.*, c.nombre AS curso_nombre FROM asistencia a JOIN cursos c ON a.id_curso = c.id_curso WHERE a.id_alumno = ? ORDER BY a.fecha DESC LIMIT 30");
$asistencia->execute([$id_alumno]);
$asistencia = $asistencia->fetchAll(PDO::FETCH_ASSOC);

$total_pagado = array_sum(array_column($pagos, 'total'));
$ultimo_pago = $pagos[0] ?? null;

$config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
$tutores = $pdo->query("SELECT id_usuario, usuario, email FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY usuario")->fetchAll(PDO::FETCH_ASSOC);
$porcentajeBeca = (float)($config['porcentaje_beca'] ?? 50.0);
$recargoPorDia = (float)($config['recargo_por_dia'] ?? 1000);
$diaLimite = (int)($config['dia_limite_pago'] ?? 10);
$diasGracia = (int)($config['dias_gracia_pago'] ?? 10);

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
$diaCobroTutor = (int)($alumno['padre_dia_cobro'] ?? 0);
$diaVenc = (int)($alumno['dia_vencimiento'] ?? ($diaCobroTutor >= 1 && $diaCobroTutor <= 31 ? $diaCobroTutor : $diaLimite));
$vencimiento = $diaVenc + $diasGracia;
$recargo = ($dia_hoy > $vencimiento) ? ($dia_hoy - $vencimiento) * $recargoPorDia : 0;
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

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show"><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'editado'): ?>
        <div class="alert alert-success alert-dismissible fade show">Alumno actualizado correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif (isset($_GET['ok'])): ?>
        <div class="alert alert-success alert-dismissible fade show">Alumno avanzado al curso siguiente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif (isset($_GET['err']) && $_GET['err'] === 'ultimo'): ?>
        <div class="alert alert-warning alert-dismissible fade show">Este alumno ya está en el último curso de este nivel.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoPago"><i class="bi bi-cash-coin me-1"></i>Registrar pago</button>
                <button type="button" class="btn btn-light btn-sm" onclick="editarAlumnoFicha()"><i class="bi bi-pencil-square me-1"></i>Editar</button>
                <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold btn-recordatorio" data-bs-toggle="modal" data-bs-target="#modalRecordatorio"><i class="bi bi-bell-fill me-1"></i>Recordatorio</button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalEliminar"><i class="bi bi-trash-fill me-1"></i>Eliminar</button>
                <?php if ($alumno['becado']): ?><span class="badge bg-warning text-dark me-1" title="Descuento sobre la cuota">Descuento</span><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="flex-fill" style="min-width:0;">
                            <small class="text-muted">Curso</small>
                            <p class="fw-bold mb-0" style="overflow-wrap:anywhere;"><?= htmlspecialchars($alumno['curso_tipo'] . ' - ' . $alumno['curso_nombre']) ?></p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <?php if ($siguiente_curso): ?>
                                <form method="POST" class="mb-0" onsubmit="return confirm('¿Avanzar a <?= htmlspecialchars($siguiente_curso['nombre']) ?>?')">
                                    <?= campoCSRF() ?>
                                    <input type="hidden" name="accion" value="avanzar">
                                    <button class="btn btn-sm btn-outline-success py-0"><i class="bi bi-arrow-right-circle"></i> Avanzar a <?= htmlspecialchars($siguiente_curso['nombre']) ?></button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-secondary">Último curso</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted">Cédula</small>
                    <p class="mb-0"><?= htmlspecialchars($alumno['ci']) ?></p>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted">Teléfono</small>
                    <p class="mb-0"><?= htmlspecialchars($alumno['telefono'] ?: '—') ?></p>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted">Año ingreso</small>
                    <p class="mb-0"><?= $alumno['anio_ingreso'] ?></p>
                </div>
                <div class="col-6 col-md-3">
                    <small class="text-muted">Tutor/a</small>
                    <p class="mb-0" style="overflow-wrap:anywhere;"><?= htmlspecialchars($alumno['padre_nombre'] ?: '—') ?>
                        <?php if ($alumno['padre_dia_cobro']): ?>
                            <span class="badge bg-light text-dark border ms-1" title="Día de cobro de la cuota">Cobra día <?= (int)$alumno['padre_dia_cobro'] ?></span>
                        <?php endif; ?>
                    </p>
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
        <div class="col-6 col-md">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body py-2 d-flex flex-column justify-content-center" style="min-height:110px;">
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
        <div class="col-6 col-md">
            <div class="card shadow-sm text-center h-100 <?= $deudaCantina > 0 ? 'border-warning' : '' ?>">
                <div class="card-body py-2 d-flex flex-column justify-content-center" style="min-height:110px;">
                    <small class="text-muted">Cantina</small>
                    <h4 class="mb-0 text-<?= $deudaCantina > 0 ? 'warning' : 'secondary' ?>"><?= $deudaCantina > 0 ? number_format($deudaCantina, 0, ',', '.') : 'Al día' ?></h4>
                    <?php if ($deudaCantina > 0): ?>
                        <button class="btn btn-sm btn-warning mt-1" data-bs-toggle="modal" data-bs-target="#modalPagoCantina"><i class="bi bi-cash-coin me-1"></i> Cobrar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $totalRestantes = (int)array_sum(array_column($entradas_info, 'entregadas_alumno'));
        $totalAsignadas = (int)array_sum(array_column($entradas_info, 'asignadas_alumno'));
        $totalVendidas = max(0, $totalAsignadas - $totalRestantes);
        ?>
        <div class="col-6 col-md">
            <div class="card shadow-sm text-center h-100 <?= $totalRestantes > 0 ? 'border-warning' : '' ?>">
                <div class="card-body py-2 d-flex flex-column justify-content-center" style="min-height:110px;">
                    <small class="text-muted">Rifas / entradas</small>
                    <h4 class="mb-0 text-<?= $totalRestantes > 0 ? 'warning' : ($totalAsignadas > 0 ? 'success' : 'secondary') ?>"><?= $totalAsignadas > 0 ? $totalVendidas . '/' . $totalAsignadas : 'Al día' ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body py-2 d-flex flex-column justify-content-center" style="min-height:110px;">
                    <small class="text-muted">Cuota este mes</small>
                    <h4 class="mb-0"><?= number_format($cuota_valor, 0, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card shadow-sm text-center h-100 <?= $deuda > 0 ? 'border-danger' : '' ?>">
                <div class="card-body py-2 d-flex flex-column justify-content-center" style="min-height:110px;">
                    <small class="text-muted">Deuda actual</small>
                    <h4 class="mb-0 text-<?= $deuda > 0 ? 'danger' : 'success' ?>"><?= $deuda > 0 ? number_format($deuda, 0, ',', '.') : 'Al día' ?></h4>
                    <?php if ($deuda > 0): ?>
                        <small class="text-muted d-block">Incluye recargo de Gs <?= number_format($recargo, 0, ',', '.') ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($horarios)): ?>
    <div class="card shadow mb-4">
        <div class="card-header bg-white pt-3 pb-0 border-0"><h5 class="mb-0 fs-6 fw-semibold"><i class="bi bi-calendar-week me-1 text-secondary"></i> Horarios</h5></div>
        <div class="card-body pt-2">
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
        <div class="card-header bg-white pt-3 pb-0 border-0"><h5 class="mb-0 fs-6 fw-semibold"><i class="bi bi-clipboard-check me-1 text-secondary"></i> Asistencia (últimos 30 registros)</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Fecha</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php $presentes = 0; foreach ($asistencia as $a): $presentes += $a['presente']; ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
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
        <div class="card-header bg-white pt-3 pb-0 border-0">
            <h5 class="mb-0 fs-6 fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cash-stack me-1 text-secondary"></i> Historial de pagos</span>
                <span class="badge bg-light border text-dark fw-normal">Total: <?= number_format($total_pagado, 0, ',', '.') ?> Gs</span>
            </h5>
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
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span><?= htmlspecialchars(ucfirst($p['concepto'])) ?></span>
                                            <?php if (!empty($p['evento_titulo'])): ?>
                                                <span class="badge bg-light text-dark border" title="Evento asociado"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($p['evento_titulo']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
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
<!-- Modal Editar Alumno -->
<div class="modal fade" id="modalEditarAlumno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Editar Alumno</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ficha_alumno.php?id=<?= $id_alumno ?>">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_alumno" value="<?= $id_alumno ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="f_nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" id="f_apellido" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Curso *</label>
                            <input type="hidden" name="id_curso" id="f_id_curso" value="">
                            <button type="button" class="btn btn-outline-danger w-100 d-flex justify-content-between align-items-center py-2 border-2" onclick="cursoPickerAbrir('f_id_curso','f_lblCurso')">
                                <span id="f_lblCurso"><i class="bi bi-book me-1"></i> Seleccionar curso...</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Año de ingreso *</label>
                            <input type="number" name="anio_ingreso" id="f_anio_ingreso" class="form-control" required min="2000" max="2099">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Horas profesionales</label>
                            <input type="number" step="0.01" name="horas_profesionales" id="f_horas_profesionales" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="ci" id="f_ci" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="f_telefono" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tutor/a</label>
                            <div style="position:relative;">
                                <input type="text" id="f_buscarTutor" class="form-control mb-1" placeholder="Buscar tutor/a por nombre o email..." autocomplete="off">
                                <input type="hidden" name="id_padre" id="f_id_padre" value="">
                                <div id="f_listaTutores" class="list-group" style="position:absolute;z-index:1000;display:none;max-height:180px;overflow-y:auto;width:100%;top:100%;left:0;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="becado" id="f_becado" class="form-check-input" value="1">
                                <label class="form-check-label" for="f_becado">Descuento</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Día de vencimiento de cuota</label>
                            <input type="number" name="dia_vencimiento" id="f_dia_vencimiento" class="form-control" min="1" max="31" placeholder="Ej: 10">
                            <small class="text-muted">Vacío = usa el día de cobro del tutor/a o la config general.</small>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="activo" id="f_activo" class="form-check-input" checked>
                                <label class="form-check-label" for="f_activo">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Recordatorio -->
<div class="modal fade" id="modalRecordatorio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-bell-fill me-2"></i>Enviar recordatorio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">¿Enviar recordatorio de deudas al tutor/a de <strong><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></strong>?</p>
                <small class="text-muted">Se enviará un correo con el resumen de deudas del alumno. Solo se envía si hay deuda y el tutor/a tiene email cargado.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" class="d-inline">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="enviar_recordatorio">
                    <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-send-fill me-1"></i>Sí, enviar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trash-fill me-2"></i>Eliminar alumno</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">¿Seguro que deseas eliminar a <strong><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></strong>?</p>
                <small class="text-muted">Se borrarán también sus registros asociados (pagos, asistencias, etc.). Esta acción no se puede deshacer.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" class="d-inline">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash-fill me-1"></i>Eliminar definitivamente</button>
                </form>
            </div>
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
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="agregar_pago">
                    <input type="hidden" name="id_alumno" value="<?= $id_alumno ?>">
                    <div id="infoRecargo" class="alert alert-warning py-1 px-2 mb-2 small" style="display:none;"></div>
                    <div id="infoEntradas" class="alert alert-info py-1 px-2 mb-2 small" style="display:none;"><i class="bi bi-info-circle"></i> Entradas = rifas/boletos entregados al alumno para vender; registra aquí el monto de lo vendido.</div>
                    <div id="infoMatricula" class="alert alert-success py-1 px-2 mb-2 small" style="display:none;"><i class="bi bi-mortarboard"></i> La matrícula se registra una sola vez por año.</div>
                    <div class="small text-muted mb-2"><i class="bi bi-calendar-event"></i> Vencimiento de cuota: día <?= $diaVenc ?> + <?= $diasGracia ?> días de gracia (vence el día <?= $vencimiento ?>).</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha *</label>
                            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Concepto *</label>
                            <select name="concepto" class="form-select" required>
                                <option value="cuota">Cuota</option>
                                <option value="folleto">Folleto</option>
                                <option value="entradas">Entradas (rifas)</option>
                                <option value="matrícula">Matrícula</option>
                                <option value="vestuarios">Vestuarios</option>
                            </select>
                        </div>
                        <div class="col-md-12" id="campoEvento" style="display:none;">
                            <label class="form-label">Evento (vestuario) *</label>
                            <select name="id_evento" class="form-select">
                                <option value="">-- Seleccionar evento --</option>
                                <?php foreach ($eventos as $ev): ?>
                                    <option value="<?= (int)$ev['id_evento'] ?>"><?= htmlspecialchars($ev['titulo']) ?> (<?= date('d/m/Y', strtotime($ev['fecha'])) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12" id="campoLotes" style="display:none;">
                            <label class="form-label">Rifa / lote *</label>
                            <select name="id_lote" id="selectLote" class="form-select">
                                <option value="">-- Seleccionar rifa --</option>
                                <?php foreach ($entradas_info as $en): ?>
                                    <?php $restantes = (int)$en['entregadas_alumno']; ?>
                                    <option value="<?= (int)$en['id_entrada_curso'] ?>"
                                            data-precio="<?= (float)$en['precio'] ?>"
                                            data-max="<?= $restantes ?>">
                                        <?= htmlspecialchars($en['evento_titulo'] ?? 'General') ?> — <?= number_format((float)$en['precio'], 0, ',', '.') ?> Gs/ud (distribuida)
                                    </option>
                                <?php endforeach; ?>
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
                    <?= campoCSRF() ?>
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
const diaVenc = <?= $diaVenc ?>;
const vencimiento = <?= $vencimiento ?>;
const recargoPorDia = <?= $recargoPorDia ?>;
const precios = <?= $precios_json ?>;
const porcBeca = <?= $porcentajeBeca ?>;
const alumnoBecado = <?= $alumno['becado'] ? 1 : 0 ?>;

document.querySelector('#modalNuevoPago input[name="fecha"]')?.addEventListener('change', actualizarRecargo);
document.querySelector('#modalNuevoPago select[name="concepto"]')?.addEventListener('change', function() {
    actualizarMonto();
    actualizarRecargo();
});
document.querySelector('#selectLote')?.addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    if (opt && opt.dataset.precio) {
        document.querySelector('#modalNuevoPago input[name="monto"]').value = opt.dataset.precio;
        document.querySelector('#modalNuevoPago input[name="cantidad"]').value = '1';
        document.querySelector('#modalNuevoPago input[name="cantidad"]').max = opt.dataset.max;
    }
    calcularTotalPago();
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
    if (concepto === 'entradas') {
        const loteSel = document.querySelector('#selectLote');
        const opt = loteSel?.selectedOptions?.[0];
        if (opt && opt.dataset.precio) {
            montoInput.value = opt.dataset.precio;
        } else {
            montoInput.value = parseFloat(precios['entradas']) || 0;
        }
        calcularTotalPago();
        return;
    }
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
    const infoMatricula = document.getElementById('infoMatricula');
    const campoEvento = document.getElementById('campoEvento');
    const selectEvento = document.querySelector('#campoEvento select[name="id_evento"]');
    const campoLotes = document.getElementById('campoLotes');
    const selectLote = document.getElementById('selectLote');
    const recargoInput = document.getElementById('inputRecargo');

    infoEntradas.style.display = concepto === 'entradas' ? 'block' : 'none';
    infoMatricula.style.display = concepto === 'matrícula' ? 'block' : 'none';
    campoEvento.style.display = concepto === 'vestuarios' ? 'block' : 'none';
    campoLotes.style.display = concepto === 'entradas' ? 'block' : 'none';
    if (selectEvento) {
        selectEvento.required = concepto === 'vestuarios';
        if (campoEvento.style.display === 'none') selectEvento.value = '';
    }
    if (selectLote) {
        selectLote.required = concepto === 'entradas';
        if (campoLotes.style.display === 'none') selectLote.value = '';
    }

    if (concepto === 'cuota' && fecha) {
        const dia = parseInt(fecha.split('-')[2]);
        if (dia > vencimiento) {
            const diasAtraso = dia - vencimiento;
            const recargo = diasAtraso * recargoPorDia;
            recargoInput.value = recargo;
            infoRecargo.style.display = 'block';
            infoRecargo.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + diasAtraso + ' día(s) de atraso (venc. día ' + vencimiento + ') → recargo de Gs ' + recargo.toLocaleString('es');
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

const FICHA_ALUMNO = <?= json_encode($alumno, JSON_UNESCAPED_UNICODE) ?>;
const FICHA_TUTORES = <?= json_encode(array_map(function($t){ return ['id'=>$t['id_usuario'],'usuario'=>$t['usuario'],'email'=>$t['email']]; }, $tutores), JSON_UNESCAPED_UNICODE) ?>;

function editarAlumnoFicha() {
    const a = FICHA_ALUMNO;
    document.getElementById('f_nombre').value = a.nombre;
    document.getElementById('f_apellido').value = a.apellido;
    document.getElementById('f_id_curso').value = a.id_curso;
    document.getElementById('f_lblCurso').textContent = cursoPickerNombre(a.id_curso) || 'Seleccionar curso...';
    document.getElementById('f_anio_ingreso').value = a.anio_ingreso;
    document.getElementById('f_horas_profesionales').value = a.horas_profesionales || 0;
    document.getElementById('f_ci').value = a.ci;
    document.getElementById('f_telefono').value = a.telefono || '';
    document.getElementById('f_id_padre').value = a.id_padre || '';
    const tutorMatch = FICHA_TUTORES.find(t => t.id == a.id_padre);
    document.getElementById('f_buscarTutor').value = tutorMatch ? tutorMatch.usuario : '';
    document.getElementById('f_becado').checked = (a.becado == 1);
    document.getElementById('f_dia_vencimiento').value = a.dia_vencimiento || '';
    document.getElementById('f_activo').checked = (a.activo == 1);
    new bootstrap.Modal(document.getElementById('modalEditarAlumno')).show();
}

function buscarTutorFicha(valor) {
    const lista = document.getElementById('f_listaTutores');
    const hidden = document.getElementById('f_id_padre');
    if (!valor.trim()) { lista.style.display = 'none'; hidden.value = ''; return; }
    const term = valor.toLowerCase();
    const filtrados = FICHA_TUTORES.filter(t =>
        t.usuario.toLowerCase().includes(term) || t.email.toLowerCase().includes(term)
    );
    if (filtrados.length === 0) { lista.style.display = 'none'; return; }
    lista.innerHTML = filtrados.map(t =>
        `<button type="button" class="list-group-item list-group-item-action py-1" onclick="seleccionarTutorFicha(${t.id},'${t.usuario.replace(/'/g,"\\'")}')">${t.usuario} <small class="text-muted">(${t.email})</small></button>`
    ).join('');
    lista.style.display = 'block';
}
function seleccionarTutorFicha(id, usuario) {
    document.getElementById('f_buscarTutor').value = usuario;
    document.getElementById('f_id_padre').value = id;
    document.getElementById('f_listaTutores').style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('f_buscarTutor');
    if (input) {
        input.addEventListener('input', function() { buscarTutorFicha(this.value); });
        input.addEventListener('blur', function() {
            setTimeout(() => { document.getElementById('f_listaTutores').style.display = 'none'; }, 200);
        });
    }
});
</script>

<?php include '../includes/curso_picker.php'; ?>
<?php include '../includes/footer.php'; ?>
