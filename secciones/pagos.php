<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../includes/header.php';   // ← primero carga functions.php
include '../includes/navbar.php';
require_once '../config/db.php';

verificarPermiso('pagos');   // ← ahora funciona
$mensaje = '';

// Procesar nuevo pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_pago') {
    $id_alumno = (int)$_POST['id_alumno'];
    $fecha = $_POST['fecha'];
    $concepto = trim($_POST['concepto']);
    $cantidad = (int)$_POST['cantidad'];
    $monto = (float)$_POST['monto'];
    $recargo = (float)$_POST['recargo'];
    $total = (float)$_POST['total'];
    $metodo_pago = $_POST['metodo_pago'];

    $sql = "INSERT INTO pagos (id_alumno, fecha, concepto, cantidad, monto, descuento, recargo, total, metodo_pago)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([$id_alumno, $fecha, $concepto, $cantidad, $monto, $recargo, $total, $metodo_pago]);
        $mensaje = '<i class="bi bi-check-circle-fill text-success"></i> Pago registrado correctamente.';
    } catch (PDOException $e) {
        $mensaje = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Error: ' . $e->getMessage();
    }
}

// Obtener tipos de cursos
$tipos = ['Acrotelas', 'Infantil', 'Superior'];
$tipoSeleccionado = $_GET['tipo'] ?? '';
$cursoSeleccionado = $_GET['curso'] ?? 0;

$cursosFiltro = [];
if ($tipoSeleccionado) {
    $sql = "SELECT * FROM cursos WHERE tipo = ? AND activo = 1 ORDER BY orden";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tipoSeleccionado]);
    $cursosFiltro = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener alumnos según filtros
$alumnos = [];
$sql = "SELECT a.*, c.nombre AS curso_nombre, c.tipo AS curso_tipo
        FROM alumnos a
        INNER JOIN cursos c ON a.id_curso = c.id_curso
        WHERE a.activo = 1";
$params = [];
if ($tipoSeleccionado) {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipoSeleccionado;
}
if ($cursoSeleccionado > 0) {
    $sql .= " AND a.id_curso = ?";
    $params[] = $cursoSeleccionado;
}
$sql .= " ORDER BY a.apellido, a.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener precios para el curso seleccionado
$preciosCurso = [];
if ($cursoSeleccionado > 0) {
    $sql = "SELECT * FROM precios WHERE id_curso = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cursoSeleccionado]);
    $preciosCurso = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener configuración de recargo y día límite
$config = [];
$stmt = $pdo->query("SELECT clave, valor FROM configuracion");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['clave']] = $row['valor'];
}
$porcentajeBeca = (float)($config['porcentaje_beca'] ?? 45.45);
$recargoPorDia = (float)($config['recargo_por_dia'] ?? 1000);
$diaLimite = (int)($config['dia_limite_pago'] ?? 10);

// Conceptos disponibles
$conceptos = ['matrícula', 'cuota', 'vestuarios', 'entradas', 'folleto'];
$iconos = [
    'matrícula' => 'bi-file-earmark-text',
    'cuota' => 'bi-calendar-check',
    'vestuarios' => 'bi-person',
    'entradas' => 'bi-ticket',
    'folleto' => 'bi-book'
];
$conceptoSeleccionado = $_GET['concepto'] ?? '';
?>

<div class="container mt-3">
    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?= $mensaje ?></div>
    <?php endif; ?>

    <!-- FILTROS -->
    <div class="card shadow mb-3">
        <div class="card-header bg-danger text-white py-2">
            <i class="bi bi-funnel"></i> Filtrar por curso
        </div>
        <div class="card-body py-2">
            <form method="GET" id="filtroForm">
                <div class="row g-2">
                    <div class="col-md-4 d-flex flex-column">
                        <label class="form-label mb-1 small">Tipo de curso</label>
                        <select name="tipo" id="filtroTipo" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= $tipoSeleccionado === $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex flex-column">
                        <label class="form-label mb-1 small">Curso</label>
                        <select name="curso" id="filtroCurso" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="0">Todos los cursos</option>
                            <?php foreach ($cursosFiltro as $curso): ?>
                                <option value="<?= $curso['id_curso'] ?>" <?= $cursoSeleccionado == $curso['id_curso'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($curso['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex flex-column justify-content-end">
                        <a href="?<?= http_build_query(['tipo' => '', 'curso' => 0, 'concepto' => '']) ?>" class="btn btn-secondary btn-sm w-100">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar filtros
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- BUSCADOR POR NOMBRE -->
    <div class="row g-2 mb-3">
        <div class="col-md-12">
            <input type="text" id="buscador" class="form-control form-control-sm" placeholder="Buscar alumno por nombre...">
        </div>
    </div>

    <!-- CARDS DE CONCEPTOS -->
    <?php if ($cursoSeleccionado > 0): ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white py-2">
                <i class="bi bi-tags"></i> Conceptos de pago para este curso
            </div>
            <div class="card-body py-2">
                <div class="row g-2">
                    <?php foreach ($conceptos as $concepto):
                        $precioData = array_filter($preciosCurso, function ($p) use ($concepto) {
                            return $p['concepto'] === $concepto;
                        });
                        $precio = !empty($precioData) ? reset($precioData)['precio'] : 0;
                        $tienePrecio = $precio > 0;
                    ?>
                        <div class="col-6 col-md-4 col-lg-2">
                            <div class="card text-center shadow-sm h-100 <?= ($conceptoSeleccionado === $concepto) ? 'border-danger border-3' : '' ?> <?= !$tienePrecio ? 'opacity-50' : '' ?>"
                                style="cursor: pointer;" onclick="seleccionarConcepto('<?= $concepto ?>')">
                                <div class="card-body p-2">
                                    <i class="bi <?= $iconos[$concepto] ?> fs-2 text-<?= $tienePrecio ? 'danger' : 'secondary' ?>"></i>
                                    <h6 class="card-title mt-1 mb-0 small"><?= ucfirst($concepto) ?></h6>
                                    <?php if ($tienePrecio): ?>
                                        <span class="badge bg-success small">Gs <?= number_format($precio, 0, ',', '.') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary small">Sin precio</span>
                                    <?php endif; ?>
                                    <?php if ($conceptoSeleccionado === $concepto): ?>
                                        <span class="badge bg-danger d-block mt-1">Seleccionado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TABLA DE ALUMNOS -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white py-2">
            <i class="bi bi-people-fill"></i> Alumnos
            <?php if ($tipoSeleccionado): ?>
                <span class="badge bg-light text-dark ms-2"><?= $tipoSeleccionado ?></span>
            <?php endif; ?>
            <?php if ($cursoSeleccionado > 0): ?>
                <span class="badge bg-light text-dark ms-2">
                    <?php
                    $cursoNombre = array_filter($cursosFiltro, function ($c) use ($cursoSeleccionado) {
                        return $c['id_curso'] == $cursoSeleccionado;
                    });
                    echo !empty($cursoNombre) ? reset($cursoNombre)['nombre'] : 'Curso';
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-2">
            <?php if (empty($alumnos)): ?>
                <div class="alert alert-warning">No hay alumnos para los filtros seleccionados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="tablaAlumnos">
                        <thead class="text-center">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Curso</th>
                                <th>Becado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos as $alumno): ?>
                                <tr>
                                    <td class="nombre-alumno"><?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($alumno['curso_tipo']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($alumno['curso_nombre']) ?></td>
                                    <td class="text-center">
                                        <?= $alumno['becado'] ? '<i class="bi bi-check-circle-fill text-success fs-5"></i>' : '<i class="bi bi-x-circle-fill text-danger fs-5"></i>' ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm" onclick="verPagos(<?= $alumno['id_alumno'] ?>)">
                                            <i class="bi bi-eye-fill"></i>
                                            <span class="d-none d-sm-inline">Ver pagos</span>
                                        </button>
                                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalPago"
                                            data-alumno='<?= json_encode([
                                                'id_alumno' => $alumno['id_alumno'],
                                                'nombre' => $alumno['nombre'],
                                                'apellido' => $alumno['apellido'],
                                                'curso_tipo' => $alumno['curso_tipo'],
                                                'curso_nombre' => $alumno['curso_nombre'],
                                                'id_curso' => $alumno['id_curso'],
                                                'becado' => $alumno['becado']
                                            ], JSON_HEX_APOS) ?>'>
                                            <i class="bi bi-plus-circle-fill"></i>
                                            <span class="d-none d-sm-inline">Nuevo pago</span>
                                        </button>
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
<!-- MODAL para VER PAGOS -->
<!-- ========================================================== -->
<div class="modal fade" id="modalVerPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="contenidoVerPagos">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- MODAL para NUEVO PAGO (sin descuento) -->
<!-- ========================================================== -->
<div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle-fill"></i> Nuevo Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formPago">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar_pago">
                    <input type="hidden" name="id_alumno" id="pago_id_alumno" value="">
                    <input type="hidden" name="id_curso" id="pago_id_curso" value="">
                    <input type="hidden" name="porcentaje_beca_global" id="porcentaje_beca_global" value="<?= obtenerPorcentajeBeca($pdo) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Alumno</label>
                            <input type="text" id="pago_alumno_nombre" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Curso</label>
                            <input type="text" id="pago_curso" class="form-control" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha *</label>
                            <input type="date" name="fecha" id="pago_fecha" class="form-control" required onchange="calcularRecargo()" oninput="calcularRecargo()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Concepto *</label>
                            <select name="concepto" id="pago_concepto" class="form-select" required onchange="actualizarPrecio()">
                                <option value="">Seleccionar concepto</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" id="pago_cantidad" class="form-control" value="1" min="1" onchange="calcularTotal()" oninput="calcularTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Monto unitario *</label>
                            <input type="number" step="0.01" name="monto" id="pago_monto" class="form-control" required oninput="calcularTotal()">
                            <small class="text-muted" id="pago_beca_info"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Recargo (Gs)</label>
                            <input type="number" step="0.01" name="recargo" id="pago_recargo" class="form-control" value="0" readonly>
                            <small class="text-muted" id="pago_recargo_info"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total (Gs)</label>
                            <input type="number" step="0.01" name="total" id="pago_total" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Método de pago</label>
                            <select name="metodo_pago" class="form-select">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================================
// Variables globales
// ============================================================
const porcentajeBeca = <?= $porcentajeBeca ?>;
const recargoPorDia = <?= $recargoPorDia ?>;
const diaLimite = <?= $diaLimite ?>;

// ============================================================
// SELECCIONAR CONCEPTO
// ============================================================
function seleccionarConcepto(concepto) {
    const url = new URL(window.location.href);
    url.searchParams.set('concepto', concepto);
    window.location.href = url.toString();
}

// ============================================================
// CARGAR ALUMNO EN MODAL
// ============================================================
function cargarAlumno(alumno) {
    console.log('Datos del alumno:', alumno);

    if (!alumno.id_curso) {
        console.error('Error: id_curso no definido', alumno);
        return;
    }

    document.getElementById('pago_id_alumno').value = alumno.id_alumno;
    document.getElementById('pago_id_curso').value = alumno.id_curso;
    document.getElementById('pago_alumno_nombre').value = alumno.nombre + ' ' + alumno.apellido;
    document.getElementById('pago_curso').value = alumno.curso_tipo + ' - ' + alumno.curso_nombre;

    // Marcar becado
    document.getElementById('pago_id_alumno').setAttribute('data-becado', alumno.becado ? '1' : '0');

    const url = 'obtener_precios.php?id_curso=' + alumno.id_curso;
    console.log('Fetching precios desde:', url);

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Error HTTP: ' + response.status);
            return response.json();
        })
        .then(data => {
            console.log('Datos recibidos:', data);
            const selectConcepto = document.getElementById('pago_concepto');
            selectConcepto.innerHTML = '<option value="">Seleccionar concepto</option>';
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.concepto;
                option.dataset.precio = item.precio;
                let label = item.concepto.charAt(0).toUpperCase() + item.concepto.slice(1);
                if (item.concepto === 'cuota' && item.precio_con_beca) {
                    label += ' (Base Gs ' + item.precio + ' - Beca Gs ' + item.precio_con_beca + ')';
                } else {
                    label += ' (Gs ' + item.precio + ')';
                }
                option.textContent = label;
                selectConcepto.appendChild(option);
            });

            const params = new URLSearchParams(window.location.search);
            const conceptoSeleccionado = params.get('concepto') || '';
            if (conceptoSeleccionado) {
                for (let opt of selectConcepto.options) {
                    if (opt.value === conceptoSeleccionado) {
                        selectConcepto.value = conceptoSeleccionado;
                        break;
                    }
                }
                actualizarPrecio();
            }
        })
        .catch(error => {
            console.error('Error cargando precios:', error);
            alert('No se pudieron cargar los conceptos. Revisa la consola.');
        });

    // Resetear campos
    document.getElementById('pago_fecha').value = '';
    document.getElementById('pago_cantidad').value = 1;
    document.getElementById('pago_monto').value = '';
    document.getElementById('pago_recargo').value = 0;
    document.getElementById('pago_total').value = 0;
    document.getElementById('pago_recargo_info').innerHTML = '';
    document.getElementById('pago_beca_info').innerHTML = '';
    calcularTotal();
}

// ============================================================
// ACTUALIZAR PRECIO SUGERIDO (con redondeo al millar y beca)
// ============================================================
function actualizarPrecio() {
    console.log('actualizarPrecio ejecutado');
    const selectConcepto = document.getElementById('pago_concepto');
    const selectedOption = selectConcepto.options[selectConcepto.selectedIndex];
    if (!selectedOption || !selectedOption.dataset.precio) {
        document.getElementById('pago_monto').value = '';
        console.log('No se seleccionó concepto válido');
        return;
    }
    let precio = parseFloat(selectedOption.dataset.precio) || 0;
    const esBecado = document.getElementById('pago_id_alumno').getAttribute('data-becado') === '1';
    const esCuota = selectedOption.value.toLowerCase() === 'cuota';

    let montoFinal = precio;

    if (esCuota) {
        if (esBecado) {
            const precioConBeca = precio * (porcentajeBeca / 100);
            montoFinal = Math.round(precioConBeca / 1000) * 1000;
        } else {
            montoFinal = Math.round(precio / 1000) * 1000;
        }
        document.getElementById('pago_beca_info').innerHTML = esBecado
            ? '<i class="bi bi-info-circle-fill text-warning"></i> Beca aplicada (paga el ' + porcentajeBeca + '% de la cuota)'
            : '';
    } else {
        montoFinal = precio;
        document.getElementById('pago_beca_info').innerHTML = '';
    }

    document.getElementById('pago_monto').value = montoFinal;
    console.log('Monto asignado:', montoFinal);
    calcularTotal();
}

// ============================================================
// VER PAGOS (AJAX)
// ============================================================
function verPagos(idAlumno) {
    const modal = new bootstrap.Modal(document.getElementById('modalVerPagos'));
    modal.show();

    document.getElementById('contenidoVerPagos').innerHTML = `
        <div class="modal-body text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;

    fetch('obtener_pagos.php?id_alumno=' + idAlumno)
        .then(response => {
            if (!response.ok) throw new Error('Error al cargar los pagos');
            return response.text();
        })
        .then(html => {
            document.getElementById('contenidoVerPagos').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('contenidoVerPagos').innerHTML = `
                <div class="modal-body">
                    <div class="alert alert-danger">Error: ${error.message}</div>
                </div>
            `;
        });
}

// ============================================================
// CÁLCULOS (Recargo y Total)
// ============================================================
function calcularRecargo() {
    console.log('calcularRecargo ejecutado');
    const fecha = document.getElementById('pago_fecha').value;
    if (!fecha) {
        console.log('Fecha vacía');
        return;
    }
    const dia = new Date(fecha).getDate();
    let recargo = 0;
    let diasAtraso = 0;
    if (dia > diaLimite) {
        diasAtraso = dia - diaLimite;
        recargo = diasAtraso * recargoPorDia;
    }
    console.log('Recargo calculado:', recargo);
    document.getElementById('pago_recargo').value = recargo;
    if (recargo > 0) {
        document.getElementById('pago_recargo_info').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> Recargo por atraso: ' + recargo + ' Gs (' + diasAtraso + ' días)';
    } else {
        document.getElementById('pago_recargo_info').innerHTML = '';
    }
    calcularTotal();
}

function calcularTotal() {
    console.log('calcularTotal ejecutado');
    const monto = parseFloat(document.getElementById('pago_monto').value) || 0;
    const cantidad = parseInt(document.getElementById('pago_cantidad').value) || 1;
    const recargo = parseFloat(document.getElementById('pago_recargo').value) || 0;
    console.log('monto:', monto, 'cantidad:', cantidad, 'recargo:', recargo);
    const total = (monto * cantidad) + recargo;
    console.log('Total calculado:', total);
    document.getElementById('pago_total').value = total.toFixed(2);
}

// ============================================================
// FILTRO DE BÚSQUEDA Y LISTENER DEL MODAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscador');
    const tabla = document.getElementById('tablaAlumnos');

    if (buscador && tabla) {
        buscador.addEventListener('keyup', function() {
            const filtro = this.value.toLowerCase();
            const filas = tabla.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let fila of filas) {
                const nombre = fila.cells[0].textContent.toLowerCase();
                fila.style.display = nombre.includes(filtro) ? '' : 'none';
            }
        });
    }

    // Listener para cargar alumno al abrir el modal
    const modalPago = document.getElementById('modalPago');
    modalPago.addEventListener('show.bs.modal', function (event) {
        console.log('Modal abriéndose...');
        const button = event.relatedTarget;
        console.log('Botón:', button);
        const alumnoData = button.getAttribute('data-alumno');
        console.log('data-alumno:', alumnoData);
        if (alumnoData) {
            try {
                const alumno = JSON.parse(alumnoData);
                console.log('Alumno parseado:', alumno);
                cargarAlumno(alumno);
            } catch (e) {
                console.error('Error parsing alumno data:', e);
            }
        } else {
            console.warn('No se encontró data-alumno en el botón');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>