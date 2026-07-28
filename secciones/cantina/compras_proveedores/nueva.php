<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $id_proveedor = (int) $_POST['id_proveedor'];
    $fecha = $_POST['fecha'];
    $observaciones = trim($_POST['observaciones']);
    $productos_seleccionados = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $precios_compra = $_POST['precios_compra'] ?? [];
    
    $productos_compra = [];
    foreach ($productos_seleccionados as $index => $id_producto) {
        if (empty($id_producto) || empty($cantidades[$index]) || $cantidades[$index] <= 0) continue;
        $precio = (float) $precios_compra[$index];
        $cantidad = (int) $cantidades[$index];
        $subtotal = $precio * $cantidad;
        $productos_compra[] = [
            'id_producto' => $id_producto,
            'cantidad' => $cantidad,
            'precio_compra' => $precio,
            'subtotal' => $subtotal
        ];
    }
    
    if (empty($productos_compra)) {
        $error = "Debe seleccionar al menos un producto con cantidad y precio válidos.";
    } elseif ($id_proveedor <= 0) {
        $error = "Debe seleccionar un proveedor.";
    } else {
        try {
            $id_compra = registrarCompraProveedor($pdo, $id_proveedor, $fecha, $productos_compra, $observaciones);
            header("Location: index.php?exito=1");
            exit;
        } catch (Exception $e) {
            $error = "Error al registrar compra: " . $e->getMessage();
        }
    }
}

$mostrarVolver = true;
$volverUrl = 'index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';

$proveedores = obtenerProveedores($pdo);
$productos = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_OBJ);
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-cart-plus"></i> Nueva Compra a Proveedor</h4>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="accion" value="guardar">
        
        <div class="card shadow mb-3">
            <div class="card-header bg-danger text-white">Datos de la compra</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Proveedor *</label>
                        <select name="id_proveedor" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p->id_proveedor ?>"><?= htmlspecialchars($p->nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" placeholder="Notas adicionales">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-danger text-white">Productos</div>
            <div class="card-body">
                <div id="productos-container">
                    <div class="row g-2 mb-2 producto-row">
                        <div class="col-md-4">
                            <label class="form-label small">Producto *</label>
                            <select name="productos[]" class="form-select form-select-sm" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?= $p->id_producto ?>"><?= htmlspecialchars($p->nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Cantidad *</label>
                            <input type="number" name="cantidades[]" class="form-control form-control-sm" value="1" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Precio compra (Gs) *</label>
                            <input type="number" step="0.01" name="precios_compra[]" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="eliminarFila(this)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="agregarProducto()"><i class="bi bi-plus-circle"></i> Agregar otro producto</button>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-danger"><i class="bi bi-check-circle"></i> Registrar Compra</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>

</div>

<script>
function agregarProducto() {
    const container = document.getElementById('productos-container');
    const firstRow = container.querySelector('.producto-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('select, input').forEach(el => el.value = '');
    newRow.querySelector('input[type="number"]').value = 1;
    newRow.querySelector('input[name="precios_compra[]"]').value = 0;
    container.appendChild(newRow);
}

function eliminarFila(btn) {
    const filas = document.querySelectorAll('.producto-row');
    if (filas.length > 1) {
        btn.closest('.producto-row').remove();
    } else {
        alert('Debe haber al menos un producto.');
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>