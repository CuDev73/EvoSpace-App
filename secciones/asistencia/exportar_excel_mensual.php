<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}


require_once '../../config/db.php';
verificarPermiso('asistencia');

require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate; // <-- NUEVO

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

if ($id_curso == 0) {
    header('Location: mensual.php');
    exit;
}

// Obtener curso
$stmt = $pdo->prepare("SELECT nombre, tipo FROM cursos WHERE id_curso = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: mensual.php');
    exit;
}

// Obtener alumnos
$stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE id_curso = ? AND activo = 1 ORDER BY apellido, nombre");
$stmt->execute([$id_curso]);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($alumnos)) {
    header('Location: mensual.php?error=No+hay+alumnos');
    exit;
}

// Obtener días con registros
$stmt = $pdo->prepare("SELECT DISTINCT DAY(fecha) as dia FROM asistencia WHERE id_curso = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ? ORDER BY dia");
$stmt->execute([$id_curso, $mes, $anio]);
$dias = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($dias)) {
    $dias = range(1, cal_days_in_month(CAL_GREGORIAN, $mes, $anio));
}

// Obtener asistencias
$ids = array_column($alumnos, 'id_alumno');
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT id_alumno, DAY(fecha) as dia, presente FROM asistencia WHERE id_curso = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ? AND id_alumno IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$id_curso, $mes, $anio], $ids));
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$asistencias = [];
foreach ($resultados as $row) {
    $asistencias[$row['id_alumno']][$row['dia']] = $row['presente'];
}

// Crear Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Asistencia');

// Encabezados
$col = 1;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Alumno');
foreach ($dias as $dia) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', $dia);
}
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Total Presentes');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Total Ausencias');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Porcentaje');

// Estilos a encabezados
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');

// Datos
$row = 2;
foreach ($alumnos as $alumno) {
    $col = 1;
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $alumno['apellido'] . ' ' . $alumno['nombre']);
    $presentes = 0;
    foreach ($dias as $dia) {
        $presente = $asistencias[$alumno['id_alumno']][$dia] ?? null;
        if ($presente === null) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, 'N/A');
        } else {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $presente ? 'P' : 'A');
            if ($presente) $presentes++;
        }
    }
    $totalDias = count($dias);
    $ausencias = $totalDias - $presentes;
    $porcentaje = $totalDias > 0 ? round(($presentes / $totalDias) * 100, 1) : 0;
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $presentes);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $ausencias);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $porcentaje . '%');
    $row++;
}

// Autoajustar columnas
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Enviar archivo
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="asistencia_mensual_' . $curso['tipo'] . '_' . $curso['nombre'] . '_' . $mes . '-' . $anio . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>