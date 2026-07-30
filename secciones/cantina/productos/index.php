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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $id = $_POST['id'] ?? '';
    $nombre = trim($_POST['nombre']);
    $precio_venta = (float) $_POST['precio_venta'];
    $precio_compra = (float) $_POST['precio_compra'];
    $cantidad = (int) $_POST['cantidad'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre) || $precio_venta <= 0) {
        $error = "Nombre y precio de venta son obligatorios.";
    } else {
        try {
            guardarProducto($pdo, $id, $nombre, $precio_venta, $precio_compra, $cantidad, $activo);
            $mensaje = "Producto guardado correctamente.";
            header("Location: index.php?exito=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

if (isset($_GET['eliminar'])) {
    try {
        eliminarProducto($pdo, (int) $_GET['eliminar']);
        $mensaje = "Producto eliminado correctamente.";
        header("Location: index.php?eliminado=1");
        exit;
    } catch (PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

$mostrarVolver = true;
$volverUrl = '../index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';

$productos = obtenerProductosCompletos($pdo);
$editProducto = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id_producto = ?");
    $stmt->execute([$_GET['editar']]);
    $editProducto = $stmt->fetch(PDO::FETCH_OBJ);
}
if (isset($_GET['exito']) || isset($_GET['eliminado'])) {
    $mensaje = $_GET['exito'] ? "Producto guardado correctamente." : "Producto eliminado correctamente.";
}
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-box-seam"></i> Productos</h4>
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="limpiarFormulario()"><i class="bi bi-plus-circle"></i> Nuevo Producto</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th class="text-end">Precio Venta</th>
                            <th class="text-end">Precio Compra</th>
                            <th class="text-center">Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr><td colspan="7" class="text-center">No hay productos.</td></tr>
                        <?php else: ?>
                            <?php foreach ($productos as $p): ?>
                                <tr>
                                    <td><?= $p->id_producto ?></td>
                                    <td><?= htmlspecialchars($p->nombre) ?></td>
                                    <td class="text-end"><?= number_format($p->precio, 0, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format($p->precio_compra ?? 0, 0, ',', '.') ?></td>
                                    <td class="text-center"><?= $p->cantidad ?></td>
                                    <td><?= $p->activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="editarProducto(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="bi bi-pencil"></i></button>
                                        <a href="?eliminar=<?= $p->id_producto ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este producto?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Producto (sin cambios) -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalTitulo">Nuevo Producto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" id="id_producto" value="0">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Precio Venta (Gs) *</label>
                            <input type="number" name="precio_venta" id="precio_venta" class="form-control" required data-moneda>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Precio Compra (Gs)</label>
                            <input type="number" name="precio_compra" id="precio_compra" class="form-control" value="0" data-moneda>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cantidad (Stock)</label>
                            <input type="number" name="cantidad" id="cantidad" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input type="checkbox" name="activo" id="activo" class="form-check-input" checked>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function limpiarFormulario() {
    document.getElementById('modalTitulo').innerText = 'Nuevo Producto';
    document.getElementById('id_producto').value = '0';
    document.getElementById('nombre').value = '';
    document.getElementById('precio_venta').value = '';
    document.getElementById('precio_compra').value = '0';
    document.getElementById('cantidad').value = '0';
    document.getElementById('activo').checked = true;
}

function editarProducto(p) {
    document.getElementById('modalTitulo').innerText = 'Editar Producto';
    document.getElementById('id_producto').value = p.id_producto;
    document.getElementById('nombre').value = p.nombre;
    document.getElementById('precio_venta').value = Math.round(p.precio);
    document.getElementById('precio_compra').value = Math.round(p.precio_compra || 0);
    document.getElementById('cantidad').value = p.cantidad || 0;
    document.getElementById('activo').checked = p.activo == 1;
}
</script>

<?php include '../../../includes/footer.php'; ?>