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

// Procesar agregar/editar proveedor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_proveedor'])) {
    $id = $_POST['id'] ?? '';
    $nombre = trim($_POST['nombre']);
    $nombre_contacto = trim($_POST['nombre_contacto']);
    $telefono = trim($_POST['telefono']);
    $whatsapp = trim($_POST['whatsapp']);
    $email = trim($_POST['email']);
    $direccion = trim($_POST['direccion']);
    $tipo_productos = trim($_POST['tipo_productos']);

    if (empty($nombre)) {
        $error = "El nombre o razón social es obligatorio.";
    } else {
        try {
            if ($id == '') {
                insertarProveedor($pdo, $nombre, $nombre_contacto, $telefono, $whatsapp, $email, $direccion, $tipo_productos);
                $mensaje = "Proveedor agregado correctamente.";
            } else {
                actualizarProveedor($pdo, $id, $nombre, $nombre_contacto, $telefono, $whatsapp, $email, $direccion, $tipo_productos);
                $mensaje = "Proveedor actualizado correctamente.";
            }
            header("Location: index.php?exito=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error al guardar proveedor: " . $e->getMessage();
        }
    }
}

// Eliminar proveedor
if (isset($_GET['eliminar_proveedor'])) {
    try {
        eliminarProveedor($pdo, (int) $_GET['eliminar_proveedor']);
        $mensaje = "Proveedor eliminado correctamente.";
        header("Location: index.php?exito=1");
        exit;
    } catch (PDOException $e) {
        $error = "Error al eliminar proveedor: " . $e->getMessage();
    }
}

// Procesar pago a proveedor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_pago_proveedor'])) {
    $id_pago = $_POST['id_pago'] ?? '';
    $id_proveedor = (int) $_POST['id_proveedor'];
    $fecha = $_POST['fecha'];
    $monto = (float) $_POST['monto'];
    $concepto = trim($_POST['concepto'] ?? '');
    if ($id_proveedor <= 0 || $monto <= 0) {
        $error = "Datos de pago inválidos.";
    } else {
        try {
            if ($id_pago == '') {
                insertarPagoProveedor($pdo, $id_proveedor, $fecha, $monto, $concepto);
                $mensaje = "Pago a proveedor registrado correctamente.";
            } else {
                $mensaje = "Pago registrado correctamente.";
            }
            header("Location: index.php?exito=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error al guardar pago: " . $e->getMessage();
        }
    }
}
if (isset($_GET['eliminar_pago_proveedor'])) {
    try {
        eliminarPagoProveedor($pdo, (int) $_GET['eliminar_pago_proveedor']);
        $mensaje = "Pago eliminado correctamente.";
        header("Location: index.php?exito=1");
        exit;
    } catch (PDOException $e) {
        $error = "Error al eliminar pago: " . $e->getMessage();
    }
}

include '../../../includes/header.php';
include '../../../includes/navbar.php';

$proveedores = obtenerProveedores($pdo);
$pagosProveedores = obtenerPagosProveedores($pdo);

if (isset($_GET['exito'])) {
    $mensaje = $mensaje ?: "Operación realizada correctamente.";
}
?>

<div class="container mt-3">
    <!-- Botones de Volver (arriba) -->
    <div class="row g-2 mb-3">
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

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Botón para agregar proveedor -->
    <div class="mb-3">
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalProveedor" onclick="limpiarFormularioProveedor()">
            <i class="bi bi-plus-circle"></i> Nuevo Proveedor
        </button>
    </div>

    <!-- Tabla proveedores -->
    <div class="card shadow mb-4">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-list"></i> Proveedores
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre/Razón Social</th>
                            <th>Contacto</th>
                            <th>Teléfono</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proveedores)): ?>
                            <tr><td colspan="7" class="text-center">No hay proveedores.</td></tr>
                        <?php else: ?>
                            <?php foreach ($proveedores as $p): ?>
                                <tr>
                                    <td><?= $p->id_proveedor ?></td>
                                    <td><?= htmlspecialchars($p->nombre) ?></td>
                                    <td><?= htmlspecialchars($p->nombre_contacto ?? '') ?></td>
                                    <td><?= htmlspecialchars($p->telefono ?? '') ?></td>
                                    <td>
                                        <?php
                                        $whatsapp_num = $p->whatsapp ?? $p->telefono ?? '';
                                        if (!empty($whatsapp_num)) {
                                            $numero_limpio = preg_replace('/[^0-9]/', '', $whatsapp_num);
                                            if (substr($numero_limpio, 0, 1) === '0') {
                                                $numero_limpio = '595' . substr($numero_limpio, 1);
                                            } elseif (substr($numero_limpio, 0, 3) !== '595' && strlen($numero_limpio) == 9) {
                                                $numero_limpio = '595' . $numero_limpio;
                                            }
                                            echo '<a href="https://wa.me/' . $numero_limpio . '" target="_blank" class="btn btn-success btn-sm" title="Enviar mensaje por WhatsApp">
                                                    <i class="bi bi-whatsapp"></i> ' . htmlspecialchars($whatsapp_num) . '
                                                  </a>';
                                        } else {
                                            echo '<span class="text-muted">Sin contacto</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($p->email ?? '') ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalProveedor" onclick="editarProveedor(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="bi bi-pencil"></i></button>
                                        <a href="index.php?eliminar_proveedor=<?= $p->id_proveedor ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este proveedor?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Formulario pago a proveedor -->
    <div class="card shadow mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="bi bi-cash"></i> Registrar Pago a Proveedor
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3" id="formPagoProveedor">
                <input type="hidden" name="accion_pago_proveedor" value="1">
                <input type="hidden" name="id_pago" id="id_pago" value="">
                <div class="col-md-3">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Proveedor *</label>
                    <select name="id_proveedor" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?= $p->id_proveedor ?>"><?= htmlspecialchars($p->nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monto (Gs) *</label>
                    <input type="number" step="0.01" name="monto" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Concepto</label>
                    <input type="text" name="concepto" class="form-control" placeholder="Opcional">
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-warning" id="btnGuardarPago">Guardar Pago</button>
                    <button type="reset" class="btn btn-secondary">Cancelar edición</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla pagos a proveedores -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-receipt"></i> Historial de Pagos a Proveedores
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th class="text-end">Monto</th>
                            <th>Concepto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagosProveedores)): ?>
                            <tr><td colspan="6" class="text-center">No hay pagos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pagosProveedores as $pp): ?>
                                <tr>
                                    <td><?= $pp->id_pago ?></td>
                                    <td><?= date('d/m/Y', strtotime($pp->fecha)) ?></td>
                                    <td><?= htmlspecialchars($pp->proveedor_nombre) ?></td>
                                    <td class="text-end"><?= number_format($pp->monto, 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($pp->concepto ?? '') ?></td>
                                    <td>
                                        <a href="index.php?eliminar_pago_proveedor=<?= $pp->id_pago ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este pago?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Botón Volver (abajo) -->
    <div class="mt-4">
        <a href="../index.php" class="btn btn-secondary w-100">
            <i class="bi bi-arrow-left"></i> Volver al panel de Cantina
        </a>
    </div>
</div>

<!-- Modal para Agregar/Editar Proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalTituloProveedor">Nuevo Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formProveedor">
                <div class="modal-body">
                    <input type="hidden" name="accion_proveedor" value="1">
                    <input type="hidden" name="id" id="proveedor_id" value="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre o Razón Social *</label>
                            <input type="text" name="nombre" id="proveedor_nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre del Contacto</label>
                            <input type="text" name="nombre_contacto" id="proveedor_contacto" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono (fijo o celular)</label>
                            <input type="text" name="telefono" id="proveedor_telefono" class="form-control" placeholder="Ej: 021-123456">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp (celular)</label>
                            <input type="text" name="whatsapp" id="proveedor_whatsapp" class="form-control" placeholder="Ej: 0961751338" oninput="formatearWhatsApp(this)">
                            <small class="text-muted">Se convertirá automáticamente a formato internacional (+595).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="email" id="proveedor_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Productos</label>
                            <input type="text" name="tipo_productos" id="proveedor_tipo_productos" class="form-control" placeholder="Ej: Alimentos, Bebidas, Útiles">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Dirección de Almacén/Oficinas</label>
                            <textarea name="direccion" id="proveedor_direccion" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnGuardarProveedor">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function limpiarFormularioProveedor() {
    document.getElementById('modalTituloProveedor').innerText = 'Nuevo Proveedor';
    document.getElementById('proveedor_id').value = '0';
    document.getElementById('proveedor_nombre').value = '';
    document.getElementById('proveedor_contacto').value = '';
    document.getElementById('proveedor_telefono').value = '';
    document.getElementById('proveedor_whatsapp').value = '';
    document.getElementById('proveedor_email').value = '';
    document.getElementById('proveedor_tipo_productos').value = '';
    document.getElementById('proveedor_direccion').value = '';
    document.getElementById('btnGuardarProveedor').innerText = 'Guardar';
}

function editarProveedor(data) {
    document.getElementById('modalTituloProveedor').innerText = 'Editar Proveedor';
    document.getElementById('proveedor_id').value = data.id_proveedor;
    document.getElementById('proveedor_nombre').value = data.nombre;
    document.getElementById('proveedor_contacto').value = data.nombre_contacto || '';
    document.getElementById('proveedor_telefono').value = data.telefono || '';
    document.getElementById('proveedor_whatsapp').value = data.whatsapp || '';
    document.getElementById('proveedor_email').value = data.email || '';
    document.getElementById('proveedor_tipo_productos').value = data.tipo_productos || '';
    document.getElementById('proveedor_direccion').value = data.direccion || '';
    document.getElementById('btnGuardarProveedor').innerText = 'Actualizar';
}

function formatearWhatsApp(input) {
    let valor = input.value.replace(/\D/g, '');
    if (valor.length === 0) {
        input.value = '';
        return;
    }
    if (valor[0] === '0' && valor.length > 1) {
        valor = '595' + valor.substring(1);
    } else if (valor.length === 9 && valor[0] !== '5' && valor.substring(0,3) !== '595') {
        valor = '595' + valor;
    } else if (valor.length === 10 && valor.substring(0,3) !== '595' && valor[0] !== '0') {
        if (valor.substring(0,3) !== '595') {
            valor = '595' + valor;
        }
    }
    if (valor.length > 3) {
        input.value = '+' + valor;
    } else {
        input.value = valor;
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>