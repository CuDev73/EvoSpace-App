<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once __DIR__ . '/../../helpers/functions.php';

include '../../includes/header.php';
include '../../includes/navbar.php';
require_once '../../config/db.php';

verificarPermiso('eventos'); // ahora sí existe
require_once 'models/EventoModel.php';
require_once 'models/NotificacionModel.php';

$mensaje = '';
$eventoModel = new EventoModel($pdo);

// ==========================================================
// 1. PROCESAR NUEVO EVENTO
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_evento') {
    verificarTokenCSRF();
    $datos = [
        'titulo'            => trim($_POST['titulo']),
        'fecha'             => $_POST['fecha'],
        'hora'              => !empty($_POST['hora']) ? $_POST['hora'] : null,
        'lugar'             => !empty($_POST['lugar']) ? trim($_POST['lugar']) : null,
        'enlace_ubicacion'  => !empty($_POST['enlace_ubicacion']) ? trim($_POST['enlace_ubicacion']) : null,
        'descripcion'       => !empty($_POST['descripcion']) ? trim($_POST['descripcion']) : null,
        'color'             => $_POST['color'] ?? '#c81015',
        'ramas'             => isset($_POST['ramas']) ? array_map('intval', $_POST['ramas']) : []
    ];

    try {
        $eventoModel->crearEvento($datos);
        $notif = $eventoModel->obtenerResultadoNotificacion();
        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Evento registrado correctamente.';
        $mensaje .= ' ' . resumenNotificacion($notif);
    } catch (Exception $e) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
    }
}

// ==========================================================
// 2. ACTUALIZAR EVENTO (editar)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_evento') {
    verificarTokenCSRF();
    $id_evento = (int)$_POST['id_evento'];
    $datos = [
        'titulo'            => trim($_POST['titulo']),
        'fecha'             => $_POST['fecha'],
        'hora'              => !empty($_POST['hora']) ? $_POST['hora'] : null,
        'lugar'             => !empty($_POST['lugar']) ? trim($_POST['lugar']) : null,
        'enlace_ubicacion'  => !empty($_POST['enlace_ubicacion']) ? trim($_POST['enlace_ubicacion']) : null,
        'descripcion'       => !empty($_POST['descripcion']) ? trim($_POST['descripcion']) : null,
        'color'             => $_POST['color'] ?? '#c81015',
        'ramas'             => isset($_POST['ramas']) ? array_map('intval', $_POST['ramas']) : []
    ];

    try {
        $eventoModel->actualizarEvento($id_evento, $datos);
        $notif = $eventoModel->obtenerResultadoNotificacion();
        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Evento actualizado correctamente.';
        $mensaje .= ' ' . resumenNotificacion($notif);
    } catch (Exception $e) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
    }
}

// ==========================================================
// 3. ELIMINAR EVENTO (POST con token)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_evento') {
    verificarTokenCSRF();
    $id_eliminar = (int)$_POST['id_evento'];
    try {
        $eventoModel->eliminarEvento($id_eliminar);
        $mensaje = '<i class="bi bi-trash-fill text-success"></i> Evento eliminado correctamente.';
    } catch (Exception $e) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al eliminar: ' . $e->getMessage();
    }
}

// ==========================================================
// 3.5 ENVIAR RECORDATORIO (POST con token)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'recordatorio_evento') {
    verificarTokenCSRF();
    $id_record = (int)$_POST['id_evento'];
    try {
        $cant = $eventoModel->enviarRecordatorio($id_record);
        $mensaje = '<i class="bi bi-bell-fill text-success"></i> Recordatorio enviado a ' . $cant . ' padre(s).';
    } catch (Exception $e) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al enviar recordatorio: ' . $e->getMessage();
    }
    $desdeRecordatorio = true;
}

// ==========================================================
// 4. OBTENER CURSOS Y EVENTOS
// ==========================================================
$cursoSeleccionado = isset($_GET['curso']) ? (int)$_GET['curso'] : 0;

$sqlCursos = "SELECT id_curso, nombre, tipo FROM cursos WHERE activo = 1 ORDER BY tipo, orden";
$stmt = $pdo->query($sqlCursos);
$todosCursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cursosPorTipo = [];
foreach ($todosCursos as $c) {
    $cursosPorTipo[$c['tipo']][] = $c;
}

$todosEventos = $eventoModel->obtenerEventos();

$eventosFiltrados = [];
foreach ($todosEventos as $ev) {
    $cumpleCurso = true;
    if ($cursoSeleccionado > 0) {
        $cumpleCurso = false;
        if (isset($ev['ramas']) && is_array($ev['ramas'])) {
            foreach ($ev['ramas'] as $rama) {
                if (isset($rama['id_curso']) && $rama['id_curso'] == $cursoSeleccionado) {
                    $cumpleCurso = true;
                    break;
                }
            }
        }
    }
    if ($cumpleCurso) {
        $eventosFiltrados[] = $ev;
    }
}

$tipoColores = [
    'Acrotelas' => 'warning',
    'Infantil'  => 'info',
    'Superior'  => 'primary'
];
?>

<div class="container mt-3">
    <?php if ($mensaje): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FILTROS -->
    <div class="card shadow mb-3">
        <div class="card-header bg-evo text-white py-2 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-funnel"></i> Filtrar eventos por curso</span>
            <button class="btn btn-sm btn-light fw-bold text-danger" data-bs-toggle="modal" data-bs-target="#modalNuevoEvento">
                <i class="bi bi-plus-circle-fill text-danger"></i> Nuevo Evento
            </button>
        </div>
        <div class="card-body py-2">
            <form method="GET" id="filtroForm">
                <div class="row g-2">
                    <div class="col-md-8 d-flex flex-column">
                        <label class="form-label mb-1 small text-muted fw-bold">Seleccionar Curso</label>
                        <select name="curso" id="filtroCurso" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="0">Todos los cursos</option>
                            <?php foreach ($cursosPorTipo as $tipo => $cursos): ?>
                                <optgroup label="<?= htmlspecialchars($tipo) ?>">
                                    <?php foreach ($cursos as $curso): ?>
                                        <option value="<?= $curso['id_curso'] ?>" <?= $cursoSeleccionado == $curso['id_curso'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($curso['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex flex-column justify-content-end">
                        <a href="eventos.php" class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar filtros
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- BUSCADOR -->
    <div class="row g-2 mb-3">
        <div class="col-md-12">
            <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar evento por título, lugar o descripción...">
        </div>
    </div>

    <!-- TABLA DE EVENTOS -->
    <div class="card shadow">
        <div class="card-header bg-evo text-white py-2">
            <i class="bi bi-calendar-event-fill"></i> Panel de Eventos
            <?php if ($cursoSeleccionado > 0): ?>
                <span class="badge bg-light text-dark ms-2">
                    <?php
                    $cursoNombre = array_filter($todosCursos, function ($c) use ($cursoSeleccionado) {
                        return $c['id_curso'] == $cursoSeleccionado;
                    });
                    $curso = !empty($cursoNombre) ? reset($cursoNombre) : null;
                    echo $curso ? htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) : 'Curso';
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-2">
            <?php if (empty($eventosFiltrados)): ?>
                <div class="alert alert-warning mb-0">No se encontraron eventos programados para el curso seleccionado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle" id="tablaEventos">
                        <thead class="text-center table-light">
                            <tr>
                                <th style="width: 50px;">Color</th>
                                <th>Título</th>
                                <th>Fecha y Hora</th>
                                <th>Lugar</th>
                                <th style="width: 60px;">Flyer</th>
                                <th>Cursos Notificados</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventosFiltrados as $ev): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="rounded shadow-sm" style="width: 28px; height: 28px; background-color: <?= htmlspecialchars($ev['color'] ?? '#c81015') ?>; margin: 0 auto;"></div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?= htmlspecialchars($ev['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <small class="text-muted d-block text-truncate" style="max-width: 250px;"><?= htmlspecialchars($ev['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td class="text-center">
                                        <i class="bi bi-calendar3 text-danger me-1"></i><?= date('d/m/Y', strtotime($ev['fecha'])) ?><br>
                                        <small class="text-muted fw-bold"><i class="bi bi-clock me-1"></i><?= !empty($ev['hora']) ? date('H:i', strtotime($ev['hora'])) : '--:--' ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="small"><?= htmlspecialchars($ev['lugar'] ?? '', ENT_QUOTES, 'UTF-8') ?: '<span class="text-muted">No especificado</span>' ?></span>
                                        <?php if (!empty($ev['enlace_ubicacion'])): ?>
                                            <a href="<?= htmlspecialchars($ev['enlace_ubicacion'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-link p-0 d-block small text-primary"><i class="bi bi-geo-alt-fill"></i> Mapa</a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($ev['imagen'])): ?>
                                            <a href="/evospace/<?= $ev['imagen'] ?>" target="_blank">
                                                <img src="/evospace/<?= $ev['imagen'] ?>" alt="flyer" style="width:50px;height:50px;object-fit:cover;border-radius:6px;">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <?php if (empty($ev['ramas'])): ?>
                                            <span class="badge bg-secondary small">General</span>
                                        <?php else: ?>
                                            <div class="table-responsive" style="max-height:120px;overflow-y:auto;">
                                                <table class="table table-bordered table-sm mb-0" style="min-width:170px;font-size:0.72rem;">
                                                    <thead class="table-light text-center">
                                                        <tr>
                                                            <th style="width:30%;">Tipo</th>
                                                            <th>Curso</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="text-center">
                                                        <?php foreach ($ev['ramas'] as $rama): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($rama['tipo'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= htmlspecialchars($rama['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <small class="text-muted d-block text-center mt-1"><?= count($ev['ramas']) ?> curso(s)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button class="btn btn-warning btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalEditarEvento"
                                                    data-evento='<?= htmlspecialchars(json_encode($ev, JSON_HEX_APOS), ENT_QUOTES, 'UTF-8') ?>'>
                                                <i class="bi bi-pencil-fill"></i>
                                                <span class="d-none d-sm-inline">Editar</span>
                                            </button>
                                            <?php if (date('Y-m-d', strtotime($ev['fecha'])) >= date('Y-m-d')): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Enviar recordatorio de \'<?= htmlspecialchars($ev['titulo'], ENT_QUOTES, 'UTF-8') ?>\' a los tutores de los cursos asociados?');">
                                                    <?= campoCSRF() ?>
                                                    <input type="hidden" name="accion" value="recordatorio_evento">
                                                    <input type="hidden" name="id_evento" value="<?= $ev['id_evento'] ?>">
                                                    <button type="submit" class="btn btn-info btn-sm px-3" title="Enviar recordatorio a tutores">
                                                        <i class="bi bi-bell-fill"></i>
                                                        <span class="d-none d-sm-inline">Recordar</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que querés eliminar el evento: \'<?= htmlspecialchars($ev['titulo'], ENT_QUOTES, 'UTF-8') ?>\'?');">
                                                <?= campoCSRF() ?>
                                                <input type="hidden" name="accion" value="eliminar_evento">
                                                <input type="hidden" name="id_evento" value="<?= $ev['id_evento'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm px-3">
                                                    <i class="bi bi-trash-fill"></i>
                                                    <span class="d-none d-sm-inline">Eliminar</span>
                                                </button>
                                            </form>
                                        </div>
                                        <?php if (!empty($ev['ultimo_recordatorio'])): ?>
                                            <small class="text-muted d-block mt-1"><i class="bi bi-bell"></i> Rec. enviado: <?= date('d/m/Y', strtotime($ev['ultimo_recordatorio'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- MODAL NUEVO EVENTO -->
<!-- ========================================================== -->
<div class="modal fade" id="modalNuevoEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle-fill"></i> Registrar Nuevo Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEvento" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="agregar_evento">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Título del Evento *</label>
                            <input type="text" name="titulo" class="form-control form-control-sm" required placeholder="Ej: Festival de Fin de Año">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Fecha *</label>
                            <input type="date" name="fecha" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Hora</label>
                            <input type="time" name="hora" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Lugar</label>
                            <input type="text" name="lugar" class="form-control form-control-sm" placeholder="Ej: Teatro Municipal">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Enlace de Ubicación</label>
                            <input type="url" name="enlace_ubicacion" class="form-control form-control-sm" placeholder="https://maps.google.com/...">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold small">Descripción</label>
                            <textarea name="descripcion" class="form-control form-control-sm" rows="2" placeholder="Detalles adicionales..."></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Color</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="#c81015" style="height: 38px;">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Flyer / Imagen</label>
                            <input type="file" name="imagen" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-danger mb-1 small"><i class="bi bi-bell-fill"></i> Seleccionar Cursos a Notificar:</label>
                            <input type="text" id="buscarRamas" class="form-control form-control-sm mb-2" placeholder="Buscar curso...">
                            <div class="p-3 border rounded bg-light" style="max-height: 250px; overflow-y: auto;">
                                <?php foreach ($cursosPorTipo as $tipo => $cursos): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small text-uppercase text-muted"><?= $tipo ?></span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm seleccionar-todos" data-tipo="<?= $tipo ?>">
                                                Seleccionar todos
                                            </button>
                                        </div>
                                        <div class="row g-1 mt-1">
                                            <?php foreach ($cursos as $curso): ?>
                                                <div class="col-md-6 rama-item" data-nombre="<?= htmlspecialchars($curso['nombre']) ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="ramas[]" value="<?= $curso['id_curso'] ?>" id="curso_check_<?= $curso['id_curso'] ?>">
                                                        <label class="form-check-label small" for="curso_check_<?= $curso['id_curso'] ?>">
                                                            <?= htmlspecialchars($curso['nombre']) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">A los cursos marcados se les generará una notificación automática.</small>
                        </div>
                        <div class="col-md-12">
                            <button type="button" class="btn btn-outline-info btn-sm w-100" data-bs-toggle="modal" data-bs-target="#modalPreviewEvento" id="btnPrevNuevo">
                                <i class="bi bi-eye me-1"></i> Vista previa de la notificación
                            </button>
                            <small class="text-muted d-block text-center mt-1">Muestra el correo tal y como lo verán los tutores.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="bi bi-save"></i> Guardar e Informar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- MODAL EDITAR EVENTO -->
<!-- ========================================================== -->
<div class="modal fade" id="modalEditarEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-fill"></i> Editar Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEditarEvento" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="editar_evento">
                    <input type="hidden" name="id_evento" id="edit_id_evento" value="0">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Título del Evento *</label>
                            <input type="text" name="titulo" id="edit_titulo" class="form-control form-control-sm" required placeholder="Ej: Festival de Fin de Año">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Fecha *</label>
                            <input type="date" name="fecha" id="edit_fecha" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Hora</label>
                            <input type="time" name="hora" id="edit_hora" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Lugar</label>
                            <input type="text" name="lugar" id="edit_lugar" class="form-control form-control-sm" placeholder="Ej: Teatro Municipal">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Enlace de Ubicación</label>
                            <input type="url" name="enlace_ubicacion" id="edit_enlace" class="form-control form-control-sm" placeholder="https://maps.google.com/...">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold small">Descripción</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control form-control-sm" rows="2" placeholder="Detalles adicionales..."></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">Color</label>
                            <input type="color" name="color" id="edit_color" class="form-control form-control-color w-100" value="#c81015" style="height: 38px;">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">Flyer / Imagen</label>
                            <input type="file" name="imagen" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-danger mb-1 small"><i class="bi bi-bell-fill"></i> Seleccionar Cursos a Notificar:</label>
                            <div class="p-3 border rounded bg-light" style="max-height: 250px; overflow-y: auto;">
                                <?php foreach ($cursosPorTipo as $tipo => $cursos): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small text-uppercase text-muted"><?= $tipo ?></span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm seleccionar-todos-edit" data-tipo="<?= $tipo ?>">
                                                Seleccionar todos
                                            </button>
                                        </div>
                                        <div class="row g-1 mt-1">
                                            <?php foreach ($cursos as $curso): ?>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="ramas[]" value="<?= $curso['id_curso'] ?>" id="edit_curso_check_<?= $curso['id_curso'] ?>">
                                                        <label class="form-check-label small" for="edit_curso_check_<?= $curso['id_curso'] ?>">
                                                            <?= htmlspecialchars($curso['nombre']) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">A los cursos marcados se les generará una notificación automática.</small>
                        </div>
                        <div class="col-md-12">
                            <button type="button" class="btn btn-outline-info btn-sm w-100" data-bs-toggle="modal" data-bs-target="#modalPreviewEvento" id="btnPrevEditar">
                                <i class="bi bi-eye me-1"></i> Vista previa de la notificación
                            </button>
                            <small class="text-muted d-block text-center mt-1">Muestra el correo tal y como lo verán los tutores.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold"><i class="bi bi-save"></i> Actualizar Evento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- MODAL VISTA PREVIA DE LA NOTIFICACIÓN -->
<!-- ========================================================== -->
<div class="modal fade" id="modalPreviewEvento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Vista previa de la notificación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="contenidoPreview" style="background:#f2f2f2;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscador');
    if (buscador) {
        buscador.addEventListener('keyup', function() {
            const filtro = this.value.toLowerCase();
            document.querySelectorAll('#tablaEventos tbody tr').forEach(fila => {
                fila.style.display = fila.textContent.toLowerCase().includes(filtro) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.seleccionar-todos').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = this.closest('.mb-3');
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            const todasMarcadas = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !todasMarcadas);
            this.textContent = todasMarcadas ? 'Seleccionar todos' : 'Deseleccionar todos';
        });
    });

    document.querySelectorAll('.seleccionar-todos-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = this.closest('.mb-3');
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            const todasMarcadas = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !todasMarcadas);
            this.textContent = todasMarcadas ? 'Seleccionar todos' : 'Deseleccionar todos';
        });
    });

    document.querySelectorAll('#tablaEventos [data-evento]').forEach(btn => {
        btn.addEventListener('click', function() {
            const ev = JSON.parse(this.dataset.evento);
            eventoEditando = ev;
            document.getElementById('edit_id_evento').value = ev.id_evento;
            document.getElementById('edit_titulo').value = ev.titulo;
            document.getElementById('edit_fecha').value = ev.fecha;
            document.getElementById('edit_hora').value = ev.hora || '';
            document.getElementById('edit_lugar').value = ev.lugar || '';
            document.getElementById('edit_enlace').value = ev.enlace_ubicacion || '';
            document.getElementById('edit_descripcion').value = ev.descripcion || '';
            document.getElementById('edit_color').value = ev.color || '#c81015';

            const ramasIds = ev.ramas ? ev.ramas.map(r => r.id_curso) : [];
            document.querySelectorAll('#modalEditarEvento input[name="ramas[]"]').forEach(cb => {
                cb.checked = ramasIds.includes(parseInt(cb.value));
            });

            document.querySelectorAll('.seleccionar-todos-edit').forEach(btn => {
                const container = btn.closest('.mb-3');
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                const todasMarcadas = Array.from(checkboxes).every(cb => cb.checked);
                btn.textContent = todasMarcadas ? 'Deseleccionar todos' : 'Seleccionar todos';
            });
        });
    });

    const buscarRamas = document.getElementById('buscarRamas');
    if (buscarRamas) {
        buscarRamas.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.rama-item').forEach(it => {
                it.style.display = (!q || (it.dataset.nombre || '').toLowerCase().includes(q)) ? '' : 'none';
            });
        });
    }

    const CURSOS_MAP = <?= json_encode(array_reduce($cursosPorTipo, function($acc,$cs){ foreach($cs as $c){ $acc[$c['id_curso']] = $c['tipo'] . ' - ' . $c['nombre']; } return $acc; }, []), JSON_UNESCAPED_UNICODE) ?>;
    function escHtml(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function formatFecha(iso){
        if (!iso) return '—';
        const p = String(iso).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
    }
    let eventoEditando = null;

    function buildTemplate(d) {
        const color = /^#[0-9a-fA-F]{6}$/.test(d.color || '') ? d.color : '#c81015';
        const titulo = escHtml(d.titulo) || 'Evento';
        const cursoLabel = d.cursos.length === 0 ? 'General'
            : d.cursos.length === 1 ? escHtml(d.cursos[0])
            : escHtml(d.cursos[0]) + ' y ' + (d.cursos.length - 1) + ' más';
        const flyer = d.imagenSrc
            ? `<div style="background-color:#ffffff;"><img src="${escHtml(d.imagenSrc)}" width="600" alt="${titulo}" style="display:block;width:100%;max-width:600px;height:auto;"></div>`
            : '';
        const desc = d.descripcion ? `
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #eeeeee;padding-top:14px;margin-top:6px;">
              <tr><td>
                <p style="margin:0 0 4px;font-size:12px;color:#888888;text-transform:uppercase;letter-spacing:0.5px;">Descripción</p>
                <p style="margin:0;font-size:14px;color:#555555;line-height:1.6;">${escHtml(d.descripcion).replace(/\n/g,'<br>')}</p>
              </td></tr>
            </table>` : '';
        const mapa = d.enlace ? `
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:22px;">
              <tr><td style="background-color:${color};border-radius:4px;">
                <a href="${escHtml(d.enlace)}" target="_blank" style="display:inline-block;padding:10px 20px;font-size:14px;color:#ffffff;text-decoration:none;font-family:Arial,sans-serif;">Ver ubicación en el mapa</a>
              </td></tr>
            </table>` : '';
        return `
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f2f2;padding:24px 0;">
          <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
              <tr>
                <td style="background-color:#C81015;padding:16px 24px;" align="left">
                  <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="width:32px;height:32px;background-color:rgba(255,255,255,0.15);border-radius:6px;text-align:center;vertical-align:middle;color:#ffffff;font-size:13px;font-weight:bold;">EA</td>
                      <td style="padding-left:10px;color:#ffffff;font-size:15px;font-weight:bold;">Instituto EvolucionArte</td>
                    </tr>
                  </table>
                </td>
              </tr>
              ${flyer}
              <tr>
                <td style="padding:24px;">
                  <p style="margin:0 0 4px;font-size:12px;color:#888888;text-transform:uppercase;letter-spacing:0.5px;">Curso: ${cursoLabel}</p>
                  <h1 style="margin:0 0 18px;font-size:22px;color:${color};">${titulo}</h1>
                  <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                    <tr><td style="padding:4px 0;font-size:14px;color:#333333;">📅 <strong>Fecha:</strong> ${escHtml(formatFecha(d.fecha))}</td></tr>
                    <tr><td style="padding:4px 0;font-size:14px;color:#333333;">🕒 <strong>Hora:</strong> ${escHtml(d.hora || 'Sin horario')}</td></tr>
                    <tr><td style="padding:4px 0;font-size:14px;color:#333333;">📍 <strong>Lugar:</strong> ${escHtml(d.lugar || 'No especificado')}</td></tr>
                  </table>
                  ${mapa}
                  ${desc}
                </td>
              </tr>
              <tr>
                <td style="background-color:#f7f7f7;padding:14px 24px;text-align:center;">
                  <p style="margin:0 0 4px;font-size:12px;color:#999999;">Este correo fue enviado automáticamente por EvoSpace.</p>
                  <p style="margin:0;font-size:12px;color:#999999;">Instituto EvolucionArte · Ingresá a tu panel de tutor/a para más detalles.</p>
                </td>
              </tr>
            </table>
          </td></tr>
        </table>`;
    }

    function recogerPreview(modalId, ev) {
        const root = document.getElementById(modalId);
        const d = { cursos: [] };
        ['titulo','fecha','hora','lugar','enlace_ubicacion','descripcion','color'].forEach(c => {
            const el = root.querySelector(`[name="${c}"]`);
            d[c] = el ? el.value : '';
        });
        root.querySelectorAll('input[name="ramas[]"]:checked').forEach(cb => {
            d.cursos.push(CURSOS_MAP[cb.value] || 'Curso');
        });
        const fileInput = root.querySelector('input[name="imagen"]');
        if (fileInput && fileInput.files && fileInput.files[0]) {
            d.imagenSrc = URL.createObjectURL(fileInput.files[0]);
        } else if (ev && ev.imagen) {
            d.imagenSrc = '/evospace/' + ev.imagen;
        } else {
            d.imagenSrc = '';
        }
        return d;
    }

    const btnPrevNuevo = document.getElementById('btnPrevNuevo');
    if (btnPrevNuevo) btnPrevNuevo.addEventListener('click', () => {
        document.getElementById('contenidoPreview').innerHTML = buildTemplate(recogerPreview('modalNuevoEvento', null));
    });
    const btnPrevEditar = document.getElementById('btnPrevEditar');
    if (btnPrevEditar) btnPrevEditar.addEventListener('click', () => {
        document.getElementById('contenidoPreview').innerHTML = buildTemplate(recogerPreview('modalEditarEvento', eventoEditando));
    });
});
</script>

<?php include '../../includes/footer.php'; ?>