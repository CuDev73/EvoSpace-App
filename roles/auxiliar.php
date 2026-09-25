<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'auxiliar') {
    header('Location: /evospace/index.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

$hoy = date('Y-m-d');
$hora = (int)date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 18 ? 'Buenas tardes' : 'Buenas noches');
$nombreUsuario = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'EvoSpace';

$diasES = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mesesES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$diaSemana = (int)date('N');
$diaNum = (int)date('j');
$mesNum = (int)date('n') - 1;
$fechaFormateada = $diasES[$diaSemana] . ', ' . $diaNum . ' de ' . $mesesES[$mesNum] . ' de ' . date('Y');

$misSecciones = [
    'asistencia'    => ['titulo' => 'Asistencia',        'url' => '/evospace/secciones/asistencia/index.php', 'icono' => 'bi-clipboard-check', 'color' => 'danger'],
    'alumnos'       => ['titulo' => 'Alumnos / Inscripciones', 'url' => '/evospace/secciones/alumnos.php', 'icono' => 'bi-people-fill', 'color' => 'primary'],
    'cantina'       => ['titulo' => 'Cantina',           'url' => '/evospace/secciones/cantina/index.php', 'icono' => 'bi-cup-straw', 'color' => 'warning'],
    'horarios'      => ['titulo' => 'Horarios',          'url' => '/evospace/secciones/horarios.php', 'icono' => 'bi-calendar-week-fill', 'color' => 'primary'],
    'eventos'       => ['titulo' => 'Eventos',           'url' => '/evospace/secciones/eventos/eventos.php', 'icono' => 'bi-calendar-event-fill', 'color' => 'info'],
    'profesores'    => ['titulo' => 'Profesores',        'url' => '/evospace/secciones/profesores.php', 'icono' => 'bi-person-badge-fill', 'color' => 'dark'],
];

$cardsPermitidas = [];
foreach ($misSecciones as $perm => $datos) {
    if (tienePermiso($perm)) {
        $cardsPermitidas[$perm] = $datos;
    }
}
?>

<div class="container mt-3">
    <div class="dashboard-greeting">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-person-workspace me-2"></i><?= $saludo ?>, <?= htmlspecialchars($nombreUsuario) ?></h4>
            <small><?= $fechaFormateada ?></small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark fs-6 px-3 py-2"><i class="bi bi-building me-1"></i> Instituto Evolución Arte</span>
        </div>
    </div>

    <h5 class="mt-4 mb-3 text-muted"><i class="bi bi-grid-3x3-gap-fill me-1"></i> Tus secciones</h5>
    <?php if (empty($cardsPermitidas)): ?>
        <div class="alert alert-warning">No tenés secciones habilitadas. Contactá al administrador.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($cardsPermitidas as $datos): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="<?= $datos['url'] ?>" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm text-center">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-<?= $datos['color'] ?> bg-opacity-10 mb-2" style="width:48px;height:48px;font-size:1.4rem;"><i class="bi <?= $datos['icono'] ?> text-<?= $datos['color'] ?>"></i></div>
                                <div class="fw-bold"><?= $datos['titulo'] ?></div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>