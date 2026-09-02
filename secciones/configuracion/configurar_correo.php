<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
include '../../includes/header.php';
$mostrarVolver = true;
include '../../includes/navbar.php';
require_once '../../config/db.php';
require_once __DIR__ . '/../../helpers/functions.php';
verificarPermiso('configuracion');

$claves = ['correo_firma', 'correo_pie', 'correo_pie2', 'correo_remitente'];
$config = [];
foreach ($claves as $c) {
    $config[$c] = $pdo->query("SELECT valor FROM configuracion WHERE clave = '" . $c . "'")->fetchColumn() ?: '';
}

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    foreach ($claves as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->execute([$campo, $valor]);
        $config[$campo] = $valor;
    }
    $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Configuración del correo de eventos guardada.</div>';
}
?>

<div class="container mt-3 pb-4">
    <?= $mensaje ?>

    <div class="page-header mb-3">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-envelope-fill me-2"></i> Configurar Correo de Eventos</h3>
            <small>Personaliza los textos que reciben los tutores en las notificaciones de eventos</small>
        </div>
    </div>

    <form method="POST" class="row g-4">
        <?= campoCSRF() ?>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-evo text-white py-2">
                    <i class="bi bi-chat-quote-fill"></i> Textos de la notificación
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Firma</label>
                        <input type="text" name="correo_firma" class="form-control"
                            placeholder="Ej: Equipo Instituto EvolucionArte"
                            value="<?= htmlspecialchars($config['correo_firma']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pie del correo (línea 1)</label>
                        <input type="text" name="correo_pie" class="form-control"
                            placeholder="Ej: Este correo fue enviado automáticamente por EvoSpace."
                            value="<?= htmlspecialchars($config['correo_pie']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pie del correo (línea 2)</label>
                        <input type="text" name="correo_pie2" class="form-control"
                            placeholder="Ej: Instituto EvolucionArte · Ingresá a tu panel de tutor/a para más detalles."
                            value="<?= htmlspecialchars($config['correo_pie2']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-evo text-white py-2">
                    <i class="bi bi-person-badge-fill"></i> Remitente
                </div>
                <div class="card-body">
                    <div class="mb-0">
                        <label class="form-label">Nombre que verán los tutores</label>
                        <input type="text" name="correo_remitente" class="form-control"
                            placeholder="Ej: Instituto EvolucionArte"
                            value="<?= htmlspecialchars($config['correo_remitente']) ?>">
                        <small class="text-muted">Nombre que se muestra al recibir el correo (el envío usa la cuenta evospace.system@gmail.com).</small>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3 bg-light">
                <div class="card-body small text-muted">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    La cabecera del correo, el mensaje de bienvenida y el botón del mapa se arman con los datos del evento
                    (título, fecha, hora, lugar, flyer y mensaje de bienvenida).
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-evo">
                    <i class="bi bi-save"></i> Guardar configuración
                </button>
            </div>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>