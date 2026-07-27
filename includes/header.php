<?php
require_once __DIR__ . '/../helpers/functions.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evolucionarte</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="../img/evolucionarte-removebg-preview.ico">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
        }

        body {
            background-color: #d8d5c9;
            padding-top: 60px;
            padding-bottom: 30px;
        }

        /* Botones grandes (como en admin.php) */
        .btn-evo {
            background-color: #c81015;
            color: white;
            height: 80px;
            border: none;
            border-radius: 15px;
            width: 100%;
            font-size: 1.2rem;
        }

        .btn-evo:hover {
            background-color: #a30d11;
            color: white;
        }

        .evento-header {
            background-color: #c81015;
            color: white;
        }

        .bi {
            display: inline-block;
            font-size: 1.2rem;
            vertical-align: -0.125em;
        }

        /* ==========================================================
           ESTILO GLOBAL PARA TODOS LOS BOTONES (misma altura)
           ========================================================== */
        .btn {
            padding: 0.5rem 1.5rem;
            font-size: 0.9rem;
            border-radius: 0.375rem;
            height: 42px;                     /* <-- altura fija */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        /* Para botones que necesitan ocupar todo el ancho disponible (flex-fill) */
        .btn-flex {
            flex: 1;
            height: 42px;
        }

        /* Ajuste para botones dentro de formularios con gap */
        .d-flex.gap-2 .btn {
            flex: 1;
        }

        /* Para que el botón "Volver" no se estire de más */
        .btn-volver {
            height: 42px;
        }

        /* ==========================================================
           BOTÓN "VOLVER" GLOBAL (se muestra con $mostrarVolver = true)
           ========================================================== */
        .volver-container {
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <!-- El navbar se incluye por separado, pero aquí podemos agregar el botón volver -->
    <?php if (isset($mostrarVolver) && $mostrarVolver === true): ?>
    <div class="container mt-3 volver-container">
        <a href="/evospace/secciones/cantina.php" class="btn btn-secondary btn-volver">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    <?php endif; ?>