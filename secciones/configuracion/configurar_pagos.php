<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}


include '../../includes/header.php';
include '../../includes/navbar.php';
require_once '../../config/db.php';

verificarPermiso('configuracion');

// Función para obtener porcentaje de beca (si no existe en functions.php)
if (!function_exists('obtenerPorcentajeBeca')) {
    function obtenerPorcentajeBeca($pdo) {
        $stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_beca'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['valor'] : 45.45;
    }
}

$mensaje = '';
$porcentaje_beca = obtenerPorcentajeBeca($pdo);

// ==========================================================
// 1. Asegurar conceptos según el tipo de curso
// ==========================================================
$conceptos_por_tipo = [
    'Acrotelas' => ['matrícula', 'cuota', 'vestuarios', 'entradas'],
    'Infantil'  => ['matrícula', 'cuota', 'vestuarios', 'entradas', 'folleto'],
    'Superior'  => ['matrícula', 'cuota', 'vestuarios', 'entradas', 'folleto']
];

$sqlCursos = "SELECT id_curso, tipo FROM cursos WHERE activo = 1";
$stmtCursos = $pdo->query($sqlCursos);
$todosCursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

foreach ($todosCursos as $curso) {
    $conceptos_requeridos = $conceptos_por_tipo[$curso['tipo']] ?? [];
    $sqlCheck = "SELECT concepto FROM precios WHERE id_curso = ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$curso['id_curso']]);
    $existentes = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

    $faltantes = array_diff($conceptos_requeridos, $existentes);
    foreach ($faltantes as $concepto) {
        $sqlInsert = "INSERT INTO precios (id_curso, concepto, precio, descuento_beca, aplica_beca) 
                      VALUES (?, ?, 0, 0, 0)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([$curso['id_curso'], $concepto]);
    }
}

// ==========================================================
// 2. Procesar actualización de precios y porcentaje de beca
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar_precios') {
        $precios = $_POST['precio'] ?? [];
        $errores = 0;
        foreach ($precios as $id_precio => $precio) {
            $precio = (float)$precio;
            $sql = "UPDATE precios SET precio = ? WHERE id_precio = ?";
            $stmt = $pdo->prepare($sql);
            if (!$stmt->execute([$precio, $id_precio])) {
                $errores++;
            }
        }
        if ($errores === 0) {
            $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Precios actualizados correctamente.</div>';
        } else {
            $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> Error al actualizar algunos precios.</div>';
        }
    }

    if ($accion === 'actualizar_beca') {
        $nuevo_porcentaje = (float)$_POST['porcentaje_beca'];
        if ($nuevo_porcentaje >= 0 && $nuevo_porcentaje <= 100) {
            $sql = "UPDATE configuracion SET valor = ? WHERE clave = 'porcentaje_beca'";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nuevo_porcentaje])) {
                $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Descuento actualizado correctamente.</div>';
                $porcentaje_beca = $nuevo_porcentaje;
            } else {
                $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> Error al actualizar el descuento.</div>';
            }
        } else {
            $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> El porcentaje debe estar entre 0 y 100.</div>';
        }
    }
}

// ==========================================================
// 3. Obtener datos para mostrar
// ==========================================================
$sql = "SELECT c.id_curso, c.nombre AS curso_nombre, c.tipo AS curso_tipo, 
               p.id_precio, p.concepto, p.precio
        FROM cursos c
        LEFT JOIN precios p ON c.id_curso = p.id_curso
        WHERE c.activo = 1
        ORDER BY c.tipo, c.orden, FIELD(p.concepto, 'matrícula', 'cuota', 'vestuarios', 'entradas', 'folleto')";
$stmt = $pdo->query($sql);
$datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cursos = [];
foreach ($datos as $row) {
    $cursos[$row['curso_tipo']][$row['id_curso']]['nombre'] = $row['curso_nombre'];
    if ($row['concepto']) {
        $cursos[$row['curso_tipo']][$row['id_curso']]['precios'][$row['concepto']] = [
            'id_precio' => $row['id_precio'],
            'precio' => $row['precio']
        ];
    }
}

$conceptos_orden = ['matrícula', 'cuota', 'vestuarios', 'entradas', 'folleto'];
$iconos = [
    'matrícula' => 'bi-file-earmark-text',
    'cuota' => 'bi-calendar-check',
    'vestuarios' => 'bi-person',
    'entradas' => 'bi-ticket',
    'folleto' => 'bi-book'
];
?>

<div class="container mt-3 pb-4">
    <?= $mensaje ?>

    <!-- SECCIÓN: Porcentaje de descuento global -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-evo text-white py-2">
            <i class="bi bi-percent"></i> Descuento global para cuota
        </div>
        <div class="card-body py-2">
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="accion" value="actualizar_beca">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Porcentaje a pagar (%)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" name="porcentaje_beca"
                            class="form-control" value="<?= $porcentaje_beca ?>"
                            min="0" max="100" required>
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="bi bi-save"></i> Actualizar
                        </button>
                    </div>
                </div>
                <div class="col-md-8">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Actual: <strong><?= $porcentaje_beca ?>%</strong> del precio base.
                        Ej: cuota Gs 250.000 → con descuento Gs <?= number_format(round(250000 * $porcentaje_beca / 100 / 1000) * 1000, 0, ',', '.') ?>
                    </small>
                </div>
            </form>
        </div>
    </div>

    <!-- SECCIÓN: Precios por curso (agrupados por tipo con divisores) -->
    <form method="POST" id="formPrecios">
        <input type="hidden" name="accion" value="actualizar_precios">

        <?php 
        $tipos = array_keys($cursos);
        $total_tipos = count($tipos);
        $contador_tipo = 0;
        foreach ($cursos as $tipo => $cursosTipo): 
            $contador_tipo++;
        ?>
            <!-- Separador entre tipos (excepto antes del primero) -->
            <?php if ($contador_tipo > 1): ?>
                <hr class="my-4 border-2 border-danger">
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header bg-evo text-white py-2">
                    <i class="bi bi-tag"></i> <?= $tipo ?>
                    <span class="badge bg-light text-dark ms-2"><?= count($cursosTipo) ?> cursos</span>
                </div>
                <div class="card-body py-2">
                    <?php foreach ($cursosTipo as $id_curso => $curso): ?>
                        <div class="row mb-1 py-1 align-items-center">
                            <div class="col-md-2 fw-semibold small"><?= htmlspecialchars($curso['nombre']) ?></div>
                            <div class="col-md-10">
                                <div class="row g-1">
                                    <?php foreach ($conceptos_orden as $concepto):
                                        $precioData = $curso['precios'][$concepto] ?? null;
                                        if (!$precioData) continue;
                                        $precio = $precioData['precio'];
                                        $precioConBeca = ($concepto === 'cuota') ? $precio * ($porcentaje_beca / 100) : $precio;
                                        $precioConBecaRedondeado = round($precioConBeca / 1000) * 1000;
                                    ?>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-0">
                                                <i class="bi <?= $iconos[$concepto] ?> me-1"></i><?= ucfirst($concepto) ?>
                                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Gs</span>
                                <input type="number"
                                    name="precio[<?= $precioData['id_precio'] ?>]"
                                    class="form-control form-control-sm"
                                    value="<?= (int)$precio ?>" data-moneda>
                                <?php if ($concepto === 'cuota'): ?>
                                    <span class="input-group-text bg-warning-subtle text-dark small" title="Con descuento (<?= $porcentaje_beca ?>%)">
                                        <small>Dto: Gs <?= number_format($precioConBecaRedondeado, 0, ',', '.') ?></small>
                                    </span>
                                <?php endif; ?>
                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2 mt-4 pb-3">
            <a href="configuracion.php" class="btn btn-secondary flex-fill">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <button type="button" class="btn btn-danger flex-fill" data-bs-toggle="modal" data-bs-target="#modalConfirmar">
                <i class="bi bi-save"></i> Guardar todos los precios
            </button>
        </div>
    </form>
</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Confirmar cambios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas <strong>guardar todos los cambios</strong> en los precios?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarGuardar">
                    <i class="bi bi-check-circle"></i> Sí, guardar cambios
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnConfirmarGuardar').addEventListener('click', function() {
    document.getElementById('formPrecios').submit();
});
</script>

<?php include '../../includes/footer.php'; ?>