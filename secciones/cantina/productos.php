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

// Procesar guardar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id_producto = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
        $nombre = trim($_POST['nombre']);
        $precio = (float)$_POST['precio'];
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre) || $precio <= 0) {
            $mensaje = '<div class="alert alert-danger">Nombre y precio válido son obligatorios.</div>';
        } else {
            try {
                if ($id_producto > 0) {
                    $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, precio = ?, activo = ? WHERE id_producto = ?");
                    $stmt->execute([$nombre, $precio, $activo, $id_producto]);
                    $mensaje = '<div class="alert alert-success">Producto actualizado.</div>';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO productos (nombre, precio, activo) VALUES (?, ?, ?)");
                    $stmt->execute([$nombre, $precio, $activo]);
                    $mensaje = '<div class="alert alert-success">Producto creado.</div>';
                }
            } catch (Exception $e) {
                $mensaje = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        }
    }

    if ($accion === 'eliminar') {
        $id = (int)$_POST['id_producto'];
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id_producto = ?");
        $stmt->execute([$id]);
        $mensaje = '<div class="alert alert-success">Producto eliminado.</div>';
    }
}

// Obtener productos
$productos = $pdo->query("SELECT * FROM productos ORDER BY nombre")->fetchAll();
?>

<div class="container mt-3">
    <?= $mensaje ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-box-seam"></i> Productos de Cantina</h4>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="limpiarFormulario()">
            <i class="bi bi-plus-circle"></i> Nuevo Producto
        </button>
    </div>

    <!-- Buscador -->
    <div class="row g-2 mb-3">
        <div class="col-md-12">
            <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar producto por nombre...">
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="tablaProductos">
                    <thead class="table-light text-center">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Precio (Gs)</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTabla">
                        <?php if (empty($productos)): ?>
                            <tr><td colspan="5" class="text-center">No hay productos.</td></tr>
                        <?php else: ?>
                            <?php foreach ($productos as $p): ?>
                                <tr data-nombre="<?= htmlspecialchars(strtolower($p['nombre'])) ?>">
                                    <td class="text-center"><?= $p['id_producto'] ?></td>
                                    <td class="text-center"><?= htmlspecialchars($p['nombre']) ?></td>
                                    <td class="text-center"><?= number_format($p['precio'], 0, ',', '.') ?></td>
                                    <td class="text-center"><?= $p['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalProducto"
                                                onclick="editarProducto(<?= htmlspecialchars(json_encode($p)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar producto?')">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
                                            <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
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

<!-- Modal Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalTitulo">Nuevo Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_producto" id="id_producto" value="0">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Precio (Gs)</label>
                        <input type="number" step="0.01" name="precio" id="precio" class="form-control" required>
                    </div>
                    <div class="form-check">
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
    document.getElementById('precio').value = '';
    document.getElementById('activo').checked = true;
}

function editarProducto(producto) {
    document.getElementById('modalTitulo').innerText = 'Editar Producto';
    document.getElementById('id_producto').value = producto.id_producto;
    document.getElementById('nombre').value = producto.nombre;
    document.getElementById('precio').value = producto.precio;
    document.getElementById('activo').checked = (producto.activo == 1);
}

// Buscador en tiempo real (sin tildes)
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscador');
    const tabla = document.getElementById('tablaProductos');
    if (buscador && tabla) {
        buscador.addEventListener('keyup', function() {
            const filtro = this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const filas = tabla.querySelectorAll('tbody tr');
            filas.forEach(fila => {
                const nombre = fila.dataset.nombre || '';
                fila.style.display = nombre.includes(filtro) ? '' : 'none';
            });
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>