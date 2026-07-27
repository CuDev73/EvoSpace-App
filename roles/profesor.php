<?php
session_start();

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] !== 'profesor' && $_SESSION['rol'] !== 'admin')) {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

// Obtener todos los cursos con alumnos activos
$cursos = $pdo->query("
    SELECT DISTINCT c.id_curso, c.nombre, c.tipo, COUNT(a.id_alumno) as total_alumnos
    FROM cursos c
    INNER JOIN alumnos a ON c.id_curso = a.id_curso
    WHERE c.activo = 1 AND a.activo = 1
    GROUP BY c.id_curso
    ORDER BY c.tipo, c.orden
")->fetchAll();

// Obtener lista única de tipos para el índice
$tipos = array_unique(array_column($cursos, 'tipo'));
?>

<div class="container mt-3">

    <!-- Encabezado -->
    <div class="bg-danger text-white p-4 rounded mb-4">
        <h3 class="h3 fw-bold"><i class="bi bi-clipboard-check"></i> Panel de Asistencia</h3>
        <p class="mb-0">Selecciona un curso para gestionar la asistencia</p>
    </div>

    <!-- ÍNDICE DE TIPOS -->
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

    <?php if (empty($cursos)): ?>
        <div class="alert alert-warning">No hay cursos con alumnos asignados.</div>
    <?php else: ?>
        <?php
        $grupo_actual = '';
        foreach ($cursos as $curso):
            // Mostrar título de grupo cuando cambia el tipo
            if ($curso['tipo'] != $grupo_actual):
                if ($grupo_actual != ''): ?>
                    <hr class="my-4 border-2 border-danger">
                <?php endif; ?>
                <!-- ANCLA para el índice -->
                <div id="tipo-<?= urlencode($curso['tipo']) ?>" class="scroll-mt-3"></div>
                <h4 class="mt-4 mb-3 text-secondary">
                    <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($curso['tipo']) ?>
                </h4>
                <div class="row g-3">
            <?php
                $grupo_actual = $curso['tipo'];
            endif;
        ?>
            <div class="col-md-4 col-lg-3">
                <div class="card shadow h-100 text-center">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        <i class="bi bi-book fs-1 text-danger"></i>
                        <h5 class="card-title mt-2"><?= htmlspecialchars($curso['nombre']) ?></h5>
                        <p class="card-text text-muted small"><?= $curso['total_alumnos'] ?> alumnos</p>
                        <div class="d-flex gap-2 mt-2 flex-wrap justify-content-center">
                            <a href="/evospace/secciones/asistencia/registrar.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-plus-circle"></i> Hoy
                            </a>
                            <a href="/evospace/secciones/asistencia/mensual.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-danger btn-sm">
                                <i class="bi bi-calendar-month"></i> Mensual
                            </a>
                            <a href="/evospace/secciones/asistencia/ver.php?id_curso=<?= $curso['id_curso'] ?>" class="btn btn-info btn-sm text-white">
                                <i class="bi bi-eye"></i> Historial
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div> <!-- cierra el último row del grupo -->
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>