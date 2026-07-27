<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'padre') {
    header('Location: /evospace/index.php');
    exit;
}

require_once '../../config/db.php';
require_once 'models/NotificacionModel.php';

$id_notificacion = (int)$_GET['id'];
$notificacionModel = new NotificacionModel($pdo);
$notificacionModel->marcarLeida($id_notificacion);

header('Location: /evospace/roles/padre.php');
exit;