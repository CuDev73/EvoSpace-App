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
    public function enviarNotificacionEvento($eventoId, $titulo, $descripcion, $fecha, $hora, $lugar, $enlace, $cursosIds, $color = '#c81015')
    {
        // 1. Obtener padres únicos (DISTINCT) con sus IDs y emails
        $placeholders = implode(',', array_fill(0, count($cursosIds), '?'));
        $sql = "SELECT DISTINCT u.id_usuario, u.email, u.nombre_completo 
                FROM alumnos a
                INNER JOIN usuarios u ON a.id_padre = u.id_usuario
                WHERE a.id_curso IN ($placeholders) AND u.activo = 1 AND a.activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($cursosIds);
        $padres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Si hay padres, enviar correo (uno por padre)
        if (!empty($padres)) {
            $fechaFormateada = date('d/m/Y', strtotime($fecha));
            $horaFormateada = $hora ? date('H:i', strtotime($hora)) : 'Sin horario';
            $lugarTexto = $lugar ?: 'No especificado';
            $descripcionTexto = $descripcion ?: '';
            $enlaceTexto = $enlace ?: '';

            $asunto = $titulo;
            $mensajeHTML = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .evento { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 5px solid $color; }
                        .detalle { margin: 8px 0; }
                        .map-link { display: inline-block; margin-top: 10px; padding: 8px 16px; background: $color; color: #fff !important; text-decoration: none; border-radius: 5px; }
                        .map-link:hover { opacity: 0.85; }
                    </style>
                </head>
                <body>
                    <div class='evento'>
                        <h2 style='margin-top:0;color:$color;'>$titulo</h2>
                        <p><strong>Fecha:</strong> $fechaFormateada</p>
                        <p><strong>Hora:</strong> $horaFormateada</p>
                        <p><strong>Lugar:</strong> $lugarTexto</p>
                        " . ($enlaceTexto ? "<p><a href='$enlaceTexto' target='_blank' class='map-link'>Ver en mapa</a></p>" : "") . "
                        " . ($descripcionTexto ? "<p><strong>Descripción:</strong><br>" . nl2br($descripcionTexto) . "</p>" : "") . "
                    </div>
                    <p style='margin-top: 20px;'>Por favor, revisa el panel de EvoSpace para más detalles.</p>
                    <p style='color: #6c757d; font-size: 0.9rem;'>Este correo es automático, no respondas a esta dirección.</p>
                </body>
                </html>
            ";

            foreach ($padres as $padre) {
                enviarCorreo($padre['email'], $asunto, $mensajeHTML);
            }
        }

        // 3. Guardar notificaciones en la base de datos (una por padre, no por curso)
        $sqlInsert = "INSERT INTO notificaciones (id_evento, id_usuario, titulo, mensaje, tipo) VALUES (?, ?, ?, ?, 'evento')";
        $stmtInsert = $this->db->prepare($sqlInsert);
        foreach ($padres as $padre) {
            $stmtInsert->execute([$eventoId, $padre['id_usuario'], $titulo, $descripcionTexto]);
        }
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