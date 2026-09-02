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

$mensaje = '';
$uploadDir = __DIR__ . '/../../uploads/recibo/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$config = [];
$stmt = $pdo->query("SELECT clave, valor FROM configuracion");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['clave']] = $row['valor'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $campos = ['recibo_nombre', 'recibo_titulo', 'recibo_ruc', 'recibo_mensaje', 'recibo_pie'];
    foreach ($campos as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
        $stmt->execute([$valor, $campo]);
    }

    if (!empty($_FILES['recibo_logo']['name'])) {
        if ($_FILES['recibo_logo']['error'] !== UPLOAD_ERR_OK) {
            $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> El logo no se pudo subir (código ' . $_FILES['recibo_logo']['error'] . ').</div>';
        } else {
            $ext = strtolower(pathinfo($_FILES['recibo_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $nombre = 'logo_recibo.' . $ext;
                if (move_uploaded_file($_FILES['recibo_logo']['tmp_name'], $uploadDir . $nombre)) {
                    $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'recibo_logo'");
                    $stmt->execute(['uploads/recibo/' . $nombre]);
                    $config['recibo_logo'] = 'uploads/recibo/' . $nombre;
                    $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Configuración del recibo guardada y logo actualizado.</div>';
                } else {
                    $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> El logo no se pudo subir. Verificá que la carpeta uploads/recibo tenga permisos de escritura.</div>';
                }
            } else {
                $mensaje = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> Formato de imagen no válido.</div>';
            }
        }
    }

    if (isset($_POST['eliminar_logo'])) {
        $stmt = $pdo->prepare("UPDATE configuracion SET valor = '' WHERE clave = 'recibo_logo'");
        $stmt->execute();
        $config['recibo_logo'] = '';
    }

    if (empty($mensaje)) {
        $mensaje = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Configuración del recibo guardada.</div>';
    }

    $stmt = $pdo->query("SELECT clave, valor FROM configuracion");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['clave']] = $row['valor'];
    }
}
?>

<div class="container mt-3 pb-4">
    <?= $mensaje ?>

    <div class="page-header mb-3">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i> Configurar Recibo</h3>
            <small>Personaliza los datos que aparecen en los recibos PDF</small>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="row g-4">
        <?= campoCSRF() ?>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-evo text-white py-2">
                    <i class="bi bi-building"></i> Datos de la institución
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la institución</label>
                        <input type="text" name="recibo_nombre" class="form-control"
                            value="<?= htmlspecialchars($config['recibo_nombre'] ?? 'EvoSpace') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Título / Subtítulo</label>
                        <input type="text" name="recibo_titulo" class="form-control"
                            value="<?= htmlspecialchars($config['recibo_titulo'] ?? 'Academia de Artes Escénicas') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">RUC</label>
                        <input type="text" name="recibo_ruc" class="form-control"
                            value="<?= htmlspecialchars($config['recibo_ruc'] ?? '12345678-0') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje principal (pie del recibo)</label>
                        <input type="text" name="recibo_mensaje" class="form-control"
                            value="<?= htmlspecialchars($config['recibo_mensaje'] ?? 'Gracias por confiar en EvoSpace') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pie de página adicional</label>
                        <input type="text" name="recibo_pie" class="form-control"
                            value="<?= htmlspecialchars($config['recibo_pie'] ?? 'Este documento es un comprobante de pago válido') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-evo text-white py-2">
                    <i class="bi bi-image"></i> Logo
                </div>
                <div class="card-body text-center">
                    <img id="logoPreview" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" class="img-fluid mb-3 d-none"
                        style="max-height:120px;" alt="Vista previa">
                    <?php if (!empty($config['recibo_logo'])): ?>
                        <img id="logoActual" src="/evospace/<?= $config['recibo_logo'] ?>" class="img-fluid mb-3"
                            style="max-height:120px;" alt="Logo actual">
                        <div class="mb-2">
                            <input type="hidden" name="eliminar_logo" id="eliminarLogoHidden" value="0">
                            <button type="button" onclick="document.getElementById('eliminarLogoHidden').value='1'; return confirmarEliminar(this.form, '¿Eliminar el logo del recibo? Esta acción no se puede deshacer.')"
                                class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i> Eliminar logo
                            </button>
                        </div>
                    <?php else: ?>
                        <div id="sinLogo" class="text-muted mb-3 py-4">
                            <i class="bi bi-image" style="font-size:3rem;"></i>
                            <p class="mt-2">Sin logo</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="recibo_logo" id="inputLogo" class="form-control form-control-sm" accept="image/*">
                    <small class="text-muted">JPG, PNG, GIF o WebP. Se redimensionará automáticamente.</small>
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

<script>
document.getElementById('inputLogo').addEventListener('change', function() {
    const archivo = this.files[0];
    const preview = document.getElementById('logoPreview');
    const actual = document.getElementById('logoActual');
    const sinLogo = document.getElementById('sinLogo');
    if (!archivo) { preview.classList.add('d-none'); return; }
    const lector = new FileReader();
    lector.onload = function(e) {
        preview.src = e.target.result;
        preview.classList.remove('d-none');
        if (actual) actual.classList.add('d-none');
        if (sinLogo) sinLogo.style.display = 'none';
    };
    lector.readAsDataURL(archivo);
});
</script>
