<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../helpers/functions.php';

include '../../includes/header.php';
include '../../includes/navbar.php';
require_once '../../config/db.php';

verificarPermiso('eventos');

$mensaje = '';
$tipoMensaje = 'info';

// ==========================================================
// 1. Crear lote
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_lote') {
    verificarTokenCSRF();
    $id_curso = (int)($_POST['id_curso'] ?? 0);
    $id_evento = !empty($_POST['id_evento']) ? (int)$_POST['id_evento'] : null;
    $cantidad = (int)($_POST['cantidad'] ?? 0);
    $precio = (float)($_POST['precio'] ?? 0);
    $fecha = $_POST['fecha_asignacion'] ?? date('Y-m-d');

    if ($id_curso && $cantidad > 0 && $precio >= 0) {
        $stmt = $pdo->prepare("INSERT INTO entradas_curso (id_curso, id_evento, cantidad, precio, fecha_asignacion) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_curso, $id_evento, $cantidad, $precio, $fecha]);
        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Lote de entradas/rifas creado correctamente.';
        $tipoMensaje = 'success';
    } else {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Datos inválidos: curso, cantidad y precio son obligatorios.';
        $tipoMensaje = 'danger';
    }
}

// ==========================================================
// 2. Distribuir a alumnos (UPSERT)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'distribuir') {
    verificarTokenCSRF();
    $id_lote = (int)($_POST['id_entrada_curso'] ?? 0);
    $cantidades = $_POST['cantidades'] ?? [];

    $stmtLote = $pdo->prepare("SELECT id_curso, cantidad, estado FROM entradas_curso WHERE id_entrada_curso = ?");
    $stmtLote->execute([$id_lote]);
    $lote = $stmtLote->fetch(PDO::FETCH_ASSOC);

    if (!$lote) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Lote no encontrado.';
        $tipoMensaje = 'danger';
    } elseif ($lote['estado'] !== 'activa') {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> No se puede distribuir un lote cerrado.';
        $tipoMensaje = 'danger';
    } else {
        $totalNuevo = 0;
        $filas = [];
        foreach ($cantidades as $idAlumno => $cant) {
            $cant = (int)$cant;
            if ($cant > 0) {
                $filas[] = [(int)$idAlumno, $cant];
                $totalNuevo += $cant;
            }
        }
        if ($totalNuevo > $lote['cantidad']) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> La suma entregada (' . $totalNuevo . ') excede la cantidad del lote (' . $lote['cantidad'] . ').';
            $tipoMensaje = 'danger';
        } else {
            $fechaHoy = date('Y-m-d');
            $stmtUpsert = $pdo->prepare("
                INSERT INTO entradas_alumno (id_entrada_curso, id_alumno, cantidad, cantidad_total, fecha_entrega)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad), cantidad_total = VALUES(cantidad), fecha_entrega = VALUES(fecha_entrega)
            ");
            $pdo->beginTransaction();
            try {
                // Poner en cero los que ya no llevan entradas
                $stmtReset = $pdo->prepare("
                    UPDATE entradas_alumno SET cantidad = 0
                    WHERE id_entrada_curso = ? AND id_alumno NOT IN (" . (count($filas) ? implode(',', array_column($filas, 0)) : 'NULL') . ")
                ");
                $stmtReset->execute([$id_lote]);
                foreach ($filas as [$idAlumno, $cant]) {
                    $stmtUpsert->execute([$id_lote, $idAlumno, $cant, $cant, $fechaHoy]);
                }
                $pdo->commit();
                $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Distribución guardada (' . count($filas) . ' alumno(s)).';
                $tipoMensaje = 'success';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al guardar: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
        }
    }
}

// ==========================================================
// 3. Cerrar lote
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cerrar_lote') {
    verificarTokenCSRF();
    $stmt = $pdo->prepare("UPDATE entradas_curso SET estado = 'cerrada' WHERE id_entrada_curso = ?");
    $stmt->execute([(int)$_POST['id_entrada_curso']]);
    $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Lote cerrado.';
    $tipoMensaje = 'success';
}

// ==========================================================
// 4. Eliminar lote
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_lote') {
    verificarTokenCSRF();
    $stmt = $pdo->prepare("DELETE FROM entradas_curso WHERE id_entrada_curso = ?");
    $stmt->execute([(int)$_POST['id_entrada_curso']]);
    $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Lote eliminado.';
    $tipoMensaje = 'success';
}

// ==========================================================
// Datos para las vistas
// ==========================================================
$lotes = $pdo->query("
    SELECT ec.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo, e.titulo AS evento_titulo,
           COALESCE((SELECT SUM(cantidad) FROM entradas_alumno ea WHERE ea.id_entrada_curso = ec.id_entrada_curso AND ea.cantidad > 0), 0) AS entregadas
    FROM entradas_curso ec
    JOIN cursos c ON ec.id_curso = c.id_curso
    LEFT JOIN eventos e ON ec.id_evento = e.id_evento
    ORDER BY ec.fecha_asignacion DESC, ec.id_entrada_curso DESC
")->fetchAll(PDO::FETCH_ASSOC);

$cursos = $pdo->query("SELECT id_curso, nombre, tipo FROM cursos WHERE activo = 1 ORDER BY tipo, orden")->fetchAll(PDO::FETCH_ASSOC);
$eventos = $pdo->query("SELECT id_evento, titulo, fecha FROM eventos ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);

// Lote a distribuir
$loteDistribuir = null;
$alumnosLote = [];
if (isset($_GET['lote'])) {
    $idLote = (int)$_GET['lote'];
    $stmt = $pdo->prepare("
        SELECT ec.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo, e.titulo AS evento_titulo
        FROM entradas_curso ec
        JOIN cursos c ON ec.id_curso = c.id_curso
        LEFT JOIN eventos e ON ec.id_evento = e.id_evento
        WHERE ec.id_entrada_curso = ?
    ");
    $stmt->execute([$idLote]);
    $loteDistribuir = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($loteDistribuir) {
        $stmt = $pdo->prepare("
            SELECT a.id_alumno, a.nombre, a.apellido,
                   COALESCE((SELECT ea.cantidad FROM entradas_alumno ea WHERE ea.id_entrada_curso = ? AND ea.id_alumno = a.id_alumno), 0) AS asignadas
            FROM alumnos a
            WHERE a.id_curso = ? AND a.activo = 1
            ORDER BY a.apellido, a.nombre
        ");
        $stmt->execute([$idLote, $loteDistribuir['id_curso']]);
        $alumnosLote = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>


<div class="container mt-3 pb-4">
    <h4 class="mb-3"><i class="bi bi-ticket-perforated-fill me-2"></i>Entradas / Rifas</h4>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show"><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($loteDistribuir): ?>
        <!-- Distribución -->
        <div class="card shadow mb-4">
            <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-1"></i> Distribuir: <?= htmlspecialchars($loteDistribuir['curso_tipo'] . ' - ' . $loteDistribuir['curso_nombre']) ?> <?= $loteDistribuir['evento_titulo'] ? '(' . htmlspecialchars($loteDistribuir['evento_titulo']) . ')' : '' ?></span>
                <span class="badge bg-light text-dark">Lote: <?= (int)$loteDistribuir['cantidad'] ?> ud</span>
            </div>
            <form method="POST" id="formDistribucion">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="distribuir">
                    <input type="hidden" name="id_entrada_curso" value="<?= (int)$loteDistribuir['id_entrada_curso'] ?>">
                    <div class="card-body pb-0 pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle tabla-distribucion">
                            <thead class="table-light"><tr><th>Alumno</th><th style="width:160px;">Cantidad a entregar</th></tr></thead>
                            <tbody>
                                <?php foreach ($alumnosLote as $al): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($al['apellido'] . ', ' . $al['nombre']) ?></td>
                                        <td>
                                            <input type="number" min="0" max="<?= (int)$loteDistribuir['cantidad'] ?>" name="cantidades[<?= (int)$al['id_alumno'] ?>]" class="form-control form-control-sm" value="<?= (int)$al['asignadas'] ?>" data-lote="<?= (int)$loteDistribuir['cantidad'] ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                    <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                        <small class="text-muted">Suma actual: <strong id="sumaEntregas">0</strong> / <?= (int)$loteDistribuir['cantidad'] ?></small>
                        <div>
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar distribución</button>
                        </div>
                    </div>
                </form>
        </div>
    <?php endif; ?>

    <!-- Lista de lotes -->
    <div class="card shadow">
        <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-grid-fill me-1"></i> Lotes de entradas / rifas</span>
            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalNuevoLote"><i class="bi bi-plus-circle"></i> Nuevo lote</button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($lotes)): ?>
                <div class="p-3 text-muted">Aún no hay lotes. Crea el primero con <strong>Nuevo lote</strong>.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Curso</th><th>Evento</th><th>Cantidad</th><th>Entregadas</th><th>Precio ud.</th><th>Valor total</th><th>Fecha</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lotes as $l):
                                $restante = max(0, (int)$l['cantidad'] - (int)$l['entregadas']);
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['curso_tipo'] . ' - ' . $l['curso_nombre']) ?></td>
                                    <td><?= $l['evento_titulo'] ? htmlspecialchars($l['evento_titulo']) : '<span class="text-muted">—</span>' ?></td>
                                    <td><?= (int)$l['cantidad'] ?></td>
                                    <td><?= (int)$l['entregadas'] ?> <span class="text-muted">/ <?= $restante ?> restantes</span></td>
                                    <td><?= number_format((float)$l['precio'], 0, ',', '.') ?></td>
                                    <td class="fw-bold"><?= number_format((float)$l['precio'] * (int)$l['cantidad'], 0, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($l['fecha_asignacion'])) ?></td>
                                    <td><span class="badge bg-<?= $l['estado'] === 'activa' ? 'success' : 'secondary' ?>"><?= $l['estado'] ?></span></td>
                                    <td class="text-end">
                                        <a href="index.php?lote=<?= (int)$l['id_entrada_curso'] ?>" class="btn btn-sm btn-outline-primary" title="Distribuir a alumnos"><i class="bi bi-people-fill"></i></a>
                                        <?php if ($l['estado'] === 'activa'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Cerrar este lote? Ya no se podrá distribuir.')">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="accion" value="cerrar_lote">
                                                <input type="hidden" name="id_entrada_curso" value="<?= (int)$l['id_entrada_curso'] ?>">
                                                <button class="btn btn-sm btn-outline-warning" title="Cerrar lote"><i class="bi bi-lock-fill"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este lote y su distribución?')">
                                            <?= campoCSRF() ?>
                                            <input type="hidden" name="accion" value="eliminar_lote">
                                            <input type="hidden" name="id_entrada_curso" value="<?= (int)$l['id_entrada_curso'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Eliminar lote"><i class="bi bi-trash-fill"></i></button>
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
</div>

<!-- Modal Nuevo Lote -->
<div class="modal fade" id="modalNuevoLote" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo lote de entradas / rifas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="crear_lote">
                    <div class="mb-3">
                        <label class="form-label">Curso *</label>
                        <input type="hidden" name="id_curso" id="id_curso_lote" value="">
                        <button type="button" class="btn btn-outline-danger w-100 d-flex justify-content-between align-items-center py-2 border-2" onclick="cursoPickerAbrir('id_curso_lote','lblCursoLote')">
                            <span id="lblCursoLote"><i class="bi bi-book me-1"></i> Seleccionar curso...</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Evento (opcional)</label>
                        <select name="id_evento" class="form-select">
                            <option value="">-- Sin evento / general --</option>
                            <?php foreach ($eventos as $ev): ?>
                                <option value="<?= (int)$ev['id_evento'] ?>"><?= htmlspecialchars($ev['titulo']) ?> (<?= date('d/m/Y', strtotime($ev['fecha'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cantidad (ud) *</label>
                            <input type="number" name="cantidad" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Precio unitario (Gs) *</label>
                            <input type="number" name="precio" class="form-control" min="0" step="500" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Fecha de asignación</label>
                        <input type="date" name="fecha_asignacion" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check-lg"></i> Crear lote</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function actualizarSuma() {
    const inputs = document.querySelectorAll('input[name^="cantidades["]');
    let suma = 0;
    inputs.forEach(i => suma += parseInt(i.value || 0));
    const el = document.getElementById('sumaEntregas');
    if (el) {
        el.textContent = suma;
        const lote = inputs[0] ? parseInt(inputs[0].dataset.lote) : 0;
        el.className = suma > lote ? 'text-danger fw-bold' : 'text-success fw-bold';
    }
}
document.querySelectorAll('input[name^="cantidades["]').forEach(i => i.addEventListener('input', actualizarSuma));
actualizarSuma();
</script>

<style>
.tabla-distribucion tbody tr:last-child td { border-bottom: 0 !important; }
</style>

<?php include '../../includes/curso_picker.php'; ?>
<?php include '../../includes/footer.php'; ?>