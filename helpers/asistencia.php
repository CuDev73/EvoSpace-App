<?php
if (!function_exists('cursoPerteneceAProfesor')) {
    /**
     * Verifica que el curso pertenezca al profesor dado (vía horarios).
     */
    function cursoPerteneceAProfesor($pdo, $id_curso, $id_profesor) {
        if (!$id_profesor) return false;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM horarios WHERE id_curso = ? AND id_profesor = ?");
        $stmt->execute([(int)$id_curso, (int)$id_profesor]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('obtenerAlumnosConAsistencia')) {
    /**
     * Alumnos activos del curso + su asistencia de la fecha indicada.
     * Devuelve por cada alumno: presente y observaciones (si existe registro).
     */
    function obtenerAlumnosConAsistencia($pdo, $id_curso, $fecha) {
        $alumnos = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE id_curso = ? AND activo = 1 ORDER BY apellido, nombre");
        $alumnos->execute([(int)$id_curso]);
        $alumnos = $alumnos->fetchAll(PDO::FETCH_ASSOC);

        $asistencias = [];
        if (!empty($alumnos)) {
            $ids = array_column($alumnos, 'id_alumno');
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id_alumno, presente, observaciones FROM asistencia WHERE id_curso = ? AND fecha = ? AND id_alumno IN ($ph)");
            $stmt->execute(array_merge([(int)$id_curso, $fecha], $ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $asistencias[$a['id_alumno']] = $a;
            }
        }

        foreach ($alumnos as &$a) {
            $a['presente'] = isset($asistencias[$a['id_alumno']]) ? (int)$asistencias[$a['id_alumno']]['presente'] : 1;
            $a['observaciones'] = $asistencias[$a['id_alumno']]['observaciones'] ?? '';
        }
        unset($a);

        return [
            'fecha' => $fecha,
            'alumnos' => $alumnos,
            'asistencias' => $asistencias
        ];
    }
}

if (!function_exists('guardarAsistenciaDiaria')) {
    /**
     * UPSERT de asistencia diaria. Preserva observaciones si no se envían.
     * $estados: [id_alumno => ['presente' => 1|0, 'observaciones' => string]]
     */
    function guardarAsistenciaDiaria($pdo, $id_curso, $fecha, $estados, $id_profesor = null) {
        $id_curso = (int)$id_curso;
        if ($id_profesor && !cursoPerteneceAProfesor($pdo, $id_curso, $id_profesor)) {
            throw new Exception('El curso no pertenece al profesor.');
        }

        $alumnos = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE id_curso = ? AND activo = 1");
        $alumnos->execute([$id_curso]);
        $ids = $alumnos->fetchAll(PDO::FETCH_COLUMN);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO asistencia (id_alumno, id_curso, fecha, presente, observaciones)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE presente = VALUES(presente), observaciones = VALUES(observaciones)"
            );
            foreach ($ids as $id_alumno) {
                $id_alumno = (int)$id_alumno;
                $presente = !empty($estados[$id_alumno]['presente']) ? 1 : 0;
                $obs = trim($estados[$id_alumno]['observaciones'] ?? '');
                $stmt->execute([$id_alumno, $id_curso, $fecha, $presente, $obs]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('guardarAsistenciaMensual')) {
    /**
     * UPSERT de asistencia de todo un mes. Preserva observaciones cargadas en la vista diaria.
     * $estados: [id_alumno => [dia => 1|0]]
     */
    function guardarAsistenciaMensual($pdo, $id_curso, $mes, $anio, $estados, $id_profesor = null) {
        $id_curso = (int)$id_curso;
        $mes = (int)$mes;
        $anio = (int)$anio;
        if ($id_profesor && !cursoPerteneceAProfesor($pdo, $id_curso, $id_profesor)) {
            throw new Exception('El curso no pertenece al profesor.');
        }

        $diasTotales = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO asistencia (id_alumno, id_curso, fecha, presente)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE presente = VALUES(presente)"
            );
            foreach ($estados as $id_alumno => $dias) {
                $id_alumno = (int)$id_alumno;
                $registrado = array_fill(1, $diasTotales, 0);
                foreach ($dias as $dia => $presente) {
                    $registrado[(int)$dia] = $presente ? 1 : 0;
                }
                for ($dia = 1; $dia <= $diasTotales; $dia++) {
                    $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
                    $stmt->execute([$id_alumno, $id_curso, $fecha, $registrado[$dia]]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}