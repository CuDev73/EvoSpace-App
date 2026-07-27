<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

include '../includes/header.php';
include '../includes/navbar.php';

$error = '';
$success = false;
$mensaje_abono = '';

// ============================================================
// PROCESAR ACTUALIZACIÓN DE SALARIO
// ============================================================
if (isset($_REQUEST['guardar_salario'])) {
    $id_usuario = (int)$_REQUEST['id_usuario'];
    $salario_base = floatval($_REQUEST['salario_base']);
    $activo = isset($_REQUEST['activo']) ? 1 : 0;

    if (guardarSalarioProfesor($pdo, $id_usuario, $salario_base, $activo)) {
        $success = true;
        header("Location: profesores.php?success=1");
        exit();
    } else {
        $error = "Error al guardar salario.";
    }
}

// ============================================================
// PAGAR SALARIO COMPLETO (genera un abono por el saldo pendiente)
// ============================================================
if (isset($_REQUEST['pagar_salario'])) {
    $id_usuario = (int)$_REQUEST['id_usuario'];
    $mes = date('m');
    $anio = date('Y');
    $pendiente = salarioPendienteProfesor($pdo, $id_usuario, $mes, $anio);
    
    if ($pendiente > 0) {
        // Obtener nombre del profesor
        $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id_usuario = :id");
        $stmt->execute(['id' => $id_usuario]);
        $nombre = $stmt->fetchColumn();
        
        $fecha = date('Y-m-d');
        $result = insertarAbono($pdo, $fecha, $nombre, $pendiente);
        if ($result) {
            $mensaje_abono = "Salario pagado correctamente (Gs " . number_format($pendiente, 0, ',', '.') . ")";
            header("Location: profesores.php?success_abono=1");
            exit();
        } else {
            $error = "Error al registrar el pago.";
        }
    } else {
        $error = "Este profesor no tiene saldo pendiente este mes.";
    }
}

// ============================================================
// PROCESAR ABONOS (manuales)
// ============================================================
if (isset($_REQUEST['idBorrarAbono'])) {
    $id = (int)$_REQUEST['idBorrarAbono'];
    if (eliminarAbono($pdo, $id)) {
        $mensaje_abono = "Abono eliminado correctamente.";
    } else {
        $error = "Error al eliminar abono.";
    }
    header("Location: profesores.php");
    exit();
}

$idEditarAbono = isset($_GET['idEditarAbono']) ? (int)$_GET['idEditarAbono'] : 0;
$abono_edit = null;
if ($idEditarAbono > 0) {
    $abono_edit = obtenerAbonoPorId($pdo, $idEditarAbono);
}

if (isset($_REQUEST['guardarAbono'])) {
    $id_abono = $_REQUEST['id_abono'] ?? '';
    $fecha_abono = $_REQUEST['fecha_abono'];
    $profesor_abono = trim($_REQUEST['profesor']);
    $monto_abono = floatval($_REQUEST['monto_abono']);

    if (empty($profesor_abono) || $monto_abono <= 0) {
        $error = "El nombre del profesor y el monto son obligatorios.";
    } else {
        if ($id_abono == '') {
            $result = insertarAbono($pdo, $fecha_abono, $profesor_abono, $monto_abono);
            if ($result) {
                $mensaje_abono = "Abono registrado correctamente.";
                header("Location: profesores.php?success_abono=1");
                exit();
            } else {
                $error = "Error al insertar abono.";
            }
        } else {
            $result = actualizarAbono($pdo, $id_abono, $fecha_abono, $profesor_abono, $monto_abono);
            if ($result) {
                $mensaje_abono = "Abono actualizado correctamente.";
                header("Location: profesores.php?success_abono=1");
                exit();
            } else {
                $error = "Error al actualizar abono.";
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

// Abonos con filtro por profesor
$filtroProfesor = isset($_GET['filtro_profesor']) ? (int)$_GET['filtro_profesor'] : 0;
if ($filtroProfesor > 0) {
    $abonos = obtenerAbonosPorProfesor($pdo, $filtroProfesor, $mesActual, $anioActual);
} else {
    $abonos = obtenerAbonos($pdo);
}
$total_abonos = array_sum(array_column($abonos, 'monto_abono'));

$listaProfesores = obtenerListaProfesores($pdo);

if (isset($_GET['success'])) {
    $success = true;
}
if (isset($_GET['success_abono'])) {
    $mensaje_abono = "Abono registrado correctamente.";
}

// Variables para el formulario de abono (edición)
$id_abono = $abono_edit ? $abono_edit['id_abono'] : '';
$fecha_abono = $abono_edit ? $abono_edit['fecha_abono'] : date('Y-m-d');
$profesor_abono = $abono_edit ? $abono_edit['profesor'] : '';
$monto_abono = $abono_edit ? $abono_edit['monto_abono'] : '';
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
    <!-- SECCIÓN PROFESORES                                         -->
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

    <!-- Tabla de Profesores (con abonos del mes y pendiente) -->
    <div class="card shadow mb-4">
        <div class="card-header bg-danger text-white py-2">
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
                                        <?php if (($fila["salario_pendiente"] ?? 0) > 0): ?>
                                            <a href="profesores.php?pagar_salario=1&id_usuario=<?= $fila['id_usuario'] ?>" 
                                               class="btn btn-success btn-sm" 
                                               onclick="return confirm('¿Pagar salario completo (Gs <?= number_format($fila['salario_pendiente'], 0, ',', '.') ?>) a <?= htmlspecialchars($fila['usuario']) ?>?')">
                                                <i class="bi bi-cash"></i> Pagar
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pagado</span>
                                        <?php endif; ?>
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

    <!-- ========================================================== -->
    <!-- SECCIÓN ABONOS                                             -->
    <!-- ========================================================== -->
    <div class="row g-2 mb-4 align-items-center">
        <div class="col-md-4">
            <h5 class="mb-0 text-secondary"><i class="bi bi-cash-stack"></i> Gestión de Abonos</h5>
        </div>
        <div class="col-md-4">
            <form method="GET" class="d-flex">
                <select name="filtro_profesor" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                    <option value="0">Todos los profesores</option>
                    <?php foreach ($listaProfesores as $p): ?>
                        <option value="<?= $p['id_usuario'] ?>" <?= ($filtroProfesor == $p['id_usuario']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="profesores.php" class="btn btn-secondary btn-sm">Limpiar</a>
            </form>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success btn-sm w-100 w-md-auto" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAbono" aria-expanded="<?= ($idEditarAbono > 0) ? 'true' : 'false' ?>" aria-controls="collapseAbono">
                <i class="bi bi-plus-circle-fill"></i> <?= $idEditarAbono > 0 ? "Editar Abono" : "Nuevo Abono" ?>
            </button>
        </div>
    </div>

    <!-- Formulario de Abonos -->
    <div class="collapse <?= ($idEditarAbono > 0) ? 'show' : '' ?> mb-4" id="collapseAbono">
        <div class="card shadow">
            <div class="card-header <?= $idEditarAbono > 0 ? 'bg-warning text-dark' : 'bg-success text-white' ?> py-2">
                <i class="bi <?= $idEditarAbono > 0 ? 'bi-pencil-square' : 'bi-plus-circle-fill' ?>"></i>
                <?= $idEditarAbono > 0 ? "Actualizar Abono ID: $idEditarAbono" : "Registrar Nuevo Abono" ?>
            </div>
            <div class="card-body">
                <form method="POST" action="profesores.php">
                    <input type="hidden" name="id_abono" value="<?= $id_abono ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1 small">Profesor *</label>
                            <input type="text" name="profesor" class="form-control form-control-sm" list="listaProfesores" value="<?= htmlspecialchars($profesor_abono) ?>" placeholder="Escribe para buscar..." required autocomplete="off">
                            <datalist id="listaProfesores">
                                <?php foreach ($listaProfesores as $p): ?>
                                    <option value="<?= htmlspecialchars($p['nombre_completo']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1 small">Fecha del Abono *</label>
                            <input type="date" name="fecha_abono" class="form-control form-control-sm" value="<?= htmlspecialchars($fecha_abono) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1 small">Monto (Gs) *</label>
                            <input type="number" step="0.01" name="monto_abono" class="form-control form-control-sm" value="<?= htmlspecialchars($monto_abono) ?>" required>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end mt-3">
                            <?php if ($idEditarAbono > 0): ?>
                                <a href="profesores.php" class="btn btn-secondary btn-sm">Cancelar Edición</a>
                            <?php endif; ?>
                            <button type="submit" name="guardarAbono" class="btn <?= $idEditarAbono > 0 ? 'btn-warning' : 'btn-success' ?> btn-sm">
                                <i class="bi bi-save-fill"></i> <?= $idEditarAbono > 0 ? 'Actualizar' : 'Guardar' ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabla de Abonos -->
    <div class="card shadow mb-4">
        <div class="card-header bg-danger text-white py-2">
            <i class="bi bi-receipt"></i> Registro de Abonos
            <span class="badge bg-light text-dark ms-2"><?= count($abonos) ?></span>
            <?php if ($filtroProfesor > 0): ?>
                <span class="badge bg-info ms-2">Filtrado por profesor</span>
            <?php endif; ?>
        </div>
        <div class="card-body p-2">
            <!-- Buscador para abonos -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <input type="text" id="buscadorAbonos" class="form-control form-control-sm" placeholder="Buscar abono por profesor o ID...">
                </div>
            </div>

            <?php if (empty($abonos)): ?>
                <div class="alert alert-info mb-0">No hay abonos registrados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="tablaAbonos">
                        <thead class="text-center">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Profesor</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abonos as $abono): ?>
                                <tr data-profesor="<?= strtolower(htmlspecialchars($abono["profesor"])) ?>" data-id="<?= $abono['id_abono'] ?>">
                                    <td class="text-center align-middle"><?= $abono["id_abono"] ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($abono["fecha_abono"]) ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($abono["profesor"]) ?></td>
                                    <td class="text-center align-middle"><?= number_format($abono["monto_abono"], 0, ',', '.') ?> Gs</td>
                                    <td class="text-center align-middle">
                                        <a href="profesores.php?idEditarAbono=<?= $abono['id_abono'] ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-pencil-square"></i> <span class="d-none d-sm-inline">Editar</span>
                                        </a>
                                        <a href="profesores.php?idBorrarAbono=<?= $abono['id_abono'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar este abono?')">
                                            <i class="bi bi-trash-fill"></i> <span class="d-none d-sm-inline">Borrar</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="3" class="text-center">Total de abonos:</th>
                                <th class="text-center"><?= number_format($total_abonos, 0, ',', '.') ?> Gs</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal para editar salario -->
<div class="modal fade" id="modalSalario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-cash"></i> Editar Salario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_usuario" id="salario_id_usuario">
                    <div class="mb-3">
                        <label class="form-label">Profesor</label>
                        <input type="text" id="salario_usuario" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Salario Base (Gs)</label>
                        <input type="number" step="0.01" name="salario_base" id="salario_base" class="form-control" required>
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

<script>
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

        // Buscador de abonos
        const buscadorAbonos = document.getElementById('buscadorAbonos');
        const tablaAbonos = document.getElementById('tablaAbonos');
        if (buscadorAbonos && tablaAbonos) {
            buscadorAbonos.addEventListener('keyup', function() {
                const filtro = this.value.toLowerCase();
                const filas = tablaAbonos.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                for (let fila of filas) {
                    const profesor = fila.dataset.profesor || '';
                    const id = fila.dataset.id || '';
                    const texto = profesor + ' ' + id;
                    fila.style.display = texto.includes(filtro) ? '' : 'none';
                }
            });
        }
    });

    function cargarSalario(data) {
        document.getElementById('salario_id_usuario').value = data.id_usuario;
        document.getElementById('salario_usuario').value = data.usuario;
        document.getElementById('salario_base').value = data.salario_base || '';
        document.getElementById('salario_activo').value = data.activo || 1;
    }
</script>

<?php
include '../includes/footer.php';
ob_end_flush();
?>