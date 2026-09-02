<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

$id_pago = isset($_GET['id_pago']) ? (int)$_GET['id_pago'] : 0;
if (!$id_pago) {
    http_response_code(400);
    exit('ID de pago inválido');
}

$sql = "SELECT p.*, a.nombre, a.apellido, a.ci, a.becado, a.id_alumno,
               c.nombre AS curso_nombre, c.tipo AS curso_tipo
        FROM pagos p
        INNER JOIN alumnos a ON p.id_alumno = a.id_alumno
        INNER JOIN cursos c ON a.id_curso = c.id_curso
        WHERE p.id_pago = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_pago]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    http_response_code(404);
    exit('Pago no encontrado');
}

if (!verificarAccesoAlumno($pdo, (int)$pago['id_alumno'])) {
    denegarAcceso();
}

$alumno = $pago['nombre'] . ' ' . $pago['apellido'];
$curso = $pago['curso_tipo'] . ' - ' . $pago['curso_nombre'];

$config = [];
$stmtConfig = $pdo->query("SELECT clave, valor FROM configuracion");
while ($row = $stmtConfig->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['clave']] = $row['valor'];
}

$nombreInstitucion = $config['recibo_nombre'] ?? 'EvoSpace';
$titulo = $config['recibo_titulo'] ?? 'Academia de Artes Escénicas';
$ruc = $config['recibo_ruc'] ?? '12345678-0';
$mensaje = $config['recibo_mensaje'] ?? 'Gracias por confiar en EvoSpace';
$pie = $config['recibo_pie'] ?? 'Este documento es un comprobante de pago válido';
$logoPath = $config['recibo_logo'] ?? '';

$logoHtml = '';
if ($logoPath) {
    $logoFullPath = __DIR__ . '/../' . $logoPath;
    if (file_exists($logoFullPath)) {
        $logoData = base64_encode(file_get_contents($logoFullPath));
        $logoExt = strtolower(pathinfo($logoFullPath, PATHINFO_EXTENSION));
        $mimeType = ($logoExt === 'png') ? 'image/png' : (($logoExt === 'gif') ? 'image/gif' : (($logoExt === 'webp') ? 'image/webp' : 'image/jpeg'));
        $logoHtml = '<img src="data:' . $mimeType . ';base64,' . $logoData . '" style="max-height:30px;margin-bottom:2px;" /><br>';
    }
}

$comprobanteHtml = '';
if (!empty($pago['imagen'])) {
    $compFullPath = __DIR__ . '/../' . $pago['imagen'];
    if (file_exists($compFullPath)) {
        $compData = base64_encode(file_get_contents($compFullPath));
        $compExt = strtolower(pathinfo($compFullPath, PATHINFO_EXTENSION));
        $compMime = ($compExt === 'png') ? 'image/png' : (($compExt === 'gif') ? 'image/gif' : (($compExt === 'webp') ? 'image/webp' : 'image/jpeg'));
        $comprobanteHtml = '<div style="text-align:center;margin:8px 0 2px;"><p style="font-size:7pt;color:#555;margin:0 0 3px;">Comprobante</p><img src="data:' . $compMime . ';base64,' . $compData . '" style="max-width:100%;height:auto;" /></div>';
    }
}

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => [80, 150],
    'margin_left' => 5,
    'margin_right' => 5,
    'margin_top' => 5,
    'margin_bottom' => 5,
]);

$html = '
<style>
    body { font-family: "Helvetica", sans-serif; font-size: 8pt; text-align: center; }
    h2 { font-size: 11pt; margin: 0 0 2px; }
    .subt { font-size: 7pt; color: #666; margin: 0 0 4px; }
    hr { border: none; border-top: 1px dashed #999; margin: 4px 0; }
    table { width: 100%; font-size: 7pt; border-collapse: collapse; }
    td { padding: 2px 4px; text-align: left; }
    td.v { text-align: right; }
    .total { font-size: 10pt; font-weight: bold; }
    .footer { font-size: 6pt; color: #999; margin-top: 6px; }
    .label { color: #555; width: 50%; }
</style>

' . $logoHtml . '
<h2>' . htmlspecialchars($nombreInstitucion) . '</h2>
<p class="subt">' . htmlspecialchars($titulo) . '<br>RUC: ' . htmlspecialchars($ruc) . '</p>
<hr>
<p style="font-size:7pt;text-align:left;margin:2px 0;">
    <b>Recibo N°:</b> ' . str_pad($pago['id_pago'], 6, '0', STR_PAD_LEFT) . '<br>
    <b>Fecha:</b> ' . date('d/m/Y', strtotime($pago['fecha'])) . '<br>
    <b>Alumno:</b> ' . htmlspecialchars($alumno) . '<br>
    <b>CI:</b> ' . htmlspecialchars($pago['ci']) . '<br>
    <b>Curso:</b> ' . htmlspecialchars($curso) . '
</p>
<hr>
<table>
    <tr><td class="label">Concepto</td><td class="v">' . htmlspecialchars(ucfirst($pago['concepto'])) . '</td></tr>
    <tr><td class="label">Cantidad</td><td class="v">' . $pago['cantidad'] . '</td></tr>
    <tr><td class="label">Monto unitario</td><td class="v">Gs ' . number_format($pago['monto'], 0, ',', '.') . '</td></tr>';

if ($pago['descuento'] > 0) {
    $html .= '<tr><td class="label">Descuento</td><td class="v">' . number_format($pago['descuento'], 2) . '%</td></tr>';
}
if ($pago['recargo'] > 0) {
    $html .= '<tr><td class="label">Recargo</td><td class="v">Gs ' . number_format($pago['recargo'], 0, ',', '.') . '</td></tr>';
}

$html .= '
</table>
<hr>
<p style="text-align:right;margin:2px 0;">
    <span class="total">Total: Gs ' . number_format($pago['total'], 0, ',', '.') . '</span>
</p>
<p style="font-size:7pt;text-align:left;margin:2px 0;">
    <b>Método de pago:</b> ' . $pago['metodo_pago'] . '
' . $comprobanteHtml . '
<p class="footer">' . htmlspecialchars($mensaje) . '<br>' . htmlspecialchars($pie) . '</p>
</div>';

$mpdf->WriteHTML($html);
$mpdf->Output('recibo_' . str_pad($pago['id_pago'], 6, '0', STR_PAD_LEFT) . '.pdf', 'I');
