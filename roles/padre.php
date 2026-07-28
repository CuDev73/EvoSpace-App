<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'padre') {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';
require_once '../secciones/eventos/models/NotificacionModel.php';

$id_padre = $_SESSION['id_usuario'];

// Obtener notificaciones para el padre
$notificacionModel = new NotificacionModel($pdo);
$notificaciones = $notificacionModel->obtenerNotificacionesPadre($id_padre);

// Obtener datos del padre
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
            WHERE ec.id_curso IN ($placeholders) AND e.fecha >= CURDATE()
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
$porcentajeBeca = (float)($config['porcentaje_beca'] ?? 45.45);
$recargoPorDia = (float)($config['recargo_por_dia'] ?? 1000);
$diaLimite = (int)($config['dia_limite_pago'] ?? 10);

// Filtro de mes/año
$mesFiltro = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$anioFiltro = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// Función para calcular deuda de un alumno en un mes específico
function calcularDeuda($pdo, $id_alumno, $mes, $anio, $porcentajeBeca, $recargoPorDia, $diaLimite) {
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

    // 4. Calcular recargo solo si es el mes actual y el día actual > día límite
    $recargo = 0;
    $hoy = getdate();
    if ($mes == $hoy['mon'] && $anio == $hoy['year'] && $hoy['mday'] > $diaLimite) {
        $diasAtraso = $hoy['mday'] - $diaLimite;
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

    <!-- Notificaciones (ya se arregló en NotificacionModel) -->
    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white">
            <i class="bi bi-bell-fill"></i> Notificaciones
            <?php if (count($notificaciones) > 0): ?>
                <span class="badge bg-danger rounded-pill ms-2"><?= count($notificaciones) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($notificaciones)): ?>
                <p class="text-muted">No tienes notificaciones.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notificaciones as $notif): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= htmlspecialchars($notif['titulo']) ?></strong>
                                <p class="mb-0 small"><?= htmlspecialchars($notif['mensaje']) ?></p>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($notif['fecha'])) ?></small>
                            </div>
                            <?php if (!$notif['leida']): ?>
                                <a href="/evospace/secciones/eventos/marcar_leida.php?id=<?= $notif['id_notificacion'] ?>" class="btn btn-sm btn-outline-primary">Marcar como leída</a>
                            <?php else: ?>
                                <span class="badge bg-secondary">Leída</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
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

    <!-- Lista de hijos con deuda -->
    <div class="card shadow">
        <div class="card-header bg-evo text-white">
            <i class="bi bi-people-fill"></i> Mis hijos - Resumen de deudas
            <span class="badge bg-light text-dark ms-2">Mes: <?= date('F', mktime(0,0,0,$mesFiltro,1)) . ' ' . $anioFiltro ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($hijos)): ?>
                <div class="alert alert-info">No tienes hijos registrados en el sistema.</div>
            <?php else: 
                $deudaTotal = 0;
            ?>
                <div class="row">
                    <?php foreach ($hijos as $hijo): 
                        // Calcular total pagado histórico
                        $stmtPago = $pdo->prepare("SELECT SUM(total) AS total_pagado FROM pagos WHERE id_alumno = ?");
                        $stmtPago->execute([$hijo['id_alumno']]);
                        $total_historico = $stmtPago->fetch(PDO::FETCH_ASSOC)['total_pagado'] ?? 0;

                        // Calcular deuda del mes
                        $deudaInfo = calcularDeuda($pdo, $hijo['id_alumno'], $mesFiltro, $anioFiltro, $porcentajeBeca, $recargoPorDia, $diaLimite);
                        $deudaTotal += $deudaInfo['deuda'];

                        // Estado de pago
                        $estado = 'al_dia';
                        $estadoTexto = 'Al día';
                        $estadoColor = 'success';
                        if ($deudaInfo['deuda'] > 0) {
                            $estado = 'moroso';
                            $estadoTexto = 'Moroso (debe Gs ' . number_format($deudaInfo['deuda'], 0, ',', '.') . ')';
                            $estadoColor = 'danger';
                        } elseif ($deudaInfo['pagado'] > 0 && $deudaInfo['recargo'] == 0 && $deudaInfo['deuda'] == 0) {
                            $estadoTexto = 'Pagado completo';
                            $estadoColor = 'success';
                        } elseif ($deudaInfo['pagado'] == 0 && $deudaInfo['deuda'] == 0) {
                            $estadoTexto = 'Sin cuota este mes';
                            $estadoColor = 'secondary';
                        }
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-hover">
                                <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-person-fill me-1"></i> <?= htmlspecialchars($hijo['nombre'] . ' ' . $hijo['apellido']) ?></span>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($hijo['curso_tipo']) ?></span>
                                </div>
                                <div class="card-body">
                                    <p><strong>Curso:</strong> <?= htmlspecialchars($hijo['curso_tipo'] . ' - ' . $hijo['curso_nombre']) ?></p>
                                    <p><strong>Año ingreso:</strong> <?= $hijo['anio_ingreso'] ?></p>
                                    <p><strong>Descuento:</strong> <?= $hijo['becado'] ? 'Sí' : 'No' ?></p>
                                    <p><strong>Total pagado histórico:</strong> <?= number_format($total_historico, 0, ',', '.') ?> Gs</p>

                                    <hr>
                                    <p class="mb-1"><strong>Resumen de <?= date('F', mktime(0,0,0,$mesFiltro,1)) . ' ' . $anioFiltro ?>:</strong></p>
                                    <ul class="list-unstyled small">
                                        <li>Cuota: <?= number_format($deudaInfo['cuota'], 0, ',', '.') ?> Gs</li>
                                        <?php if ($deudaInfo['recargo'] > 0): ?>
                                            <li class="text-danger">Recargo: + <?= number_format($deudaInfo['recargo'], 0, ',', '.') ?> Gs</li>
                                        <?php endif; ?>
                                        <li>Pagado en cuotas: <?= number_format($deudaInfo['pagado'], 0, ',', '.') ?> Gs</li>
                                        <li class="fw-bold <?= $deudaInfo['deuda'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            Deuda: <?= number_format($deudaInfo['deuda'], 0, ',', '.') ?> Gs
                                        </li>
                                    </ul>

                                    <p>
                                        <span class="badge bg-<?= $estadoColor ?>"><?= $estadoTexto ?></span>
                                    </p>

                                    <a href="/evospace/secciones/pagos.php?ver_hijo=<?= $hijo['id_alumno'] ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-eye-fill"></i> Ver pagos
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Resumen total de deuda -->
                <div class="bg-light rounded p-3 border d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <strong class="fs-5">Total deuda del mes: <?= number_format($deudaTotal, 0, ',', '.') ?> Gs</strong>
                    <?php if ($deudaTotal > 0): ?>
                        <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>¡Atención!</span>
                    <?php else: ?>
                        <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>¡Todo al día!</span>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>