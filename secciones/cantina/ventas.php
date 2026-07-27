<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
verificarPermiso('cantina');

include '../../includes/header.php';
include '../../includes/navbar.php';

$mensaje = '';

// Procesar nueva venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_venta') {
    $productos = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $metodo_pago = $_POST['metodo_pago'] ?? 'Efectivo';
    $tipo_comprador = $_POST['tipo_comprador'] ?? 'otro';
    $nombre_comprador = trim($_POST['nombre_comprador'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (empty($productos) || empty($cantidades)) {
        $mensaje = '<div class="alert alert-danger">Debe seleccionar al menos un producto.</div>';
    } elseif (empty($nombre_comprador)) {
        $mensaje = '<div class="alert alert-danger">Debe ingresar el nombre del comprador.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            // Calcular total y preparar detalles
            $total = 0;
            $detalles = [];
            foreach ($productos as $index => $id_producto) {
                if (empty($id_producto) || empty($cantidades[$index]) || $cantidades[$index] <= 0) continue;
                $stmt = $pdo->prepare("SELECT precio FROM productos WHERE id_producto = ? AND activo = 1");
                $stmt->execute([$id_producto]);
                $producto = $stmt->fetch();
                if (!$producto) continue;
                $precio = $producto['precio'];
                $cantidad = (int)$cantidades[$index];
                $subtotal = $precio * $cantidad;
                $total += $subtotal;
                $detalles[] = [
                    'id_producto' => $id_producto,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $subtotal
                ];
            }

            if (empty($detalles)) {
                throw new Exception('No se agregaron productos válidos.');
            }

            // Insertar venta (con los nuevos campos)
            $stmt = $pdo->prepare("INSERT INTO ventas (total, metodo_pago, tipo_comprador, nombre_comprador, observaciones) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$total, $metodo_pago, $tipo_comprador, $nombre_comprador, $observaciones]);
            $id_venta = $pdo->lastInsertId();

            // Insertar detalles
            $stmtDetalle = $pdo->prepare("INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                                          VALUES (?, ?, ?, ?, ?)");
            foreach ($detalles as $det) {
                $stmtDetalle->execute([$id_venta, $det['id_producto'], $det['cantidad'], $det['precio_unitario'], $det['subtotal']]);
            }

            $pdo->commit();
            $mensaje = '<div class="alert alert-success">Venta registrada correctamente. Total: Gs ' . number_format($total, 0, ',', '.') . '</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Obtener productos activos para el formulario
$productosActivos = $pdo->query("SELECT id_producto, nombre, precio FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Obtener ventas (listado) con el nuevo campo
$ventas = $pdo->query("SELECT v.*, 
                       (SELECT COUNT(*) FROM detalle_ventas WHERE id_venta = v.id_venta) as total_items
                       FROM ventas v ORDER BY v.fecha DESC")->fetchAll();

// Si se pide nueva venta, mostramos el formulario
$mostrarFormulario = isset($_GET['accion']) && $_GET['accion'] === 'nueva';
?>

<div class="container mt-3">
    <?= $mensaje ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-cart-plus"></i> Ventas de Cantina</h4>
        <a href="?accion=nueva" class="btn btn-danger <?= $mostrarFormulario ? 'd-none' : '' ?>">
            <i class="bi bi-plus-circle"></i> Nueva Venta
        </a>
    </div>

    <?php if ($mostrarFormulario): ?>
        <!-- Formulario de nueva venta -->
        <div class="card shadow mb-4">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-cart-plus"></i> Registrar Venta
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar_venta">

                    <!-- Productos -->
                    <div id="productos-container">
                        <div class="row g-2 mb-2 product-row">
                            <div class="col-md-5">
                                <label class="form-label small">Producto</label>
                                <select name="productos[]" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($productosActivos as $p): ?>
                                        <option value="<?= $p['id_producto'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Gs <?= number_format($p['precio'], 0, ',', '.') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Cantidad</label>
                                <input type="number" name="cantidades[]" class="form-control form-control-sm" value="1" min="1">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="this.closest('.product-row').remove()">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="agregarProducto()">
                                <i class="bi bi-plus-circle"></i> Agregar otro producto
                            </button>
                        </div>
                    </div>

                    <!-- Comprador -->
                    <div class="row mt-3 g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo de comprador</label>
                            <select name="tipo_comprador" id="tipo_comprador" class="form-select" required>
                                <option value="alumno">Alumno</option>
                                <option value="profesor">Profesor</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre del comprador *</label>
                            <input type="text" name="nombre_comprador" class="form-control" placeholder="Ej: Juan Pérez" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Método de pago</label>
                            <select name="metodo_pago" class="form-select" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Fiado">Fiado</option>
                            </select>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <label class="form-label">Observaciones (opcional)</label>
                            <input type="text" name="observaciones" class="form-control" placeholder="Ej: Cliente regular, nota...">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-check-circle"></i> Guardar Venta</button>
                        <a href="ventas.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Buscador de ventas -->
    <div class="row g-2 mb-3">
        <div class="col-md-12">
            <input type="text" id="buscadorVentas" class="form-control form-control-sm" placeholder="Buscar por comprador o ID de venta...">
        </div>
    </div>

    <!-- Listado de ventas -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-clock-history"></i> Historial de Ventas
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="tablaVentas">
                    <thead class="table-light text-center">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Comprador</th>
                            <th>Tipo</th>
                            <th>Total (Gs)</th>
                            <th>Método</th>
                            <th>Items</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                            <tr><td colspan="8" class="text-center">No hay ventas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $v): ?>
                                <tr data-comprador="<?= strtolower(htmlspecialchars($v['nombre_comprador'] ?? '')) ?>" data-id="<?= $v['id_venta'] ?>">
                                    <td class="text-center"><?= $v['id_venta'] ?></td>
                                    <td class="text-center"><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($v['nombre_comprador'] ?? 'Anónimo') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $v['tipo_comprador'] === 'alumno' ? 'primary' : ($v['tipo_comprador'] === 'profesor' ? 'info' : 'secondary') ?>">
                                            <?= ucfirst($v['tipo_comprador'] ?? 'otro') ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?= number_format($v['total'], 0, ',', '.') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $v['metodo_pago'] === 'Efectivo' ? 'success' : ($v['metodo_pago'] === 'Transferencia' ? 'info' : 'warning') ?>">
                                            <?= $v['metodo_pago'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?= $v['total_items'] ?></td>
                                    <td class="text-center"><?= htmlspecialchars($v['observaciones'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Botón Volver -->
    <div class="d-flex gap-2 mt-4 pb-3">
        <a href="/evospace/secciones/cantina.php" class="btn btn-secondary flex-fill">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<script>
function agregarProducto() {
    const container = document.getElementById('productos-container');
    const firstRow = container.querySelector('.product-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('select, input').forEach(el => el.value = '');
    newRow.querySelector('input[type="number"]').value = 1;
    container.appendChild(newRow);
}

// Buscador en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscadorVentas');
    const tabla = document.getElementById('tablaVentas');
    if (buscador && tabla) {
        buscador.addEventListener('keyup', function() {
            const filtro = this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const filas = tabla.querySelectorAll('tbody tr');
            filas.forEach(fila => {
                const comprador = fila.dataset.comprador || '';
                const id = fila.dataset.id || '';
                const texto = comprador + ' ' + id;
                fila.style.display = texto.includes(filtro) ? '' : 'none';
            });
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>