<?php
// Siempre al inicio del archivo
//session_start();
//include 'db.php'; // Asegura que $conn esté disponible

function obtenerInsumosBajoStock($umbral = 5) {
    global $conn;
    
    // Verificar que la conexión existe y es MySQLi
    if (!($conn instanceof mysqli)) {
        throw new Exception("Conexión a la base de datos no válida");
    }
    
    $query = "SELECT id, codigo, insumo, stock, ubicacion 
              FROM componentes 
              WHERE stock <= ?";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $umbral);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
function obtenerDatosAgrupadosPorMes($conn, $filtros = []) {
    $filtro = [];
    $params = [];
    $tipos = '';

    if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
        $filtro[] = "fecha_sol BETWEEN ? AND ?";
        $params[] = $filtros['fecha_inicio'];
        $params[] = $filtros['fecha_fin'];
        $tipos .= 'ss';
    }

    $where = count($filtro) > 0 ? 'WHERE ' . implode(' AND ', $filtro) : '';

    $stmt = $conn->prepare("SELECT fecha_sol, insumos FROM cirugias $where");
    if (!empty($params)) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Cargar mapa de especialidad desde componentes
    $mapa_especialidad = [];
    $res = $conn->query("SELECT insumo, especialidad FROM componentes");
    while ($fila = $res->fetch_assoc()) {
        $nombre = trim($fila['insumo']);
        $esp = $fila['especialidad'] ?? 'Sin asignar';
        $mapa_especialidad[$nombre] = $esp;
    }

    $insumos_agregados = [];

    while ($row = $result->fetch_assoc()) {
        $fecha = $row['fecha_sol'];
        $mes = date('Y-m', strtotime($fecha));
        $insumos = explode(',', $row['insumos']);

        foreach ($insumos as $item) {
            $item = trim($item);

            if (preg_match('/(.*?)\s*\(x?(\d+)\)/i', $item, $matches)) {
                $nombre = trim($matches[1]);
                $cantidad = (int)$matches[2];
            } else {
                $nombre = $item;
                $cantidad = 1;
            }

            // Aplicar filtro por especialidad (desde el mapa)
            $especialidad = $mapa_especialidad[$nombre] ?? 'Sin asignar';
            if (!empty($filtros['especialidad']) && $especialidad !== $filtros['especialidad']) {
                continue; // saltar si no coincide
            }

            $key = $mes . '|' . $nombre;
            if (!isset($insumos_agregados[$key])) {
                $insumos_agregados[$key] = [
                    'mes' => $mes,
                    'insumo' => $nombre,
                    'cantidad' => 0
                ];
            }

            $insumos_agregados[$key]['cantidad'] += $cantidad;
        }
    }

    return array_values($insumos_agregados);
}

function obtenerDatosParaHeatmap($conn, $filtros = []) {
    $filtro = [];
    $params = [];
    $tipos = '';

    if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
        $filtro[] = "fecha_sol BETWEEN ? AND ?";
        $params[] = $filtros['fecha_inicio'];
        $params[] = $filtros['fecha_fin'];
        $tipos .= 'ss';
    }

    $where = count($filtro) > 0 ? 'WHERE ' . implode(' AND ', $filtro) : '';
    $stmt = $conn->prepare("SELECT fecha_sol, insumos FROM cirugias $where");

    if (!empty($params)) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Cargar mapa de especialidad desde componentes
    $mapa_especialidad = [];
    $res = $conn->query("SELECT insumo, especialidad FROM componentes");
    while ($fila = $res->fetch_assoc()) {
        $nombre = trim($fila['insumo']);
        $esp = $fila['especialidad'] ?? 'Sin asignar';
        $mapa_especialidad[$nombre] = $esp;
    }

    $raw = [];

    while ($row = $result->fetch_assoc()) {
        $mes = date('Y-m', strtotime($row['fecha_sol']));
        $insumos = explode(',', $row['insumos']);

        foreach ($insumos as $item) {
            $item = trim($item);

            if (preg_match('/(.*?)\s*\(x?(\d+)\)/i', $item, $matches)) {
                $nombre = trim($matches[1]);
                $cantidad = (int)$matches[2];
            } else {
                $nombre = $item;
                $cantidad = 1;
            }

            // Aplicar filtro por especialidad (desde el mapa)
            $especialidad = $mapa_especialidad[$nombre] ?? 'Sin asignar';
            if (!empty($filtros['especialidad']) && $especialidad !== $filtros['especialidad']) {
                continue;
            }

            $raw[] = [
                'mes' => $mes,
                'insumo' => $nombre,
                'cantidad' => $cantidad
            ];
        }
    }

    return $raw;
}


function obtenerTopInsumosPorEspecialidad($conn, $filtros = []) {
    $where = [];
    $params = [];
    $tipos = '';

    if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
        $where[] = "fecha_sol BETWEEN ? AND ?";
        $params[] = $filtros['fecha_inicio'];
        $params[] = $filtros['fecha_fin'];
        $tipos .= 'ss';
    }

    $where_sql = '';
    if (!empty($where)) {
        $where_sql = "WHERE " . implode(" AND ", $where);
    }

    $stmt = $conn->prepare("SELECT fecha_sol, insumos FROM cirugias $where_sql");
    if (!empty($params)) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $componentes = $conn->query("SELECT insumo, especialidad FROM componentes")->fetch_all(MYSQLI_ASSOC);

    $mapa_especialidad = [];
    foreach ($componentes as $c) {
        $insumo = trim($c['insumo'] ?? '');
        $especialidad = $c['especialidad'] ?? 'Sin asignar';
        $mapa_especialidad[$insumo] = $especialidad;
    }

    $conteo = [];

    while ($row = $result->fetch_assoc()) {
        $insumos = explode(',', $row['insumos']);
        foreach ($insumos as $item) {
            $item = trim($item);

            if (preg_match('/(.*?)\s*\(x?(\d+)\)/i', $item, $matches)) {
                $nombre = trim($matches[1]);
                $cantidad = (int)$matches[2];
            } else {
                $nombre = $item;
                $cantidad = 1;
            }

            $especialidad = $mapa_especialidad[$nombre] ?? 'Sin asignar';

            // ❗ Aplica el filtro por especialidad si está definido
            if (!empty($filtros['especialidad']) && $filtros['especialidad'] !== $especialidad) {
                continue;
            }

            $clave = $especialidad . '|' . $nombre;
            if (!isset($conteo[$clave])) {
                $conteo[$clave] = ['especialidad' => $especialidad, 'insumo' => $nombre, 'cantidad' => 0];
            }

            $conteo[$clave]['cantidad'] += $cantidad;
        }
    }

    // Tomar el insumo más usado por cada especialidad
    $top = [];
    foreach ($conteo as $registro) {
        $esp = $registro['especialidad'];
        if (!isset($top[$esp]) || $registro['cantidad'] > $top[$esp]['cantidad']) {
            $top[$esp] = $registro;
        }
    }

    return array_values($top);
}
// Reutiliza el código de obtenerTopInsumosPorEspecialidad para listar todos los insumos por especialidad

