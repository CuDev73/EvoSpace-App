<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once '../../config/db.php';
require_once '../../helpers/asistencia.php';
verificarPermiso('asistencia');

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

if ($id_curso == 0) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $presentes = $_POST['presente'] ?? [];
    $observaciones = $_POST['observacion'] ?? [];
    $estados = [];
    $alumnoIds = array_unique(array_merge(array_keys($presentes), array_keys($observaciones)));
    foreach ($alumnoIds as $id_alumno) {
        $estados[(int)$id_alumno] = [
            'presente' => !empty($presentes[$id_alumno]) ? 1 : 0,
            'observaciones' => trim($observaciones[$id_alumno] ?? ''),
        ];
    }
    try {
        guardarAsistenciaDiaria($pdo, $id_curso, $fecha, $estados);
        header('Location: registrar.php?id_curso=' . $id_curso . '&fecha=' . $fecha . '&guardado=1');
        exit;
    } catch (Exception $e) {
        $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> Error: ' . $e->getMessage() . '</div>';
    }
}

$mostrarVolver = true;
$volverUrl = 'index.php';
include '../../includes/header.php';
include '../../includes/navbar.php';

$stmt = $pdo->prepare("SELECT nombre, tipo FROM cursos WHERE id_curso = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: index.php');
    exit;
}

$dataAsistencia = obtenerAlumnosConAsistencia($pdo, $id_curso, $fecha);
$alumnos = $dataAsistencia['alumnos'];
$asistenciasExistentes = $dataAsistencia['asistencias'];

$mensaje = $mensaje ?? '';
if (isset($_GET['guardado']) && $_GET['guardado'] == 1) {
    $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Asistencia guardada correctamente.</div>';
}
?>
<div class="container mt-3">
    <div class="bg-danger text-white p-4 rounded mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="h3 fw-bold"><i class="bi bi-clipboard-check"></i> Registrar Asistencia</h3>
            <p class="mb-0"><?= htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) ?> | <?= date('d/m/Y', strtotime($fecha)) ?></p>
        </div>
        <?php if ($fecha === date('Y-m-d')): ?>
            <span class="badge bg-light text-dark fs-6 px-3"><i class="bi bi-sun"></i> Hoy</span>
        <?php endif; ?>
    </div>

    <?= $mensaje ?>

    <div class="card shadow">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people-fill"></i> Lista de alumnos</span>
            <div>
                <button type="button" class="btn btn-light btn-sm me-2" onclick="marcarTodos(true)">
                    <i class="bi bi-check-all"></i> Todos presentes
                </button>
                <button type="button" class="btn btn-light btn-sm" onclick="marcarTodos(false)">
                    <i class="bi bi-x-lg"></i> Todos ausentes
                </button>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" id="formAsistencia">
                <?= campoCSRF() ?>
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
                                    $asistencia = $asistenciasExistentes[$alumno['id_alumno']] ?? null;
                                    $presente = $asistencia ? $asistencia['presente'] : 1;
                                    $obs = $asistencia ? $asistencia['observaciones'] : '';
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
                                            <input type="text" name="observacion[<?= $alumno['id_alumno'] ?>]" class="form-control form-control-sm" placeholder="Observación" value="<?= htmlspecialchars($obs) ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save"></i> Guardar asistencia
                    </button>
                    <a href="mensual.php?id_curso=<?= $id_curso ?>" class="btn btn-outline-danger">
                        <i class="bi bi-calendar-month"></i> Vista mensual
                    </a>
                </div>
            </form>
        </div>
    </div>

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
                    <button type="submit" class="btn btn-primary">Ver esta fecha</button>
                </div>
                <div class="col-md-4 d-flex align-items-end justify-content-end">
                    <a href="registrar.php?id_curso=<?= $id_curso ?>" class="btn btn-outline-secondary">Hoy</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function marcarTodos(presente) {
    document.querySelectorAll('input[name^="presente"]').forEach(cb => {
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
