<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
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

$padres = $pdo->query("SELECT id_usuario, usuario FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY usuario")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'crear_padre') {
    $nombre = trim($_POST['nombre_padre']);
    $usuario = trim($_POST['usuario_padre']);
    $email = trim($_POST['email_padre']);
    $cedula = trim($_POST['cedula_padre']);
    $password = $_POST['password_padre'];
    if (empty($nombre) || empty($usuario) || empty($email) || empty($cedula) || empty($password)) {
        $mensaje = 'Todos los campos son obligatorios.';
        $tipoMensaje = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Email inválido.';
        $tipoMensaje = 'danger';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? OR usuario = ? OR cedula = ?");
        $stmt->execute([$email, $usuario, $cedula]);
        $existente = $stmt->fetch();
        if ($existente) {
            $mensaje = 'Ya existe un usuario con ese email, usuario o cédula.';
            $tipoMensaje = 'danger';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, nombre_completo, email, cedula, password_hash, id_rol, activo) VALUES (?, ?, ?, ?, ?, 3, 1)");
            $stmt->execute([$usuario, $nombre, $email, $cedula, $hash]);
            $nuevoId = $pdo->lastInsertId();
            $mensaje = 'Padre creado correctamente. Ya podés seleccionarlo.';
            $tipoMensaje = 'success';
$padres = $pdo->query("SELECT id_usuario, usuario, nombre_completo, cedula FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'inscribir') {
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
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-evo text-white"><i class="bi bi-person-fill"></i> Datos del alumno</div>
                <div class="card-body">
                    <form method="POST" id="formInscripcion">
                        <input type="hidden" name="accion" value="inscribir">
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
                            <label class="form-label small">Padre/Madre</label>
                            <div class="d-flex gap-1">
                                <select name="id_padre" id="selectPadre" class="form-select form-select-sm flex-fill">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($padres as $p): ?>
                                        <option value="<?= $p['id_usuario'] ?>"><?= htmlspecialchars($p['nombre_completo'] . ' (' . $p['cedula'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCrearPadre" title="Crear nuevo padre"><i class="bi bi-plus-lg"></i></button>
                            </div>
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
        <div class="col-md-7">
            <h5 class="text-secondary"><i class="bi bi-book"></i> Seleccionar curso</h5>
            <div class="row g-2" id="listaCursos">
                <?php foreach ($cursos as $curso):
                    $cupo_completo = $curso['cupo_maximo'] && $curso['inscriptos'] >= $curso['cupo_maximo'];
                    $horarios = $horarios_agrupados[$curso['id_curso']] ?? [];
                ?>
                    <div class="col-6 col-lg-4">
                        <div class="card curso-card h-100 <?= $cupo_completo ? 'border-danger opacity-50' : '' ?>" data-id="<?= $curso['id_curso'] ?>" data-nombre="<?= htmlspecialchars($curso['nombre'] . ' (' . $curso['tipo'] . ')') ?>" onclick="seleccionarCurso(this)">
                            <div class="card-body p-2 text-center">
                                <h6 class="card-title mb-1 small"><?= htmlspecialchars($curso['nombre']) ?></h6>
                                <span class="badge bg-secondary"><?= $curso['tipo'] ?></span>
                                <?php if ($curso['cupo_maximo']): ?>
                                    <span class="badge bg-<?= $cupo_completo ? 'danger' : 'success' ?> ms-1">
                                        <?= $curso['inscriptos'] ?>/<?= $curso['cupo_maximo'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-info ms-1"><?= $curso['inscriptos'] ?> insc.</span>
                                <?php endif; ?>
                                <?php if (!empty($horarios)): ?>
                                    <div class="mt-1 small text-muted">
                                                        <?php foreach ($horarios as $h): 
                                                            $diasArr = explode(',', $h['dia_semana']); ?>
                                                            <div><?php foreach ($diasArr as $d): ?><span class="badge bg-secondary me-1" style="font-size:0.6rem;"><?= $dias[(int)trim($d)] ?? '?' ?></span><?php endforeach; ?> <?= substr($h['hora_inicio'], 0, 5) ?>-<?= substr($h['hora_fin'], 0, 5) ?></div>
                                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($cupo_completo): ?>
                                    <div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle-fill"></i> Cupo lleno</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Padre -->
<div class="modal fade" id="modalCrearPadre" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill"></i> Nuevo Padre/Madre</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Crear padre</button>
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
</script>

<style>
.curso-card { cursor: pointer; transition: all .15s; border: 2px solid transparent; }
.curso-card:hover { border-color: var(--evo-red, #c81015); }
.curso-card.selected { border-color: var(--evo-red, #c81015); background: #fef2f2; }
</style>

<script>
let cursoSeleccionado = null;

function seleccionarCurso(el) {
    document.querySelectorAll('.curso-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    cursoSeleccionado = el.dataset.id;
    document.getElementById('btnInscribir').textContent = 'Inscribir en ' + el.dataset.nombre;
}

document.getElementById('formInscripcion').addEventListener('submit', function(e) {
    if (!cursoSeleccionado) {
        e.preventDefault();
        alert('Seleccioná un curso primero.');
        return;
    }
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id_curso';
    input.value = cursoSeleccionado;
    this.appendChild(input);
});
</script>

<?php include '../includes/footer.php'; ?>
