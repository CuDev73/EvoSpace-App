<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

$id_abono = isset($_GET['id_abono']) ? (int)$_GET['id_abono'] : 0;
if (!$id_abono) {
    http_response_code(400);
    exit('ID de pago invalido');
}

$stmt = $pdo->prepare("SELECT a.*, u.nombre_completo, u.usuario FROM abonos a JOIN usuarios u ON a.profesor = u.usuario WHERE a.id_abono = ?");
$stmt->execute([$id_abono]);
$abono = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$abono) {
    http_response_code(404);
    exit('Pago no encontrado');
}

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['admin', 'auxiliar'], true)) {
    if ($rol !== 'profesor' || $abono['profesor'] !== ($_SESSION['usuario'] ?? '')) {
        denegarAcceso();
    }
}

$config = [];
$stmtConfig = $pdo->query("SELECT clave, valor FROM configuracion");
while ($row = $stmtConfig->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['clave']] = $row['valor'];
}

$nombreInstitucion = $config['recibo_nombre'] ?? 'EvoSpace';
$titulo = $config['recibo_titulo'] ?? 'Academia de Artes Escenicas';
$ruc = $config['recibo_ruc'] ?? '12345678-0';
$mensaje = $config['recibo_mensaje'] ?? 'Gracias por confiar en EvoSpace';
$pie = $config['recibo_pie'] ?? 'Este documento es un comprobante de pago valido';
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
if (!empty($abono['imagen'])) {
    $compFullPath = __DIR__ . '/../' . $abono['imagen'];
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
    td { padding: 2px 4px; }
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
    <b>Recibo N:</b> ' . str_pad($abono['id_abono'], 6, '0', STR_PAD_LEFT) . '<br>
    <b>Fecha:</b> ' . date('d/m/Y', strtotime($abono['fecha_abono'])) . '<br>
    <b>Profesor:</b> ' . htmlspecialchars($abono['nombre_completo'] ?? $abono['usuario']) . '<br>
    <b>Usuario:</b> ' . htmlspecialchars($abono['usuario']) . '
</p>
<hr>
<table>
    <tr><td class="label">Concepto</td><td class="v">Pago a profesor</td></tr>
    <tr><td class="label">Monto</td><td class="v">Gs ' . number_format($abono['monto_abono'], 0, ',', '.') . '</td></tr>';

if ($abono['descripcion']) {
    $html .= '<tr><td class="label">Descripcion</td><td class="v">' . htmlspecialchars($abono['descripcion']) . '</td></tr>';
}

$html .= '
</table>
<hr>
<p style="text-align:right;margin:2px 0;">
    <span class="total">Total: Gs ' . number_format($abono['monto_abono'], 0, ',', '.') . '</span>
</p>
' . $comprobanteHtml . '
<p class="footer">' . htmlspecialchars($mensaje) . '<br>' . htmlspecialchars($pie) . '</p>';

$mpdf->WriteHTML($html);
$mpdf->Output('recibo_profesor_' . str_pad($abono['id_abono'], 6, '0', STR_PAD_LEFT) . '.pdf', 'I');
