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
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

if ($id_curso == 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos del curso
$stmt = $pdo->prepare("SELECT nombre, tipo FROM cursos WHERE id_curso = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: index.php');
    exit;
}

// Obtener alumnos del curso
$alumnos = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE id_curso = ? AND activo = 1 ORDER BY apellido, nombre");
$alumnos->execute([$id_curso]);
$alumnos = $alumnos->fetchAll(PDO::FETCH_ASSOC);

if (empty($alumnos)) {
    header('Location: index.php?error=No+hay+alumnos');
    exit;
}

$diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
$diasConRegistros = range(1, $diasEnMes);

// Obtener asistencias existentes
$ids = array_column($alumnos, 'id_alumno');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT id_alumno, DAY(fecha) as dia, presente FROM asistencia WHERE id_curso = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ? AND id_alumno IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$id_curso, $mes, $anio], $ids));
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$asistencias = [];
foreach ($resultados as $row) {
    $asistencias[$row['id_alumno']][$row['dia']] = $row['presente'];
}

$mensaje = '';
if (isset($_GET['guardado'])) {
    $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Asistencia mensual guardada correctamente.</div>';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    verificarTokenCSRF();
    $id_curso_post = (int)$_POST['id_curso'];
    $mes_post = (int)$_POST['mes'];
    $anio_post = (int)$_POST['anio'];
    $estados = $_POST['estado'] ?? [];

    try {
        guardarAsistenciaMensual($pdo, $id_curso_post, $mes_post, $anio_post, $estados);
        header('Location: mensual.php?id_curso=' . $id_curso_post . '&mes=' . $mes_post . '&anio=' . $anio_post . '&guardado=1');
        exit;
    } catch (Exception $e) {
        $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> Error al guardar: ' . $e->getMessage() . '</div>';
    }
}

// Obtener meses y años para navegación
$meses = [];
for ($m=1; $m<=12; $m++) {
    $meses[$m] = date('F', mktime(0,0,0,$m,1));
}
$anios = range(date('Y')-2, date('Y')+1);
?>
    <?php $mostrarVolver = true; $volverUrl = 'index.php'; ?>
    <?php include '../../includes/header.php'; ?>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container mt-3">
        <div class="bg-danger text-white p-4 rounded mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="h3 fw-bold"><i class="bi bi-calendar-month"></i> Asistencia Mensual</h3>
                <p class="mb-0"><?= htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) ?> | <?= $meses[$mes] . ' ' . $anio ?></p>
            </div>
            <!-- Ya no van botones aquí, solo el título -->
        </div>

        <?= $mensaje ?>

        <!-- Navegación de mes -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                    <div class="col-md-3">
                        <label class="form-label">Mes</label>
                        <select name="mes" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($meses as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= $num == $mes ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Año</label>
                        <select name="anio" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($anios as $a): ?>
                                <option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="mensual.php?id_curso=<?= $id_curso ?>" class="btn btn-outline-secondary w-100">Mes actual</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla mensual -->
        <div class="card shadow">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table"></i> Días con registros</span>
                <span class="badge bg-light text-dark"><?= count($diasConRegistros) ?> días</span>
            </div>
            <div class="card-body p-0" style="overflow-x: auto;">
                <form method="POST">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="id_curso" value="<?= $id_curso ?>">
                    <input type="hidden" name="mes" value="<?= $mes ?>">
                    <input type="hidden" name="anio" value="<?= $anio ?>">
                    <table class="table table-bordered table-sm table-hover mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="min-width: 120px; position: sticky; left: 0; background: white; z-index: 1;">Alumno</th>
                                <?php foreach ($diasConRegistros as $dia): ?>
                                    <th style="min-width: 35px;" class="text-center">
                                        <?= $dia ?>
                                        <br><small class="text-muted"><?= date('D', strtotime("$anio-$mes-$dia")) ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos as $alumno): ?>
                                <tr>
                                    <td class="fw-bold" style="position: sticky; left: 0; background: white; z-index: 1;">
                                        <?= htmlspecialchars($alumno['apellido'] . ' ' . $alumno['nombre']) ?>
                                    </td>
                                    <?php foreach ($diasConRegistros as $dia): 
                                        $estado = $asistencias[$alumno['id_alumno']][$dia] ?? 0;
                                    ?>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" name="estado[<?= $alumno['id_alumno'] ?>][<?= $dia ?>]" value="1" <?= $estado ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="marcarTodos(true)">Marcar todos presentes</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="marcarTodos(false)">Marcar todos ausentes</button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="guardar" class="btn btn-danger">
                                <i class="bi bi-save"></i> Guardar mes
                            </button>
                            <a href="exportar_excel_mensual.php?id_curso=<?= $id_curso ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-success">
                                <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function marcarTodos(estado) {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = estado);
        }
    </script>

    <?php include '../../includes/footer.php'; ?>