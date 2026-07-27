<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
require_once '../../../config/db.php';
require_once '../funciones.php';
verificarPermiso('cantina');

$mensaje = '';
$error = '';

// --- PROCESAR FORMULARIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_compra'])) {
    $id = $_POST['id'] ?? '';
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $id_alumno = (int) $_POST['id_alumno'];
    $producto = trim($_POST['producto']);
    $monto = (float) $_POST['monto'];
    $pagado = isset($_POST['pagado']) ? 1 : 0;

    if ($id_alumno <= 0 || empty($producto) || $monto <= 0) {
        $error = "Todos los campos son obligatorios y el monto debe ser mayor a 0.";
    } else {
        try {
            if ($id == '') {
                insertarCompraAlumno($pdo, $fecha, $id_alumno, $producto, $monto, $pagado);
                $mensaje = "Compra registrada correctamente.";
            } else {
                actualizarCompraAlumno($pdo, $id, $fecha, $id_alumno, $producto, $monto, $pagado);
                $mensaje = "Compra actualizada correctamente.";
            }
            header("Location: index.php?rama=" . ($_GET['rama'] ?? '') . "&exito=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

if (isset($_GET['eliminar'])) {
    try {
        eliminarCompraAlumno($pdo, (int) $_GET['eliminar']);
        $mensaje = "Compra eliminada correctamente.";
        header("Location: index.php?rama=" . ($_GET['rama'] ?? '') . "&exito=1");
        exit;
    } catch (PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

if (isset($_POST['accion_pago'])) {
    $id_alumno = (int) $_POST['id_alumno_pago'];
    $monto = (float) $_POST['monto_pago'];
    $fecha = $_POST['fecha_pago'] ?? date('Y-m-d');
    if ($id_alumno > 0 && $monto > 0) {
        try {
            insertarPagoAlumnoCantina($pdo, $fecha, $id_alumno, $monto);
            $mensaje = "Pago registrado correctamente.";
            header("Location: index.php?rama=" . ($_GET['rama'] ?? '') . "&exito=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error al registrar pago: " . $e->getMessage();
        }
    } else {
        $error = "Datos de pago inválidos.";
    }
}

include '../../../includes/header.php';
include '../../../includes/navbar.php';

$ramaFiltro = isset($_GET['rama']) ? $_GET['rama'] : null;
$compras = obtenerComprasAlumnos($pdo, $ramaFiltro);
$deudaTotal = obtenerDeudaTotalCantina($pdo);

$alumnos = $pdo->query("
    SELECT a.id_alumno, a.nombre, a.apellido, c.tipo AS rama, c.nombre AS curso
    FROM alumnos a
    JOIN cursos c ON a.id_curso = c.id_curso
    WHERE a.activo = 1
    ORDER BY a.apellido, a.nombre
")->fetchAll(PDO::FETCH_OBJ);

$productos = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_OBJ);

$editCompra = isset($_GET['editar']) ? obtenerCompraAlumno($pdo, (int) $_GET['editar']) : null;

if (isset($_GET['exito'])) {
    $mensaje = $mensaje ?: "Operación realizada correctamente.";
}
?>

<div class="container mt-3">
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-8">
            <h4><i class="bi bi-cart"></i> Compras de Alumnos (Fiado)</h4>
        </div>
        <!-- Enlaces eliminados según solicitud -->
    </div>

    <!-- Filtro por rama -->
    <div class="card shadow mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0">Filtrar por rama:</label>
                </div>
                <div class="col-md-3">
                    <select name="rama" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        <option value="Superior" <?= $ramaFiltro === 'Superior' ? 'selected' : '' ?>>Superior</option>
                        <option value="Infantil" <?= $ramaFiltro === 'Infantil' ? 'selected' : '' ?>>Infantil</option>
                        <option value="Acrotelas" <?= $ramaFiltro === 'Acrotelas' ? 'selected' : '' ?>>Acrotelas</option>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="index.php" class="btn btn-secondary btn-sm">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Deuda total -->
    <div class="alert <?= $deudaTotal > 0 ? 'alert-danger' : 'alert-success' ?>">
        <strong>Deuda total de alumnos en cantina:</strong> <?= number_format($deudaTotal, 0, ',', '.') ?> Gs
    </div>

    <!-- Formulario de compra -->
    <div class="card shadow mb-4">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-plus-circle"></i> <?= $editCompra ? 'Editar compra' : 'Nueva compra' ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion_compra" value="1">
                <input type="hidden" name="id" value="<?= $editCompra ? $editCompra->id_compra : '' ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control" value="<?= $editCompra ? $editCompra->fecha : date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Alumno *</label>
                        <select name="id_alumno" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($alumnos as $a): ?>
                                <option value="<?= $a->id_alumno ?>" <?= $editCompra && $editCompra->id_alumno == $a->id_alumno ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a->apellido . ', ' . $a->nombre . ' (' . $a->rama . ' - ' . $a->curso . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Producto *</label>
                        <select name="producto" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= htmlspecialchars($p->nombre) ?>" <?= $editCompra && $editCompra->producto == $p->nombre ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p->nombre) ?> (Gs <?= number_format($p->precio, 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Monto (Gs) *</label>
                        <input type="number" step="0.01" name="monto" class="form-control" value="<?= $editCompra ? $editCompra->monto : '' ?>" required>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="pagado" value="1" <?= $editCompra && $editCompra->pagado ? 'checked' : '' ?>>
                            <label class="form-check-label">Pagado</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-danger"><?= $editCompra ? 'Actualizar' : 'Guardar' ?></button>
                        <?php if ($editCompra): ?>
                            <a href="index.php<?= $ramaFiltro ? '?rama=' . urlencode($ramaFiltro) : '' ?>" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de compras -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white">
            <i class="bi bi-list"></i> Registro de compras
            <span class="badge bg-light text-dark ms-2"><?= count($compras) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Alumno</th>
                            <th>Rama</th>
                            <th>Producto</th>
                            <th class="text-end">Monto</th>
                            <th>Pagado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($compras)): ?>
                            <tr><td colspan="8" class="text-center">No hay compras registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($compras as $c): ?>
                                <tr>
                                    <td><?= $c->id_compra ?></td>
                                    <td><?= date('d/m/Y', strtotime($c->fecha)) ?></td>
                                    <td><?= htmlspecialchars($c->apellido . ', ' . $c->nombre) ?></td>
                                    <td><?= htmlspecialchars($c->rama) ?></td>
                                    <td><?= htmlspecialchars($c->producto) ?></td>
                                    <td class="text-end"><?= number_format($c->monto, 0, ',', '.') ?></td>
                                    <td><?= $c->pagado ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                                    <td>
                                        <a href="index.php?editar=<?= $c->id_compra ?><?= $ramaFiltro ? '&rama=' . urlencode($ramaFiltro) : '' ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i></a>
                                        <a href="index.php?eliminar=<?= $c->id_compra ?><?= $ramaFiltro ? '&rama=' . urlencode($ramaFiltro) : '' ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar esta compra?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sección de pagos y deuda -->
    <div class="card shadow mt-4">
        <div class="card-header bg-info text-white">
            <i class="bi bi-cash"></i> Pagos y deudas por alumno
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3 mb-3">
                <input type="hidden" name="accion_pago" value="1">
                <div class="col-md-3">
                    <label class="form-label">Alumno</label>
                    <select name="id_alumno_pago" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($alumnos as $a): ?>
                            <option value="<?= $a->id_alumno ?>"><?= htmlspecialchars($a->apellido . ', ' . $a->nombre . ' (' . $a->rama . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha_pago" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monto (Gs)</label>
                    <input type="number" step="0.01" name="monto_pago" class="form-control" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Registrar pago</button>
                </div>
            </form>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h6>Deuda por alumno</h6>
                    <ul class="list-group">
                        <?php
                        $alumnosDeuda = $pdo->query("
                            SELECT a.id_alumno, a.nombre, a.apellido, c.tipo AS rama,
                                   COALESCE((SELECT SUM(monto) FROM compras_alumnos WHERE id_alumno = a.id_alumno AND pagado = 0), 0) AS deuda
                            FROM alumnos a
                            JOIN cursos c ON a.id_curso = c.id_curso
                            WHERE a.activo = 1
                            HAVING deuda > 0
                            ORDER BY deuda DESC
                        ")->fetchAll(PDO::FETCH_OBJ);
                        ?>
                        <?php if (empty($alumnosDeuda)): ?>
                            <li class="list-group-item">Sin deudas registradas.</li>
                        <?php else: ?>
                            <?php foreach ($alumnosDeuda as $ad): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($ad->apellido . ', ' . $ad->nombre . ' (' . $ad->rama . ')') ?>
                                    <span class="badge bg-danger"><?= number_format($ad->deuda, 0, ',', '.') ?> Gs</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Historial de pagos por alumno</h6>
                    <form method="GET" class="mb-2">
                        <div class="input-group">
                            <select name="ver_pagos" class="form-select">
                                <option value="">Seleccionar alumno...</option>
                                <?php foreach ($alumnos as $a): ?>
                                    <option value="<?= $a->id_alumno ?>" <?= (isset($_GET['ver_pagos']) && $_GET['ver_pagos'] == $a->id_alumno) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a->apellido . ', ' . $a->nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-secondary">Ver</button>
                        </div>
                    </form>
                    <?php if (isset($_GET['ver_pagos']) && $_GET['ver_pagos'] > 0): ?>
                        <?php
                        $idAlumnoPagos = (int) $_GET['ver_pagos'];
                        $pagos = obtenerPagosAlumnoCantina($pdo, $idAlumnoPagos);
                        ?>
                        <ul class="list-group">
                            <?php if (empty($pagos)): ?>
                                <li class="list-group-item">Sin pagos registrados para este alumno.</li>
                            <?php else: ?>
                                <?php foreach ($pagos as $p): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= date('d/m/Y', strtotime($p->fecha)) ?>
                                        <span class="badge bg-success"><?= number_format($p->monto, 0, ',', '.') ?> Gs</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<div class="container mt-3">
    <!-- Botones de Volver -->
    <div class="row g-2 mb-6">
        <div class="col-md-6">
            <button onclick="history.back()" class="btn btn-secondary w-100">
                <i class="bi bi-arrow-left"></i> Volver atrás
            </button>
        </div>
        <div class="col-md-6">
            <a href="../index.php" class="btn btn-secondary w-100">
                <i class="bi bi-house"></i> Volver al panel de Cantina
            </a>
        </div>
    </div>

</div>

<?php include '../../../includes/footer.php'; ?>
