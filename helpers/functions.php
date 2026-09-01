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

// ============================================================
// ACCESO A RECURSOS POR PERTENENCIA
// ============================================================

function verificarAccesoAlumno($pdo, $id_alumno) {
    $usuario = (int)($_SESSION['id_usuario'] ?? 0);
    $rol = $_SESSION['rol'] ?? '';
    if (in_array($rol, ['admin', 'auxiliar'], true)) {
        return true;
    }
    if ($rol === 'padre') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE id_alumno = ? AND id_padre = ?");
        $stmt->execute([$id_alumno, $usuario]);
        return (bool)$stmt->fetchColumn();
    }
    if ($rol === 'profesor') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM alumnos a
            JOIN horarios h ON a.id_curso = h.id_curso
            JOIN profesores p ON h.id_profesor = p.id_profesor
            WHERE a.id_alumno = ? AND p.id_usuario = ?
        ");
        $stmt->execute([$id_alumno, $usuario]);
        return (bool)$stmt->fetchColumn();
    }
    return false;
}

function denegarAcceso() {
    http_response_code(403);
    exit('No tenés permisos para acceder a este recurso.');
}

function redirigirSegunRol($rol) {
    $rutas = [
        'admin'    => '/evospace/roles/admin.php',
        'profesor' => '/evospace/roles/profesor.php',
        'padre'    => '/evospace/roles/padre.php',
        'auxiliar' => '/evospace/roles/admin.php'
    ];
    // Si el rol no está en el array, redirige al login
    $url = isset($rutas[$rol]) ? $rutas[$rol] : '/evospace/index.php';
    header('Location: ' . $url);
    exit;
}

function formatoMoneda($monto) {
    return 'Gs ' . number_format($monto, 0, ',', '.');
}

// ============================================================
// PROTECCIÓN CSRF
// ============================================================

function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function campoCSRF() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generarTokenCSRF()) . '">';
}

function verificarTokenCSRF() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $token)) {
        http_response_code(403);
        die('Solicitud inválida. Token de seguridad no coincide. Recargá la página e intentá de nuevo.');
    }
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
        $cuota = round($cuota * ($porcentajeBeca / 100) / 1000) * 1000;
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
    return $result ? (float)$result['valor'] : 50.0;
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
// RECORDATORIO DE DEUDAS A TUTORES
// ============================================================
// Calcula la deuda del mes (cuota del curso + recargo + cantina) de un alumno,
// usando la misma lógica que la ficha del alumno.
function calcularDeudaMensualAlumno($pdo, $id_alumno) {
    $config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    $porcentajeBeca = (float)($config['porcentaje_beca'] ?? 50.0);
    $recargoPorDia = (float)($config['recargo_por_dia'] ?? 1000);
    $diaLimite = (int)($config['dia_limite_pago'] ?? 10);
    $diasGracia = (int)($config['dias_gracia_pago'] ?? 10);

    $stmt = $pdo->prepare("SELECT a.*, c.nombre AS curso_nombre, u.dia_cobro AS tutor_dia_cobro FROM alumnos a LEFT JOIN cursos c ON a.id_curso = c.id_curso LEFT JOIN usuarios u ON a.id_padre = u.id_usuario WHERE a.id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$alumno) return ['cuota' => 0, 'cantina' => 0, 'total' => 0, 'curso' => ''];

    $stmt = $pdo->prepare("SELECT precio FROM precios WHERE id_curso = ? AND concepto = 'cuota'");
    $stmt->execute([$alumno['id_curso']]);
    $cuota_base = (float)$stmt->fetchColumn() ?: 0;
    $cuota_valor = $alumno['becado'] ? round($cuota_base * ($porcentajeBeca / 100) / 1000) * 1000 : round($cuota_base / 1000) * 1000;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pagos WHERE id_alumno = ? AND concepto = 'cuota' AND MONTH(fecha) = ? AND YEAR(fecha) = ?");
    $stmt->execute([$id_alumno, date('m'), date('Y')]);
    $pagado_este_mes = (float)$stmt->fetchColumn();

    $dia_hoy = (int)date('j');
    $diaCobroTutor = (int)($alumno['tutor_dia_cobro'] ?? 0);
    $diaVenc = (int)($alumno['dia_vencimiento'] ?? ($diaCobroTutor >= 1 && $diaCobroTutor <= 31 ? $diaCobroTutor : $diaLimite));
    $vencimiento = $diaVenc + $diasGracia;
    $recargo = ($dia_hoy > $vencimiento) ? ($dia_hoy - $vencimiento) * $recargoPorDia : 0;
    $deudaCuota = max(0, ($cuota_valor + $recargo) - $pagado_este_mes);

    $deudaCantina = 0;
    if (is_dir(__DIR__ . '/../secciones/cantina')) {
        $sql = "SELECT COALESCE(SUM(total - monto_pagado), 0) FROM ventas WHERE id_alumno = ? AND estado_pago IN ('pendiente','parcial')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_alumno]);
        $deudaCantina = (float)$stmt->fetchColumn();
    }

    return [
        'cuota'    => $deudaCuota,
        'cantina'  => $deudaCantina,
        'total'    => $deudaCuota + $deudaCantina,
        'curso'    => $alumno['curso_nombre'] ?? '',
        'nombre'   => trim(($alumno['nombre'] ?? '') . ' ' . ($alumno['apellido'] ?? '')),
    ];
}

// Devuelve true si hoy corresponde enviar el recordatorio automático
// (activado, día alcanzado y aún no enviado este mes).
function recordatorioDeudaPendiente($pdo) {
    $config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'recordatorio_deuda_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (($config['recordatorio_deuda_activo'] ?? '0') !== '1') return false;
    $dia = max(1, min(31, (int)($config['recordatorio_deuda_dia'] ?? 25)));
    $mesAnio = date('Y-m');
    if ($config['recordatorio_deuda_ultimo'] ?? '' === $mesAnio) return false;
    return (int)date('j') >= $dia;
}

// Configuración del recordatorio (textos editables + día/envío).
function configRecordatorio($pdo) {
    $config = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'recordatorio_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'dia'           => max(1, min(31, (int)($config['recordatorio_deuda_dia'] ?? 25))),
        'activo'        => (string)($config['recordatorio_deuda_activo'] ?? '0'),
        'ultimo'        => (string)($config['recordatorio_deuda_ultimo'] ?? ''),
        'asunto'        => (string)($config['recordatorio_asunto'] ?? 'Recordatorio de deudas — {mes}'),
        'mensaje'       => (string)($config['recordatorio_mensaje'] ?? 'Te escribimos para recordarte las deudas pendientes del mes de {mes}.'),
        'despedida'     => (string)($config['recordatorio_despedida'] ?? 'Podés abonar por la secretaría del instituto. ¡Muchas gracias por tu atención!'),
    ];
}

// Construye el cuerpo HTML del recordatorio a partir de filas ya renderizadas.
// $filas: array de [nombreHijo, curso, cuota, cantina]
function construirCorreoRecordatorio($pdo, $filas, $primerNombre, $esAlumno = false) {
    $config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    $saludoBase = $config['correo_saludo'] ?? 'Apreciado/a {tutor}:';
    $firmaBase = $config['correo_firma'] ?? 'Equipo Instituto EvolucionArte';
    $remitente = $config['correo_remitente'] ?? 'Instituto EvolucionArte';
    $rcfg = configRecordatorio($pdo);

    $mesesES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $mesNombre = $mesesES[(int)date('n')] ?? date('F');

    $saludo = str_replace('{tutor}', htmlspecialchars($primerNombre, ENT_QUOTES, 'UTF-8'), $saludoBase);
    $mensajeCuerpo = str_replace('{mes}', $mesNombre, $rcfg['mensaje']);

    $filasHtml = '';
    $totalDeuda = 0.0;
    foreach ($filas as $f) {
        $totalDeuda += (float)$f['total'];
        $filasHtml .= "<tr>
            <td style='padding:8px 12px;border:1px solid #eee;'>" . htmlspecialchars($f['nombre']) . "</td>
            <td style='padding:8px 12px;border:1px solid #eee;'>" . htmlspecialchars($f['curso']) . "</td>
            <td style='padding:8px 12px;border:1px solid #eee;text-align:right;'>" . ($f['cuota'] > 0 ? 'Gs ' . number_format($f['cuota'], 0, ',', '.') : '—') . "</td>
            <td style='padding:8px 12px;border:1px solid #eee;text-align:right;'>" . ($f['cantina'] > 0 ? 'Gs ' . number_format($f['cantina'], 0, ',', '.') : '—') . "</td>
        </tr>";
    }
    if ($filasHtml === '') return null;
    $etiqueta = $esAlumno ? 'Hijo/a' : 'Hijos/as';

    $asunto = str_replace('{mes}', $mesNombre, $rcfg['asunto']);
    $despedida = str_replace('{mes}', $mesNombre, $rcfg['despedida']);

    $html = "<div style='font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:auto;'>
        <h2 style='color:#c81015;'>Recordatorio de deudas</h2>
        <p>$saludo</p>
        <p>$mensajeCuerpo</p>
        <table style='border-collapse:collapse;width:100%;'>
            <thead><tr>
                <th style='padding:8px 12px;border:1px solid #eee;background:#f7f7f7;text-align:left;'>$etiqueta</th>
                <th style='padding:8px 12px;border:1px solid #eee;background:#f7f7f7;text-align:left;'>Curso</th>
                <th style='padding:8px 12px;border:1px solid #eee;background:#f7f7f7;'>Cuota</th>
                <th style='padding:8px 12px;border:1px solid #eee;background:#f7f7f7;'>Cantina</th>
            </tr></thead>
            <tbody>$filasHtml</tbody>
        </table>
        <p style='margin-top:16px;'>Total a regularizar: <strong>Gs " . number_format($totalDeuda, 0, ',', '.') . "</strong></p>
        <p>$despedida</p>
        <p style='margin:0;color:#999;font-size:12px;'>" . htmlspecialchars($firmaBase) . "</p>
    </div>";

    return [
        'html' => $html,
        'asunto' => $asunto,
        'remitente' => $remitente,
    ];
}

// Envía un correo a cada tutor/a que tenga al menos un hijo activo con deudas
// (cuota del mes y/o cantina). Devuelve la cantidad de correos enviados.
function enviarRecordatorioDeudasTutores($pdo) {
    // Tutores activos con al menos un hijo activo
    $tutores = $pdo->query("
        SELECT DISTINCT u.id_usuario, u.nombre_completo, u.usuario, u.email
        FROM usuarios u
        JOIN alumnos a ON a.id_padre = u.id_usuario
        WHERE u.id_rol = (SELECT id_rol FROM roles WHERE nombre = 'padre')
          AND u.activo = 1 AND a.activo = 1
        ORDER BY u.nombre_completo
    ")->fetchAll(PDO::FETCH_ASSOC);

    $enviados = 0;
    foreach ($tutores as $tutor) {
        $email = filter_var(trim($tutor['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) continue;

        $stmt = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE id_padre = ? AND activo = 1 ORDER BY apellido, nombre");
        $stmt->execute([$tutor['id_usuario']]);
        $hijos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $filas = [];
        foreach ($hijos as $id_alumno) {
            $d = calcularDeudaMensualAlumno($pdo, $id_alumno);
            if ($d['total'] <= 0) continue;
            $filas[] = $d;
        }

        if (empty($filas)) continue;

        $primerNombre = preg_split('/\s+/', trim($tutor['nombre_completo'] ?: $tutor['usuario']), 2)[0];
        $correo = construirCorreoRecordatorio($pdo, $filas, $primerNombre);
        if (!$correo) continue;

        if (enviarCorreo($email, $correo['asunto'], $correo['html'], $primerNombre, '', $correo['remitente'])) {
            $enviados++;
        }
    }
    return $enviados;
}

// Envía un recordatorio al tutor/a de UN alumno específico (si tiene deudas).
// Devuelve true si se envió.
function enviarRecordatorioDeudaAlumno($pdo, $id_alumno) {
    $id_alumno = (int)$id_alumno;

    $stmt = $pdo->prepare("SELECT a.*, u.id_usuario AS tutor_id, u.nombre_completo, u.usuario, u.email
        FROM alumnos a
        LEFT JOIN usuarios u ON a.id_padre = u.id_usuario
        WHERE a.id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$alumno || (int)$alumno['activo'] !== 1) return false;

    $email = filter_var(trim($alumno['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) return false;

    $d = calcularDeudaMensualAlumno($pdo, $id_alumno);
    if ($d['total'] <= 0) return false;

    $primerNombre = preg_split('/\s+/', trim($alumno['nombre_completo'] ?: $alumno['usuario']), 2)[0];
    $correo = construirCorreoRecordatorio($pdo, [$d], $primerNombre, true);
    if (!$correo) return false;

    return enviarCorreo($email, $correo['asunto'], $correo['html'], $primerNombre, '', $correo['remitente']);
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
 * $imagenRuta: ruta absoluta a una imagen para embebir (usar 'cid:flyer' en el HTML)
 * $nombreRemitente: nombre visible del remitente (si vacío usa SMTP_FROM_NAME)
 */
function enviarCorreo($destinatario, $asunto, $mensajeHTML, $nombreDestinatario = '', $imagenRuta = '', $nombreRemitente = '') {
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
        $mail->setFrom(SMTP_FROM, $nombreRemitente !== '' ? $nombreRemitente : SMTP_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($destinatario, $nombreDestinatario ?: $destinatario);

        if ($imagenRuta && is_file($imagenRuta)) {
            $mail->AddEmbeddedImage($imagenRuta, 'flyer');
        }

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

/**
 * Genera un texto legible con el resultado del envío de notificaciones de un evento.
 */
function resumenNotificacion(array $notif): string
{
    $total = (int)($notif['total'] ?? 0);
    $enviados = (int)($notif['enviados'] ?? 0);
    $invalidos = (int)($notif['invalidos'] ?? 0);
    $errores = (int)($notif['errores'] ?? 0);

    if ($total === 0) {
        return '<span class="text-muted">No hay tutores con alumnos en los cursos seleccionados para notificar.</span>';
    }
    $partes = [];
    $partes[] = "<strong>$enviados</strong> de <strong>$total</strong> tutor(es) notificado(s) por correo";
    if ($invalidos > 0) $partes[] = "<strong>$invalidos</strong> con email inválido";
    if ($errores > 0) $partes[] = "<strong>$errores</strong> con error de envío";
    return '<span class="text-muted">(' . implode(', ', $partes) . ')</span>';
}

require_once __DIR__ . '/../vendor/autoload.php';