<?php
// secciones/funciones.php - Todas las funciones con PDO

// ============================================================
// FUNCIONES DE PROFESORES (usando PDO)
// ============================================================

function obtenerProfesores($pdo) {
    $sql = "SELECT u.*, 
                   p.id_profesor, p.salario_base, p.activo as prof_activo
            FROM usuarios u
            LEFT JOIN profesores p ON u.id_usuario = p.id_usuario
            WHERE u.id_rol = 2
            ORDER BY u.usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function guardarSalarioProfesor($pdo, $id_usuario, $salario_base, $activo) {
    // Verificar si ya existe
    $sql = "SELECT id_profesor FROM profesores WHERE id_usuario = :id_usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_usuario' => $id_usuario]);
    $existe = $stmt->fetchColumn();

    if ($existe) {
        $sql = "UPDATE profesores SET salario_base = :salario_base, activo = :activo WHERE id_usuario = :id_usuario";
    } else {
        $sql = "INSERT INTO profesores (id_usuario, salario_base, activo) VALUES (:id_usuario, :salario_base, :activo)";
    }
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['id_usuario' => $id_usuario, 'salario_base' => $salario_base, 'activo' => $activo]);
}

// ============================================================
// FUNCIONES DE ABONOS (usando PDO)
// ============================================================

function obtenerAbonos($pdo) {
    $sql = "SELECT * FROM abonos ORDER BY id_abono DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerAbonoPorId($pdo, $id_abono) {
    $sql = "SELECT * FROM abonos WHERE id_abono = :id_abono";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_abono' => $id_abono]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function insertarAbono($pdo, $fecha_abono, $profesor, $monto_abono) {
    $sql = "INSERT INTO abonos (fecha_abono, profesor, monto_abono) VALUES (:fecha_abono, :profesor, :monto_abono)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'fecha_abono' => $fecha_abono,
        'profesor' => $profesor,
        'monto_abono' => $monto_abono
    ]);
}

function actualizarAbono($pdo, $id_abono, $fecha_abono, $profesor, $monto_abono) {
    $sql = "UPDATE abonos SET fecha_abono = :fecha_abono, profesor = :profesor, monto_abono = :monto_abono WHERE id_abono = :id_abono";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'id_abono' => $id_abono,
        'fecha_abono' => $fecha_abono,
        'profesor' => $profesor,
        'monto_abono' => $monto_abono
    ]);
}

function eliminarAbono($pdo, $id_abono) {
    $sql = "DELETE FROM abonos WHERE id_abono = :id_abono";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['id_abono' => $id_abono]);
}

// ============================================================
// FUNCIÓN PARA OBTENER LISTA DE PROFESORES PARA DATALIST
// ============================================================

function obtenerListaProfesores($pdo) {
    $sql = "SELECT u.id_usuario, u.usuario, u.nombre_completo 
            FROM usuarios u
            WHERE u.id_rol = 2 AND u.activo = 1
            ORDER BY u.usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lista = [];
    foreach ($resultados as $row) {
        $nombre = !empty($row['nombre_completo']) ? $row['nombre_completo'] : $row['usuario'];
        $lista[] = ['id_usuario' => $row['id_usuario'], 'nombre_completo' => $nombre];
    }
    return $lista;
}

function obtenerAbonosPorProfesor($pdo, $id_usuario, $mes = null, $anio = null) {
    if (!$mes) $mes = date('m');
    if (!$anio) $anio = date('Y');
    $sql = "SELECT * FROM abonos 
            WHERE profesor = (SELECT usuario FROM usuarios WHERE id_usuario = :id_usuario)
            AND MONTH(fecha_abono) = :mes AND YEAR(fecha_abono) = :anio
            ORDER BY fecha_abono DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_usuario' => $id_usuario, 'mes' => $mes, 'anio' => $anio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function totalAbonosProfesorMes($pdo, $id_usuario, $mes = null, $anio = null) {
    $abonos = obtenerAbonosPorProfesor($pdo, $id_usuario, $mes, $anio);
    return array_sum(array_column($abonos, 'monto_abono'));
}

function salarioPendienteProfesor($pdo, $id_usuario, $mes = null, $anio = null) {
    // Obtener salario base del profesor
    $stmt = $pdo->prepare("SELECT salario_base FROM profesores WHERE id_usuario = :id_usuario");
    $stmt->execute(['id_usuario' => $id_usuario]);
    $salarioBase = (float)$stmt->fetchColumn() ?: 0;
    $totalAbonos = totalAbonosProfesorMes($pdo, $id_usuario, $mes, $anio);
    return max(0, $salarioBase - $totalAbonos);
}

function obtenerProfesoresConAbonos($pdo, $mes = null, $anio = null) {
    if (!$mes) $mes = date('m');
    if (!$anio) $anio = date('Y');
    
    $profesores = obtenerProfesores($pdo);
    foreach ($profesores as &$p) {
        $p['abonos_mes'] = totalAbonosProfesorMes($pdo, $p['id_usuario'], $mes, $anio);
        $p['salario_pendiente'] = max(0, ($p['salario_base'] ?? 0) - $p['abonos_mes']);
    }
    return $profesores;
}
?>