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

$claves = ['recordatorio_deuda_dia', 'recordatorio_deuda_activo', 'recordatorio_deuda_ultimo', 'recordatorio_asunto', 'recordatorio_mensaje', 'recordatorio_despedida'];
$config = [];
foreach ($claves as $c) {
    $config[$c] = $pdo->query("SELECT valor FROM configuracion WHERE clave = '" . $c . "'")->fetchColumn() ?: '';
}
$config['recordatorio_deuda_dia'] = $config['recordatorio_deuda_dia'] ?: '25';
$config['recordatorio_asunto'] = $config['recordatorio_asunto'] ?: 'Recordatorio de deudas — {mes}';
$config['recordatorio_mensaje'] = $config['recordatorio_mensaje'] ?: 'Te escribimos para recordarte las deudas pendientes del mes de {mes}.';
$config['recordatorio_despedida'] = $config['recordatorio_despedida'] ?: 'Podés abonar por la secretaría del instituto. ¡Muchas gracias por tu atención!';

$mensaje = '';
$mensajeTipo = 'success';
$correosEnviados = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? 'guardar';

    if ($accion === 'guardar') {
        $dia = max(1, min(31, (int)($_POST['recordatorio_deuda_dia'] ?? 25)));
        $activo = !empty($_POST['recordatorio_deuda_activo']) ? '1' : '0';
        $asunto = trim($_POST['recordatorio_asunto'] ?? '');
        $mensajeTexto = trim($_POST['recordatorio_mensaje'] ?? '');
        $despedida = trim($_POST['recordatorio_despedida'] ?? '');
        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->execute(['recordatorio_deuda_dia', $dia]);
        $stmt->execute(['recordatorio_deuda_activo', $activo]);
        $stmt->execute(['recordatorio_asunto', $asunto]);
        $stmt->execute(['recordatorio_mensaje', $mensajeTexto]);
        $stmt->execute(['recordatorio_despedida', $despedida]);
        $config['recordatorio_deuda_dia'] = $dia;
        $config['recordatorio_deuda_activo'] = $activo;
        $config['recordatorio_asunto'] = $asunto;
        $config['recordatorio_mensaje'] = $mensajeTexto;
        $config['recordatorio_despedida'] = $despedida;
        $mensaje = '<i class="bi bi-check-circle-fill"></i> Configuración del recordatorio guardada.';
    } elseif ($accion === 'enviar') {
        $correosEnviados = enviarRecordatorioDeudasTutores($pdo);
        $mesAnio = date('Y-m');
        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('recordatorio_deuda_ultimo', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->execute([$mesAnio]);
        $config['recordatorio_deuda_ultimo'] = $mesAnio;
        if ($correosEnviados > 0) {
            $mensaje = '<i class="bi bi-check-circle-fill"></i> Recordatorio enviado a <strong>' . $correosEnviados . '</strong> tutor(es) con deudas.';
        } else {
            $mensaje = '<i class="bi bi-info-circle-fill"></i> Ningún tutor con deudas para notificar, o hubo errores de envío.';
            $mensajeTipo = 'warning';
        }
    }
}
?>

<div class="container mt-3 pb-4">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensajeTipo ?> alert-dismissible fade show">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="page-header mb-3">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-bell-fill me-2"></i> Recordatorio de Deudas a Tutores</h3>
            <small>Envía cada mes un correo a los tutores con la lista de los hijos que deben (cuota y cantina)</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-evo text-white py-2">
                    <i class="bi bi-gear-fill"></i> Configuración automática
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= campoCSRF() ?>
                        <input type="hidden" name="accion" value="guardar">
                        <div class="mb-3">
                            <label class="form-label">Día del mes para enviar</label>
                            <input type="number" name="recordatorio_deuda_dia" class="form-control" min="1" max="31" value="<?= (int)$config['recordatorio_deuda_dia'] ?>" required>
                            <small class="text-muted">Ese día, la primera vez que un administrador entre al panel, se envían los correos automáticamente (una vez por mes).</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="recordatorio_deuda_activo" id="chkActivo" class="form-check-input" value="1" <?= $config['recordatorio_deuda_activo'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="chkActivo">Recordatorio automático activado</label>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Asunto del correo</label>
                            <input type="text" name="recordatorio_asunto" class="form-control" value="<?= htmlspecialchars($config['recordatorio_asunto']) ?>">
                            <small class="text-muted">Podés usar <code>{mes}</code> (ej: "Recordatorio de deudas — {mes}").</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensaje (cuerpo antes de la tabla)</label>
                            <textarea name="recordatorio_mensaje" class="form-control" rows="3"><?= htmlspecialchars($config['recordatorio_mensaje']) ?></textarea>
                            <small class="text-muted">Podés usar <code>{mes}</code>.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Despedida</label>
                            <textarea name="recordatorio_despedida" class="form-control" rows="2"><?= htmlspecialchars($config['recordatorio_despedida']) ?></textarea>
                            <small class="text-muted">Texto al final del correo, antes de la firma.</small>
                        </div>
                        <button type="submit" class="btn btn-evo"><i class="bi bi-save"></i> Guardar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-evo text-white py-2">
                    <i class="bi bi-send-fill"></i> Envío manual
                </div>
                <div class="card-body">
                    <p class="text-muted small">Envía ahora el recordatorio a todos los tutores activos que tengan al menos un hijo con deuda (cuota del mes o cantina).</p>
                    <?php if (!empty($config['recordatorio_deuda_ultimo'])): ?>
                        <p class="small mb-2"><i class="bi bi-clock-history"></i> Último envío: <strong><?= htmlspecialchars($config['recordatorio_deuda_ultimo']) ?></strong></p>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('¿Enviar el recordatorio de deudas a los tutores ahora?');">
                        <?= campoCSRF() ?>
                        <input type="hidden" name="accion" value="enviar">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-bell-fill"></i> Enviar recordatorio ahora
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-3 bg-light">
                <div class="card-body small text-muted">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Cada tutor recibe un correo con una tabla: su hijo/a, curso, deuda de cuota del mes actual, deuda de cantina y total. Solo se notifica a tutores con hijos activos y con saldo pendiente.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>