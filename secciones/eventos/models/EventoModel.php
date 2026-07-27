<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/NotificacionModel.php';

class EventoModel
{
    private $db;
    private $notificacionModel;

    public function __construct($pdo)
    {
        $this->db = $pdo;
        $this->notificacionModel = new NotificacionModel($pdo);
    }

    public function crearEvento(array $data): int
    {
        $this->validar($data);

        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO eventos (titulo, descripcion, fecha, hora, lugar, enlace_ubicacion, color) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['titulo'],
                $data['descripcion'] ?? null,
                $data['fecha'],
                $data['hora'] ?? null,
                $data['lugar'] ?? null,
                $data['enlace_ubicacion'] ?? null,
                $data['color'] ?? '#c81015'
            ]);
            $eventoId = (int) $this->db->lastInsertId();

            $this->guardarRamas($eventoId, $data['ramas']);

            // 🔔 Notificar por correo a los padres
            $this->notificacionModel->enviarNotificacionEvento(
                $eventoId,
                $data['titulo'],
                $data['descripcion'] ?? '',
                $data['fecha'],
                $data['hora'] ?? null,
                $data['lugar'] ?? null,
                $data['enlace_ubicacion'] ?? null,
                $data['ramas']
            );

            $this->db->commit();
            return $eventoId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizarEvento(int $id, array $data): bool
    {
        $this->validar($data);

        $this->db->beginTransaction();
        try {
            $sql = "UPDATE eventos SET 
                        titulo = ?, descripcion = ?, fecha = ?, hora = ?, 
                        lugar = ?, enlace_ubicacion = ?, color = ?
                    WHERE id_evento = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['titulo'],
                $data['descripcion'] ?? null,
                $data['fecha'],
                $data['hora'] ?? null,
                $data['lugar'] ?? null,
                $data['enlace_ubicacion'] ?? null,
                $data['color'] ?? '#c81015',
                $id
            ]);

            $stmt = $this->db->prepare("DELETE FROM evento_curso WHERE id_evento = ?");
            $stmt->execute([$id]);
            $this->guardarRamas($id, $data['ramas']);

            // También notificar al editar (opcional)
            $this->notificacionModel->enviarNotificacionEvento(
                $id,
                $data['titulo'],
                $data['descripcion'] ?? '',
                $data['fecha'],
                $data['hora'] ?? null,
                $data['lugar'] ?? null,
                $data['enlace_ubicacion'] ?? null,
                $data['ramas']
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function eliminarEvento(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM evento_curso WHERE id_evento = ?");
            $stmt->execute([$id]);
            $stmt = $this->db->prepare("DELETE FROM eventos WHERE id_evento = ?");
            $stmt->execute([$id]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerEventos(): array
    {
        $sql = "SELECT * FROM eventos ORDER BY fecha DESC, hora DESC";
        $eventos = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($eventos as &$ev) {
            $ev['ramas'] = $this->obtenerCursosDeEvento($ev['id_evento']);
        }
        return $eventos;
    }

    public function obtenerEvento(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM eventos WHERE id_evento = ?");
        $stmt->execute([$id]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$evento) return null;
        $evento['ramas'] = $this->obtenerCursosDeEvento($id);
        return $evento;
    }

    private function guardarRamas(int $eventoId, array $ramas): void
    {
        if (empty($ramas)) return;
        $sqlRama = "INSERT INTO evento_curso (id_evento, id_curso) VALUES (?, ?)";
        $stmtRama = $this->db->prepare($sqlRama);
        foreach (array_unique($ramas) as $id_curso) {
            $stmtRama->execute([$eventoId, (int)$id_curso]);
        }
    }

    private function obtenerCursosDeEvento(int $eventoId): array
    {
        $sql = "SELECT c.id_curso, c.nombre, c.tipo 
                FROM cursos c
                INNER JOIN evento_curso ec ON c.id_curso = ec.id_curso
                WHERE ec.id_evento = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$eventoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validar(array $data): void
    {
        if (empty($data['titulo'])) {
            throw new InvalidArgumentException('El evento necesita un título.');
        }
        if (empty($data['fecha'])) {
            throw new InvalidArgumentException('El evento necesita una fecha.');
        }
        if (empty($data['ramas']) || !is_array($data['ramas'])) {
            throw new InvalidArgumentException('Debe seleccionarse al menos un curso.');
        }
    }
}