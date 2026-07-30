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
            // Verificar duplicados
            $stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE ci = ? AND id_alumno != ?");
            $stmt->execute([$ci, $id_alumno]);
            $dup = $stmt->fetch();
            if ($dup) {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Ya existe un alumno con CI \'<strong>' . htmlspecialchars($ci) . '</strong>\' asignado a <strong>' . htmlspecialchars($dup['nombre'] . ' ' . $dup['apellido']) . '</strong>.';
                $tipoMensaje = 'danger';
            } else {
            try {
                if (!$id_alumno) {
                    $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Usá Inscripciones para dar de alta nuevos alumnos.';
                    $tipoMensaje = 'danger';
                } else {
                    $sql = "UPDATE alumnos SET 
                                nombre=?, apellido=?, id_curso=?, anio_ingreso=?, 
                                horas_profesionales=?, ci=?, telefono=?, id_padre=?, becado=?, activo=?
                            WHERE id_alumno=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $apellido, $id_curso, $anio_ingreso, $horas_profesionales, $ci, $telefono, $id_padre, $becado, $activo, $id_alumno]);
                    $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Alumno actualizado correctamente.';
                    $tipoMensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
            }
        }
    }
}

// ==========================================================
// OBTENER LISTA DE ALUMNOS CON DATOS DE CURSO Y PADRE
// ==========================================================
$esProfesor = ($_SESSION['rol'] ?? '') === 'profesor';
$id_profesor = null;
if ($esProfesor) {
    $stmt = $pdo->prepare("SELECT id_profesor FROM profesores WHERE id_usuario = ?");
    $stmt->execute([(int)$_SESSION['id_usuario']]);
    $id_profesor = $stmt->fetchColumn();
}

$sql = "SELECT a.id_alumno, a.nombre, a.apellido, a.id_curso, a.anio_ingreso, a.ci, a.telefono, a.id_padre, a.becado, a.activo,
               COALESCE((SELECT SUM(horas) FROM horas_profesionales_log WHERE id_alumno = a.id_alumno), 0) AS horas_profesionales,
               c.nombre AS curso_nombre, c.tipo AS curso_tipo,
               u.usuario AS nombre_padre, u.email AS email_padre
        FROM alumnos a
        INNER JOIN cursos c ON a.id_curso = c.id_curso
        LEFT JOIN usuarios u ON a.id_padre = u.id_usuario";
if ($esProfesor && $id_profesor) {
    $sql .= " INNER JOIN horarios h ON h.id_curso = c.id_curso AND h.id_profesor = ?";
}
$sql .= " ORDER BY a.id_alumno DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($esProfesor && $id_profesor ? [$id_profesor] : []);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de cursos para el formulario
$stmt = $pdo->query("SELECT id_curso, nombre, tipo FROM cursos WHERE activo = 1 ORDER BY tipo, orden");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de padres para el formulario (CORREGIDO: usa id_rol)
$stmt = $pdo->query("SELECT id_usuario, usuario, email FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY usuario");
$padres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-3 pb-4">
    <div class="page-header">
        <div>
            <h4 class="h4 fw-bold mb-0"><i class="bi bi-people-fill me-2"></i>Gestión de Alumnos</h4>
            <small>Administra los alumnos del sistema</small>
        </div>
        <a href="inscripciones.php" class="btn btn-light btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Nueva Inscripción
        </a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros: buscador + curso -->
    <div class="row g-2 mb-3">
        <div class="col-md-8">
            <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar alumno por nombre, apellido o CI...">
        </div>
        <div class="col-md-4">
            <select id="filtroCurso" class="form-select form-select-sm">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursos as $curso): ?>
                    <option value="<?= htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) ?>"><?= htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Tabla de alumnos -->
    <div class="card shadow">
        <div class="card-header bg-evo text-white py-2">
            <i class="bi bi-people-fill"></i> Alumnos Registrados
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="tablaAlumnos">
                    <thead class="text-center" style="background: var(--evo-bg-alt);">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Curso</th>
                            <th>Año Ingreso</th>
                            <th>Horas Prof.</th>
                            <th>CI</th>
                            <th>Teléfono</th>
                            <th>Padre</th>
                            <th>Descto.</th>
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
                                            <a href="ficha_alumno.php?id=<?= $alumno['id_alumno'] ?>" class="btn btn-info btn-sm text-white" title="Ver ficha">
                                                <i class="bi bi-file-person-fill"></i>
                                            </a>
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
<!-- MODAL para EDITAR ALUMNO -->
<!-- ========================================================== -->
<div class="modal fade" id="modalAlumno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title" id="modalTituloAlumno"><i class="bi bi-pencil-fill me-2"></i>Editar Alumno</h5>
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
                            <div style="position:relative;">
                                <input type="text" id="buscarPadre" class="form-control mb-1" placeholder="Buscar padre por nombre o email..." autocomplete="off">
                                <input type="hidden" name="id_padre" id="id_padre" value="">
                                <div id="listaPadres" class="list-group" style="position:absolute;z-index:1000;display:none;max-height:180px;overflow-y:auto;width:100%;top:100%;left:0;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="becado" id="becado" class="form-check-input" value="1">
                                    <label class="form-check-label" for="becado">Descuento</label>
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
                    <button type="submit" class="btn btn-danger">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const padres = <?= json_encode(array_map(function($p) {
        return ['id' => $p['id_usuario'], 'usuario' => $p['usuario'], 'email' => $p['email']];
    }, $padres)) ?>;

    function buscarPadre(valor) {
        const lista = document.getElementById('listaPadres');
        const hidden = document.getElementById('id_padre');
        if (!valor.trim()) { lista.style.display = 'none'; hidden.value = ''; return; }
        const term = valor.toLowerCase();
        const filtrados = padres.filter(p =>
            p.usuario.toLowerCase().includes(term) || p.email.toLowerCase().includes(term)
        );
        if (filtrados.length === 0) {
            lista.style.display = 'none';
            return;
        }
        lista.innerHTML = filtrados.map(p =>
            `<button type="button" class="list-group-item list-group-item-action py-1" onclick="seleccionarPadre(${p.id},'${p.usuario.replace(/'/g,"\\'")}')">${p.usuario} <small class="text-muted">(${p.email})</small></button>`
        ).join('');
        lista.style.display = 'block';
    }

    function seleccionarPadre(id, usuario) {
        document.getElementById('buscarPadre').value = usuario;
        document.getElementById('id_padre').value = id;
        document.getElementById('listaPadres').style.display = 'none';
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
        const padreMatch = padres.find(p => p.id == alumno.id_padre);
        document.getElementById('buscarPadre').value = padreMatch ? padreMatch.usuario : '';
        document.getElementById('becado').checked = (alumno.becado == 1);
        document.getElementById('activo').checked = (alumno.activo == 1);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const buscador = document.getElementById('buscador');
        const filtroCurso = document.getElementById('filtroCurso');
        const tabla = document.getElementById('tablaAlumnos');
        
        function filtrar() {
            if (!tabla) return;
            const texto = (buscador ? buscador.value.toLowerCase() : '');
            const curso = (filtroCurso ? filtroCurso.value.toLowerCase() : '');
            const filas = tabla.querySelectorAll('tbody tr');
            filas.forEach(fila => {
                const nombre = fila.cells[1]?.textContent.toLowerCase() || '';
                const cursoCelda = fila.cells[2]?.textContent.toLowerCase() || '';
                const ciCelda = fila.cells[5]?.textContent.toLowerCase() || '';
                const matchTexto = !texto || nombre.includes(texto) || ciCelda.includes(texto);
                const matchCurso = !curso || cursoCelda.includes(curso);
                fila.style.display = (matchTexto && matchCurso) ? '' : 'none';
            });
        }

        if (buscador) buscador.addEventListener('keyup', filtrar);
        if (filtroCurso) filtroCurso.addEventListener('change', filtrar);

        const inputPadre = document.getElementById('buscarPadre');
        if (inputPadre) {
            inputPadre.addEventListener('input', function() { buscarPadre(this.value); });
            inputPadre.addEventListener('blur', function() {
                setTimeout(() => {
                    const lista = document.getElementById('listaPadres');
                    if (lista) lista.style.display = 'none';
                }, 200);
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>