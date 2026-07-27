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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_venta') {
    $fecha = $_POST['fecha'];
    $metodo_pago = $_POST['metodo_pago'];
    $estado_pago = $_POST['estado_pago'];
    $tipo_comprador = $_POST['tipo_comprador'] ?? 'otro';
    $nombre_comprador = trim($_POST['nombre_comprador']);
    $id_alumno = !empty($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : null;
    $id_usuario = !empty($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : null;
    $observaciones = trim($_POST['observaciones']);
    $productos_seleccionados = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    
    $productos_venta = [];
    $total = 0;
    foreach ($productos_seleccionados as $index => $id_producto) {
        if (empty($id_producto) || empty($cantidades[$index]) || $cantidades[$index] <= 0) continue;
        $stmt = $pdo->prepare("SELECT precio FROM productos WHERE id_producto = ?");
        $stmt->execute([$id_producto]);
        $precio = (float) $stmt->fetchColumn();
        if ($precio) {
            $cantidad = (int)$cantidades[$index];
            $subtotal = $precio * $cantidad;
            $productos_venta[] = [
                'id_producto' => $id_producto,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal
            ];
            $total += $subtotal;
        }
    }
    if (empty($productos_venta)) {
        $error = "Debe seleccionar al menos un producto con cantidad válida.";
    } elseif (empty($nombre_comprador)) {
        $error = "El nombre del comprador es obligatorio.";
    } else {
        try {
            $id_venta = registrarVenta($pdo, $fecha, $productos_venta, $total, $metodo_pago, $tipo_comprador, $nombre_comprador, $id_alumno, $id_usuario, $observaciones, $estado_pago);
            header("Location: index.php?exito=1");
            exit;
        } catch (Exception $e) {
            $error = "Error al registrar venta: " . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
include '../../../includes/navbar.php';

$productos = $pdo->query("SELECT * FROM productos WHERE activo = 1 AND cantidad > 0 ORDER BY nombre")->fetchAll(PDO::FETCH_OBJ);
?>

<div class="container mt-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-cart-plus"></i> Nueva Venta</h4>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver al historial</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="formVenta">
        <input type="hidden" name="accion" value="guardar_venta">
        
        <div class="card shadow mb-3">
            <div class="card-header bg-danger text-white">Datos de la venta</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Buscar comprador (alumno, profesor, padre)</label>
                        <input type="text" id="buscadorComprador" class="form-control" placeholder="Escribe para buscar...">
                        <input type="hidden" name="id_alumno" id="id_alumno">
                        <input type="hidden" name="id_usuario" id="id_usuario">
                        <input type="hidden" name="tipo_comprador" id="tipo_comprador" value="otro">
                        <div id="resultadosBusqueda" class="list-group mt-1" style="display:none;"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nombre comprador *</label>
                        <input type="text" name="nombre_comprador" id="nombre_comprador" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Método de pago</label>
                        <select name="metodo_pago" class="form-select">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Fiado">Fiado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado del pago</label>
                        <select name="estado_pago" class="form-select">
                            <option value="pagado">Pagado</option>
                            <option value="pendiente">Pendiente (fiado)</option>
                            <option value="parcial">Parcial</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" placeholder="Ej: Cliente regular, descuento, etc.">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-danger text-white">Productos</div>
            <div class="card-body">
                <div id="productos-container">
                    <div class="row g-2 mb-2 producto-row">
                        <div class="col-md-6">
                            <label class="form-label small">Producto</label>
                            <select name="productos[]" class="form-select form-select-sm" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?= $p->id_producto ?>" data-precio="<?= $p->precio ?>"><?= htmlspecialchars($p->nombre) ?> (Stock: <?= $p->cantidad ?>) - Gs <?= number_format($p->precio, 0, ',', '.') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Cantidad</label>
                            <input type="number" name="cantidades[]" class="form-control form-control-sm" value="1" min="1">
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
            <button type="submit" class="btn btn-danger"><i class="bi bi-check-circle"></i> Registrar Venta</button>
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>

    <!-- Botones de Volver (abajo) -->
    <div class="row g-2 mt-4">
        <div class="col-md-6">
            <button onclick="history.back()" class="btn btn-secondary w-100">
                <i class="bi bi-arrow-left"></i> Volver atrás
            </button>
        </div>
        <div class="col-md-6">
            <a href="../index.php" class="btn btn-secondary w-100">
                <i class="bi bi-house"></i> Volver al panel de Cantina
            </a>
        </div>
    </div>
</div>

<script>
document.getElementById('buscadorComprador').addEventListener('input', function() {
    let termino = this.value;
    let resultadosDiv = document.getElementById('resultadosBusqueda');
    if (termino.length < 2) {
        resultadosDiv.style.display = 'none';
        return;
    }
    fetch('../api/buscar_comprador.php?q=' + encodeURIComponent(termino))
        .then(response => response.json())
        .then(data => {
            resultadosDiv.innerHTML = '';
            if (data.length === 0) {
                resultadosDiv.innerHTML = '<div class="list-group-item">No se encontraron resultados</div>';
            } else {
                data.forEach(item => {
                    let btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    btn.textContent = item.nombre + ' (' + item.tipo + ')';
                    btn.dataset.id = item.id;
                    btn.dataset.tipo = item.tipo;
                    btn.addEventListener('click', function() {
                        document.getElementById('nombre_comprador').value = item.nombre;
                        document.getElementById('id_alumno').value = (item.tipo === 'alumno') ? item.id : '';
                        document.getElementById('id_usuario').value = (item.tipo !== 'alumno') ? item.id : '';
                        document.getElementById('tipo_comprador').value = item.tipo;
                        document.getElementById('buscadorComprador').value = item.nombre;
                        resultadosDiv.style.display = 'none';
                    });
                    resultadosDiv.appendChild(btn);
                });
            }
            resultadosDiv.style.display = 'block';
        })
        .catch(error => console.error('Error:', error));
});

function agregarProducto() {
    const container = document.getElementById('productos-container');
    const firstRow = container.querySelector('.producto-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('select, input').forEach(el => el.value = '');
    newRow.querySelector('input[type="number"]').value = 1;
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