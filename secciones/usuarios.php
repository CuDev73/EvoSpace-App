<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../includes/header.php';   // ← primero carga functions.php
include '../includes/navbar.php';
require_once '../config/db.php';
require_once 'funciones.php';   // incluye asegurarPerfilProfesor

verificarPermiso('usuarios'); 

// Asegurar columna dia_cobro (migración fase13) para instalaciones desactualizadas
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'dia_cobro'");
    if (!$checkCol->fetch()) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN dia_cobro TINYINT NULL DEFAULT NULL AFTER activo");
    }
} catch (PDOException $e) {
    // No bloquear la página si falla la comprobación
}

$mensaje = '';
$tipoMensaje = 'info';

// ==========================================================
// PROCESAR ACCIONES POST
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';

    // ELIMINAR USUARIO
    if ($accion === 'eliminar' && isset($_POST['id_usuario'])) {
        $id = (int)$_POST['id_usuario'];
        if ($id == $_SESSION['id_usuario']) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> No puedes eliminar tu propio usuario.';
            $tipoMensaje = 'danger';
        } else {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
            if ($stmt->execute([$id])) {
                $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Usuario eliminado correctamente.';
                $tipoMensaje = 'success';
            } else {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al eliminar.';
                $tipoMensaje = 'danger';
            }
        }
    }

    // GUARDAR USUARIO
    if ($accion === 'guardar') {
        $id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : 0;
        $usuario = trim($_POST['usuario']);
        $nombre_completo = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $cedula = trim($_POST['cedula']);
        $id_rol = (int)$_POST['id_rol'];
        $password = $_POST['password'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        $dia_cobro = isset($_POST['dia_cobro']) && $_POST['dia_cobro'] !== '' ? (int)$_POST['dia_cobro'] : null;
        if ($dia_cobro !== null && ($dia_cobro < 1 || $dia_cobro > 31)) $dia_cobro = null;

        if (empty($usuario) || empty($email) || empty($cedula)) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Usuario, email y cédula son obligatorios.';
            $tipoMensaje = 'danger';
        } else {
            // Verificar duplicados
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE (usuario = ? OR cedula = ?) AND id_usuario != ?");
            $stmt->execute([$usuario, $cedula, $id_usuario]);
            $dup = $stmt->fetch();
            if ($dup) {
                $dupStmt = $pdo->prepare("SELECT usuario, cedula FROM usuarios WHERE id_usuario = ?");
                $dupStmt->execute([$dup['id_usuario']]);
                $dupData = $dupStmt->fetch();
                $partes = [];
                if ($dupData['usuario'] === $usuario) $partes[] = "el usuario '<strong>$usuario</strong>'";
                if ($dupData['cedula'] === $cedula) $partes[] = "la cédula '<strong>$cedula</strong>'";
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Ya existe un usuario con ' . implode(' y ', $partes) . '.';
                $tipoMensaje = 'danger';
            } else {
            try {
                if ($id_usuario > 0) {
                    // EDITAR
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE usuarios SET usuario=?, nombre_completo=?, email=?, cedula=?, password_hash=?, id_rol=?, activo=?, dia_cobro=? WHERE id_usuario=?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$usuario, $nombre_completo, $email, $cedula, $hash, $id_rol, $activo, $dia_cobro, $id_usuario]);
                    } else {
                        $sql = "UPDATE usuarios SET usuario=?, nombre_completo=?, email=?, cedula=?, id_rol=?, activo=?, dia_cobro=? WHERE id_usuario=?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$usuario, $nombre_completo, $email, $cedula, $id_rol, $activo, $dia_cobro, $id_usuario]);
                    }
                    // Actualizar permisos (solo los marcados a mano)
                    $permisosSeleccionados = isset($_POST['permisos']) ? array_map('trim', $_POST['permisos']) : [];
                    $stmt = $pdo->prepare("DELETE FROM usuarios_permisos WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    if (!empty($permisosSeleccionados)) {
                        $stmt = $pdo->prepare("INSERT INTO usuarios_permisos (id_usuario, permiso) VALUES (?, ?)");
                        foreach ($permisosSeleccionados as $permiso) {
                            $stmt->execute([$id_usuario, $permiso]);
                        }
                    }
                    // Si el usuario tiene rol profesor, asegurar su fila en la tabla profesores
                    asegurarPerfilProfesor($pdo, $id_usuario, $id_rol);
                    $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Usuario actualizado correctamente.';
                    $tipoMensaje = 'success';
                } else {
                    // CREAR
                    if (empty($password)) {
                        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> La contraseña es obligatoria para nuevos usuarios.';
                        $tipoMensaje = 'danger';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO usuarios (usuario, nombre_completo, email, cedula, password_hash, id_rol, activo, dia_cobro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$usuario, $nombre_completo, $email, $cedula, $hash, $id_rol, $activo, $dia_cobro]);
                        $id_usuario = $pdo->lastInsertId();
                        // Guardar permisos (solo los marcados a mano)
                        $permisosSeleccionados = isset($_POST['permisos']) ? array_map('trim', $_POST['permisos']) : [];
                        if (!empty($permisosSeleccionados)) {
                            $stmt = $pdo->prepare("INSERT INTO usuarios_permisos (id_usuario, permiso) VALUES (?, ?)");
                            foreach ($permisosSeleccionados as $permiso) {
                                $stmt->execute([$id_usuario, $permiso]);
                            }
                        }
                        // Si el usuario tiene rol profesor, asegurar su fila en la tabla profesores
                        asegurarPerfilProfesor($pdo, $id_usuario, $id_rol);
                        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Usuario creado correctamente.';
                        $tipoMensaje = 'success';
                    }
                }
            } catch (PDOException $e) {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
        }
    }
}

    // CAMBIAR ESTADO
    if ($accion === 'toggle_activo' && isset($_POST['id_usuario'])) {
        $id = (int)$_POST['id_usuario'];
        $nuevo_estado = (int)$_POST['nuevo_estado'];
        if ($id == $_SESSION['id_usuario'] && $nuevo_estado == 0) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> No puedes desactivar tu propio usuario.';
            $tipoMensaje = 'danger';
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
            if ($stmt->execute([$nuevo_estado, $id])) {
                $estadoTexto = $nuevo_estado ? 'activado' : 'desactivado';
                $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Usuario ' . $estadoTexto . ' correctamente.';
                $tipoMensaje = 'success';
            } else {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al cambiar estado.';
                $tipoMensaje = 'danger';
            }
        }
    }

    // ASIGNAR HIJOS
    if ($accion === 'guardar_hijos' && isset($_POST['id_padre'])) {
        $id_padre = (int)$_POST['id_padre'];
        $hijos_seleccionados = isset($_POST['hijos']) ? array_map('intval', $_POST['hijos']) : [];

        $stmt = $pdo->prepare("UPDATE alumnos SET id_padre = NULL WHERE id_padre = ?");
        $stmt->execute([$id_padre]);

        if (!empty($hijos_seleccionados)) {
            $placeholders = implode(',', array_fill(0, count($hijos_seleccionados), '?'));
            $sql = "UPDATE alumnos SET id_padre = ? WHERE id_alumno IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$id_padre], $hijos_seleccionados);
            $stmt->execute($params);
        }

        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Relación padre-hijos actualizada correctamente.';
        $tipoMensaje = 'success';
    }
}

// ==========================================================
// OBTENER DATOS CON FILTROS
// ==========================================================
$filtro_rol = isset($_GET['rol']) ? $_GET['rol'] : '';
$filtro_estado = isset($_GET['estado']) ? (int)$_GET['estado'] : -1;

$sql = "SELECT u.*, r.nombre AS rol_nombre, 
               (SELECT GROUP_CONCAT(permiso) FROM usuarios_permisos WHERE id_usuario = u.id_usuario) AS permisos_lista,
               COUNT(a.id_alumno) AS total_hijos
        FROM usuarios u
        LEFT JOIN roles r ON u.id_rol = r.id_rol
        LEFT JOIN alumnos a ON u.id_usuario = a.id_padre
        WHERE 1=1";
$params = [];
if ($filtro_rol) {
    $sql .= " AND r.nombre = ?";
    $params[] = $filtro_rol;
}
if ($filtro_estado !== -1) {
    $sql .= " AND u.activo = ?";
    $params[] = $filtro_estado;
}
$sql .= " GROUP BY u.id_usuario ORDER BY u.id_usuario DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir permisos a array
foreach ($usuarios as &$u) {
    $u['permisos_array'] = !empty($u['permisos_lista']) ? explode(',', $u['permisos_lista']) : [];
}
unset($u);

$roles = $pdo->query("SELECT id_rol, nombre FROM roles ORDER BY id_rol")->fetchAll();

// Obtener todos los permisos disponibles para el selector
$todosPermisos = $pdo->query("SELECT nombre, descripcion FROM permisos ORDER BY nombre")->fetchAll();

// Array de etiquetas amigables para permisos
$etiquetasPermisos = [
    'alumnos' => 'Alumnos',
    'pagos' => 'Pagos',
    'profesores' => 'Profesores',
    'eventos' => 'Eventos',
    'asistencia' => 'Asistencia',
    'cantina' => 'Cantina',
    'configuracion' => 'Configuración',
    'usuarios' => 'Usuarios',
    'gestionar_usuarios' => 'Gestionar Usuarios',
    'horarios' => 'Horarios',
];

// Permisos por defecto según rol (ya no se aplican automáticamente; el admin elige a mano)
$permisosPorDefecto = [
    2 => [], // profesor
    3 => [], // padre
    4 => [], // auxiliar
];

// Mapeo permiso → sección visible en el menú (para mostrar qué ve cada rol)
$seccionesPermiso = [
    'alumnos' => 'Alumnos',
    'asistencia' => 'Asistencia',
    'horarios' => 'Horarios',
    'profesores' => 'Profesores',
    'cantina' => 'Cantina',
    'eventos' => 'Eventos / Rifas',
    'pagos' => 'Pagos',
    'configuracion' => 'Configuración',
    'usuarios' => 'Usuarios',
    'gestionar_usuarios' => 'Gestionar usuarios',
];

// Icono y color por rol
$iconosRol = [
    'admin' => 'bi-shield-lock-fill',
    'profesor' => 'bi-person-badge-fill',
    'padre' => 'bi-people-fill',
    'auxiliar' => 'bi-person-gear-fill',
];
$colorRol = [
    'admin' => 'danger',
    'profesor' => 'info',
    'padre' => 'success',
    'auxiliar' => 'warning',
];

// Secciones (menú) que ve cada rol según sus permisos
$visibilidadRol = [];
foreach ($roles as $r) {
    if ($r['nombre'] === 'admin') {
        $visibilidadRol['admin'] = array_keys($seccionesPermiso);
    } else {
        $visibilidadRol[$r['nombre']] = $permisosPorDefecto[$r['id_rol']] ?? [];
    }
}

// Agrupar usuarios por rol
$usuariosPorRol = [];
foreach ($usuarios as $u) {
    $rolKey = $u['rol_nombre'] ?? 'sin_rol';
    if (!isset($usuariosPorRol[$rolKey])) {
        $usuariosPorRol[$rolKey] = [
            'nombre' => $rolKey === 'padre' ? 'Tutor/a' : ucfirst($rolKey),
            'usuarios' => [],
        ];
    }
    $usuariosPorRol[$rolKey]['usuarios'][] = $u;
}

// Orden: admin, profesor, padrino, auxiliar, resto
$ordenRoles = ['admin', 'profesor', 'padre', 'auxiliar'];
uksort($usuariosPorRol, function ($a, $b) use ($ordenRoles) {
    $ia = array_search($a, $ordenRoles, true);
    $ib = array_search($b, $ordenRoles, true);
    if ($ia === false && $ib === false) return strcmp($a, $b);
    if ($ia === false) return 1;
    if ($ib === false) return -1;
    return $ia <=> $ib;
});

// Obtener alumnos para el modal de hijos
$alumnos_todos = $pdo->query("
    SELECT a.id_alumno, CONCAT(a.nombre, ' ', a.apellido) AS nombre_completo, 
           c.nombre AS curso_nombre, c.tipo AS curso_tipo
    FROM alumnos a
    INNER JOIN cursos c ON a.id_curso = c.id_curso
    ORDER BY a.nombre, a.apellido
")->fetchAll();
?>

<div class="container mt-3 pb-4">
    <!-- Título -->
    <div class="page-header">
        <div>
            <h4 class="h4 fw-bold mb-0"><i class="bi bi-people-fill me-2"></i>Gestión de Usuarios</h4>
            <small>Administra los usuarios del sistema y asigna hijos a padres</small>
        </div>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalUsuario" onclick="limpiarFormulario()">
            <i class="bi bi-person-plus-fill me-1"></i> Nuevo Usuario
        </button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros y búsqueda -->
    <div class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Rol</label>
            <select id="filtroRol" class="form-select form-select-sm" onchange="aplicarFiltros()">
                <option value="">Todos</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['nombre'] ?>" <?= $filtro_rol === $r['nombre'] ? 'selected' : '' ?>><?= ucfirst($r['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Estado</label>
            <select id="filtroEstado" class="form-select form-select-sm" onchange="aplicarFiltros()">
                <option value="-1">Todos</option>
                <option value="1" <?= $filtro_estado === 1 ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= $filtro_estado === 0 ? 'selected' : '' ?>>Inactivos</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Buscar</label>
            <input type="text" id="buscadorUsuario" class="form-control form-control-sm" placeholder="Nombre, email, cédula...">
        </div>
        <div class="col-md-3 text-end">
            <a href="?rol=&estado=-1" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle me-1"></i>Limpiar filtros</a>
            <span id="contadorUsuarios" class="text-muted small ms-2"></span>
        </div>
    </div>

    <!-- Lista única de usuarios -->
    <?php if (empty($usuarios)): ?>
        <div class="alert alert-info">No hay usuarios registrados.</div>
    <?php else: ?>
        <div class="card shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0" id="tablaUsuarios">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:50px;">ID</th>
                                <th style="min-width:170px;">Usuario</th>
                                <th style="min-width:130px;">Rol</th>
                                <th style="min-width:200px;">Email</th>
                                <th style="min-width:100px;">Cédula</th>
                                <th class="text-center" style="min-width:100px;">Estado</th>
                                <th class="text-center" style="min-width:170px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u):
                                $rolNombre = $u['rol_nombre'] ?? 'Sin rol';
                                $iconoRol = $iconosRol[$rolNombre] ?? 'bi-person-fill';
                                $colorRolU = $colorRol[$rolNombre] ?? 'secondary';
                            ?>
                            <tr data-rol="<?= htmlspecialchars($rolNombre) ?>" data-activo="<?= $u['activo'] ?>" class="fila-usuario">
                                <td><?= $u['id_usuario'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi <?= $iconoRol ?> text-<?= $colorRolU ?>"></i>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($u['usuario']) ?>
                                            <?php if ($u['id_usuario'] == $_SESSION['id_usuario']): ?>
                                                <span class="badge bg-secondary ms-1">Tú</span>
                                            <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?= htmlspecialchars($u['nombre_completo']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $colorRolU ?>"><?= htmlspecialchars(ucfirst($rolNombre == 'padre' ? 'Tutor/a' : $rolNombre)) ?></span>
                                    <?php if ($rolNombre === 'padre' && !empty($u['dia_cobro'])): ?>
                                        <br><small class="text-muted">Día de cobro: <?= (int)$u['dia_cobro'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['cedula']) ?></td>
                                <td class="text-center">
                                    <?php if ($u['activo']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Inactivo</span>
                                    <?php endif; ?>
                                    <?php if ($rolNombre === 'padre'): ?>
                                        <br>
                                        <button class="btn btn-link btn-sm p-0 text-primary mt-1" data-bs-toggle="modal" data-bs-target="#modalHijos"
                                                onclick="cargarHijos(<?= $u['id_usuario'] ?>, '<?= htmlspecialchars($u['usuario']) ?>')" title="Asignar hijos">
                                            <i class="bi bi-people-fill"></i> Hijos (<?= (int)$u['total_hijos'] ?>)
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1 flex-wrap">
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalUsuario"
                                                onclick="editarUsuario(<?= htmlspecialchars(json_encode($u)) ?>)">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <?php if ($u['id_usuario'] != $_SESSION['id_usuario']): ?>
                                            <form method="POST" style="display:inline-block;">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="accion" value="toggle_activo">
                                                <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="<?= $u['activo'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-<?= $u['activo'] ? 'warning' : 'success' ?> btn-sm" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="bi bi-<?= $u['activo'] ? 'pause-circle-fill' : 'play-circle-fill' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline-block;" onsubmit="return confirmarEliminar(this, '¿Seguro que deseas eliminar este usuario?');">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================== -->
<!-- MODAL para AGREGAR / EDITAR USUARIO -->
<!-- ========================================================== -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title" id="modalTituloUsuario"><i class="bi bi-person-fill me-2"></i>Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formUsuario">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_usuario" id="id_usuario" value="0">
                    <div class="mb-3">
                        <label class="form-label">Usuario *</label>
                        <input type="text" name="usuario" id="usuario" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre completo *</label>
                        <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula *</label>
                        <input type="text" name="cedula" id="cedula" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
                        <small class="text-muted">Obligatoria para nuevos usuarios.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select name="id_rol" id="id_rol" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>"><?= ucfirst($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="activo" id="activo" class="form-check-input" checked>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                    <div class="mb-3" id="campoDiaCobro" style="display:none;">
                        <label class="form-label">Día de cobro (cuota de sus hijos)</label>
                        <input type="number" name="dia_cobro" id="dia_cobro" class="form-control" min="1" max="31" placeholder="Ej: 10">
                        <small class="text-muted">Día del mes en que este tutor/a paga. Se usa como vencimiento de cuota de sus hijos cuando el alumno no tiene uno propio.</small>
                    </div>

                    <!-- ===== SELECCIÓN DE PERMISOS ===== -->
                    <div class="mb-3">
                        <label class="form-label">Permisos adicionales</label>
                        <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($todosPermisos as $p): ?>
                                <div class="form-check">
                                    <input class="form-check-input permiso-checkbox" type="checkbox" name="permisos[]" value="<?= $p['nombre'] ?>" id="permiso_<?= $p['nombre'] ?>">
                                    <label class="form-check-label small" for="permiso_<?= $p['nombre'] ?>">
                                        <?= htmlspecialchars($etiquetasPermisos[$p['nombre']] ?? $p['nombre']) ?>
                                        <span class="text-muted">(<?= htmlspecialchars($p['descripcion']) ?>)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Marca los permisos que tendrá este usuario. Nada viene preseleccionado por defecto; si es admin tendrá todos automáticamente.</small>
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

<!-- ========================================================== -->
<!-- MODAL para ASIGNAR HIJOS -->
<!-- ========================================================== -->
<div class="modal fade" id="modalHijos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-people-fill me-2"></i>Asignar hijos a <span id="nombrePadre"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formHijos">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="guardar_hijos">
                    <input type="hidden" name="id_padre" id="id_padre_modal" value="0">

                    <div class="mb-3">
                        <label class="form-label">Buscar alumno</label>
                        <input type="text" id="buscadorHijos" class="form-control" placeholder="Escribe el nombre...">
                    </div>

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th><input type="checkbox" id="seleccionarTodos" onclick="toggleTodos()"></th>
                                    <th>Nombre</th>
                                    <th>Curso</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody id="listaHijos">
                                <?php foreach ($alumnos_todos as $alumno): ?>
                                    <tr class="fila-alumno">
                                        <td><input type="checkbox" name="hijos[]" value="<?= $alumno['id_alumno'] ?>" class="checkbox-hijo"></td>
                                        <td><?= htmlspecialchars($alumno['nombre_completo']) ?></td>
                                        <td><?= htmlspecialchars($alumno['curso_nombre']) ?></td>
                                        <td><span class="badge bg-<?= $alumno['curso_tipo'] === 'Acrotelas' ? 'warning' : ($alumno['curso_tipo'] === 'Infantil' ? 'info' : 'primary') ?>"><?= htmlspecialchars($alumno['curso_tipo']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Selecciona los hijos que pertenecen a este padre.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar asignación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function limpiarFormulario() {
        document.getElementById('modalTituloUsuario').innerHTML = '<i class="bi bi-person-fill me-2"></i>Nuevo Usuario';
        document.getElementById('id_usuario').value = '0';
        document.getElementById('usuario').value = '';
        document.getElementById('nombre_completo').value = '';
        document.getElementById('email').value = '';
        document.getElementById('cedula').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').placeholder = 'Contraseña (obligatoria)';
        document.getElementById('password').required = true;
        document.getElementById('id_rol').value = '<?= $roles[0]['id_rol'] ?? 3 ?>';
        document.getElementById('activo').checked = true;
        document.getElementById('dia_cobro').value = '';
        actualizarCampoCobro();
        preseleccionarPermisos();
    }

    function actualizarCampoCobro() {
        const esPadre = document.getElementById('id_rol').value == '3';
        document.getElementById('campoDiaCobro').style.display = esPadre ? 'block' : 'none';
    }

    function preseleccionarPermisos() {
        // No preseleccionar nada por defecto: el admin elige los permisos a mano
        document.querySelectorAll('.permiso-checkbox').forEach(cb => {
            cb.checked = false;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const rolSelect = document.getElementById('id_rol');
        if (rolSelect) {
            rolSelect.addEventListener('change', function() {
                actualizarCampoCobro();
            });
        }
    });

    function editarUsuario(usuario) {
        document.getElementById('modalTituloUsuario').innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Usuario';
        document.getElementById('id_usuario').value = usuario.id_usuario;
        document.getElementById('usuario').value = usuario.usuario;
        document.getElementById('nombre_completo').value = usuario.nombre_completo || '';
        document.getElementById('email').value = usuario.email;
        document.getElementById('cedula').value = usuario.cedula;
        document.getElementById('password').value = '';
        document.getElementById('password').placeholder = 'Dejar en blanco para no cambiar';
        document.getElementById('password').required = false;
        document.getElementById('id_rol').value = usuario.id_rol;
        document.getElementById('activo').checked = (usuario.activo == 1);
        document.getElementById('dia_cobro').value = usuario.dia_cobro || '';
        actualizarCampoCobro();
        // Marcar permisos
        const permisosArray = usuario.permisos_array || [];
        document.querySelectorAll('.permiso-checkbox').forEach(cb => {
            cb.checked = permisosArray.includes(cb.value);
        });
    }

    function cargarHijos(idPadre, nombrePadre) {
        document.getElementById('id_padre_modal').value = idPadre;
        document.getElementById('nombrePadre').textContent = nombrePadre;
        fetch('get_hijos.php?id_padre=' + idPadre)
            .then(response => response.json())
            .then(data => {
                document.querySelectorAll('.checkbox-hijo').forEach(cb => {
                    cb.checked = data.includes(parseInt(cb.value));
                });
            })
            .catch(error => console.error('Error al cargar hijos:', error));
    }

    function toggleTodos() {
        const checked = document.getElementById('seleccionarTodos').checked;
        document.querySelectorAll('.checkbox-hijo').forEach(cb => cb.checked = checked);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const buscador = document.getElementById('buscadorHijos');
        if (buscador) {
            buscador.addEventListener('keyup', function() {
                const filtro = this.value.toLowerCase();
                document.querySelectorAll('.fila-alumno').forEach(fila => {
                    const nombre = fila.cells[1].textContent.toLowerCase();
                    fila.style.display = nombre.includes(filtro) ? '' : 'none';
                });
            });
        }
    });

    // Filtro combinado: búsqueda + rol + estado en la lista única
    function aplicarFiltros() {
        const term = (document.getElementById('buscadorUsuario').value || '').toLowerCase().trim();
        const rol = document.getElementById('filtroRol').value;
        const estado = document.getElementById('filtroEstado').value;
        let visibles = 0;
        document.querySelectorAll('#tablaUsuarios .fila-usuario').forEach(row => {
            const cumpleRol = !rol || row.dataset.rol === rol;
            const cumpleEstado = estado === '-1' || row.dataset.activo === estado;
            const texto = row.textContent.toLowerCase();
            const cumpleTexto = !term || texto.includes(term);
            const vis = cumpleRol && cumpleEstado && cumpleTexto;
            row.style.display = vis ? '' : 'none';
            if (vis) visibles++;
        });
        const contador = document.getElementById('contadorUsuarios');
        if (contador) contador.textContent = visibles + ' de ' + document.querySelectorAll('#tablaUsuarios .fila-usuario').length + ' usuarios';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('buscadorUsuario');
        if (input) {
            input.addEventListener('keyup', aplicarFiltros);
        }
        aplicarFiltros();
    });
</script>

<?php include '../includes/footer.php'; ?>