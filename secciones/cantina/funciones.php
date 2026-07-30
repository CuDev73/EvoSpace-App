<?php
// ============================================================
// secciones/cantina/funciones.php - Todas las funciones con PDO
// ============================================================

// ============================================================
// PRODUCTOS
// ============================================================

function obtenerProductosCompletos($pdo, $activo = null) {
    $sql = "SELECT p.* FROM productos p";
    if ($activo !== null) {
        $sql .= " WHERE p.activo = " . ($activo ? 1 : 0);
    }
    $sql .= " ORDER BY p.nombre";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function guardarProducto($pdo, $id, $nombre, $precio_venta, $precio_compra, $cantidad, $activo = 1) {
    if ($id) {
        $sql = "UPDATE productos SET nombre=?, precio=?, precio_compra=?, cantidad=?, activo=? WHERE id_producto=?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$nombre, $precio_venta, $precio_compra, $cantidad, $activo, $id]);
    } else {
        $sql = "INSERT INTO productos (nombre, precio, precio_compra, cantidad, activo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$nombre, $precio_venta, $precio_compra, $cantidad, $activo]);
    }
}

function eliminarProducto($pdo, $id) {
    $sql = "DELETE FROM productos WHERE id_producto = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$id]);
}

function actualizarStock($pdo, $id_producto, $cantidad) {
    $sql = "UPDATE productos SET cantidad = cantidad - ? WHERE id_producto = ? AND cantidad >= ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$cantidad, $id_producto, $cantidad]);
}

// ============================================================
// VENTAS
// ============================================================

function registrarVenta($pdo, $fecha, $productos, $total, $metodo_pago, $tipo_comprador, $nombre_comprador, $id_alumno = null, $id_usuario = null, $observaciones = '', $estado_pago = 'pagado') {
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO ventas (fecha, total, metodo_pago, tipo_comprador, nombre_comprador, id_alumno, id_usuario, observaciones, estado_pago)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fecha, $total, $metodo_pago, $tipo_comprador, $nombre_comprador, $id_alumno, $id_usuario, $observaciones, $estado_pago]);
        $id_venta = $pdo->lastInsertId();

        $sqlDetalle = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
        $stmtDetalle = $pdo->prepare($sqlDetalle);
        foreach ($productos as $prod) {
            $stmtDetalle->execute([$id_venta, $prod['id_producto'], $prod['cantidad'], $prod['precio_unitario'], $prod['subtotal']]);
            actualizarStock($pdo, $prod['id_producto'], $prod['cantidad']);
        }

        if ($estado_pago == 'pendiente' && $id_alumno) {
            notificarDeudaAlumno($pdo, $id_alumno, $id_venta, $total);
        }

        $pdo->commit();
        return $id_venta;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function obtenerVentas($pdo, $filtros = []) {
    $sql = "SELECT v.*, 
                   a.nombre AS alumno_nombre, a.apellido AS alumno_apellido,
                   u.usuario AS usuario_nombre,
                   (SELECT COUNT(*) FROM detalle_ventas WHERE id_venta = v.id_venta) AS total_items
            FROM ventas v
            LEFT JOIN alumnos a ON v.id_alumno = a.id_alumno
            LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
            WHERE 1=1";
    $params = [];
    if (isset($filtros['fecha_inicio'])) {
        $sql .= " AND v.fecha >= ?";
        $params[] = $filtros['fecha_inicio'];
    }
    if (isset($filtros['fecha_fin'])) {
        $sql .= " AND v.fecha <= ?";
        $params[] = $filtros['fecha_fin'];
    }
    if (isset($filtros['tipo_comprador'])) {
        $sql .= " AND v.tipo_comprador = ?";
        $params[] = $filtros['tipo_comprador'];
    }
    if (isset($filtros['estado_pago'])) {
        $sql .= " AND v.estado_pago = ?";
        $params[] = $filtros['estado_pago'];
    }
    if (isset($filtros['nombre_comprador'])) {
        $sql .= " AND v.nombre_comprador LIKE ?";
        $params[] = '%' . $filtros['nombre_comprador'] . '%';
    }
    $sql .= " ORDER BY v.fecha DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function obtenerVenta($pdo, $id_venta) {
    $sql = "SELECT v.*, 
                   a.nombre AS alumno_nombre, a.apellido AS alumno_apellido,
                   u.usuario AS usuario_nombre
            FROM ventas v
            LEFT JOIN alumnos a ON v.id_alumno = a.id_alumno
            LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
            WHERE v.id_venta = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_venta]);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

function obtenerDetalleVenta($pdo, $id_venta) {
    $sql = "SELECT dv.*, p.nombre AS producto_nombre
            FROM detalle_ventas dv
            JOIN productos p ON dv.id_producto = p.id_producto
            WHERE dv.id_venta = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_venta]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function eliminarVenta($pdo, $id_venta) {
    try {
        $pdo->beginTransaction();
        $detalles = obtenerDetalleVenta($pdo, $id_venta);
        foreach ($detalles as $d) {
            $sql = "UPDATE productos SET cantidad = cantidad + ? WHERE id_producto = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$d->cantidad, $d->id_producto]);
        }
        $sql = "DELETE FROM ventas WHERE id_venta = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_venta]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ============================================================
// NOTIFICACIONES DE DEUDA
// ============================================================

function notificarDeudaAlumno($pdo, $id_alumno, $id_venta, $monto) {
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.email 
        FROM alumnos a
        JOIN usuarios u ON a.id_padre = u.id_usuario
        WHERE a.id_alumno = ? AND u.activo = 1
    ");
    $stmt->execute([$id_alumno]);
    $padres = $stmt->fetchAll(PDO::FETCH_OBJ);
    if (empty($padres)) return;

    $titulo = "Nueva deuda en cantina";
    $mensaje = "El alumno ha registrado una deuda de Gs " . number_format($monto, 0, ',', '.') . " en la cantina. Por favor, revisa el panel de pagos.";

    foreach ($padres as $padre) {
        $sql = "INSERT INTO notificaciones (id_usuario, titulo, mensaje, tipo) VALUES (?, ?, ?, 'pago')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$padre->id_usuario, $titulo, $mensaje]);
        enviarCorreo($padre->email, $titulo, $mensaje);
    }
}

// ============================================================
// GANANCIAS Y ESTADÍSTICAS
// ============================================================

function obtenerGanancias($pdo, $fecha_inicio = null, $fecha_fin = null) {
    $sql = "SELECT 
                SUM(v.total) AS total_ventas,
                SUM(dv.cantidad * dv.precio_unitario) AS total_ingresos,
                SUM(dv.cantidad * p.precio_compra) AS total_costos,
                (SUM(dv.cantidad * dv.precio_unitario) - SUM(dv.cantidad * p.precio_compra)) AS ganancia_total
            FROM ventas v
            JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
            JOIN productos p ON dv.id_producto = p.id_producto
            WHERE v.estado_pago = 'pagado'";
    $params = [];
    if ($fecha_inicio) {
        $sql .= " AND v.fecha >= ?";
        $params[] = $fecha_inicio;
    }
    if ($fecha_fin) {
        $sql .= " AND v.fecha <= ?";
        $params[] = $fecha_fin;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

function obtenerGananciasPorProducto($pdo, $fecha_inicio = null, $fecha_fin = null) {
    $sql = "SELECT 
                p.id_producto,
                p.nombre AS producto,
                SUM(dv.cantidad) AS total_vendido,
                SUM(dv.cantidad * dv.precio_unitario) AS ingreso,
                SUM(dv.cantidad * p.precio_compra) AS costo,
                (SUM(dv.cantidad * dv.precio_unitario) - SUM(dv.cantidad * p.precio_compra)) AS ganancia
            FROM ventas v
            JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
            JOIN productos p ON dv.id_producto = p.id_producto
            WHERE v.estado_pago = 'pagado'";
    $params = [];
    if ($fecha_inicio) {
        $sql .= " AND v.fecha >= ?";
        $params[] = $fecha_inicio;
    }
    if ($fecha_fin) {
        $sql .= " AND v.fecha <= ?";
        $params[] = $fecha_fin;
    }
    $sql .= " GROUP BY p.id_producto ORDER BY ganancia DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

// ============================================================
// BUSCADOR DE COMPRADORES (AJAX)
// ============================================================

function buscarCompradores($pdo, $termino, $tipo = null) {
    $sql = "SELECT id, nombre, tipo FROM (
        SELECT id_usuario AS id, usuario AS nombre, 'profesor' AS tipo FROM usuarios WHERE id_rol = 2 AND usuario LIKE ?
        UNION
        SELECT id_usuario AS id, usuario AS nombre, 'padre' AS tipo FROM usuarios WHERE id_rol = 3 AND usuario LIKE ?
        UNION
        SELECT id_alumno AS id, CONCAT(nombre, ' ', apellido) AS nombre, 'alumno' AS tipo FROM alumnos WHERE activo = 1 AND (nombre LIKE ? OR apellido LIKE ?)
    ) AS compradores";
    $params = ["%$termino%", "%$termino%", "%$termino%", "%$termino%"];
    if ($tipo) {
        $sql .= " WHERE tipo = ?";
        $params[] = $tipo;
    }
    $sql .= " ORDER BY nombre LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

// ============================================================
// DEUDA CANTINA (desde ventas)
// ============================================================

function obtenerDeudaAlumnoCantina($pdo, $id_alumno) {
    $sql = "SELECT COALESCE(SUM(total), 0) FROM ventas WHERE id_alumno = ? AND estado_pago IN ('pendiente','parcial')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_alumno]);
    return (float) $stmt->fetchColumn();
}

function obtenerDeudaTotalCantina($pdo) {
    $sql = "SELECT COALESCE(SUM(total), 0) FROM ventas WHERE estado_pago IN ('pendiente','parcial')";
    $stmt = $pdo->query($sql);
    return (float) $stmt->fetchColumn();
}

// ============================================================
// FUNCIÓN AUXILIAR PARA FORMATO DE WHATSAPP
// ============================================================

function formatearWhatsApp($numero) {
    // Elimina todo excepto dígitos
    $numero = preg_replace('/[^0-9]/', '', $numero);
    // Si el número empieza con 0, lo reemplaza por 595 (código de Paraguay)
    if (preg_match('/^0/', $numero)) {
        $numero = '595' . substr($numero, 1);
    }
    // Si el número empieza con 9 y tiene 9 dígitos (celular sin código de país)
    if (preg_match('/^9/', $numero) && strlen($numero) == 9) {
        $numero = '595' . $numero;
    }
    // Si el número ya tiene código de país (ej. 5959...), lo deja igual
    return $numero;
}
?>