<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../config/db.php';
require_once '../../helpers/functions.php';
verificarPermiso('asistencia');

$mostrarVolver = true;
$volverUrl = '/evospace/roles/' . ($_SESSION['rol'] ?? 'admin') . '.php';
include '../../includes/header.php';
include '../../includes/navbar.php';

$cursos = $pdo->query("
    SELECT DISTINCT c.id_curso, c.nombre, c.tipo, c.orden, COUNT(a.id_alumno) as total_alumnos
    FROM cursos c
    INNER JOIN alumnos a ON c.id_curso = a.id_curso
    WHERE c.activo = 1 AND a.activo = 1
    GROUP BY c.id_curso
    ORDER BY c.tipo, c.orden
")->fetchAll();

$tipos = array_unique(array_column($cursos, 'tipo'));

// Estadísticas de hoy
$hoy = date('Y-m-d');
$statsHoy = $pdo->prepare("
    SELECT COUNT(*) as total, SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presentes
    FROM asistencia WHERE fecha = ? AND id_curso = ?
");
?>

<div class="container mt-3">
    <div class="page-header mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-clipboard-check me-2"></i> Registro de Asistencia</h4>
            <small>Seleccioná un curso para gestionar la asistencia</small>
        </div>
    </div>

    <?php if (count($tipos) > 1): ?>
        <div class="mb-4 p-3 bg-light rounded border">
            <strong><i class="bi bi-list-ul"></i> Ir a:</strong>
            <?php foreach ($tipos as $tipo): ?>
                <a href="#tipo-<?= urlencode($tipo) ?>" class="btn btn-outline-danger btn-sm ms-2">
                    <?= htmlspecialchars($tipo) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="input-group mb-3" style="max-width:420px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="buscarCursoAsistencia" class="form-control" placeholder="Buscar curso...">
    </div>

    <?php if (empty($cursos)): ?>
        <div class="alert alert-warning">No hay cursos con alumnos asignados.</div>
    <?php else: ?>
        <?php
        $grupo_actual = '';
        foreach ($cursos as $curso):
            if ($curso['tipo'] != $grupo_actual):
                if ($grupo_actual != ''): ?>
                    <hr class="my-4 border-2 border-danger">
                <?php endif; ?>
                <div id="tipo-<?= urlencode($curso['tipo']) ?>" class="scroll-mt-3"></div>
                <h4 class="mt-4 mb-3 text-secondary">
                    <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($curso['tipo']) ?>
                </h4>
                <div class="row g-3">
            <?php
                $grupo_actual = $curso['tipo'];
            endif;
        ?>
            <div class="col-md-4 col-lg-3" data-tipo="<?= htmlspecialchars($curso['tipo']) ?>" data-nombre="<?= htmlspecialchars($curso['nombre']) ?>">
                <div class="card shadow h-100 text-center">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        <i class="bi bi-book fs-1 text-danger"></i>
                        <h5 class="card-title mt-2"><?= htmlspecialchars($curso['nombre']) ?></h5>
                        <p class="card-text text-muted small"><?= $curso['total_alumnos'] ?> alumnos</p>
                        <div class="d-flex gap-2 mt-2 flex-wrap justify-content-center">
                            <a href="registrar.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-plus-circle"></i> Hoy
                            </a>
                            <a href="mensual.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-danger btn-sm">
                                <i class="bi bi-calendar-month"></i> Mensual
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bus = document.getElementById('buscarCursoAsistencia');
    if (!bus) return;
    bus.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('[data-nombre]').forEach(col => {
            const texto = ((col.dataset.nombre || '') + ' ' + (col.dataset.tipo || '')).toLowerCase();
            col.style.display = (!q || texto.includes(q)) ? '' : 'none';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
