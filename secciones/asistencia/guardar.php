<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once '../../config/db.php';
verificarPermiso('asistencia');

$id_curso = (int)$_POST['id_curso'];
$fecha = $_POST['fecha'];
$presentes = $_POST['presente'] ?? [];
$observaciones = $_POST['observacion'] ?? [];

if ($id_curso == 0 || empty($fecha)) {
    header('Location: /evospace/roles/profesor.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Obtener todos los alumnos del curso (para asegurar que se registren todos)
    $stmtAlumnos = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE id_curso = ? AND activo = 1");
    $stmtAlumnos->execute([$id_curso]);
    $alumnosCurso = $stmtAlumnos->fetchAll(PDO::FETCH_COLUMN);

    // Eliminar asistencias existentes para esta fecha y curso
    $stmt = $pdo->prepare("DELETE FROM asistencia WHERE id_curso = ? AND fecha = ?");
    $stmt->execute([$id_curso, $fecha]);

    // Insertar un registro para cada alumno del curso
    $stmtInsert = $pdo->prepare("INSERT INTO asistencia (id_alumno, id_curso, fecha, presente, observaciones) VALUES (?, ?, ?, ?, ?)");
    foreach ($alumnosCurso as $id_alumno) {
        $presente = isset($presentes[$id_alumno]) ? 1 : 0;
        $observacion = trim($observaciones[$id_alumno] ?? '');
        $stmtInsert->execute([$id_alumno, $id_curso, $fecha, $presente, $observacion]);
    }

    $pdo->commit();
    header('Location: registrar.php?id_curso=' . $id_curso . '&fecha=' . $fecha . '&guardado=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: registrar.php?id_curso=' . $id_curso . '&fecha=' . $fecha . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>