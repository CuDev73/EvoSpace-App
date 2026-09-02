<?php

class NotificacionModel
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Envía notificaciones (correo y base de datos) a los padres de los cursos seleccionados.
     * Ahora evita duplicados: un padre recibe un solo correo y una sola notificación en BD,
     * aunque tenga hijos en varios cursos seleccionados.
     */
    public function contarPadresNotificados($cursosIds): int
    {
        if (empty($cursosIds)) return 0;
        $placeholders = implode(',', array_fill(0, count($cursosIds), '?'));
        $sql = "SELECT COUNT(DISTINCT u.id_usuario)
                FROM alumnos a
                INNER JOIN usuarios u ON a.id_padre = u.id_usuario
                WHERE a.id_curso IN ($placeholders) AND u.activo = 1 AND a.activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($cursosIds);
        return (int) $stmt->fetchColumn();
    }

    public function enviarNotificacionEvento($eventoId, $titulo, $descripcion, $fecha, $hora, $lugar, $enlace, $cursosIds, $color = '#c81015', $mensajeBienvenida = null)
    {
        $resultado = ['total' => 0, 'enviados' => 0, 'invalidos' => 0, 'errores' => 0];
        if (empty($cursosIds)) return $resultado;

        // 1. Padres únicos con el curso (el primero de su hijo en los cursos seleccionados)
        $placeholders = implode(',', array_fill(0, count($cursosIds), '?'));
        $sql = "SELECT u.id_usuario, u.email, u.nombre_completo, cu.nombre AS curso_nombre, cu.tipo AS curso_tipo
                FROM alumnos a
                INNER JOIN usuarios u ON a.id_padre = u.id_usuario
                INNER JOIN cursos cu ON a.id_curso = cu.id_curso
                WHERE a.id_curso IN ($placeholders) AND u.activo = 1 AND a.activo = 1
                ORDER BY u.id_usuario, cu.id_curso";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($cursosIds);

        $padres = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if (!isset($padres[$fila['id_usuario']])) {
                $padres[$fila['id_usuario']] = $fila;
            }
        }
        $padres = array_values($padres);
        if (empty($padres)) return $resultado;
        $resultado['total'] = count($padres);

        // 2. Flyer / imagen del evento (si existe en disco, se embebe como adjunto)
        $imagenRuta = '';
        $stmtImg = $this->db->prepare("SELECT imagen FROM eventos WHERE id_evento = ?");
        $stmtImg->execute([$eventoId]);
        $imagen = (string) $stmtImg->fetchColumn();
        if ($imagen) {
            $rutaAbs = realpath(__DIR__ . '/../../../' . ltrim($imagen, '/'));
            if ($rutaAbs && is_file($rutaAbs)) {
                $imagenRuta = $rutaAbs;
            }
        }

        // 3. Textos del correo configurables (Configuración → Correo de Eventos)
        $configCorreo = $this->db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('correo_firma','correo_pie','correo_pie2','correo_remitente')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $firmaBase = $configCorreo['correo_firma'] ?? 'Equipo Instituto EvolucionArte';
        $pieBase = $configCorreo['correo_pie'] ?? 'Este correo fue enviado automáticamente por EvoSpace.';
        $pie2Base = $configCorreo['correo_pie2'] ?? 'Instituto EvolucionArte · Ingresá a tu panel de tutor/a para más detalles.';
        $remitente = trim($configCorreo['correo_remitente'] ?? '');

        $fechaFormateada = date('d/m/Y', strtotime($fecha));
        $horaFormateada = $hora ? date('H:i', strtotime($hora)) : 'Sin horario';
        $lugarSeguro = htmlspecialchars($lugar ?: 'No especificado', ENT_QUOTES, 'UTF-8');
        $descripcionSegura = nl2br(htmlspecialchars($descripcion ?: '', ENT_QUOTES, 'UTF-8'));
        $enlaceSeguro = htmlspecialchars($enlace ?: '', ENT_QUOTES, 'UTF-8');
        $tituloSeguro = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $colorSeguro = (is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) ? $color : '#c81015';
        $bienvenidaBase = trim($mensajeBienvenida ?? '');
        if ($bienvenidaBase === '') {
            $bienvenidaBase = 'Queremos invitarte a nuestro próximo evento. ¡Te esperamos!';
        }
        $asunto = $titulo;

        foreach ($padres as $padre) {
            $email = trim($padre['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $resultado['invalidos']++;
                continue;
            }
            $cursoLabel = htmlspecialchars((($padre['curso_tipo'] ?? '') ? $padre['curso_tipo'] . ' - ' : '') . ($padre['curso_nombre'] ?? 'Curso'), ENT_QUOTES, 'UTF-8');
            $primerNombre = trim((preg_split('/\s+/', trim($padre['nombre_completo'] ?? ''))[0] ?? ''));
            $bienvenidaSeguro = nl2br(htmlspecialchars($bienvenidaBase, ENT_QUOTES, 'UTF-8'));
            $firmaSeguro = htmlspecialchars($firmaBase, ENT_QUOTES, 'UTF-8');
            $pieSeguro = htmlspecialchars($pieBase, ENT_QUOTES, 'UTF-8');
            $pie2Seguro = htmlspecialchars($pie2Base, ENT_QUOTES, 'UTF-8');

            $mensajeHTML = "
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background-color:#f2f2f2; padding:24px 0;'>
              <tr>
                <td align='center'>
                  <table role='presentation' width='600' cellpadding='0' cellspacing='0' style='background-color:transparent; font-family:Arial, Helvetica, sans-serif;'>

                    <tr>
                      <td style='background-color:$colorSeguro; padding:16px 24px; border-radius:8px 8px 0 0;' align='left'>
                        <table role='presentation' cellpadding='0' cellspacing='0'>
                          <tr>
                            <td style='width:32px; height:32px; background-color:rgba(255,255,255,0.15); border-radius:6px; text-align:center; vertical-align:middle; color:#ffffff; font-size:13px; font-weight:bold;'>EA</td>
                            <td style='padding-left:10px; color:#ffffff; font-size:15px; font-weight:bold;'>Instituto EvolucionArte</td>
                          </tr>
                        </table>
                      </td>
                    </tr>

                    " . ($imagenRuta ? "<tr><td style='background-color:#ffffff; padding:16px 24px 0;'><img src='cid:flyer' width='552' alt='$tituloSeguro' style='display:block; width:100%; max-width:552px; height:auto; border-radius:6px;'></td></tr>" : "") . "

                    <tr>
                      <td style='background-color:#ffffff; padding:24px;'>
                        <h1 style='margin:0 0 18px; font-size:22px; color:$colorSeguro;'>$tituloSeguro</h1>
                        <p style='margin:0 0 18px; font-size:14px; color:#555555; line-height:1.6;'>$bienvenidaSeguro</p>

                        " . ($descripcionSegura ? "<p style='margin:0 0 18px; font-size:14px; color:#555555; line-height:1.6;'>$descripcionSegura</p>" : "") . "

                        <table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 0 20px;'>
                          <tr>
                            <td style='padding:4px 0; font-size:14px; color:#333333;'>📅 <strong>Fecha:</strong> $fechaFormateada</td>
                          </tr>
                          <tr>
                            <td style='padding:4px 0; font-size:14px; color:#333333;'>🕒 <strong>Hora:</strong> $horaFormateada</td>
                          </tr>
                          <tr>
                            <td style='padding:4px 0; font-size:14px; color:#333333;'>📍 <strong>Lugar:</strong> $lugarSeguro</td>
                          </tr>
                        </table>

                        " . ($enlaceSeguro ? "<table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 auto 22px;'><tr><td style='text-align:center; background-color:$colorSeguro; border-radius:4px;'><a href='$enlaceSeguro' target='_blank' style='display:inline-block; padding:10px 20px; font-size:14px; color:#ffffff; text-decoration:none; font-family:Arial, sans-serif;'>Ver ubicación en el mapa</a></td></tr></table>" : "") . "
                        " . ($firmaSeguro ? "<p style='margin:18px 0 0; font-size:14px; color:#333333; font-style:italic;'>$firmaSeguro</p>" : "") . "
                      </td>
                    </tr>

                    <tr>
                      <td style='background-color:#f7f7f7; padding:14px 24px; text-align:center; border-radius:0 0 8px 8px;'>
                        <p style='margin:0 0 4px; font-size:12px; color:#999999;'>$pieSeguro</p>
                        <p style='margin:0; font-size:12px; color:#999999;'>$pie2Seguro</p>
                      </td>
                    </tr>

                  </table>
                </td>
              </tr>
            </table>
            ";

            if (enviarCorreo($email, $asunto, $mensajeHTML, '', $imagenRuta, $remitente)) {
                $resultado['enviados']++;
            } else {
                $resultado['errores']++;
            }
        }

        // 4. Guardar notificaciones en la base de datos (una por padre, no por curso)
        $sqlInsert = "INSERT INTO notificaciones (id_evento, id_usuario, titulo, mensaje, tipo) VALUES (?, ?, ?, ?, 'evento')";
        $stmtInsert = $this->db->prepare($sqlInsert);
        foreach ($padres as $padre) {
            $stmtInsert->execute([$eventoId, $padre['id_usuario'], $titulo, $descripcion ?: '']);
        }

        return $resultado;
    }

    /**
     * Obtiene notificaciones de un padre (por su id_usuario)
     */
    public function obtenerNotificacionesPadre($id_padre)
    {
        $sql = "SELECT n.*, e.titulo as evento_titulo 
                FROM notificaciones n
                LEFT JOIN eventos e ON n.id_evento = e.id_evento
                WHERE n.id_usuario = ? AND n.tipo = 'evento'
                ORDER BY n.fecha DESC LIMIT 20";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_padre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca una notificación como leída
     */
    public function marcarLeida($id_notificacion)
    {
        $stmt = $this->db->prepare("UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ?");
        return $stmt->execute([$id_notificacion]);
    }
}