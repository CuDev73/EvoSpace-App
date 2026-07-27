<?php
require_once __DIR__ . '/../../config/db.php';

/**
 * Modelo de Notificaciones adaptado a la base de datos de EvoSpace.
 */
class NotificacionModel
{
    private PDO $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    public function enviarNotificacion(int $eventoId, string $tituloEvento, array $cursoIds): void
    {
        $mensaje = "Se ha programado el evento: '" . $tituloEvento . "'. Revisá el panel de eventos para más detalles.";
        $sql = "INSERT INTO notificaciones (id_evento, titulo, mensaje, tipo, id_curso) 
                VALUES (:id_evento, :titulo, :mensaje, :tipo, :id_curso)";
        $stmt = $this->db->prepare($sql);
        foreach (array_unique($cursoIds) as $cursoId) {
            $stmt->execute([
                ':id_evento' => $eventoId,
                ':titulo'    => 'Nuevo Evento Programado',
                ':mensaje'   => $mensaje,
                ':tipo'      => 'evento',
                ':id_curso'  => (int) $cursoId
            ]);
        }
    }

    public function obtenerNotificacionesPorCurso(int $cursoId): array
    {
        $sql = "SELECT * FROM notificaciones WHERE id_curso = :id_curso ORDER BY fecha DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_curso' => $cursoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarComoLeida(int $notificacionId): bool
    {
        $sql = "UPDATE notificaciones SET leida = 1 WHERE id_notificacion = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $notificacionId]);
    }
}