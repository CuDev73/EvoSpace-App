<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$mensaje = '';
$error = '';

// --- PROCESAR PAGO ---
if (isset($_POST['accion_pago'])) {
    $id_alumno = (int) $_POST['id_alumno_pago'];
    $monto = (float) $_POST['monto_pago'];
    $fecha = $_POST['fecha_pago'] ?? date('Y-m-d');
    $id_venta = isset($_POST['id_venta_pago']) ? (int)$_POST['id_venta_pago'] : 0;
    if ($id_alumno > 0 && $monto > 0) {
        try {
            $pdo->beginTransaction();
            insertarPagoAlumnoCantina($pdo, $fecha, $id_alumno, $monto);
            if ($id_venta > 0) {
                $stmt = $pdo->prepare("UPDATE ventas SET estado_pago = 'pagado' WHERE id_venta = ?");
                $stmt->execute([$id_venta]);
            }
            $pdo->commit();
            $mensaje = "Pago registrado correctamente.";
            header("Location: index.php?exito=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar pago: " . $e->getMessage();
        }
    } else {
        $error = "Datos de pago inválidos.";
    }
}

$mostrarVolver = true;
$volverUrl = '../index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';

$ramaFiltro = isset($_GET['rama']) ? $_GET['rama'] : null;

$alumnos = $pdo->query("
    SELECT a.id_alumno, a.nombre, a.apellido, c.tipo AS rama, c.nombre AS curso
    FROM alumnos a
    JOIN cursos c ON a.id_curso = c.id_curso
    WHERE a.activo = 1
    ORDER BY a.apellido, a.nombre
")->fetchAll(PDO::FETCH_OBJ);

if (isset($_GET['exito'])) {
    $mensaje = $mensaje ?: "Operación realizada correctamente.";
}
?>

<div class="container mt-3">
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-8">
            <h4><i class="bi bi-cart"></i> Compras de Alumnos (Fiado)</h4>
        </div>
        <!-- Enlaces eliminados según solicitud -->
    </div>

    <!-- Filtro por rama -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0">Filtrar por rama:</label>
                </div>
                <div class="col-md-3">
                    <select name="rama" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        <option value="Superior" <?= $ramaFiltro === 'Superior' ? 'selected' : '' ?>>Superior</option>
                        <option value="Infantil" <?= $ramaFiltro === 'Infantil' ? 'selected' : '' ?>>Infantil</option>
                        <option value="Acrotelas" <?= $ramaFiltro === 'Acrotelas' ? 'selected' : '' ?>>Acrotelas</option>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="index.php" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Registrar pago -->
    <div class="card shadow mb-4">
        <div class="card-header bg-success text-white">
            <i class="bi bi-cash"></i> Registrar pago
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 mb-3">
                <input type="hidden" name="accion_pago" value="1">
                <input type="hidden" name="id_alumno_pago" id="id_alumno_pago">
                <input type="hidden" name="id_venta_pago" id="id_venta_pago" value="0">
                <div class="col-md-4 position-relative">
                    <label class="form-label">Alumno</label>
                    <input type="text" id="buscarAlumnoPago" class="form-control" placeholder="Escribe para buscar..." autocomplete="off" required>
                    <div id="resultadosAlumnosPago" class="list-group mt-1 position-absolute w-100" style="display:none; z-index:1000; max-height:200px; overflow-y:auto;"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monto (Gs)</label>
                    <input type="number" step="0.01" name="monto_pago" id="monto_pago" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Registrar pago</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fiado en cantina (ventas pendientes) -->
    <div class="card shadow mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="bi bi-cart"></i> Fiado en cantina
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Comprador</th>
                            <th class="text-end">Total</th>
                            <th>Método</th>
                            <th>Pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $ventasFiado = $pdo->query("
                            SELECT v.*
                            FROM ventas v
                            WHERE v.estado_pago IN ('pendiente','parcial')
                            ORDER BY v.fecha DESC
                        ")->fetchAll(PDO::FETCH_OBJ);
                        ?>
                        <?php if (empty($ventasFiado)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No hay fiados pendientes.</td></tr>
                        <?php else: ?>
                            <?php $totalFiado = 0; ?>
                            <?php foreach ($ventasFiado as $vf): ?>
                                <?php $totalFiado += $vf->total; ?>
                                <tr>
                                    <td><?= $vf->id_venta ?></td>
                                    <td><?= date('d/m/Y', strtotime($vf->fecha)) ?></td>
                                    <td><?= htmlspecialchars($vf->nombre_comprador ?? 'Anónimo') ?></td>
                                    <td class="text-end text-danger fw-bold"><?= number_format($vf->total, 0, ',', '.') ?> Gs</td>
                                    <td><?= $vf->metodo_pago ?></td>
                                    <td>
                                        <button class="btn btn-success btn-sm" onclick="cargarPagoVenta(<?= $vf->id_venta ?>, <?= $vf->id_alumno ?? 0 ?>, '<?= htmlspecialchars($vf->nombre_comprador ?? '', ENT_QUOTES) ?>', <?= $vf->total ?>)">
                                            <i class="bi bi-cash"></i> Pagar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($ventasFiado)): ?>
                    <tfoot class="table-dark">
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end"><?= number_format($totalFiado, 0, ',', '.') ?> Gs</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include '../../../includes/footer.php'; ?>

<script>
const alumnos = <?= json_encode(array_map(function($a) {
    return ['id' => $a->id_alumno, 'nombre' => $a->apellido . ', ' . $a->nombre . ' (' . $a->rama . ')', 'apellido' => $a->apellido, 'rama' => $a->rama];
}, $alumnos)) ?>;

function setupAutocomplete(inputId, resultsId, onSelect) {
    const input = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    input.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        if (term.length < 1) { results.style.display = 'none'; return; }
        const filtrados = alumnos.filter(a =>
            a.nombre.toLowerCase().includes(term) || a.apellido.toLowerCase().includes(term) || a.rama.toLowerCase().includes(term)
        ).slice(0, 15);
        results.innerHTML = '';
        if (filtrados.length === 0) {
            results.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
        } else {
            filtrados.forEach(a => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action py-1 small';
                btn.textContent = a.nombre;
                btn.addEventListener('click', function() {
                    input.value = a.nombre;
                    onSelect(a);
                    results.style.display = 'none';
                });
                results.appendChild(btn);
            });
        }
        results.style.display = 'block';
    });
    input.addEventListener('blur', () => setTimeout(() => results.style.display = 'none', 200));
    input.addEventListener('focus', () => { if (input.value.length >= 1) results.style.display = 'block'; });
}

setupAutocomplete('buscarAlumnoPago', 'resultadosAlumnosPago', function(a) {
    document.getElementById('id_alumno_pago').value = a.id;
});

function cargarPagoVenta(idVenta, idAlumno, nombre, total) {
    document.getElementById('buscarAlumnoPago').value = nombre;
    document.getElementById('id_alumno_pago').value = idAlumno;
    document.getElementById('id_venta_pago').value = idVenta;
    document.getElementById('monto_pago').value = total;
    document.getElementById('monto_pago').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
