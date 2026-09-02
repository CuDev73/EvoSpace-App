<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/NotificacionModel.php';

class EventoModel
{
    private $db;
    private $notificacionModel;
    private $resultadoNotificacion = ['total' => 0, 'enviados' => 0, 'invalidos' => 0, 'errores' => 0];

    public function __construct($pdo)
    {
        $this->db = $pdo;
        $this->notificacionModel = new NotificacionModel($pdo);
    }

    public function obtenerResultadoNotificacion(): array
    {
        return $this->resultadoNotificacion;
    }

    public function crearEvento(array $data): int
    {
        $this->validar($data);

        $imagen = $this->subirImagen($_FILES['imagen'] ?? null);

        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO eventos (titulo, descripcion, mensaje_bienvenida, fecha, hora, lugar, enlace_ubicacion, color, imagen) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['titulo'],
                $data['descripcion'] ?? null,
                $data['mensaje_bienvenida'] ?? null,
                $data['fecha'],
                $data['hora'] ?? null,
                $data['lugar'] ?? null,
                $data['enlace_ubicacion'] ?? null,
                $data['color'] ?? '#c81015',
                $imagen
            ]);
            $eventoId = (int) $this->db->lastInsertId();

            $this->guardarRamas($eventoId, $data['ramas']);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // 🔔 Notificar por correo DESPUÉS del commit (evita correos huérfanos y no bloquea transacciones)
        $this->resultadoNotificacion = $this->notificacionModel->enviarNotificacionEvento(
            $eventoId,
            $data['titulo'],
            $data['descripcion'] ?? '',
            $data['fecha'],
            $data['hora'] ?? null,
            $data['lugar'] ?? null,
            $data['enlace_ubicacion'] ?? null,
            $data['ramas'],
            $data['color'] ?? '#c81015',
            $data['mensaje_bienvenida'] ?? null
        );

        return $eventoId;
    }

    public function actualizarEvento(int $id, array $data): bool
    {
        $this->validar($data);

        $imagen = $this->subirImagen($_FILES['imagen'] ?? null);

        $this->db->beginTransaction();
        try {
            $sql = "UPDATE eventos SET 
                        titulo = ?, descripcion = ?, mensaje_bienvenida = ?, fecha = ?, hora = ?, 
                        lugar = ?, enlace_ubicacion = ?, color = ?, imagen = COALESCE(?, imagen)
                    WHERE id_evento = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['titulo'],
                $data['descripcion'] ?? null,
                $data['mensaje_bienvenida'] ?? null,
                $data['fecha'],
                $data['hora'] ?? null,
                $data['lugar'] ?? null,
                $data['enlace_ubicacion'] ?? null,
                $data['color'] ?? '#c81015',
                $imagen,
                $id
            ]);

            $stmt = $this->db->prepare("DELETE FROM evento_curso WHERE id_evento = ?");
            $stmt->execute([$id]);
            $this->guardarRamas($id, $data['ramas']);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // 🔔 Notificar por correo DESPUÉS del commit
        $this->resultadoNotificacion = $this->notificacionModel->enviarNotificacionEvento(
            $id,
            $data['titulo'],
            $data['descripcion'] ?? '',
            $data['fecha'],
            $data['hora'] ?? null,
            $data['lugar'] ?? null,
            $data['enlace_ubicacion'] ?? null,
            $data['ramas'],
            $data['color'] ?? '#c81015',
            $data['mensaje_bienvenida'] ?? null
        );

        return true;
    }

    public function enviarRecordatorio(int $id): int
    {
        $evento = $this->obtenerEvento($id);
        if (!$evento) throw new InvalidArgumentException('Evento no encontrado.');

        if (empty($evento['ramas'])) {
            throw new InvalidArgumentException('El evento no tiene cursos asociados.');
        }

        $cursosIds = array_map(fn($r) => (int) $r['id_curso'], $evento['ramas']);
        $titulo = 'Recordatorio: ' . $evento['titulo'];
        $descripcion = 'Este es un recordatorio del evento "' . $evento['titulo'] . '".' .
            ($evento['descripcion'] ? ' Detalle: ' . $evento['descripcion'] : '') .
            ' Queda poco tiempo, ¡no te lo pierdas!';

        $notificaciones = $this->notificacionModel->contarPadresNotificados($cursosIds);

        $this->notificacionModel->enviarNotificacionEvento(
            $id,
            $titulo,
            $descripcion,
            $evento['fecha'],
            $evento['hora'] ?? null,
            $evento['lugar'] ?? null,
            $evento['enlace_ubicacion'] ?? null,
            $cursosIds,
            $evento['color'] ?? '#c81015',
            $evento['mensaje_bienvenida'] ?? null
        );

        $stmt = $this->db->prepare("UPDATE eventos SET ultimo_recordatorio = CURDATE() WHERE id_evento = ?");
        $stmt->execute([$id]);

        return $notificaciones;
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

    private function subirImagen($file): ?string
    {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return null;
        $nombre = uniqid('ev_') . '.' . $ext;
        $destino = __DIR__ . '/../../../uploads/eventos/' . $nombre;
        if (move_uploaded_file($file['tmp_name'], $destino)) {
            return 'uploads/eventos/' . $nombre;
        }
        return null;
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