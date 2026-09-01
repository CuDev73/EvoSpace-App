<nav class="navbar navbar-dark bg-evo fixed-top">
    <div class="container-fluid position-relative">
        <div class="d-flex align-items-center gap-2">
            <?php if (isset($mostrarVolver) && $mostrarVolver === true): ?>
            <a href="#" onclick="history.back(); return false;" class="btn btn-sm text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;" title="Volver">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <?php endif; ?>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

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

                    // Secciones agrupadas por categoría con sus permisos requeridos
                    $grupoSecciones = [
                        'Académico' => 'bi-book-fill',
                        'Económico' => 'bi-cash-stack',
                        'Comunicación' => 'bi-megaphone-fill',
                        'Sistema' => 'bi-gear-fill',
                    ];

                    $secciones = [
                        'Académico' => [
                            'Registro Asistencia' => ['url' => '/evospace/secciones/asistencia/index.php', 'icon' => 'bi-calendar-check-fill', 'permiso' => 'asistencia'],
                            'Alumnos' => ['url' => '/evospace/secciones/alumnos.php', 'icon' => 'bi-person-fill', 'permiso' => 'alumnos'],
                            'Inscripciones' => ['url' => '/evospace/secciones/inscripciones.php', 'icon' => 'bi-person-plus-fill', 'permiso' => 'alumnos'],
                            'Horarios' => ['url' => '/evospace/secciones/horarios.php', 'icon' => 'bi-calendar-week-fill', 'permiso' => 'horarios'],
                            'Profesores' => ['url' => '/evospace/secciones/profesores.php', 'icon' => 'bi-person-badge-fill', 'permiso' => 'profesores'],
                        ],
                        'Económico' => [
                            'Cantina' => ['url' => '/evospace/secciones/cantina/index.php', 'icon' => 'bi-cup-straw', 'permiso' => 'cantina'],
                            'Entradas / Rifas' => ['url' => '/evospace/secciones/entradas/index.php', 'icon' => 'bi-ticket-perforated-fill', 'permiso' => 'eventos'],
                        ],
                        'Comunicación' => [
                            'Eventos' => ['url' => '/evospace/secciones/eventos/eventos.php', 'icon' => 'bi-calendar-event-fill', 'permiso' => 'eventos'],
                        ],
                        'Sistema' => [
                            'Usuarios' => ['url' => '/evospace/secciones/usuarios.php', 'icon' => 'bi-people-fill', 'permiso' => 'usuarios'],
                            'Configuración' => ['url' => '/evospace/secciones/configuracion/configuracion.php', 'icon' => 'bi-gear-fill', 'permiso' => 'configuracion'],
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
                <a href="/evospace/logout.php" class="btn btn-evo w-100">
                    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</nav>

