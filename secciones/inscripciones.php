<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
if (!empty($_POST['ajax'])) {
    ob_start();
}
include '../includes/header.php';
include '../includes/navbar.php';
require_once '../config/db.php';

if (!tienePermiso('alumnos') && $_SESSION['rol'] !== 'admin') {
    header('Location: /evospace/index.php');
    exit;
}

$mensaje = '';
$tipoMensaje = 'info';

$dias = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

$cursos = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM alumnos WHERE id_curso = c.id_curso AND activo = 1) AS inscriptos
    FROM cursos c WHERE c.activo = 1 ORDER BY c.tipo, c.orden
")->fetchAll(PDO::FETCH_ASSOC);

$padres = $pdo->query("SELECT id_usuario, usuario, nombre_completo, cedula FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);

function responderJson($ok, $mensaje, $extra = []) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok, 'mensaje' => $mensaje], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'crear_padre') {
    verificarTokenCSRF();
    $esAjax = !empty($_POST['ajax']);
    $nombre = trim($_POST['nombre_padre']);
    $usuario = trim($_POST['usuario_padre']);
    $email = trim($_POST['email_padre']);
    $cedula = trim($_POST['cedula_padre']);
    $password = $_POST['password_padre'];
    $dia_cobro = isset($_POST['dia_cobro_padre']) && $_POST['dia_cobro_padre'] !== '' ? (int)$_POST['dia_cobro_padre'] : null;
    if ($dia_cobro !== null && ($dia_cobro < 1 || $dia_cobro > 31)) {
        $dia_cobro = null;
    }
    if (empty($nombre) || empty($usuario) || empty($email) || empty($cedula) || empty($password)) {
        if ($esAjax) responderJson(false, 'Todos los campos son obligatorios.');
        $mensaje = 'Todos los campos son obligatorios.';
        $tipoMensaje = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($esAjax) responderJson(false, 'Email inválido.');
        $mensaje = 'Email inválido.';
        $tipoMensaje = 'danger';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? OR usuario = ? OR cedula = ?");
        $stmt->execute([$email, $usuario, $cedula]);
        $existente = $stmt->fetch();
        if ($existente) {
            if ($esAjax) responderJson(false, 'Ya existe un usuario con ese email, usuario o cédula.');
            $mensaje = 'Ya existe un usuario con ese email, usuario o cédula.';
            $tipoMensaje = 'danger';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, nombre_completo, email, cedula, password_hash, id_rol, activo, dia_cobro) VALUES (?, ?, ?, ?, ?, 3, 1, ?)");
            $stmt->execute([$usuario, $nombre, $email, $cedula, $hash, $dia_cobro]);
            $nuevoId = $pdo->lastInsertId();
            if ($esAjax) responderJson(true, 'Tutor/a creado correctamente y seleccionado.', ['id' => (int) $nuevoId, 'nombre' => $nombre, 'cedula' => $cedula]);
            $mensaje = 'Tutor/a creado correctamente y seleccionado en el formulario.';
            $tipoMensaje = 'success';
            $padres = $pdo->query("SELECT id_usuario, usuario, nombre_completo, cedula FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'inscribir') {
    verificarTokenCSRF();
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $ci = trim($_POST['ci']);
    $telefono = trim($_POST['telefono']);
    $id_curso = (int)$_POST['id_curso'];
    $anio_ingreso = (int)$_POST['anio_ingreso'];
    $id_padre = !empty($_POST['id_padre']) ? (int)$_POST['id_padre'] : null;
    $becado = isset($_POST['becado']) ? 1 : 0;

    if (empty($nombre) || empty($apellido) || empty($ci)) {
        $mensaje = 'Nombre, apellido y cédula son obligatorios.';
        $tipoMensaje = 'danger';
    } else {
        $curso = current(array_filter($cursos, fn($c) => $c['id_curso'] == $id_curso));
        if ($curso && $curso['cupo_maximo'] && $curso['inscriptos'] >= $curso['cupo_maximo']) {
            $mensaje = 'El curso "' . htmlspecialchars($curso['nombre']) . '" ya alcanzó el cupo máximo (' . $curso['cupo_maximo'] . ').';
            $tipoMensaje = 'danger';
        } else {
            $stmt = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE ci = ?");
            $stmt->execute([$ci]);
            if ($stmt->fetch()) {
                $mensaje = 'Ya existe un alumno con CI ' . htmlspecialchars($ci) . '.';
                $tipoMensaje = 'danger';
            } else {
                $stmt = $pdo->prepare("INSERT INTO alumnos (nombre, apellido, id_curso, anio_ingreso, ci, telefono, id_padre, becado, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$nombre, $apellido, $id_curso, $anio_ingreso, $ci, $telefono, $id_padre, $becado]);
                $id_alumno = $pdo->lastInsertId();
                $mensaje = 'Alumno ' . htmlspecialchars($nombre . ' ' . $apellido) . ' inscripto correctamente en ' . htmlspecialchars($curso['nombre']) . '.';
                $tipoMensaje = 'success';
            }
        }
    }
}

$horarios_por_curso = $pdo->query("
    SELECT h.*, u.usuario AS profe_nombre
    FROM horarios h
    LEFT JOIN profesores p ON h.id_profesor = p.id_profesor
    LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
    ORDER BY h.hora_inicio
")->fetchAll(PDO::FETCH_ASSOC);

$horarios_agrupados = [];
foreach ($horarios_por_curso as $h) {
    $horarios_agrupados[$h['id_curso']][] = $h;
}
?>
<div class="container mt-3 pb-4">
    <div class="page-header">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-person-plus-fill me-2"></i>Inscripciones</h4>
            <small>Inscribí un alumno nuevo en un curso</small>
        </div>
        <a href="alumnos.php" class="btn btn-light btn-sm"><i class="bi bi-people-fill"></i> Ver todos los alumnos</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show mt-3"><?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4 mt-2">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-evo text-white"><i class="bi bi-person-fill"></i> Datos del alumno</div>
                <div class="card-body">
                    <form method="POST" id="formInscripcion">
                        <?= campoCSRF() ?>
                        <input type="hidden" name="accion" value="inscribir">
                        <input type="hidden" name="id_curso" id="id_curso_inscripcion" value="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Curso *</label>
                            <button type="button" class="btn btn-outline-danger w-100 d-flex justify-content-between align-items-center py-2 border-2" id="btnCursoInscripcion" onclick="cursoPickerAbrir('id_curso_inscripcion','lblCursoInscripcion')">
                                <span id="lblCursoInscripcion"><i class="bi bi-book me-1"></i> Seleccionar curso...</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Nombre *</label>
                            <input type="text" name="nombre" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Apellido *</label>
                            <input type="text" name="apellido" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Cédula *</label>
                            <input type="text" name="ci" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Teléfono</label>
                            <input type="text" name="telefono" class="form-control form-control-sm">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Año ingreso *</label>
                            <input type="number" name="anio_ingreso" class="form-control form-control-sm" value="<?= date('Y') ?>" required min="2000">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Tutor/a</label>
                            <div class="d-flex gap-1">
                                <select name="id_padre" id="selectPadre" class="form-select form-select-sm flex-fill">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($padres as $p): ?>
                                        <option value="<?= $p['id_usuario'] ?>"><?= htmlspecialchars($p['nombre_completo'] . ' (' . $p['cedula'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCrearPadre" title="Crear nuevo tutor/a"><i class="bi bi-plus-lg"></i></button>
                            </div>
                            <div id="alertaCrearPadre" class="small mt-1 d-none"></div>
                        </div>
                    <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="becado" id="becado" class="form-check-input" value="1">
                                <label class="form-check-label small" for="becado">Descuento (beca)</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger w-100" id="btnInscribir">Inscribir alumno</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Padre -->
<div class="modal fade" id="modalCrearPadre" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill"></i> Nuevo Tutor/a</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formCrearPadre">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="crear_padre">
                    <div class="mb-3">
                        <label class="form-label">Nombre completo *</label>
                        <input type="text" name="nombre_padre" class="form-control" required placeholder="Ej: Juan Pérez">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usuario *</label>
                        <input type="text" name="usuario_padre" class="form-control" required placeholder="nombre_usuario">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula *</label>
                        <input type="text" name="cedula_padre" class="form-control" required placeholder="Ej: 1234567-8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email_padre" class="form-control" required placeholder="ejemplo@correo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" name="password_padre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Día de cobro (cuota mensual)</label>
                        <input type="number" name="dia_cobro_padre" class="form-control" min="1" max="31" placeholder="Ej: 10">
                        <small class="text-muted">Día del mes en que este tutor/a paga la cuota de sus hijos.</small>
                    </div>
                    <div id="alertaCrearPadre" class="small d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Crear tutor/a</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
<?php if ($tipoMensaje === 'success' && ($_POST['accion'] ?? '') === 'crear_padre'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('selectPadre');
    sel.value = '<?= $nuevoId ?? '' ?>';
});
<?php endif; ?>

(function() {
    const form = document.getElementById('formCrearPadre');
    if (!form) return;
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const aviso = document.getElementById('alertaCrearPadre');
        btn.disabled = true;
        const datos = new FormData(form);
        datos.set('ajax', '1');
        try {
            const resp = await fetch(location.pathname, { method: 'POST', body: datos });
            const data = await resp.json();
            if (data.ok) {
                const sel = document.getElementById('selectPadre');
                const opt = new Option(data.nombre + ' (' + data.cedula + ')', data.id, true, true);
                sel.appendChild(opt);
                aviso.className = 'small text-success mt-1';
                aviso.textContent = 'Tutor/a creado: ' + data.nombre + ' — ya quedó seleccionado.';
                form.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalCrearPadre'));
                if (modal) modal.hide();
                setTimeout(() => { aviso.className = 'small mt-1 d-none'; aviso.textContent = ''; }, 5000);
            } else {
                aviso.className = 'small text-danger mt-1';
                aviso.textContent = data.mensaje || 'Error al crear el tutor/a.';
            }
        } catch (err) {
            aviso.className = 'small text-danger mt-1';
            aviso.textContent = 'Error de conexión al crear el tutor/a.';
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>

<style>
</style>

<script>
document.getElementById('formInscripcion').addEventListener('submit', function(e) {
    const idCurso = document.getElementById('id_curso_inscripcion');
    if (!idCurso || !idCurso.value) {
        e.preventDefault();
        alert('Seleccioná un curso primero.');
    }
});
</script>

<?php include '../includes/curso_picker.php'; ?>
<?php include '../includes/footer.php'; ?>
