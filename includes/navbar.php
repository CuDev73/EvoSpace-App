<nav class="navbar navbar-dark bg-evo fixed-top">
    <div class="container-fluid position-relative">
        <?php if (isset($mostrarVolver) && $mostrarVolver === true): ?>
        <a href="<?= isset($volverUrl) ? $volverUrl : '/evospace/secciones/cantina/index.php' ?>" class="btn btn-sm text-white d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
            <i class="bi bi-arrow-left fs-5"></i>
        </a>
        <?php else: ?>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php endif; ?>

        <!-- Título centrado -->
        <a class="navbar-brand fw-bold position-absolute start-50 translate-middle-x" href="/evospace/roles/admin.php">
            EvoSpace
        </a>

        <!-- Usuario a la derecha -->
        <span class="navbar-text text-white d-none d-md-inline ms-auto">
            <i class="bi bi-person-circle"></i> 
            <?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'EvoSpace') ?>
        </span>

        <!-- Offcanvas (menú lateral) -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar">
            <div class="offcanvas-header d-flex align-items-center justify-content-center position-relative">
                <span class="fw-bold text-white">Secciones</span>
                <button type="button" class="btn-close btn-close-white position-absolute end-0 me-3" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav flex-grow-1">
                    <?php
                    // Determinar URL de inicio según el rol
                    $inicioUrl = '/evospace/roles/admin.php';
                    if (isset($_SESSION['rol'])) {
                        if ($_SESSION['rol'] === 'profesor') {
                            $inicioUrl = '/evospace/roles/profesor.php';
                        } elseif ($_SESSION['rol'] === 'padre') {
                            $inicioUrl = '/evospace/roles/padre.php';
                        }
                    }

                    // Definir todas las secciones con sus permisos requeridos
                    $secciones = [
                        'Inicio' => [
                            'url' => $inicioUrl,
                            'icon' => 'bi-house-door-fill',
                            'permiso' => null
                        ],
                        'Registro Asistencia' => [
                            'url' => '/evospace/secciones/asistencia/index.php',
                            'icon' => 'bi-calendar-check-fill',
                            'permiso' => 'asistencia'
                        ],
                        'Alumnos' => [
                            'url' => '/evospace/secciones/alumnos.php',
                            'icon' => 'bi-person-fill',
                            'permiso' => 'alumnos'
                        ],
                        'Inscripciones' => [
                            'url' => '/evospace/secciones/inscripciones.php',
                            'icon' => 'bi-person-plus-fill',
                            'permiso' => 'alumnos'
                        ],
                        'Horarios' => [
                            'url' => '/evospace/secciones/horarios.php',
                            'icon' => 'bi-calendar-week-fill',
                            'permiso' => 'horarios'
                        ],

                        'Cantina' => [
                            'url' => '/evospace/secciones/cantina/index.php',
                            'icon' => 'bi-cup-straw',
                            'permiso' => 'cantina'
                        ],
                        'Profesores' => [
                            'url' => '/evospace/secciones/profesores.php',
                            'icon' => 'bi-person-badge-fill',
                            'permiso' => 'profesores'
                        ],
                        'Eventos' => [
                            'url' => '/evospace/secciones/eventos/eventos.php',
                            'icon' => 'bi-calendar-event-fill',
                            'permiso' => 'eventos'
                        ],
                        'Usuarios' => [
                            'url' => '/evospace/secciones/usuarios.php',
                            'icon' => 'bi-people-fill',
                            'permiso' => 'usuarios'
                        ],
                        'Configuración' => [
                            'url' => '/evospace/secciones/configuracion/configuracion.php',
                            'icon' => 'bi-gear-fill',
                            'permiso' => 'configuracion'
                        ],
                    ];

                    // Si es padre, solo mostrar "Inicio"
                    $esPadre = isset($_SESSION['rol']) && $_SESSION['rol'] === 'padre';

                    foreach ($secciones as $nombre => $datos):
                        // Si es padre, solo mostrar "Inicio"
                        if ($esPadre && $nombre !== 'Inicio') {
                            continue;
                        }

                        // Verificar permisos para otros roles
                        $mostrar = true;
                        if ($datos['permiso'] && !tienePermiso($datos['permiso'])) {
                            $mostrar = false;
                        }
                        
                        if (!$mostrar) continue;

                        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                        $navPath = parse_url($datos['url'], PHP_URL_PATH);
                        $esActivo = ($currentPath === $navPath) ? 'active' : '';
                    ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $esActivo ?>" href="<?= $datos['url'] ?>">
                                <i class="bi <?= $datos['icon'] ?> me-2"></i> <?= $nombre ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <hr>
                <a href="/evospace/logout.php" class="btn btn-evo w-100">
                    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</nav>

