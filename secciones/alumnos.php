<?php

// secciones/alumnos.php

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../includes/header.php';   // ← primero carga functions.php
include '../includes/navbar.php';
require_once '../config/db.php';

verificarPermiso('alumnos');   // ← ahora funciona

$mensaje = '';
$tipoMensaje = 'info';

// ==========================================================
// PROCESAR ACCIONES DEL FORMULARIO
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';

    // ---------- ELIMINAR ----------
    if ($accion === 'eliminar' && isset($_POST['id_alumno'])) {
        $id = (int)$_POST['id_alumno'];
        $stmt = $pdo->prepare("DELETE FROM alumnos WHERE id_alumno = ?");
        if ($stmt->execute([$id])) {
            $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Alumno eliminado correctamente.';
            $tipoMensaje = 'success';
        } else {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error al eliminar.';
            $tipoMensaje = 'danger';
        }
    }

    // ---------- GUARDAR (AGREGAR / EDITAR) ----------
    if ($accion === 'guardar') {
        $id_alumno = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $id_curso = (int)$_POST['id_curso'];
        $anio_ingreso = (int)$_POST['anio_ingreso'];
        $horas_profesionales = (float)($_POST['horas_profesionales'] ?? 0);
        $ci = trim($_POST['ci']);
        $telefono = trim($_POST['telefono']);
        $id_padre = !empty($_POST['id_padre']) ? (int)$_POST['id_padre'] : NULL;
        $becado = isset($_POST['becado']) ? 1 : 0;
        $dia_vencimiento = isset($_POST['dia_vencimiento']) && $_POST['dia_vencimiento'] !== '' ? (int)$_POST['dia_vencimiento'] : null;
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre) || empty($apellido) || empty($ci)) {
            $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Nombre, apellido y cédula son obligatorios.';
            $tipoMensaje = 'danger';
        } else {
            // Verificar duplicados
            $stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE ci = ? AND id_alumno != ?");
            $stmt->execute([$ci, $id_alumno]);
            $dup = $stmt->fetch();
            if ($dup) {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Ya existe un alumno con CI \'<strong>' . htmlspecialchars($ci) . '</strong>\' asignado a <strong>' . htmlspecialchars($dup['nombre'] . ' ' . $dup['apellido']) . '</strong>.';
                $tipoMensaje = 'danger';
            } else {
            try {
                if (!$id_alumno) {
                    $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Usá Inscripciones para dar de alta nuevos alumnos.';
                    $tipoMensaje = 'danger';
                } else {
                    $sql = "UPDATE alumnos SET 
                                nombre=?, apellido=?, id_curso=?, anio_ingreso=?, 
                                horas_profesionales=?, ci=?, telefono=?, id_padre=?, becado=?, dia_vencimiento=?, activo=?
                            WHERE id_alumno=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $apellido, $id_curso, $anio_ingreso, $horas_profesionales, $ci, $telefono, $id_padre, $becado, $dia_vencimiento, $activo, $id_alumno]);
                    $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Alumno actualizado correctamente.';
                    $tipoMensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
                $tipoMensaje = 'danger';
            }
            }
        }
    }
}

// ==========================================================
// OBTENER LISTA DE ALUMNOS CON DATOS DE CURSO Y PADRE
// ==========================================================
$esProfesor = ($_SESSION['rol'] ?? '') === 'profesor';
$id_profesor = null;
if ($esProfesor) {
    $stmt = $pdo->prepare("SELECT id_profesor FROM profesores WHERE id_usuario = ?");
    $stmt->execute([(int)$_SESSION['id_usuario']]);
    $id_profesor = $stmt->fetchColumn();
}

$sql = "SELECT a.id_alumno, a.nombre, a.apellido, a.id_curso, a.anio_ingreso, a.ci, a.telefono, a.id_padre, a.becado, a.dia_vencimiento, a.activo,
               COALESCE((SELECT SUM(horas) FROM horas_profesionales_log WHERE id_alumno = a.id_alumno), 0) AS horas_profesionales,
               c.nombre AS curso_nombre, c.tipo AS curso_tipo,
               u.usuario AS nombre_padre, u.email AS email_padre
        FROM alumnos a
        INNER JOIN cursos c ON a.id_curso = c.id_curso
        LEFT JOIN usuarios u ON a.id_padre = u.id_usuario
        ORDER BY c.tipo, c.orden, a.apellido, a.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de cursos para el formulario
$stmt = $pdo->query("SELECT id_curso, nombre, tipo, orden FROM cursos WHERE activo = 1 ORDER BY tipo, orden");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$cursosPorTipo = [];
foreach ($cursos as $curso) {
    $cursosPorTipo[$curso['tipo']][] = $curso;
}

// Obtener lista de padres para el formulario (CORREGIDO: usa id_rol)
$stmt = $pdo->query("SELECT id_usuario, usuario, email FROM usuarios WHERE id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre') ORDER BY usuario");
$padres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================================
// DATOS AUXILIARES PARA EDICIÓN
// ==========================================================
$configGeneral = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('dia_limite_pago')")->fetchAll(PDO::FETCH_KEY_PAIR);

$alumnosKeyed = [];
foreach ($alumnos as $al) {
    $alumnosKeyed[$al['id_alumno']] = $al;
}
$alumnosJson = str_replace('</', '<\\/', json_encode($alumnosKeyed, JSON_UNESCAPED_UNICODE));
?>

<div class="container mt-3 pb-4">
    <div class="page-header">
        <div>
            <h4 class="h4 fw-bold mb-0"><i class="bi bi-people-fill me-2"></i>Gestión de Alumnos</h4>
            <small>Administra los alumnos del sistema</small>
        </div>
        <a href="inscripciones.php" class="btn btn-light btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Nueva Inscripción
        </a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show" role="alert">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros: buscador + curso -->
    <div class="row g-2 mb-3">
        <div class="col-md-8">
            <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar alumno por nombre, apellido o CI...">
        </div>
        <div class="col-md-4">
            <select id="filtroCurso" class="form-select form-select-sm">
                <option value="">Todos los cursos</option>
                <?php foreach ($cursosPorTipo as $tipo => $cursosTipo): ?>
                    <optgroup label="<?= htmlspecialchars($tipo) ?>">
                        <?php foreach ($cursosTipo as $curso): ?>
                            <option value="<?= (int)$curso['id_curso'] ?>"><?= htmlspecialchars($curso['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Tabla de alumnos -->
    <div class="card shadow">
        <div class="card-header bg-evo text-white py-2">
            <i class="bi bi-people-fill"></i> Alumnos Registrados
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="tablaAlumnos">
                    <thead class="text-center" style="background: var(--evo-bg-alt);">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Curso</th>
                            <th>Año Ingreso</th>
                            <th>Horas Prof.</th>
                            <th>CI</th>
                            <th>Teléfono</th>
                            <th>Tutor/a</th>
                            <th>Descto.</th>
                            <th>Activo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alumnos)): ?>
                            <tr><td colspan="11" class="text-center">No hay alumnos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($alumnos as $alumno): ?>
                                <tr>
                                    <td class="text-center align-middle"><?= $alumno['id_alumno'] ?></td>
                                    <td class="nombre-alumno align-middle"><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($alumno['curso_tipo'] . ' - ' . $alumno['curso_nombre']) ?></td>
                                    <td class="text-center align-middle"><?= $alumno['anio_ingreso'] ?></td>
                                    <td class="text-center align-middle"><?= $alumno['curso_tipo'] === 'Superior' ? number_format((float)$alumno['horas_profesionales'], 1) : '<span class="text-muted">—</span>' ?></td>
                                    <td class="text-center align-middle ci-alumno"><?= htmlspecialchars($alumno['ci']) ?></td>
                                    <td class="text-center align-middle"><?= !empty($alumno['telefono']) ? htmlspecialchars($alumno['telefono']) : '<span class="text-muted">—</span>' ?></td>
                                    <td class="text-center align-middle"><?= htmlspecialchars($alumno['nombre_padre'] ?? 'Sin asignar') ?></td>
                                    <td class="text-center align-middle">
                                        <?= $alumno['becado'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?= $alumno['activo'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="ficha_alumno.php?id=<?= $alumno['id_alumno'] ?>" class="btn btn-info btn-sm text-white" title="Ver ficha (editar/cobrar/pagos)">
                                                <i class="bi bi-file-person-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
            <small class="text-muted" id="infoPaginacion">Total: <?= count($alumnos) ?> alumnos</small>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="paginacion"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- MODAL para EDITAR ALUMNO -->
<!-- ========================================================== -->
<div class="modal fade" id="modalAlumno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title" id="modalTituloAlumno"><i class="bi bi-pencil-fill me-2"></i>Editar Alumno</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formAlumno">
                <div class="modal-body">
                    <?= campoCSRF() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_alumno" id="id_alumno" value="0">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" id="apellido" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Curso *</label>
                            <input type="hidden" name="id_curso" id="id_curso" value="">
                            <button type="button" class="btn btn-outline-danger w-100 d-flex justify-content-between align-items-center py-2 border-2" onclick="cursoPickerAbrir('id_curso','lblCursoEditar')">
                                <span id="lblCursoEditar"><i class="bi bi-book me-1"></i> Seleccionar curso...</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Año de ingreso *</label>
                            <input type="number" name="anio_ingreso" id="anio_ingreso" class="form-control" required min="2000" max="2099">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Horas profesionales</label>
                            <input type="number" step="0.01" name="horas_profesionales" id="horas_profesionales" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="ci" id="ci" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tutor/a</label>
                            <div style="position:relative;">
                                <input type="text" id="buscarPadre" class="form-control mb-1" placeholder="Buscar tutor/a por nombre o email..." autocomplete="off">
                                <input type="hidden" name="id_padre" id="id_padre" value="">
                                <div id="listaPadres" class="list-group" style="position:absolute;z-index:1000;display:none;max-height:180px;overflow-y:auto;width:100%;top:100%;left:0;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="becado" id="becado" class="form-check-input" value="1">
                                    <label class="form-check-label" for="becado">Descuento</label>
                                </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Día de vencimiento de cuota</label>
                            <input type="number" name="dia_vencimiento" id="dia_vencimiento" class="form-control" min="1" max="31" placeholder="Ej: 10">
                            <small class="text-muted">Vacío = usa la config general (día <?= (int)$configGeneral['dia_limite_pago'] ?>).</small>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="activo" id="activo" class="form-check-input" checked>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const ALUMNOS = <?= $alumnosJson ?>;

    const padreConf = {
        id: 'id_padre',
        input: 'buscarPadre',
        lista: 'listaPadres',
        fuente: <?= json_encode(array_map(function($p) {
            return ['id' => $p['id_usuario'], 'usuario' => $p['usuario'], 'email' => $p['email']];
        }, $padres), JSON_UNESCAPED_UNICODE) ?>
    };
    function buscarPadre(valor) {
        const lista = document.getElementById(padreConf.lista);
        const hidden = document.getElementById(padreConf.id);
        if (!valor.trim()) { lista.style.display = 'none'; hidden.value = ''; return; }
        const term = valor.toLowerCase();
        const filtrados = padreConf.fuente.filter(p =>
            p.usuario.toLowerCase().includes(term) || p.email.toLowerCase().includes(term)
        );
        if (filtrados.length === 0) { lista.style.display = 'none'; return; }
        lista.innerHTML = filtrados.map(p =>
            `<button type="button" class="list-group-item list-group-item-action py-1" onclick="seleccionarPadre(${p.id},'${p.usuario.replace(/'/g,"\\'")}')">${p.usuario} <small class="text-muted">(${p.email})</small></button>`
        ).join('');
        lista.style.display = 'block';
    }
    function seleccionarPadre(id, usuario) {
        document.getElementById(padreConf.input).value = usuario;
        document.getElementById(padreConf.id).value = id;
        document.getElementById(padreConf.lista).style.display = 'none';
    }

    function editarAlumno(id) {
        const alumno = ALUMNOS[id];
        if (!alumno) return;
        document.getElementById('modalTituloAlumno').innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar Alumno';
        document.getElementById('id_alumno').value = alumno.id_alumno;
        document.getElementById('nombre').value = alumno.nombre;
        document.getElementById('apellido').value = alumno.apellido;
        document.getElementById('id_curso').value = alumno.id_curso;
        const lblCurso = document.getElementById('lblCursoEditar');
        if (lblCurso) lblCurso.textContent = cursoPickerNombre(alumno.id_curso) || 'Seleccionar curso...';
        document.getElementById('anio_ingreso').value = alumno.anio_ingreso;
        document.getElementById('horas_profesionales').value = alumno.horas_profesionales || 0;
        document.getElementById('ci').value = alumno.ci;
        document.getElementById('telefono').value = alumno.telefono || '';
        document.getElementById('id_padre').value = alumno.id_padre || '';
        const padreMatch = padreConf.fuente.find(p => p.id == alumno.id_padre);
        document.getElementById('buscarPadre').value = padreMatch ? padreMatch.usuario : '';
        document.getElementById('becado').checked = (alumno.becado == 1);
        document.getElementById('dia_vencimiento').value = alumno.dia_vencimiento || '';
        document.getElementById('activo').checked = (alumno.activo == 1);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const buscador = document.getElementById('buscador');
        const filtroCurso = document.getElementById('filtroCurso');
        const tabla = document.getElementById('tablaAlumnos');

        const POR_PAGINA = 15;
        let pagina = 1;

        const cursosIdPorTexto = new Map([
            <?php foreach ($alumnos as $al): ?>[<?= json_encode(strtolower($al['curso_tipo'] . ' - ' . $al['curso_nombre'])) ?>, <?= (int)$al['id_curso'] ?>],
            <?php endforeach; ?>
        ]);

        function filasVisibles() {
            if (!tabla) return [];
            const tbody = tabla.querySelector('tbody');
            if (!tbody) return [];
            const texto = (buscador ? buscador.value.toLowerCase() : '');
            const curso = (filtroCurso ? filtroCurso.value : '');
            const filas = [];
            tbody.querySelectorAll('tr').forEach(fila => {
                const nombre = fila.cells[1]?.textContent.toLowerCase() || '';
                const cursoCelda = fila.cells[2]?.textContent.toLowerCase() || '';
                const ciCelda = fila.cells[5]?.textContent.toLowerCase() || '';
                const cursoId = cursosIdPorTexto.get(cursoCelda) ?? '';
                const matchTexto = !texto || nombre.includes(texto) || ciCelda.includes(texto);
                const matchCurso = !curso || String(cursoId) === curso;
                if (matchTexto && matchCurso) filas.push(fila);
            });
            return filas;
        }

        function renderPaginacion(filas) {
            const total = filas.length;
            const totalPaginas = Math.max(1, Math.ceil(total / POR_PAGINA));
            if (pagina > totalPaginas) pagina = totalPaginas;
            if (pagina < 1) pagina = 1;
            const inicio = (pagina - 1) * POR_PAGINA;
            const fin = Math.min(inicio + POR_PAGINA, total);

            filas.forEach((f, i) => { f.style.display = (i >= inicio && i < fin) ? '' : 'none'; });

            const info = document.getElementById('infoPaginacion');
            if (info) info.textContent = 'Mostrando ' + (total ? (inicio + 1) + '-' + fin : 0) + ' de ' + total + ' alumnos';

            const ul = document.getElementById('paginacion');
            if (!ul) return;
            ul.innerHTML = '';
            if (totalPaginas <= 1) return;
            const li = (label, disabled, active, click) => {
                const el = document.createElement('li');
                el.className = 'page-item' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = label;
                if (!disabled) a.addEventListener('click', (e) => { e.preventDefault(); click(); });
                el.appendChild(a);
                ul.appendChild(el);
            };
            li('‹', pagina === 1, false, () => { pagina--; renderPaginacion(filas); });
            for (let p = 1; p <= totalPaginas; p++) {
                li(p, false, p === pagina, () => { pagina = p; renderPaginacion(filas); });
            }
            li('›', pagina === totalPaginas, false, () => { pagina++; renderPaginacion(filas); });
        }

        function filtrar() {
            const filas = filasVisibles();
            if (tabla) {
                const tbody = tabla.querySelector('tbody');
                if (tbody) tbody.querySelectorAll('tr').forEach(fila => { fila.style.display = 'none'; });
            }
            renderPaginacion(filas);
        }

        if (buscador) buscador.addEventListener('keyup', () => { pagina = 1; filtrar(); });
        if (filtroCurso) filtroCurso.addEventListener('change', () => { pagina = 1; filtrar(); });
        filtrar();

        const inputPadre = document.getElementById('buscarPadre');
        if (inputPadre) {
            inputPadre.addEventListener('input', function() { buscarPadre(this.value); });
            inputPadre.addEventListener('blur', function() {
                setTimeout(() => {
                    const lista = document.getElementById('listaPadres');
                    if (lista) lista.style.display = 'none';
                }, 200);
            });
        }
    });
</script>

<?php include '../includes/curso_picker.php'; ?>
<?php include '../includes/footer.php'; ?>