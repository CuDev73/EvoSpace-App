<?php
ob_start();
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/funciones.php';
verificarPermiso('profesores');

$error = '';
$success = false;
$mensaje_abono = '';

// ============================================================
// PROCESAR ACTUALIZACIÓN DE SALARIO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_salario'])) {
    verificarTokenCSRF();
    $id_usuario = (int)$_POST['id_usuario'];
    $salario_base = floatval($_POST['salario_base']);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (guardarSalarioProfesor($pdo, $id_usuario, $salario_base, $activo)) {
        header("Location: profesores.php?success=1");
        exit();
    } else {
        $error = "Error al guardar salario.";
    }
}

// ============================================================
// PROCESAR ABONOS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar_abono') {
    verificarTokenCSRF();
    $id = (int)$_POST['id_abono'];
    $abono = obtenerAbonoPorId($pdo, $id);
    if ($abono && $abono['imagen']) {
        $imgPath = __DIR__ . '/../' . $abono['imagen'];
        if (file_exists($imgPath)) unlink($imgPath);
    }
    if (eliminarAbono($pdo, $id)) {
        header("Location: profesores.php");
        exit();
    } else {
        $error = "Error al eliminar abono.";
    }
}

if (isset($_POST['guardarAbono'])) {
    $id_usuario = (int)$_POST['id_usuario_abono'];
    $fecha_abono = $_POST['fecha_abono'];
    $monto_abono = floatval($_POST['monto_abono']);
    $descripcion = trim($_POST['descripcion_abono'] ?? '');

    $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $profesor = $stmt->fetchColumn();

    if (empty($profesor) || $monto_abono <= 0) {
        $error = "Datos inválidos.";
    } else {
        $imagen = null;
        if (!empty($_FILES['imagen_abono']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen_abono']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $error = "Formato de imagen no válido.";
            } else {
                $nombre = 'abono_' . uniqid() . '.' . $ext;
                $destino = 'uploads/abonos/' . $nombre;
                if (move_uploaded_file($_FILES['imagen_abono']['tmp_name'], __DIR__ . '/../' . $destino)) {
                    $imagen = $destino;
                } else {
                    $error = "Error al subir la imagen.";
                }
            }
        }
        if (!$error) {
            if (insertarAbono($pdo, $fecha_abono, $profesor, $monto_abono, $descripcion, $imagen)) {
                header("Location: profesores.php?success_abono=1");
                exit();
            } else {
                $error = "Error al registrar el abono.";
            }
        }
    }
}

// ============================================================
// OBTENER DATOS
// ============================================================
$mesActual = date('m');
$anioActual = date('Y');

$profesores = obtenerProfesoresConAbonos($pdo, $mesActual, $anioActual);
$total_salarios = array_sum(array_column($profesores, 'salario_base'));
$total_abonos_mes = array_sum(array_column($profesores, 'abonos_mes'));
$total_pendiente = array_sum(array_column($profesores, 'salario_pendiente'));

$todosAbonos = $pdo->query("
    SELECT a.*, u.id_usuario, u.nombre_completo, u.usuario
    FROM abonos a
    JOIN usuarios u ON a.profesor = u.usuario
    ORDER BY a.fecha_abono DESC
")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
include '../includes/navbar.php';

if (isset($_GET['success'])) $success = true;
if (isset($_GET['success_abono'])) $mensaje_abono = "Abono registrado correctamente.";
?>

<div class="container mt-3">

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> Salario guardado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($mensaje_abono): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i> <?= $mensaje_abono ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ========================================================== -->
    <!-- TABLA DE PROFESORES                                        -->
    <!-- ========================================================== -->
    <div class="row g-2 mb-3 align-items-center">
        <div class="col-md-8">
            <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar profesor por nombre o usuario...">
        </div>
        <div class="col-md-4 text-end">
            <a href="/evospace/secciones/usuarios.php" class="btn btn-success btn-sm">
                <i class="bi bi-person-plus-fill"></i> Crear nuevo profesor
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header bg-evo text-white py-2">
            <i class="bi bi-people-fill"></i> Profesores Registrados
            <span class="badge bg-light text-dark ms-2"><?= count($profesores) ?></span>
        </div>
        <div class="card-body p-2">
            <?php if (empty($profesores)): ?>
                <div class="alert alert-warning mb-0">No hay profesores registrados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="tablaProfesores">
                        <thead class="text-center">
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Salario Base</th>
                                <th>Abonos (mes)</th>
                                <th>Pendiente</th>
                                <th>Activo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profesores as $fila): ?>
                                <tr>
                                    <td class="text-center align-middle"><?= $fila["id_usuario"] ?></td>
                                    <td class="nombre-profesor align-middle"><?= htmlspecialchars($fila["usuario"]) ?></td>
                                    <td class="text-end align-middle">
                                        <?= number_format($fila["salario_base"] ?? 0, 0, ',', '.') ?> Gs
                                    </td>
                                    <td class="text-end align-middle">
                                        <?= number_format($fila["abonos_mes"] ?? 0, 0, ',', '.') ?> Gs
                                    </td>
                                    <td class="text-end align-middle <?= ($fila["salario_pendiente"] ?? 0) > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                                        <?= number_format($fila["salario_pendiente"] ?? 0, 0, ',', '.') ?> Gs
                                    </td>
                                    <td class="text-center align-middle">
                                        <?= ($fila["prof_activo"] ?? 1) == 1 ? '<i class="bi bi-check-circle-fill text-success fs-5"></i>' : '<i class="bi bi-x-circle-fill text-danger fs-5"></i>' ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSalario"
                                                onclick="cargarSalario(<?= htmlspecialchars(json_encode([
                                                    'id_usuario' => $fila['id_usuario'],
                                                    'usuario' => $fila['usuario'],
                                                    'salario_base' => $fila['salario_base'] ?? 0,
                                                    'activo' => $fila['prof_activo'] ?? 1
                                                ])) ?>)">
                                            <i class="bi bi-pencil-square"></i> <span class="d-none d-sm-inline">Salario</span>
                                        </button>
                                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalPagos"
                                                onclick="cargarPagos(<?= $fila['id_usuario'] ?>)">
                                            <i class="bi bi-cash"></i> <span class="d-none d-sm-inline">Pagos</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="2" class="text-end">Totales:</th>
                                <th class="text-end"><?= number_format($total_salarios, 0, ',', '.') ?> Gs</th>
                                <th class="text-end"><?= number_format($total_abonos_mes, 0, ',', '.') ?> Gs</th>
                                <th class="text-end"><?= number_format($total_pendiente, 0, ',', '.') ?> Gs</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal Editar Salario -->
<div class="modal fade" id="modalSalario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-cash"></i> Editar Salario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="id_usuario" id="salario_id_usuario">
                    <div class="mb-3">
                        <label class="form-label">Profesor</label>
                        <input type="text" id="salario_usuario" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Salario Base (Gs)</label>
                        <input type="number" name="salario_base" id="salario_base" class="form-control" required data-moneda>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Activo</label>
                        <select name="activo" id="salario_activo" class="form-select">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="guardar_salario" class="btn btn-primary">Guardar Salario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pagos a Profesor -->
<div class="modal fade" id="modalPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-cash-stack"></i> Pagos a <span id="pagosProfesorNombre"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pagosIdUsuario">

                <!-- Info saldo -->
                <div class="row g-2 mb-3 text-center" id="pagosInfo">
                    <div class="col-4"><small class="text-muted">Salario base</small><br><strong id="pagosSalarioBase">0</strong></div>
                    <div class="col-4"><small class="text-muted">Abonado este mes</small><br><strong id="pagosAbonado">0</strong></div>
                    <div class="col-4"><small class="text-muted">Pendiente</small><br><strong id="pagosPendiente" class="text-danger">0</strong></div>
                </div>

                <!-- Formulario nuevo pago -->
                <div class="card border-success mb-3">
                    <div class="card-header bg-success text-white py-1"><small><i class="bi bi-plus-circle"></i> Nuevo pago</small></div>
                    <div class="card-body py-2">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_usuario_abono" id="idUsuarioAbono">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small">Fecha</label>
                                    <input type="date" name="fecha_abono" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Monto (Gs) *</label>
                                    <input type="number" name="monto_abono" class="form-control form-control-sm" required data-moneda>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Descripción</label>
                                    <input type="text" name="descripcion_abono" class="form-control form-control-sm" placeholder="Ej: Transferencia">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Comprobante</label>
                                    <input type="file" name="imagen_abono" class="form-control form-control-sm" accept="image/*">
                                </div>
                                <div class="col-12 text-end mt-2">
                                    <button type="submit" name="guardarAbono" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Registrar pago</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de pagos -->
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="tablaAbonosModal">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Descripción</th>
                                <th>Comprobante</th>
                                <th>Recibo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="abonosBody"></tbody>
                        <tfoot class="table-dark" id="abonosFoot" style="display:none;">
                            <tr><th colspan="2" class="text-end">Total:</th><th id="abonosTotal" colspan="5"></th></tr>
                        </tfoot>
                    </table>
                </div>
                <p class="text-muted small text-center mt-2 mb-0" id="abonosEmpty">Sin pagos registrados.</p>
            </div>
        </div>
    </div>
</div>

<script>
const abonosData = <?= json_encode($todosAbonos) ?>;
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>";
const profesoresData = <?= json_encode($profesores) ?>;

function cargarPagos(idUsuario) {
    document.getElementById('pagosIdUsuario').value = idUsuario;
    document.getElementById('idUsuarioAbono').value = idUsuario;

    const prof = profesoresData.find(p => p.id_usuario == idUsuario);
    if (prof) {
        document.getElementById('pagosProfesorNombre').textContent = prof.usuario;
        document.getElementById('pagosSalarioBase').textContent = 'Gs ' + Number(prof.salario_base || 0).toLocaleString('es-PY');
        document.getElementById('pagosAbonado').textContent = 'Gs ' + Number(prof.abonos_mes || 0).toLocaleString('es-PY');
        document.getElementById('pagosPendiente').textContent = 'Gs ' + Number(prof.salario_pendiente || 0).toLocaleString('es-PY');
    }

    const abonos = abonosData.filter(a => a.id_usuario == idUsuario);
    const tbody = document.getElementById('abonosBody');
    tbody.innerHTML = '';

    if (abonos.length === 0) {
        document.getElementById('abonosEmpty').style.display = '';
        document.getElementById('abonosFoot').style.display = 'none';
    } else {
        document.getElementById('abonosEmpty').style.display = 'none';
        document.getElementById('abonosFoot').style.display = '';
        let total = 0;
        abonos.forEach(a => {
            total += parseFloat(a.monto_abono);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${a.id_abono}</td>
                <td>${a.fecha_abono}</td>
                <td class="text-end">Gs ${Number(a.monto_abono).toLocaleString('es-PY')}</td>
                <td>${a.descripcion ? escHtml(a.descripcion) : '-'}</td>
                <td>${a.imagen ? '<a href="/evospace/' + a.imagen + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-image"></i></a>' : '-'}</td>
                <td><a href="recibo_profesor.php?id_abono=${a.id_abono}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-file-pdf"></i></a></td>
                <td><form method="POST" class="d-inline" onsubmit="return confirm('Eliminar este pago?')">
                    <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                    <input type="hidden" name="accion" value="borrar_abono">
                    <input type="hidden" name="id_abono" value="${a.id_abono}">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form></td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('abonosTotal').textContent = 'Gs ' + total.toLocaleString('es-PY');
        document.getElementById('abonosTotal').colSpan = 5;
    }
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Buscador de profesores
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscador');
    const tabla = document.getElementById('tablaProfesores');
    if (buscador && tabla) {
        buscador.addEventListener('keyup', function() {
            const filtro = this.value.toLowerCase();
            const filas = tabla.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let fila of filas) {
                const nombre = fila.cells[1].textContent.toLowerCase();
                fila.style.display = nombre.includes(filtro) ? '' : 'none';
            }
        });
    }
});

function cargarSalario(data) {
    document.getElementById('salario_id_usuario').value = data.id_usuario;
    document.getElementById('salario_usuario').value = data.usuario;
    document.getElementById('salario_base').value = Math.round(data.salario_base) || '';
    document.getElementById('salario_activo').value = data.activo || 1;
}
</script>

<?php
include '../includes/footer.php';
ob_end_flush();
?>
