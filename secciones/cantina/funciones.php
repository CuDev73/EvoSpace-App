<?php
// ============================================================
// secciones/cantina/funciones.php - Todas las funciones con PDO
// ============================================================

// ============================================================
// PRODUCTOS
// ============================================================

function obtenerProductosCompletos($pdo, $activo = null) {
    $sql = "SELECT p.*, pr.nombre AS proveedor_nombre 
            FROM productos p
            LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor";
    if ($activo !== null) {
        $sql .= " WHERE p.activo = " . ($activo ? 1 : 0);
    }
    $sql .= " ORDER BY p.nombre";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function guardarProducto($pdo, $id, $nombre, $precio_venta, $precio_compra, $cantidad, $id_proveedor = null, $activo = 1) {
    if ($id) {
        $sql = "UPDATE productos SET nombre=?, precio=?, precio_compra=?, cantidad=?, id_proveedor=?, activo=? WHERE id_producto=?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$nombre, $precio_venta, $precio_compra, $cantidad, $id_proveedor, $activo, $id]);
    } else {
        $sql = "INSERT INTO productos (nombre, precio, precio_compra, cantidad, id_proveedor, activo) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$nombre, $precio_venta, $precio_compra, $cantidad, $id_proveedor, $activo]);
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
// COMPRAS DE ALUMNOS (Fiado)
// ============================================================

function obtenerComprasAlumnos($pdo, $rama = null) {
    $sql = "
        SELECT c.*, a.nombre, a.apellido, cu.nombre AS curso_nombre, cu.tipo AS rama
        FROM compras_alumnos c
        JOIN alumnos a ON c.id_alumno = a.id_alumno
        JOIN cursos cu ON a.id_curso = cu.id_curso
    ";
    $params = [];
    if ($rama) {
        $sql .= " WHERE cu.tipo = ?";
        $params[] = $rama;
    }
    $sql .= " ORDER BY c.fecha DESC, c.id_compra DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function obtenerCompraAlumno($pdo, $id_compra) {
    $sql = "
        SELECT c.*, a.nombre, a.apellido, cu.tipo AS rama
        FROM compras_alumnos c
        JOIN alumnos a ON c.id_alumno = a.id_alumno
        JOIN cursos cu ON a.id_curso = cu.id_curso
        WHERE c.id_compra = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_compra]);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

function insertarCompraAlumno($pdo, $fecha, $id_alumno, $producto, $monto, $pagado = 0) {
    $sql = "INSERT INTO compras_alumnos (fecha, id_alumno, producto, monto, pagado) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$fecha, $id_alumno, $producto, $monto, $pagado]);
}

function actualizarCompraAlumno($pdo, $id_compra, $fecha, $id_alumno, $producto, $monto, $pagado = 0) {
    $sql = "UPDATE compras_alumnos SET fecha = ?, id_alumno = ?, producto = ?, monto = ?, pagado = ? WHERE id_compra = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$fecha, $id_alumno, $producto, $monto, $pagado, $id_compra]);
}

function eliminarCompraAlumno($pdo, $id_compra) {
    $sql = "DELETE FROM compras_alumnos WHERE id_compra = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$id_compra]);
}

function obtenerDeudaAlumnoCantina($pdo, $id_alumno) {
    $sql = "SELECT COALESCE(SUM(monto), 0) FROM compras_alumnos WHERE id_alumno = ? AND pagado = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_alumno]);
    return (float) $stmt->fetchColumn();
}

function obtenerDeudaTotalCantina($pdo) {
    $sql = "SELECT COALESCE(SUM(monto), 0) FROM compras_alumnos WHERE pagado = 0";
    $stmt = $pdo->query($sql);
    return (float) $stmt->fetchColumn();
}

function obtenerPagosAlumnoCantina($pdo, $id_alumno) {
    $sql = "SELECT * FROM pagos_alumnos_cantina WHERE id_alumno = ? ORDER BY fecha DESC, id_pago DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_alumno]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function insertarPagoAlumnoCantina($pdo, $fecha, $id_alumno, $monto) {
    $sql = "INSERT INTO pagos_alumnos_cantina (fecha, id_alumno, monto) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$fecha, $id_alumno, $monto]);
}

// ============================================================
// PROVEEDORES Y PAGOS (sin campo celular)
// ============================================================

function obtenerProveedores($pdo) {
    $sql = "SELECT * FROM proveedores ORDER BY nombre ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function obtenerProveedor($pdo, $id_proveedor) {
    $sql = "SELECT * FROM proveedores WHERE id_proveedor = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_proveedor]);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

function insertarProveedor($pdo, $nombre, $nombre_contacto = null, $telefono = null, $whatsapp = null, $email = null, $direccion = null, $tipo_productos = null) {
    $sql = "INSERT INTO proveedores (nombre, nombre_contacto, telefono, whatsapp, email, direccion, tipo_productos) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$nombre, $nombre_contacto, $telefono, $whatsapp, $email, $direccion, $tipo_productos]);
}

function actualizarProveedor($pdo, $id_proveedor, $nombre, $nombre_contacto = null, $telefono = null, $whatsapp = null, $email = null, $direccion = null, $tipo_productos = null) {
    $sql = "UPDATE proveedores SET nombre=?, nombre_contacto=?, telefono=?, whatsapp=?, email=?, direccion=?, tipo_productos=? WHERE id_proveedor=?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$nombre, $nombre_contacto, $telefono, $whatsapp, $email, $direccion, $tipo_productos, $id_proveedor]);
}

function eliminarProveedor($pdo, $id_proveedor) {
    $sql = "DELETE FROM proveedores WHERE id_proveedor = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$id_proveedor]);
}

function obtenerPagosProveedores($pdo) {
    $sql = "
        SELECT pp.*, pr.nombre AS proveedor_nombre
        FROM pagos_proveedores pp
        JOIN proveedores pr ON pp.id_proveedor = pr.id_proveedor
        ORDER BY pp.fecha DESC, pp.id_pago DESC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function insertarPagoProveedor($pdo, $id_proveedor, $fecha, $monto, $concepto = null) {
    $sql = "INSERT INTO pagos_proveedores (id_proveedor, fecha, monto, concepto) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$id_proveedor, $fecha, $monto, $concepto]);
}

function eliminarPagoProveedor($pdo, $id_pago) {
    $sql = "DELETE FROM pagos_proveedores WHERE id_pago = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$id_pago]);
}

// ============================================================
// COMPRAS A PROVEEDORES
// ============================================================

function obtenerComprasProveedores($pdo) {
    $sql = "
        SELECT cp.*, pr.nombre AS proveedor_nombre
        FROM compras_proveedores cp
        JOIN proveedores pr ON cp.id_proveedor = pr.id_proveedor
        ORDER BY cp.fecha DESC, cp.id_compra DESC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function obtenerCompraProveedor($pdo, $id_compra) {
    $sql = "
        SELECT cp.*, pr.nombre AS proveedor_nombre
        FROM compras_proveedores cp
        JOIN proveedores pr ON cp.id_proveedor = pr.id_proveedor
        WHERE cp.id_compra = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_compra]);
    return $stmt->fetch(PDO::FETCH_OBJ);
}

function obtenerDetalleCompraProveedor($pdo, $id_compra) {
    $sql = "
        SELECT dc.*, p.nombre AS producto_nombre
        FROM detalle_compra_proveedor dc
        JOIN productos p ON dc.id_producto = p.id_producto
        WHERE dc.id_compra = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_compra]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function registrarCompraProveedor($pdo, $id_proveedor, $fecha, $productos, $observaciones = '') {
    try {
        $pdo->beginTransaction();
        
        $total = 0;
        foreach ($productos as $prod) {
            $total += $prod['subtotal'];
        }
        
        $sql = "INSERT INTO compras_proveedores (id_proveedor, fecha, total, observaciones) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_proveedor, $fecha, $total, $observaciones]);
        $id_compra = $pdo->lastInsertId();
        
        $sqlDetalle = "INSERT INTO detalle_compra_proveedor (id_compra, id_producto, cantidad, precio_compra, subtotal) VALUES (?, ?, ?, ?, ?)";
        $stmtDetalle = $pdo->prepare($sqlDetalle);
        $sqlStock = "UPDATE productos SET cantidad = cantidad + ? WHERE id_producto = ?";
        $stmtStock = $pdo->prepare($sqlStock);
        foreach ($productos as $prod) {
            $stmtDetalle->execute([$id_compra, $prod['id_producto'], $prod['cantidad'], $prod['precio_compra'], $prod['subtotal']]);
            $stmtStock->execute([$prod['cantidad'], $prod['id_producto']]);
        }
        
        $pdo->commit();
        return $id_compra;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function eliminarCompraProveedor($pdo, $id_compra) {
    try {
        $pdo->beginTransaction();
        $detalles = obtenerDetalleCompraProveedor($pdo, $id_compra);
        foreach ($detalles as $d) {
            $sql = "UPDATE productos SET cantidad = cantidad - ? WHERE id_producto = ? AND cantidad >= ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$d->cantidad, $d->id_producto, $d->cantidad]);
        }
        $sql = "DELETE FROM compras_proveedores WHERE id_compra = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_compra]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
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