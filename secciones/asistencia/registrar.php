<?php
session_start();

// Permitir acceso a profesores y administradores
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

include '../../includes/header.php';
include '../../includes/navbar.php';
require_once '../../config/db.php';
verificarPermiso('asistencia');

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

if ($id_curso == 0) {
    header('Location: /evospace/roles/profesor.php');
    exit;
}

// Obtener datos del curso
$stmt = $pdo->prepare("SELECT nombre, tipo FROM cursos WHERE id_curso = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: /evospace/roles/profesor.php');
    exit;
}

// Obtener alumnos del curso
$stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE id_curso = ? AND activo = 1 ORDER BY apellido, nombre");
$stmt->execute([$id_curso]);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener asistencias ya registradas para esta fecha
$asistenciasExistentes = [];
if (!empty($alumnos)) {
    $ids = array_column($alumnos, 'id_alumno');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id_alumno, presente, observaciones FROM asistencia WHERE id_curso = ? AND fecha = ? AND id_alumno IN ($placeholders)");
    $stmt->execute(array_merge([$id_curso, $fecha], $ids));
    $asistenciasExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
}

// Mensaje de éxito/error
$mensaje = '';
if (isset($_GET['guardado']) && $_GET['guardado'] == 1) {
    $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Asistencia guardada correctamente.</div>';
}
?>

<div class="container mt-3">
    <!-- Encabezado -->
    <div class="bg-danger text-white p-4 rounded mb-4">
        <h3 class="h3 fw-bold"><i class="bi bi-clipboard-check"></i> Registrar Asistencia</h3>
        <p class="mb-0"><?= htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) ?> | <?= date('d/m/Y', strtotime($fecha)) ?></p>
    </div>

    <?= $mensaje ?>

    <!-- Formulario de asistencia -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people-fill"></i> Lista de alumnos</span>
            <div>
                <button type="button" class="btn btn-light btn-sm me-2" onclick="marcarTodos(true)">
                    <i class="bi bi-check-all"></i> Marcar todos presentes
                </button>
                <button type="button" class="btn btn-light btn-sm" onclick="marcarTodos(false)">
                    <i class="bi bi-x-lg"></i> Marcar todos ausentes
                </button>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="guardar.php" id="formAsistencia">
                <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                <input type="hidden" name="fecha" value="<?= $fecha ?>">

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Alumno</th>
                                <th class="text-center">Presente</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alumnos)): ?>
                                <tr><td colspan="4" class="text-center">No hay alumnos en este curso.</td></tr>
                            <?php else: ?>
                                <?php foreach ($alumnos as $index => $alumno): 
                                    $asistencia = $asistenciasExistentes[$alumno['id_alumno']][0] ?? null;
                                    $presente = $asistencia ? $asistencia['presente'] : 1;
                                    $observacion = $asistencia ? $asistencia['observaciones'] : '';
                                ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($alumno['apellido'] . ' ' . $alumno['nombre']) ?></td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox" name="presente[<?= $alumno['id_alumno'] ?>]" value="1" <?= $presente ? 'checked' : '' ?>>
                                                <label class="form-check-label">
                                                    <span class="badge bg-<?= $presente ? 'success' : 'danger' ?> status-badge"><?= $presente ? 'Presente' : 'Ausente' ?></span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="observacion[<?= $alumno['id_alumno'] ?>]" class="form-control form-control-sm" placeholder="Observación" value="<?= htmlspecialchars($observacion) ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save"></i> Guardar asistencia
                    </button>
                    <a href="/evospace/roles/profesor.php?id_curso=<?= $id_curso ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                    <a href="ver.php?id_curso=<?= $id_curso ?>" class="btn btn-outline-primary">
                        <i class="bi bi-eye"></i> Ver historial
                    </a>
                    <a href="exportar_excel.php?id_curso=<?= $id_curso ?>" class="btn btn-success ms-auto">
                        <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cambiar fecha -->
    <div class="card shadow mt-4">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-calendar3"></i> Cambiar fecha
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="<?= $fecha ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Ver/editar esta fecha</button>
                </div>
                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <a href="registrar.php?id_curso=<?= $id_curso ?>" class="btn btn-outline-secondary">Volver a hoy</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function marcarTodos(presente) {
        const checkboxes = document.querySelectorAll('input[name^="presente"]');
        checkboxes.forEach(cb => {
            cb.checked = presente;
            cb.dispatchEvent(new Event('change'));
        });
    }

    document.querySelectorAll('input[name^="presente"]').forEach(cb => {
        cb.addEventListener('change', function() {
            const badge = this.closest('tr').querySelector('.status-badge');
            if (this.checked) {
                badge.textContent = 'Presente';
                badge.className = 'badge bg-success status-badge';
            } else {
                badge.textContent = 'Ausente';
                badge.className = 'badge bg-danger status-badge';
            }
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>