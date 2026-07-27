<?php
ob_start(); // Iniciar buffer para evitar errores de headers
session_start();

// Permitir acceso a profesores y administradores
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once '../../config/db.php';
verificarPermiso('asistencia');

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
if ($id_curso == 0) {
    header('Location: /evospace/roles/profesor.php');
    exit;
}

// Obtener datos del curso
$stmt = $pdo->prepare("SELECT nombre, tipo FROM cursos WHERE id_curso = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: /evospace/roles/profesor.php');
    exit;
}

// Obtener todas las asistencias del curso
$stmt = $pdo->prepare("
    SELECT a.fecha, 
           a.presente,
           al.nombre, al.apellido
    FROM asistencia a
    INNER JOIN alumnos al ON a.id_alumno = al.id_alumno
    WHERE a.id_curso = ?
    ORDER BY a.fecha DESC, al.apellido, al.nombre
");
$stmt->execute([$id_curso]);
$asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por fecha
$fechas = [];
foreach ($asistencias as $row) {
    $fechas[$row['fecha']][] = $row;
}

// Incluir header y navbar (después de todas las redirecciones)
include '../../includes/header.php';
include '../../includes/navbar.php';
?>

<div class="container mt-3">
    <div class="bg-danger text-white p-4 rounded mb-4">
        <h3 class="h3 fw-bold"><i class="bi bi-eye"></i> Historial de Asistencia</h3>
        <p class="mb-0"><?= htmlspecialchars($curso['tipo'] . ' - ' . $curso['nombre']) ?></p>
    </div>

    <div class="d-flex gap-2 mb-3">
        <a href="/evospace/roles/profesor.php?id_curso=<?= $id_curso ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <a href="registrar.php?id_curso=<?= $id_curso ?>" class="btn btn-danger">
            <i class="bi bi-clipboard-check"></i> Registrar hoy
        </a>
        <a href="exportar_excel.php?id_curso=<?= $id_curso ?>" class="btn btn-success ms-auto">
            <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
        </a>
    </div>

    <?php if (empty($fechas)): ?>
        <div class="alert alert-info">No hay asistencias registradas para este curso.</div>
    <?php else: ?>
        <?php foreach ($fechas as $fecha => $registros): ?>
            <div class="card shadow mb-3">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($fecha)) ?>
                    <span class="badge bg-light text-dark ms-2">
                        <?php 
                        $presentes = array_filter($registros, fn($r) => $r['presente'] == 1);
                        echo count($presentes) . ' / ' . count($registros) . ' presentes';
                        ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Alumno</th>
                                    <th class="text-center">Estado</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $row): ?>
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
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>