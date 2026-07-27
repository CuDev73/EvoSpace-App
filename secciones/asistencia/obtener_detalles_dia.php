<?php
session_start();

// Permitir acceso a profesores y administradores
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once '../../config/db.php';
verificarPermiso('asistencia');

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';

if ($id_curso == 0 || empty($fecha)) {
    echo '<div class="alert alert-danger">Datos inválidos.</div>';
    exit;
}

// Obtener asistencias del día
$stmt = $pdo->prepare("
    SELECT al.nombre, al.apellido, a.presente, a.observaciones
    FROM asistencia a
    INNER JOIN alumnos al ON a.id_alumno = al.id_alumno
    WHERE a.id_curso = ? AND a.fecha = ?
    ORDER BY al.apellido, al.nombre
");
$stmt->execute([$id_curso, $fecha]);
$asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($asistencias)) {
    echo '<div class="alert alert-info">No hay asistencias registradas para este día.</div>';
    exit;
}

$presentes = array_filter($asistencias, fn($r) => $r['presente'] == 1);
?>

<div class="table-responsive">
    <table class="table table-hover table-sm">
        <thead class="table-light">
            <tr>
                <th>Alumno</th>
                <th class="text-center">Estado</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($asistencias as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['apellido'] . ' ' . $row['nombre']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $row['presente'] ? 'success' : 'danger' ?>">
                            <?= $row['presente'] ? 'Presente' : 'Ausente' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['observaciones'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th>Total alumnos: <?= count($asistencias) ?></th>
                <th class="text-center">Presentes: <?= count($presentes) ?></th>
                <th>Ausentes: <?= count($asistencias) - count($presentes) ?></th>
            </tr>
        </tfoot>
    </table>
</div>