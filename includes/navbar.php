<?php
// Determinar URL de inicio según el rol
$inicioUrl = '/roles/admin.php';
if (isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'profesor') {
        $inicioUrl = '/roles/profesor.php';
    } elseif ($_SESSION['rol'] === 'padre') {
        $inicioUrl = '/roles/padre.php';
    } elseif ($_SESSION['rol'] === 'auxiliar') {
        $inicioUrl = '/roles/auxiliar.php';
    }
}
?>
<nav class="navbar navbar-dark bg-evo fixed-top">
    <div class="container-fluid position-relative">
        <div class="d-flex align-items-center gap-2">
            <?php $hayVolver = isset($mostrarVolver) && $mostrarVolver === true; ?>
            <button class="navbar-toggler border-0 <?= $hayVolver ? 'd-none d-md-block' : '' ?>" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <?php if ($hayVolver): ?>
            <a href="#" onclick="history.back(); return false;" class="btn btn-sm btn-evo d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;padding:0;" title="Volver" aria-label="Volver">
                <i class="bi bi-arrow-left" style="font-size:1.4rem;"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- Título centrado -->
        <a class="navbar-brand fw-bold position-absolute start-50 translate-middle-x" href="<?= $inicioUrl ?>">
            EvoSpace
        </a>

        <!-- Usuario a la derecha -->
        <span class="navbar-text d-none d-md-inline ms-auto">
            <i class="bi bi-person-circle"></i> 
            <?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'EvoSpace') ?>
        </span>

        <!-- Offcanvas (menú lateral) -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar">
            <div class="offcanvas-header d-flex align-items-center justify-content-center position-relative">
                <span class="fw-bold">Secciones</span>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav flex-grow-1">
                    <?php
                    // Secciones agrupadas por categoría con sus permisos requeridos
                    $grupoSecciones = [
                        'Académico' => 'bi-book-fill',
                        'Económico' => 'bi-cash-stack',
                        'Comunicación' => 'bi-megaphone-fill',
                        'Sistema' => 'bi-gear-fill',
                    ];

                    $secciones = [
                        'Académico' => [
                            'Registro Asistencia' => ['url' => '/secciones/asistencia/index.php', 'icon' => 'bi-calendar-check-fill', 'permiso' => 'asistencia'],
                            'Alumnos' => ['url' => '/secciones/alumnos.php', 'icon' => 'bi-person-fill', 'permiso' => 'alumnos'],
                            'Inscripciones' => ['url' => '/secciones/inscripciones.php', 'icon' => 'bi-person-plus-fill', 'permiso' => 'alumnos'],
                            'Horarios' => ['url' => '/secciones/horarios.php', 'icon' => 'bi-calendar-week-fill', 'permiso' => 'horarios'],
                            'Profesores' => ['url' => '/secciones/profesores.php', 'icon' => 'bi-person-badge-fill', 'permiso' => 'profesores'],
                        ],
                        'Económico' => [
                            'Cantina' => ['url' => '/secciones/cantina/index.php', 'icon' => 'bi-cup-straw', 'permiso' => 'cantina'],
                            'Entradas / Rifas' => ['url' => '/secciones/entradas/index.php', 'icon' => 'bi-ticket-perforated-fill', 'permiso' => 'eventos'],
                        ],
                        'Comunicación' => [
                            'Eventos' => ['url' => '/secciones/eventos/eventos.php', 'icon' => 'bi-calendar-event-fill', 'permiso' => 'eventos'],
                        ],
                        'Sistema' => [
                            'Usuarios' => ['url' => '/secciones/usuarios.php', 'icon' => 'bi-people-fill', 'permiso' => 'usuarios'],
                            'Configuración' => ['url' => '/secciones/configuracion/configuracion.php', 'icon' => 'bi-gear-fill', 'permiso' => 'configuracion'],
                        ],
                    ];

                    // Si es padre, solo mostrar "Inicio"
                    $esPadre = isset($_SESSION['rol']) && $_SESSION['rol'] === 'padre';

                    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                    ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold <?= $currentPath === parse_url($inicioUrl, PHP_URL_PATH) ? 'active' : '' ?>" href="<?= $inicioUrl ?>">
                            <i class="bi bi-house-door-fill me-2"></i> Inicio
                        </a>
                    </li>

                    <?php if (!$esPadre): ?>
                    <?php foreach ($grupoSecciones as $grupo => $iconoGrupo): ?>
                        <?php
                        // Contar secciones permitidas del grupo; ocultar grupo si no hay ninguna
                        $grupoVisible = false;
                        foreach ($secciones[$grupo] as $datos) {
                            if (!$datos['permiso'] || tienePermiso($datos['permiso'])) {
                                $grupoVisible = true;
                                break;
                            }
                        }
                        if (!$grupoVisible) continue;
                        ?>
                        <li class="nav-item mt-2 mb-1">
                            <span class="nav-link text-uppercase small fw-bold" style="color:#c81015;letter-spacing:.5px;cursor:default;background:transparent!important;pointer-events:none;">
                                <i class="bi <?= $iconoGrupo ?> me-1"></i> <?= $grupo ?>
                            </span>
                        </li>
                        <?php foreach ($secciones[$grupo] as $nombre => $datos): ?>
                            <?php
                            // Verificar permisos
                            if ($datos['permiso'] && !tienePermiso($datos['permiso'])) {
                                continue;
                            }
                            $navPath = parse_url($datos['url'], PHP_URL_PATH);
                            $esActivo = ($currentPath === $navPath) ? 'active' : '';
                            ?>
                            <li class="nav-item">
                                <a class="nav-link ps-4 <?= $esActivo ?>" href="<?= $datos['url'] ?>">
                                    <i class="bi <?= $datos['icon'] ?> me-2"></i> <?= $nombre ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <hr>
                <a href="/logout.php" class="btn btn-evo w-100">
                    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</nav>

