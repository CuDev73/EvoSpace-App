<?php
// index.php (raíz)
session_start();
require_once 'config/db.php';

// Si ya está logueado, redirigir según rol
if (isset($_SESSION['id_usuario'])) {
    $stmt = $pdo->prepare("SELECT r.nombre FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol WHERE u.id_usuario = ?");
    $stmt->execute([$_SESSION['id_usuario']]);
    $rol = $stmt->fetchColumn();
    if ($rol) {
        redirigirSegunRol($rol);
    } else {
        header('Location: /evospace/index.php');
    }
    exit;
}

$error = isset($_GET['error']) ? 'Usuario o contraseña incorrectos.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eleccion = trim($_POST['eleccion']);
    $contrasena = $_POST['contrasena'];

    $sql = "SELECT u.*, r.nombre AS rol_nombre 
            FROM usuarios u 
            JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.usuario = ? OR u.email = ? OR u.cedula = ? 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eleccion, $eleccion, $eleccion]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $usuario['activo'] && password_verify($contrasena, $usuario['password_hash'])) {
        session_regenerate_id(true);
        
        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['usuario']    = $usuario['usuario'];
        $_SESSION['email']      = $usuario['email'];
        $_SESSION['rol']        = $usuario['rol_nombre'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'] ?? $usuario['usuario'];
        
        // Cargar permisos (si no es admin)
        if ($usuario['rol_nombre'] === 'admin') {
            $_SESSION['permisos'] = null; // admin tiene todos
        } else {
            $stmtPermisos = $pdo->prepare("SELECT permiso FROM usuarios_permisos WHERE id_usuario = ?");
            $stmtPermisos->execute([$usuario['id_usuario']]);
            $_SESSION['permisos'] = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN);
        }
        
        redirigirSegunRol($usuario['rol_nombre']);
        exit;
    } else {
        header('Location: /evospace/index.php?error=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EvoSpace - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/evospace/img/evolucionarte-removebg-preview.ico">
    <link rel="stylesheet" href="/evospace/assets/css/estilos.css">
    <style>body{padding-top:0!important}</style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <img src="/evospace/img/evolucionarte-removebg-preview.ico" alt="Logo" class="login-logo">
                <h2 class="login-title">EvoSpace</h2>
                <p class="login-subtitle">Sistema de Gestión</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Usuario o contraseña incorrectos.</span>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Usuario, Email o Cédula</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person-fill text-muted"></i></span>
                            <input type="text" name="eleccion" class="form-control" placeholder="Ingresá tu usuario" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-muted"></i></span>
                            <input type="password" name="contrasena" id="contrasena" class="form-control" placeholder="Ingresá tu contraseña" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary" id="btnVerContrasena" tabindex="-1" title="Mostrar / ocultar contraseña">
                                <i class="bi bi-eye-fill" id="iconoVerContrasena"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-evo w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('btnVerContrasena').addEventListener('click', function() {
            const input = document.getElementById('contrasena');
            const mostrar = input.type === 'password';
            input.type = mostrar ? 'text' : 'password';
            document.getElementById('iconoVerContrasena').className = mostrar ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill';
        });
    </script>
</body>
</html>