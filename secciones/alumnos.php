<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// secciones/alumnos.php

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../includes/header.php';   // ← primero carga functions.php
include '../includes/navbar.php';
require_once '../config/db.php';

verificarPermiso('alumnos');   // ← ahora funciona

$mensaje = '';
$tipoMensaje = 'info';

// ==========================================================
// PROCESAR ACCIONES DEL FORMULARIO
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ---------- ELIMINAR ----------
    if ($accion === 'eliminar' && isset($_POST['id_alumno'])) {
        $id = (int)$_POST['id_alumno'];
        $stmt = $pdo->prepare("DELETE FROM alumnos WHERE id_alumno = ?");
        if ($stmt->execute([$id])) {
            $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Alumno eliminado correctamente.';
            $tipoMensaje = 'success';
        } else {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al eliminar.';
            $tipoMensaje = 'danger';
        }
    }

    // ---------- GUARDAR (AGREGAR / EDITAR) ----------
    if ($accion === 'guardar') {
        $id_alumno = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $id_curso = (int)$_POST['id_curso'];
        $anio_ingreso = (int)$_POST['anio_ingreso'];
        $horas_profesionales = (float)($_POST['horas_profesionales'] ?? 0);
        $ci = trim($_POST['ci']);
        $telefono = trim($_POST['telefono']);
        $id_padre = !empty($_POST['id_padre']) ? (int)$_POST['id_padre'] : NULL;
        $becado = isset($_POST['becado']) ? 1 : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre) || empty($apellido) || empty($ci)) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Nombre, apellido y cédula son obligatorios.';
            $tipoMensaje = 'danger';
        } else {
            try {
                if ($id_alumno > 0) {
                    // EDITAR
                    $sql = "UPDATE alumnos SET 
                                nombre=?, apellido=?, id_curso=?, anio_ingreso=?, 
                                horas_profesionales=?, ci=?, telefono=?, id_padre=?, becado=?, activo=?
                            WHERE id_alumno=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $apellido, $id_curso, $anio_ingreso, $horas_profesionales, $ci, $telefono, $id_padre, $becado, $activo, $id_alumno]);
                    $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Alumno actualizado correctamente.';
                    $tipoMensaje = 'success';
                } else {
                    // AGREGAR
                    $sql = "INSERT INTO alumnos (nombre, apellido, id_curso, anio_ingreso, horas_profesionales, ci, telefono, id_padre, becado, activo)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $apellido, $id_curso, $anio_ingreso, $horas_profesionales, $ci, $telefono, $id_padre, $becado, $activo]);
                    $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Alumno creado correctamente.';
                    $tipoMensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
        }
    }
}

// ==========================================================
// OBTENER LISTA DE ALUMNOS CON DATOS DE CURSO Y PADRE
// ==========================================================
$sql = "SELECT a.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo,
               u.usuario AS nombre_padre, u.email AS email_padre
        FROM alumnos a
        INNER JOIN cursos c ON a.id_curso = c.id_curso
        LEFT JOIN usuarios u ON a.id_padre = u.id_usuario
        ORDER BY a.id_alumno DESC";
$stmt = $pdo->query($sql);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de cursos para el formulario
$stmt = $pdo->query("SELECT id_curso, nombre, tipo FROM cursos WHERE activo = 1 ORDER BY tipo, orden");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de padres para el formulario (CORREGIDO: usa id_rol)
$stmt = $pdo->query("SELECT id_usuario, usuario, email FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY usuario");
$padres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-3 pb-4">
    <div class="bg-danger text-white p-3 rounded mb-3 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="h4 fw-bold mb-0"><i class="bi bi-people-fill me-2"></i>Gestión de Alumnos</h4>
            <small>Administra los alumnos del sistema</small>
        </div>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalAlumno" onclick="limpiarFormulario()">
            <i class="bi bi-person-plus-fill me-1"></i> Nuevo Alumno
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Buscador -->
    <div class="row g-2 mb-3">
        <div class="col-md-12">
            <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar alumno por nombre o apellido...">
        </div>
    </div>

    <!-- Tabla de alumnos -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white py-2">
            <i class="bi bi-people-fill"></i> Alumnos Registrados
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="tablaAlumnos">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Curso</th>
                            <th>Año Ingreso</th>
                            <th>Horas Prof.</th>
                            <th>CI</th>
                            <th>Teléfono</th>
                            <th>Padre</th>
                            <th>Becado</th>
                            <th>Activo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alumnos)): ?>
                            <tr><td colspan="11" class="text-center">No hay alumnos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($alumnos as $alumno): ?>
                                <tr>
                                    <td class="text-center align-middle"><?= $alumno['id_alumno'] ?></td>
                                    <td class="nombre-alumno align-middle"><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($alumno['curso_tipo'] . ' - ' . $alumno['curso_nombre']) ?></td>
                                    <td class="text-center align-middle"><?= $alumno['anio_ingreso'] ?></td>
                                    <td class="text-center align-middle"><?= $alumno['horas_profesionales'] ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($alumno['ci']) ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($alumno['telefono']) ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($alumno['nombre_padre'] ?? 'Sin asignar') ?></td>
                                    <td class="text-center align-middle">
                                        <?= $alumno['becado'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?= $alumno['activo'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAlumno"
                                                    onclick="editarAlumno(<?= htmlspecialchars(json_encode($alumno)) ?>)">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este alumno?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_alumno" value="<?= $alumno['id_alumno'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </div>
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

<!-- ========================================================== -->
<!-- MODAL para AGREGAR / EDITAR ALUMNO -->
<!-- ========================================================== -->
<div class="modal fade" id="modalAlumno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalTituloAlumno"><i class="bi bi-person-plus-fill me-2"></i>Nuevo Alumno</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formAlumno">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_alumno" id="id_alumno" value="0">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" id="apellido" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Curso *</label>
                            <select name="id_curso" id="id_curso" class="form-select" required>
                                <?php foreach ($cursos as $curso): ?>
                                    <option value="<?= $curso['id_curso'] ?>"><?= htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Año de ingreso *</label>
                            <input type="number" name="anio_ingreso" id="anio_ingreso" class="form-control" required min="2000" max="2099">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Horas profesionales</label>
                            <input type="number" step="0.01" name="horas_profesionales" id="horas_profesionales" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="ci" id="ci" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Padre/Madre</label>
                            <select name="id_padre" id="id_padre" class="form-select">
                                <option value="">Sin asignar</option>
                                <?php foreach ($padres as $padre): ?>
                                    <option value="<?= $padre['id_usuario'] ?>"><?= htmlspecialchars($padre['usuario'] . ' (' . $padre['email'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="becado" id="becado" class="form-check-input" value="1">
                                <label class="form-check-label" for="becado">Becado</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="activo" id="activo" class="form-check-input" checked>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
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
        document.getElementById('modalTituloAlumno').innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Nuevo Alumno';
        document.getElementById('id_alumno').value = '0';
        document.getElementById('nombre').value = '';
        document.getElementById('apellido').value = '';
        document.getElementById('id_curso').value = '';
        document.getElementById('anio_ingreso').value = new Date().getFullYear();
        document.getElementById('horas_profesionales').value = '0';
        document.getElementById('ci').value = '';
        document.getElementById('telefono').value = '';
        document.getElementById('id_padre').value = '';
        document.getElementById('becado').checked = false;
        document.getElementById('activo').checked = true;
    }

    function editarAlumno(alumno) {
        document.getElementById('modalTituloAlumno').innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Alumno';
        document.getElementById('id_alumno').value = alumno.id_alumno;
        document.getElementById('nombre').value = alumno.nombre;
        document.getElementById('apellido').value = alumno.apellido;
        document.getElementById('id_curso').value = alumno.id_curso;
        document.getElementById('anio_ingreso').value = alumno.anio_ingreso;
        document.getElementById('horas_profesionales').value = alumno.horas_profesionales || 0;
        document.getElementById('ci').value = alumno.ci;
        document.getElementById('telefono').value = alumno.telefono || '';
        document.getElementById('id_padre').value = alumno.id_padre || '';
        document.getElementById('becado').checked = (alumno.becado == 1);
        document.getElementById('activo').checked = (alumno.activo == 1);
    }

    // Buscador en tiempo real
    document.addEventListener('DOMContentLoaded', function() {
        const buscador = document.getElementById('buscador');
        const tabla = document.getElementById('tablaAlumnos');
        if (buscador && tabla) {
            buscador.addEventListener('keyup', function() {
                const filtro = this.value.toLowerCase();
                const filas = tabla.querySelectorAll('tbody tr');
                filas.forEach(fila => {
                    const nombre = fila.cells[1].textContent.toLowerCase();
                    fila.style.display = nombre.includes(filtro) ? '' : 'none';
                });
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>