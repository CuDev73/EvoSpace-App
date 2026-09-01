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
    verificarTokenCSRF();
    $fecha = $_POST['fecha'];
    $metodo_pago = $_POST['metodo_pago'];
    $estado_pago = ($metodo_pago === 'Fiado') ? 'pendiente' : 'pagado';
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

$mostrarVolver = true;
$volverUrl = '../index.php';
include '../../../includes/header.php';
include '../../../includes/navbar.php';

$productos = $pdo->query("SELECT * FROM productos WHERE activo = 1 AND cantidad > 0 ORDER BY categoria, nombre")->fetchAll(PDO::FETCH_OBJ);

$categorias = array_values(array_unique(array_filter(array_map(function ($p) {
    return trim((string) $p->categoria);
}, $productos), function ($c) {
    return $c !== '';
})));

$productosJson = json_encode(array_map(function ($p) {
    return [
        'id' => (int) $p->id_producto,
        'nombre' => $p->nombre,
        'categoria' => $p->categoria !== null ? (string) $p->categoria : '',
        'precio' => (float) $p->precio,
        'stock' => (int) $p->cantidad,
    ];
}, $productos), JSON_UNESCAPED_UNICODE);
?>

<div class="container px-3 mt-3 pb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-cart-plus"></i> Nueva Venta</h4>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="formVenta" class="row g-3">
        <?= campoCSRF() ?>
        <input type="hidden" name="accion" value="guardar_venta">
        <input type="hidden" name="id_alumno" id="id_alumno">
        <input type="hidden" name="id_usuario" id="id_usuario">
        <input type="hidden" name="tipo_comprador" id="tipo_comprador" value="otro">
        <div id="hiddenProductos"></div>

        <div class="col-lg-8">
            <div class="sticky-top rounded-bottom" style="top:76px; z-index:1020; background:var(--evo-bg,#f8f9fa);">
                <div class="d-flex gap-2 pt-1 pb-1">
                    <div class="input-group input-group-sm flex-fill">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="buscadorProducto" class="form-control" placeholder="Buscar producto por nombre o categoría...">
                    </div>
                </div>
                <div class="d-flex gap-1 flex-wrap py-2" id="filtroCategorias">
                    <button type="button" class="btn btn-sm btn-danger categoria-filtro active" data-cat="">Todos</button>
                    <?php foreach ($categorias as $cat): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger categoria-filtro" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row g-2" id="gridProductos"></div>
            <div id="sinResultados" class="text-center text-muted py-4 d-none"></div>
        </div>

        <div class="col-lg-4" id="panelCompra">
            <div class="card shadow">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cart"></i> Carrito</span>
                    <span id="badgeTotalItems" class="badge bg-light text-danger">0</span>
                </div>
                <div class="card-body">
                    <div id="listaCarrito"></div>
                    <div id="carritoVacio" class="text-muted text-center small py-3">
                        El carrito está vacío<br>Añadí productos con el botón <strong>Agregar</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total</span>
                        <span id="lblTotal">Gs 0</span>
                    </div>
                    <hr>
                    <label class="form-label small">Buscar comprador (alumno, profesor, tutor/a)</label>
                    <input type="text" id="buscadorComprador" class="form-control form-control-sm" placeholder="Escribe para buscar...">
                    <div id="resultadosBusqueda" class="list-group mt-1" style="display:none;"></div>
                    <div class="mt-2">
                        <label class="form-label small">Nombre comprador *</label>
                        <input type="text" name="nombre_comprador" id="nombre_comprador" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label class="form-label small">Fecha *</label>
                            <input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Método de pago</label>
                            <select name="metodo_pago" class="form-select form-select-sm">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Fiado">Fiado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control form-control-sm" placeholder="Ej: Cliente regular, descuento, etc.">
                    </div>
                    <button type="submit" class="btn btn-danger w-100 mt-3"><i class="bi bi-check-circle"></i> Registrar Venta</button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="d-lg-none fixed-bottom bg-white border-top px-3 py-2 d-flex justify-content-between align-items-center shadow" style="z-index:1030;">
    <div>
        <small class="text-muted d-block">Total</small>
        <strong id="lblTotalMovil">Gs 0</strong>
    </div>
    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCarrito">
        <i class="bi bi-cart"></i> Ver carrito <span class="badge bg-light text-danger" id="badgeTotalItemsMovil">0</span>
    </button>
</div>

<div class="offcanvas offcanvas-bottom" tabindex="-1" id="offcanvasCarrito" style="max-height:70vh;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><i class="bi bi-cart"></i> Carrito</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div id="listaCarritoMovil"></div>
        <div class="d-flex justify-content-between fw-bold my-3">
            <span>Total</span>
            <span id="lblTotalMovil2">Gs 0</span>
        </div>
        <button type="button" class="btn btn-danger w-100" id="btnIrDatos">Continuar con datos de la venta</button>
    </div>
</div>

<style>
.producto-card { min-height: 158px; transition: box-shadow .1s, transform .1s, border-color .15s; }
.producto-card:hover { box-shadow: 0 .25rem .5rem rgba(0,0,0,.08); transform: translateY(-2px); }
.producto-card .card-body { display: flex; flex-direction: column; }
.producto-card .card-title {
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    min-height: 2.4em; line-height: 1.2em;
}
.producto-card .categoria-producto { min-height: 1.4em; line-height: 1.2em; }
.producto-card .precio-producto { min-height: 1.5em; }
.producto-card .stock-producto { min-height: 1.2em; }
.producto-card .btn-agregar { white-space: nowrap; }
</style>

<script>
const PRODUCTOS = <?= $productosJson ?>;
const carrito = {};

function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function fmtGs(n) {
    return 'Gs ' + Math.round(n).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

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

function categoriaActiva() {
    const btn = document.querySelector('#filtroCategorias .categoria-filtro.active');
    return btn ? btn.dataset.cat : '';
}

function renderGrid() {
    const grid = document.getElementById('gridProductos');
    const q = document.getElementById('buscadorProducto').value.trim().toLowerCase();
    const cat = categoriaActiva();
    grid.innerHTML = '';
    let mostrados = 0;
    PRODUCTOS.forEach(p => {
        const coincideTexto = !q || p.nombre.toLowerCase().includes(q) || p.categoria.toLowerCase().includes(q);
        const coincideCat = cat === '' || p.categoria === cat;
        if (!coincideTexto || !coincideCat) return;
        mostrados++;
        const cant = carrito[p.id] || 0;
        const div = document.createElement('div');
        div.className = 'col-6 col-sm-4 col-lg-3';
        div.innerHTML = `
            <div class="card h-100 text-center producto-card ${cant ? 'border-danger' : ''}">
                <div class="card-body p-2">
                    <div class="card-title fw-bold mb-1">${esc(p.nombre)}</div>
                    <div class="categoria-producto">${p.categoria ? '<span class="badge bg-secondary">' + esc(p.categoria) + '</span>' : '&nbsp;'}</div>
                    <div class="fw-bold text-danger mt-1 precio-producto">${fmtGs(p.precio)}</div>
                    <div class="stock-producto small text-muted">Stock: ${p.stock}</div>
                    <button type="button" id="btnAgregar${p.id}" data-id="${p.id}" class="btn btn-sm w-100 btn-agregar mt-auto ${cant ? 'btn-success' : 'btn-danger'}">
                        ${cant ? 'En carrito (' + cant + ')' : '<i class="bi bi-plus-lg"></i> Agregar'}
                    </button>
                </div>
            </div>`;
        grid.appendChild(div);
    });
    const sr = document.getElementById('sinResultados');
    if (mostrados === 0) {
        sr.classList.remove('d-none');
        sr.textContent = PRODUCTOS.length === 0
            ? 'Sin productos disponibles.'
            : q ? 'Sin resultados para "' + document.getElementById('buscadorProducto').value + '"'
                : 'Sin productos en la categoría seleccionada.';
    } else {
        sr.classList.add('d-none');
    }
}

function agregarAlCarrito(id) {
    const p = PRODUCTOS.find(x => x.id === id);
    if (!p) return;
    const actual = carrito[id] || 0;
    if (actual < p.stock) {
        carrito[id] = actual + 1;
    } else {
        alert('Stock máximo disponible: ' + p.stock);
    }
    actualizarCarrito();
}

function actualizarBotones() {
    PRODUCTOS.forEach(p => {
        const btn = document.getElementById('btnAgregar' + p.id);
        if (!btn) return;
        const cant = carrito[p.id] || 0;
        btn.innerHTML = cant
            ? '<i class="bi bi-check-circle"></i> En carrito (' + cant + ')'
            : '<i class="bi bi-plus-lg"></i> Agregar';
        btn.classList.toggle('btn-success', !!cant);
        btn.classList.toggle('btn-danger', !cant);
        btn.closest('.producto-card').classList.toggle('border-danger', !!cant);
    });
}

function quitarDelCarrito(id) {
    carrito[id] = (carrito[id] || 1) - 1;
    if (carrito[id] <= 0) delete carrito[id];
    actualizarCarrito();
}

function eliminarDelCarrito(id) {
    delete carrito[id];
    actualizarCarrito();
}

function lineaCarrito(p) {
    const cant = carrito[p.id] || 0;
    if (cant <= 0) return '';
    return `
        <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2">
            <div class="flex-grow-1 pe-2">
                <div class="small fw-bold">${esc(p.nombre)}</div>
                <div class="text-muted small">${fmtGs(p.precio)} c/u</div>
                <div class="text-danger small fw-bold">${fmtGs(p.precio * cant)}</div>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm px-1" onclick="quitarDelCarrito(${p.id})"><i class="bi bi-dash"></i></button>
                <span class="small fw-bold" style="min-width:22px;text-align:center;">${cant}</span>
                <button type="button" class="btn btn-outline-secondary btn-sm px-1" onclick="agregarAlCarrito(${p.id})"><i class="bi bi-plus"></i></button>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm" title="Quitar del carrito" onclick="eliminarDelCarrito(${p.id})"><i class="bi bi-trash"></i></button>
        </div>`;
}

function actualizarCarrito() {
    let total = 0;
    let items = 0;
    let html = '';
    PRODUCTOS.forEach(p => {
        const cant = carrito[p.id] || 0;
        if (cant > 0) {
            total += p.precio * cant;
            items += cant;
            html += lineaCarrito(p);
        }
    });
    document.getElementById('listaCarrito').innerHTML = html;
    document.getElementById('listaCarritoMovil').innerHTML = html;
    document.getElementById('carritoVacio').style.display = items ? 'none' : '';
    const totalTxt = fmtGs(total);
    document.getElementById('lblTotal').textContent = totalTxt;
    document.getElementById('lblTotalMovil').textContent = totalTxt;
    document.getElementById('lblTotalMovil2').textContent = totalTxt;
    document.getElementById('badgeTotalItems').textContent = items;
    document.getElementById('badgeTotalItemsMovil').textContent = items;

    const cont = document.getElementById('hiddenProductos');
    cont.innerHTML = '';
    PRODUCTOS.forEach(p => {
        const cant = carrito[p.id] || 0;
        if (cant <= 0) return;
        cont.insertAdjacentHTML('beforeend',
            '<input type="hidden" name="productos[]" value="' + p.id + '">' +
            '<input type="hidden" name="cantidades[]" value="' + cant + '">');
    });
    actualizarBotones();
}

document.getElementById('gridProductos').addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-agregar');
    if (btn) agregarAlCarrito(parseInt(btn.dataset.id, 10));
});

document.getElementById('buscadorProducto').addEventListener('input', renderGrid);
document.getElementById('filtroCategorias').addEventListener('click', function(e) {
    const btn = e.target.closest('.categoria-filtro');
    if (!btn) return;
    this.querySelectorAll('.categoria-filtro').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderGrid();
});

document.getElementById('formVenta').addEventListener('submit', function(e) {
    if (Object.keys(carrito).length === 0) {
        e.preventDefault();
        alert('Agregá al menos un producto al carrito.');
    }
});

document.getElementById('btnIrDatos').addEventListener('click', function() {
    const ocEl = document.getElementById('offcanvasCarrito');
    const oc = bootstrap.Offcanvas.getInstance(ocEl) || bootstrap.Offcanvas.getOrCreateInstance(ocEl);
    oc.hide();
    document.getElementById('panelCompra').scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => document.getElementById('buscadorComprador').focus(), 400);
});

renderGrid();
actualizarCarrito();
</script>

<?php include '../../../includes/footer.php'; ?>