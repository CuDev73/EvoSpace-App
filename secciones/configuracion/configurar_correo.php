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

$claves = ['correo_saludo', 'correo_mensaje', 'correo_firma', 'correo_remitente'];
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
                        <label class="form-label">Saludo</label>
                        <input type="text" name="correo_saludo" class="form-control"
                            placeholder="Ej: Apreciado/a {tutor}:"
                            value="<?= htmlspecialchars($config['correo_saludo']) ?>">
                        <small class="text-muted">Usá <code>{tutor}</code> para el nombre del tutor/a.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje de bienvenida</label>
                        <textarea name="correo_mensaje" class="form-control" rows="2"
                            placeholder="Ej: Queremos invitarte a nuestro próximo evento. ¡Te esperamos!"><?= htmlspecialchars($config['correo_mensaje']) ?></textarea>
                        <small class="text-muted">Aparece al inicio, antes de los datos del evento.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Firma</label>
                        <input type="text" name="correo_firma" class="form-control"
                            placeholder="Ej: Equipo Instituto EvolucionArte"
                            value="<?= htmlspecialchars($config['correo_firma']) ?>">
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
                    La cabecera del correo y el botón del mapa ya se arman solos con los datos del evento
                    (título, fecha, hora, lugar, flyer).
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