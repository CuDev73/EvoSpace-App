<?php
session_start();

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] !== 'profesor' && $_SESSION['rol'] !== 'admin')) {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

$id_usuario = (int) $_SESSION['id_usuario'];

// Datos del profesor
$profesor = $pdo->prepare("SELECT * FROM profesores WHERE id_usuario = ?");
$profesor->execute([$id_usuario]);
$profesor = $profesor->fetch();

// Cursos con alumnos activos
$cursos = $pdo->query("
    SELECT DISTINCT c.id_curso, c.nombre, c.tipo, c.orden, COUNT(a.id_alumno) as total_alumnos
    FROM cursos c
    INNER JOIN alumnos a ON c.id_curso = a.id_curso
    WHERE c.activo = 1 AND a.activo = 1
    GROUP BY c.id_curso
    ORDER BY c.tipo, c.orden
")->fetchAll();

// Stats de hoy
$hoy = date('Y-m-d');
$statsHoy = $pdo->prepare("
    SELECT COUNT(*) as total, SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presentes
    FROM asistencia WHERE fecha = ? AND id_curso = ?
");

$tipos = array_unique(array_column($cursos, 'tipo'));
?>

<div class="container mt-3">
    <!-- Bienvenida -->
    <div class="bg-danger text-white p-4 rounded mb-4">
        <h3 class="fw-bold mb-1"><i class="bi bi-person-badge"></i> Panel del Profesor</h3>
        <p class="mb-0">Bienvenido, <?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '') ?></p>
        <?php if ($profesor && $profesor['salario_base'] > 0): ?>
            <small class="opacity-75">Salario base: Gs <?= number_format($profesor['salario_base'], 0, ',', '.') ?></small>
        <?php endif; ?>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow h-100 text-center">
                <div class="card-body">
                    <h6 class="text-muted">Cursos a cargo</h6>
                    <h2 class="fw-bold text-danger"><?= count($cursos) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow h-100 text-center">
                <div class="card-body">
                    <h6 class="text-muted">Alumnos totales</h6>
                    <h2 class="fw-bold text-info"><?= array_sum(array_column($cursos, 'total_alumnos')) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow h-100 text-center">
                <div class="card-body">
                    <h6 class="text-muted">Hoy</h6>
                    <h2 class="fw-bold text-success"><?= date('d/m/Y') ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Enlaces rápidos -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="/evospace/secciones/asistencia/index.php" class="btn btn-danger">
            <i class="bi bi-clipboard-check"></i> Ir a Asistencia
        </a>
        <a href="/evospace/secciones/eventos/eventos.php" class="btn btn-outline-danger">
            <i class="bi bi-calendar-event"></i> Eventos
        </a>
        <a href="/evospace/secciones/alumnos.php" class="btn btn-outline-danger">
            <i class="bi bi-people"></i> Alumnos
        </a>
    </div>

    <!-- Cursos -->
    <?php if (count($tipos) > 1): ?>
        <div class="mb-3 p-2 bg-light rounded border">
            <strong class="small"><i class="bi bi-list-ul"></i> Ir a:</strong>
            <?php foreach ($tipos as $tipo): ?>
                <a href="#tipo-<?= urlencode($tipo) ?>" class="btn btn-outline-danger btn-sm ms-1"><?= htmlspecialchars($tipo) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cursos)): ?>
        <div class="alert alert-warning">No hay cursos con alumnos asignados.</div>
    <?php else: ?>
        <?php
        $grupo_actual = '';
        foreach ($cursos as $curso):
            $statsHoy->execute([$hoy, $curso['id_curso']]);
            $hoyStats = $statsHoy->fetch();
            if ($curso['tipo'] != $grupo_actual):
                if ($grupo_actual != ''): ?>
                    <hr class="my-4 border-2 border-danger">
                <?php endif; ?>
                <div id="tipo-<?= urlencode($curso['tipo']) ?>" class="scroll-mt-3"></div>
                <h4 class="mt-3 mb-3 text-secondary"><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($curso['tipo']) ?></h4>
                <div class="row g-3">
            <?php
                $grupo_actual = $curso['tipo'];
            endif;
        ?>
            <div class="col-md-4 col-lg-3">
                <div class="card shadow h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?= htmlspecialchars($curso['nombre']) ?></h5>
                            <span class="badge bg-secondary"><?= $curso['total_alumnos'] ?> al.</span>
                        </div>
                        <?php if ($hoyStats && $hoyStats['total'] > 0): ?>
                            <p class="small mb-2">
                                <span class="text-success"><?= (int)$hoyStats['presentes'] ?> presentes</span>
                                <span class="text-muted">/ <?= (int)$hoyStats['total'] ?> hoy</span>
                            </p>
                        <?php else: ?>
                            <p class="small text-muted mb-2">Sin registro hoy</p>
                        <?php endif; ?>
                        <div class="d-flex gap-1 mt-auto flex-wrap">
                            <a href="/evospace/secciones/asistencia/registrar.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-success btn-sm flex-fill"><i class="bi bi-plus-circle"></i> Hoy</a>
                            <a href="/evospace/secciones/asistencia/mensual.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-danger btn-sm flex-fill"><i class="bi bi-calendar-month"></i> Mensual</a>
                            <a href="/evospace/secciones/asistencia/ver.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-info btn-sm flex-fill text-white"><i class="bi bi-eye"></i> Historial</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
