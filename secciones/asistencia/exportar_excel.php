<?php
session_start();

// Permitir acceso a profesores y administradores
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once '../../config/db.php';
verificarPermiso('asistencia');

require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
if ($id_curso == 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos del curso
$stmt = $pdo->prepare("SELECT nombre, tipo FROM cursos WHERE id_curso = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: index.php');
    exit;
}

// Obtener alumnos del curso
$stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE id_curso = ? AND activo = 1 ORDER BY apellido, nombre");
$stmt->execute([$id_curso]);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($alumnos)) {
    header('Location: registrar.php?id_curso=' . $id_curso . '&error=No+hay+alumnos+en+el+curso');
    exit;
}

// Obtener todas las fechas de asistencia
$stmt = $pdo->prepare("SELECT DISTINCT fecha FROM asistencia WHERE id_curso = ? ORDER BY fecha");
$stmt->execute([$id_curso]);
$fechas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener asistencias
$asistencias = [];
foreach ($alumnos as $alumno) {
    $stmt = $pdo->prepare("SELECT fecha, presente FROM asistencia WHERE id_alumno = ? AND id_curso = ?");
    $stmt->execute([$alumno['id_alumno'], $id_curso]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    foreach ($rows as $fecha => $data) {
        $asistencias[$alumno['id_alumno']][$fecha] = $data[0]['presente'];
    }
}

// Crear Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Asistencia');

// ============================================
// ENCABEZADOS
// ============================================
$col = 1;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Alumno');

foreach ($fechas as $fecha) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', date('d/m/Y', strtotime($fecha)));
}

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Total Presentes');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Total Ausencias');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . '1', 'Porcentaje');

// Estilo de encabezados
$headerRange = 'A1:' . $sheet->getHighestColumn() . '1';
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFCCCCCC');
$sheet->getStyle($headerRange)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// ============================================
// DATOS
// ============================================
$row = 2;
foreach ($alumnos as $alumno) {
    $col = 1;
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $alumno['apellido'] . ' ' . $alumno['nombre']);

    $presentes = 0;
    $total = count($fechas);
    foreach ($fechas as $fecha) {
        $presente = $asistencias[$alumno['id_alumno']][$fecha] ?? null;
        if ($presente === null) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, 'N/A');
        } else {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $presente ? 'P' : 'A');
            if ($presente) $presentes++;
        }
    }

    $ausencias = $total - $presentes;
    $porcentaje = $total > 0 ? round(($presentes / $total) * 100, 1) : 0;

    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $presentes);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $ausencias);
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row, $porcentaje . '%');

    $row++;
}

// ============================================
// AUTO AJUSTAR COLUMNAS
// ============================================
foreach (range('A', $sheet->getHighestColumn()) as $colLetter) {
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// ============================================
// DESCARGAR
// ============================================
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="asistencia_' . $curso['tipo'] . '_' . $curso['nombre'] . '_' . date('Y-m-d') . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>