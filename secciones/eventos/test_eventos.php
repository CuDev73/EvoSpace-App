<?php
/**
 * Pruebas básicas del módulo Eventos y Notificaciones.
 *
 * Cómo ejecutar:
 * php test_eventos.php (desde la carpeta eventos)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/models/EventoModel.php';
require_once __DIR__ . '/models/NotificacionModel.php';

function assertTrue(bool $condicion, string $mensaje): void
{
    echo ($condicion ? "OK    " : "FALLÓ ") . "- $mensaje\n";
}

$eventoModel = new EventoModel($pdo);
$notificacionModel = new NotificacionModel($pdo);

echo "=== Pruebas del módulo Eventos y Notificaciones ===\n\n";

$idEvento = $eventoModel->crearEvento([
    'titulo'      => 'Sesión de fotos para la obra',
    'fecha'       => '2026-08-13',
    'hora'        => '15:00',
    'lugar'       => 'Avda. Lalaland c/12 de junio',
    'descripcion' => 'Llevar polleras, sombreros y utilería',
    'color'       => '#8B1A1A',
    'ramas'       => [1, 3],
]);
assertTrue($idEvento > 0, "Evento creado con ID $idEvento");

$evento = $eventoModel->obtenerEvento($idEvento);
assertTrue($evento !== null, 'El evento se puede recuperar por ID');
assertTrue(count($evento['ramas']) === 2, 'El evento tiene 2 cursos destino');

$notificaciones = $notificacionModel->obtenerNotificaciones();
$notisDelEvento = array_filter($notificaciones, fn($n) => (int) $n['id_evento'] === $idEvento);
assertTrue(count($notisDelEvento) === 2, 'Se generaron 2 notificaciones automáticas');

$eventoModel->actualizarEvento($idEvento, [
    'titulo' => 'Sesión de fotos para la obra (reprogramada)',
    'fecha'  => '2026-08-20',
    'ramas'  => [2],
]);
$eventoEditado = $eventoModel->obtenerEvento($idEvento);
assertTrue($eventoEditado['titulo'] === 'Sesión de fotos para la obra (reprogramada)', 'El título se actualizó');
assertTrue(count($eventoEditado['ramas']) === 1, 'Ahora el evento tiene solo 1 curso destino');

$notificacionesActualizadas = $notificacionModel->obtenerNotificaciones();
$notisActuales = array_filter($notificacionesActualizadas, fn($n) => (int) $n['id_evento'] === $idEvento);
assertTrue(count($notisActuales) === 1, 'Las notificaciones se regeneraron (ahora hay 1)');

$primera = array_values($notisActuales)[0] ?? null;
if ($primera) {
    $marcada = $notificacionModel->marcarLeida((int) $primera['id_notificacion']);
    assertTrue($marcada, 'Notificación marcada como leída sin errores');
}

try {
    $eventoModel->crearEvento(['titulo' => 'Evento sin curso', 'fecha' => '2026-09-01', 'ramas' => []]);
    assertTrue(false, 'Debería haber lanzado una excepción por falta de cursos');
} catch (InvalidArgumentException $e) {
    assertTrue(true, 'Rechaza correctamente un evento sin cursos (' . $e->getMessage() . ')');
}

try {
    $eventoModel->crearEvento(['fecha' => '2026-09-01', 'ramas' => [1]]);
    assertTrue(false, 'Debería haber lanzado una excepción por falta de título');
} catch (InvalidArgumentException $e) {
    assertTrue(true, 'Rechaza correctamente un evento sin título (' . $e->getMessage() . ')');
}

$eliminado = $eventoModel->eliminarEvento($idEvento);
assertTrue($eliminado, 'Evento eliminado correctamente');

$eventoBorrado = $eventoModel->obtenerEvento($idEvento);
assertTrue($eventoBorrado === null, 'El evento ya no existe tras eliminarlo');

$notificacionesFinales = $notificacionModel->obtenerNotificaciones();
$notisHuerfanas = array_filter($notificacionesFinales, fn($n) => (int) $n['id_evento'] === $idEvento);
assertTrue(count($notisHuerfanas) === 0, 'Las notificaciones del evento se eliminaron en cascada');

echo "\n=== Pruebas finalizadas ===\n";