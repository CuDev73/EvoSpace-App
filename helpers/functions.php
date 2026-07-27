<?php
// helpers/functions.php

function obtenerConexion() {
    global $pdo;
    return $pdo;
}

function obtenerRoles($pdo) {
    $stmt = $pdo->query("SELECT id_rol, nombre FROM roles ORDER BY id_rol");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerPermisosDisponibles($pdo) {
    $stmt = $pdo->query("SELECT nombre, descripcion FROM permisos ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerPermisosUsuario($pdo, $id_usuario) {
    $stmt = $pdo->prepare("SELECT permiso FROM usuarios_permisos WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function actualizarPermisosUsuario($pdo, $id_usuario, $permisos) {
    $stmt = $pdo->prepare("DELETE FROM usuarios_permisos WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    if (!empty($permisos)) {
        $stmt = $pdo->prepare("INSERT INTO usuarios_permisos (id_usuario, permiso) VALUES (?, ?)");
        foreach ($permisos as $permiso) {
            $stmt->execute([$id_usuario, $permiso]);
        }
    }
}

function tienePermiso($permiso) {
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
        return true;
    }
    return isset($_SESSION['permisos']) && is_array($_SESSION['permisos']) && in_array($permiso, $_SESSION['permisos']);
}

function verificarPermiso($permiso) {
    if (!tienePermiso($permiso)) {
        header('Location: /evospace/index.php');
        exit;
    }
}

function redirigirSegunRol($rol) {
    $rutas = [
        'admin'    => '/evospace/roles/admin.php',
        'profesor' => '/evospace/roles/profesor.php',
        'padre'    => '/evospace/roles/padre.php'
    ];
    // Si el rol no está en el array, redirige al login
    $url = isset($rutas[$rol]) ? $rutas[$rol] : '/evospace/index.php';
    header('Location: ' . $url);
    exit;
}

function formatoMoneda($monto) {
    return 'Gs ' . number_format($monto, 0, ',', '.');
}

function obtenerDeudaAlumno($pdo, $id_alumno, $mes, $anio) {
    // 1. Obtener el precio de la cuota del curso del alumno
    $stmt = $pdo->prepare("
        SELECT p.precio 
        FROM alumnos a 
        INNER JOIN precios p ON a.id_curso = p.id_curso
        WHERE a.id_alumno = ? AND p.concepto = 'cuota'
    ");
    $stmt->execute([$id_alumno]);
    $cuota = $stmt->fetchColumn();
    if (!$cuota) return 0;

    // 2. Verificar si el alumno es becado
    $stmt = $pdo->prepare("SELECT becado FROM alumnos WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $becado = $stmt->fetchColumn();
    if ($becado) {
        $porcentajeBeca = (float) $pdo->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_beca'")->fetchColumn();
        $cuota = $cuota * ($porcentajeBeca / 100);
    }

    // 3. Sumar los pagos de CUOTA en ese mes para ese alumno
    $stmt = $pdo->prepare("
        SELECT SUM(total) FROM pagos 
        WHERE id_alumno = ? 
          AND concepto = 'cuota' 
          AND MONTH(fecha) = ? 
          AND YEAR(fecha) = ?
    ");
    $stmt->execute([$id_alumno, $mes, $anio]);
    $pagado = $stmt->fetchColumn() ?: 0;

    // 4. Deuda = cuota - pagado (si es positivo)
    $deuda = $cuota - $pagado;
    return max(0, $deuda);
}

// helpers/functions.php

function obtenerPorcentajeBeca($pdo) {
    $stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_beca'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (float)$result['valor'] : 45.45;
}

function obtenerRecargoPorDia($pdo) {
    $stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'recargo_por_dia'");
    $valor = $stmt->fetchColumn();
    return $valor ? (float)$valor : 1000;
}

function obtenerDiaLimite($pdo) {
    $stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'dia_limite_pago'");
    $valor = $stmt->fetchColumn();
    return $valor ? (int)$valor : 10;
}
// ============================================================
// PHPMailer para envío de correos
// ============================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Ajusta la ruta según el nombre exacto de tu carpeta
require_once __DIR__ . '/../vendor/PHPmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPmailer/SMTP.php';
require_once __DIR__ . '/../vendor/PHPmailer/Exception.php';
/**
 * Envía un correo electrónico usando PHPMailer
 */
function enviarCorreo($destinatario, $asunto, $mensajeHTML, $nombreDestinatario = '') {
    if (empty($destinatario) || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Remitente
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($destinatario, $nombreDestinatario ?: $destinatario);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $mensajeHTML;
        $mail->AltBody = strip_tags($mensajeHTML); // Versión texto plano

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Guardar error en log
        error_log("Error al enviar correo a $destinatario: " . $mail->ErrorInfo);
        return false;
    }
}

require_once __DIR__ . '/../vendor/autoload.php';