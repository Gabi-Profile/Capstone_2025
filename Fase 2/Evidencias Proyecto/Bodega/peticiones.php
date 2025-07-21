<?php
session_start();
include 'db.php';
$nombre_usuario_filtro = isset($_GET['codigo']) ? trim($conn->real_escape_string($_GET['codigo'])) : '';
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 10;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

$sql_base = "FROM cirugias WHERE 1";
if (!empty($nombre_usuario_filtro)) {
    $sql_base .= " AND (
        cod_cirugia LIKE '%$nombre_usuario_filtro%' OR 
        cirugia LIKE '%$nombre_usuario_filtro%' OR
        rut_paciente LIKE '%$nombre_usuario_filtro%'
    )";
}

$sql_total = "SELECT COUNT(*) as total " . $sql_base;
$sql_final = "SELECT * " . $sql_base . " ORDER BY id DESC LIMIT $cantidad_por_pagina OFFSET $offset";

$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

$resultado = mysqli_query($conn, $sql_final);
$personas_dentro = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $sql = "SELECT cod_cirugia, cirugia FROM cirugias 
            WHERE cod_cirugia LIKE '%$query%' OR cirugia LIKE '%$query%' 
            LIMIT 10";
    $result = $conn->query($sql);
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['codigo'] . " - " . $row['insumo'];
    }
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["aceptar"])) {
    $id = $_POST["id"]; 

    if ($stmt = $conn->prepare("UPDATE cirugias SET estado = 'aceptada' WHERE id = ?")) {
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            die("Error actualizando estado cirugía: " . $stmt->error);
        }
    } else {
        die("Error preparando update cirugía: " . $conn->error);
    }

    if ($stmt_detalle = $conn->prepare("SELECT insumos FROM cirugias WHERE id = ?")) {
        $stmt_detalle->bind_param("i", $id);
        $stmt_detalle->execute();
        $result_detalle = $stmt_detalle->get_result();

        if ($fila = $result_detalle->fetch_assoc()) {
            $lista_insumos = $fila['insumos'];
            $insumos_array = explode(',', $lista_insumos);

            foreach ($insumos_array as $insumo_cantidad) {
                if (preg_match('/^(.*?)\s*\(x(\d+)\)$/i', trim($insumo_cantidad), $matches)) {
                    $nombre_insumo = trim($matches[1]);
                    $cantidad = (int)$matches[2];

                    if ($stmt_resta_stock = $conn->prepare("UPDATE componentes SET stock = stock - ? WHERE LOWER(insumo) = LOWER(?)")) {
                        $stmt_resta_stock->bind_param("is", $cantidad, $nombre_insumo);
                        $stmt_resta_stock->execute();
                        $stmt_resta_stock->close();
                    } else {
                        die("Error preparando update stock: " . $conn->error);
                    }
                }
            }
        }
        $stmt_detalle->close();
    } else {
        die("Error preparando select insumos: " . $conn->error);
    }

    $fecha_decision = date('Y-m-d H:i:s');
    if ($stmt_pedicion = $conn->prepare("INSERT INTO historial (id_solicitud, estado, fecha) VALUES (?, 'aceptada', ?)")) {
        $stmt_pedicion->bind_param("is", $id, $fecha_decision);
        $stmt_pedicion->execute();
        $stmt_pedicion->close();
    } else {
        die("Error preparando insert historial: " . $conn->error);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["rechazar"])) {
    $id = $_POST["id"];

    if ($stmt = $conn->prepare("UPDATE cirugias SET estado = 'rechazada' WHERE id = ?")) { 
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $fecha_decision = date('Y-m-d H:i:s'); 
            $stmt_pedicion = $conn->prepare("INSERT INTO historial (id_solicitud, estado, fecha) VALUES (?, 'rechazada', ?)");
            $stmt_pedicion->bind_param("is", $id, $fecha_decision);
            $stmt_pedicion->execute();
            
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            $mensaje = "<div class='msg error'><span class='icon'>&#10060;</span> Error al rechazar la solicitud: " . $stmt->error . "</div>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Historal de cirugias</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
            <button type="button" class="volver-btn" onclick="window.location.href='eleccion.php'">Volver</button>
        </div>
    </div>
</head>
<body>
    <div class="container">
        <div class="filters">
            <form method="GET" action="">
                <label for="codigo">Insumo:</label>
                <div class="input-sugerencias-wrapper">
                    <input type="text" id="codigo" name="codigo" autocomplete="off"
                        placeholder="Filtrar por Paciente, codigo, cirugia..."
                        value="<?php echo htmlspecialchars($nombre_usuario_filtro); ?>">
                    <div id="sugerencias" class="sugerencias-box"></div>
                </div>
                <div class="botones-filtros">
                    <button type="submit">Filtrar</button>
                    <button type="button" class="limpiar-filtros-btn" onclick="window.location='peticiones.php'">Limpiar Filtros</button>
                </div>
            </form>
        </div>
        <?php if (!empty($personas_dentro)): ?>
            <h2>Lista de cirugias</h2>
            <table>
                <tr>
                    <th>Cirugía</th>
                    <th>Pabellón</th>
                    <th>Paciente</th>
                    <th>Insumos</th>
                    <th>Insumos Devueltos</th>
                    <th>Estado</th>
                    <th>Fecha Solicitud</th>
                    <th>Resolución</th>
                </tr>
                <?php foreach ($personas_dentro as $cirugia): ?>
                    <?php 
                        $estado_class = '';
                        switch ($cirugia['estado']) {
                            case 'en proceso':
                                $estado_class = 'estado-en-proceso';
                                break;
                            case 'terminada':
                                $estado_class = 'estado-terminada';
                                break;
                            case 'aceptada':
                                $estado_class = 'estado-aceptada';
                                break;
                            case 'rechazada':
                                $estado_class = 'estado-rechazada';
                                break;
                        }
                        ?>
                <tr>
                    <td><?= htmlspecialchars($cirugia['cirugia']) ?></td>
                    <td><?= htmlspecialchars($cirugia['pabellon']) ?></td>
                    <td><?= htmlspecialchars($cirugia['rut_paciente']) ?></td>
                    <td><?= htmlspecialchars($cirugia['insumos']) ?></td>
                    <td><?= htmlspecialchars($cirugia['insumos_devueltos']) ?></td>
                    <td><?= htmlspecialchars($cirugia['estado']) ?></td>
                    <td><?= htmlspecialchars($cirugia['fecha_sol']); ?></td>
                    <td>
                        <?php if ($cirugia['estado'] == 'en proceso'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $cirugia['id']; ?>">
                                <input type="hidden" name="nro_serie" value="<?php echo $cirugia['id']; ?>">
                                <button type="submit" name="aceptar" class="aceptar-btn-table">Aceptar</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $cirugia['id']; ?>">
                                <input type="hidden" name="nro_serie" value="<?php echo $cirugia['id']; ?>">
                                <button type="submit" name="rechazar" class="rechazar-btn-table">Rechazar</button>
                            </form>
                            <?php elseif ($cirugia['estado'] == 'en devolucion'): ?>
                                <form method="GET" action="devolucion_insumos.php" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $cirugia['id']; ?>">
                                    <button type="submit" class="devolver-btn-table">Devolucion</button>
                                </form>
                            <?php elseif ($cirugia['estado'] == 'aceptada'): ?>
                                <form method="GET" action="imprimir.php" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $cirugia['id']; ?>">
                                    <button type="submit" class="aceptar-btn-table">Imprimir</button>
                                </form>
                        <?php endif; ?> 
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <form method="GET" style="margin-bottom: 10px;">
                <label for="cantidad">Mostrar:</label>
                <select name="cantidad" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 30, 40, 50] as $cantidad): ?>
                        <option value="<?= $cantidad ?>" <?= $cantidad_por_pagina == $cantidad ? 'selected' : '' ?>><?= $cantidad ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="pagina" value="1">
            </form>
            <div class="pagination-container">
                <?php
                $rango_visible = 5;
                $inicio = max(1, $pagina_actual - floor($rango_visible / 2));
                $fin = min($total_paginas, $inicio + $rango_visible - 1);

                if ($inicio > 1) {
                    echo '<a href="?pagina=1&cantidad=' . $cantidad_por_pagina . '">1</a>';
                    if ($inicio > 2) echo '<span>...</span>';
                }

                for ($i = $inicio; $i <= $fin; $i++) {
                    $active = $pagina_actual == $i ? 'active' : '';
                    echo '<a href="?pagina=' . $i . '&cantidad=' . $cantidad_por_pagina . '" class="' . $active . '">' . $i . '</a>';
                }

                if ($fin < $total_paginas) {
                    if ($fin < $total_paginas - 1) echo '<span>...</span>';
                    echo '<a href="?pagina=' . $total_paginas . '&cantidad=' . $cantidad_por_pagina . '">' . $total_paginas . '</a>';
                }

                if ($pagina_actual > 1) {
                    echo '<a href="?pagina=' . ($pagina_actual - 1) . '&cantidad=' . $cantidad_por_pagina . '">Anterior</a>';
                }

                if ($pagina_actual < $total_paginas) {
                    echo '<a href="?pagina=' . ($pagina_actual + 1) . '&cantidad=' . $cantidad_por_pagina . '">Siguiente</a>';
                }
                ?>
            </div>
        <?php else: ?>
            <p>No se encontraron resultados para tu búsqueda.</p>
        <?php endif; ?>
    </div>

    <script>
        div.addEventListener("click", () => {
            input.value = item.split(" - ")[0];
            sugerenciasBox.innerHTML = "";
            sugerenciasBox.style.display = "none";
        });

        document.addEventListener("DOMContentLoaded", function() {
            const input = document.getElementById("codigo");
            const sugerenciasBox = document.getElementById("sugerencias");

            input.addEventListener("input", function() {
                const query = input.value;

                if (query.length < 2) {
                    sugerenciasBox.innerHTML = "";
                    sugerenciasBox.style.display = "none";
                    return;
                }

                fetch(`gestion_cirugias.php?query=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        sugerenciasBox.innerHTML = "";
                        if (data.length === 0) {
                            sugerenciasBox.style.display = "none";
                            return;
                        }

                        data.forEach(item => {
                            const div = document.createElement("div");
                            div.textContent = item;
                            div.addEventListener("click", () => {
                                input.value = item.split(" - ")[0];
                                sugerenciasBox.innerHTML = "";
                                sugerenciasBox.style.display = "none";
                            });
                            sugerenciasBox.appendChild(div);
                        });
                        sugerenciasBox.style.display = "block";
                });
            });

            document.addEventListener("click", function(e) {
                if (!sugerenciasBox.contains(e.target) && e.target !== input) {
                    sugerenciasBox.style.display = "none";
                }
            });
        });

        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
