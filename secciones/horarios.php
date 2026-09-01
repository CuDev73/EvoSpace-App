<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

if (!tienePermiso('horarios') && $_SESSION['rol'] !== 'admin') {
    header('Location: /evospace/index.php');
    exit;
}

$mensaje = '';
$tipoMensaje = 'info';

$dias = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id_horario = (int)($_POST['id_horario'] ?? 0);
        $id_curso = (int)$_POST['id_curso'];
        $id_profesor = !empty($_POST['id_profesor']) ? (int)$_POST['id_profesor'] : null;
        $dias_semana = isset($_POST['dias_semana']) ? implode(',', array_map('intval', $_POST['dias_semana'])) : '1';
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];

        if ($id_horario > 0) {
            $stmt = $pdo->prepare("UPDATE horarios SET id_curso=?, id_profesor=?, dia_semana=?, hora_inicio=?, hora_fin=? WHERE id_horario=?");
            $stmt->execute([$id_curso, $id_profesor, $dias_semana, $hora_inicio, $hora_fin, $id_horario]);
            $mensaje = 'Horario actualizado.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO horarios (id_curso, id_profesor, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_curso, $id_profesor, $dias_semana, $hora_inicio, $hora_fin]);
            $mensaje = 'Horario creado.';
        }
        $tipoMensaje = 'success';
    }

    if ($accion === 'eliminar') {
        $id_horario = (int)$_POST['id_horario'];
        $stmt = $pdo->prepare("DELETE FROM horarios WHERE id_horario = ?");
        $stmt->execute([$id_horario]);
        $mensaje = 'Horario eliminado.';
        $tipoMensaje = 'success';
    }

    if ($accion === 'cupo') {
        $id_curso = (int)$_POST['id_curso'];
        $cupo = $_POST['cupo_maximo'] !== '' ? (int)$_POST['cupo_maximo'] : null;
        $stmt = $pdo->prepare("UPDATE cursos SET cupo_maximo = ? WHERE id_curso = ?");
        $stmt->execute([$cupo, $id_curso]);
        $mensaje = 'Cupo actualizado.';
        $tipoMensaje = 'success';
    }
}

$cursos = $pdo->query("SELECT id_curso, nombre, tipo, orden, cupo_maximo FROM cursos WHERE activo = 1 ORDER BY tipo, orden")->fetchAll(PDO::FETCH_ASSOC);

$profesores = $pdo->query("SELECT p.id_profesor, u.usuario FROM profesores p JOIN usuarios u ON p.id_usuario = u.id_usuario WHERE p.activo = 1 ORDER BY u.usuario")->fetchAll(PDO::FETCH_ASSOC);

$horarios = $pdo->query("
    SELECT h.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo, u.usuario AS profe_nombre
    FROM horarios h
    JOIN cursos c ON h.id_curso = c.id_curso
    LEFT JOIN profesores p ON h.id_profesor = p.id_profesor
    LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
    ORDER BY c.tipo, c.orden, h.dia_semana, h.hora_inicio
")->fetchAll(PDO::FETCH_ASSOC);

$horarios_por_curso = [];
foreach ($horarios as $h) {
    $horarios_por_curso[$h['id_curso']][] = $h;
}

$total_alumnos_por_curso = $pdo->query("SELECT id_curso, COUNT(*) AS total FROM alumnos WHERE activo = 1 GROUP BY id_curso")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<div class="container mt-3 pb-4">
    <div class="page-header">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-calendar-week me-2"></i>Horarios</h4>
            <small>Gestioná los horarios y cupos de cada curso</small>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show mt-3"><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php
    $tipos_cursos = ['Acrotelas', 'Infantil', 'Superior'];
    foreach ($tipos_cursos as $tipo):
        $cursos_tipo = array_filter($cursos, fn($c) => $c['tipo'] === $tipo);
        if (empty($cursos_tipo)) continue;
    ?>
        <h5 class="mt-4 text-secondary"><i class="bi bi-tag-fill"></i> <?= $tipo ?></h5>
        <div class="row g-3">
            <?php foreach ($cursos_tipo as $curso):
                $id = $curso['id_curso'];
                $horarios_curso = $horarios_por_curso[$id] ?? [];
                $cupo = $curso['cupo_maximo'];
                $inscriptos = $total_alumnos_por_curso[$id] ?? 0;
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-evo text-white d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><?= htmlspecialchars($curso['nombre']) ?></span>
                            <form method="POST" class="d-inline-flex align-items-center gap-1">
                                <?= campoCSRF() ?>
                                <input type="hidden" name="accion" value="cupo">
                                <input type="hidden" name="id_curso" value="<?= $id ?>">
                                <input type="number" name="cupo_maximo" value="<?= $cupo ?? '' ?>" placeholder="sin límite" class="form-control form-control-sm" style="width:80px" title="Cupo máximo (dejar vacío = sin límite)" onchange="this.form.submit()">
                                <span class="badge bg-light text-dark"><?= $inscriptos ?> insc.</span>
                            </form>
                        </div>
                        <div class="card-body">
                            <?php if (empty($horarios_curso)): ?>
                                <p class="text-muted mb-2 small">Sin horarios cargados.</p>
                            <?php else: ?>
                                <div class="mb-2">
                                    <?php foreach ($horarios_curso as $h): ?>
                                        <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                                            <span class="small">
                                                <?php
                                                $diasArray = explode(',', $h['dia_semana']);
                                                foreach ($diasArray as $d):
                                                    $d = (int)trim($d);
                                                ?>
                                                    <span class="badge bg-secondary me-1"><?= $dias[$d] ?? '?' ?></span>
                                                <?php endforeach; ?>
                                                <?= substr($h['hora_inicio'], 0, 5) ?> - <?= substr($h['hora_fin'], 0, 5) ?>
                                                <?php if ($h['profe_nombre']): ?>
                                                    <br><span class="text-muted"><?= htmlspecialchars($h['profe_nombre']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este horario?')">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_horario" value="<?= $h['id_horario'] ?>">
                                                <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-x"></i></button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-light text-dark border" onclick="cargarFormulario(<?= $id ?>, <?= htmlspecialchars(json_encode($curso['nombre'])) ?>)">
                                <i class="bi bi-plus-circle"></i> Agregar horario
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <!-- Modal para agregar/editar horario -->
    <div class="modal fade" id="modalHorario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-evo text-white">
                    <h5 class="modal-title"><i class="bi bi-clock"></i> <span id="modalTitulo">Nuevo horario</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?= campoCSRF() ?>
                        <input type="hidden" name="accion" value="guardar">
                        <input type="hidden" name="id_horario" id="id_horario" value="0">
                        <input type="hidden" name="id_curso" id="id_curso" value="">

                        <div class="mb-3">
                            <label class="form-label">Curso</label>
                            <input type="text" id="curso_nombre" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Días *</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($dias as $num => $dia): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="<?= $num ?>" id="dia_<?= $num ?>">
                                        <label class="form-check-label" for="dia_<?= $num ?>"><?= $dia ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Hora inicio *</label>
                                <input type="time" name="hora_inicio" id="hora_inicio" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Hora fin *</label>
                                <input type="time" name="hora_fin" id="hora_fin" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Profesor</label>
                            <select name="id_profesor" id="id_profesor" class="form-select">
                                <option value="">Sin asignar</option>
                                <?php foreach ($profesores as $p): ?>
                                    <option value="<?= $p['id_profesor'] ?>"><?= htmlspecialchars($p['usuario']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
</div>

<script>
function cargarFormulario(idCurso, nombreCurso) {
    document.getElementById('modalTitulo').textContent = 'Agregar horario a ' + nombreCurso;
    document.getElementById('id_horario').value = 0;
    document.getElementById('id_curso').value = idCurso;
    document.getElementById('curso_nombre').value = nombreCurso;
    document.querySelectorAll('input[name="dias_semana[]"]').forEach(cb => cb.checked = false);
    document.getElementById('hora_inicio').value = '';
    document.getElementById('hora_fin').value = '';
    document.getElementById('id_profesor').value = '';
    new bootstrap.Modal(document.getElementById('modalHorario')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
