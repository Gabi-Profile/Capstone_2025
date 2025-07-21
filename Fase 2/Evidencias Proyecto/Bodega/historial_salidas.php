<?php
session_start();
include 'db.php';

// Consulta con JOIN para obtener insumo y datos de salida
$sql = "SELECT s.fecha_salida, c.insumo, s.cantidad, s.realizado_por
        FROM salidas s
        JOIN componentes c ON s.id_componente = c.id
        ORDER BY s.fecha_salida DESC";

$resultado = $conn->query($sql);
$salidas = $resultado->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Solicitar insumos medicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
            <button type="button" class="volver-btn" onclick="window.location.href='bodega.php'">Volver</button>
        </div>
    </div>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
    <h2>📦 Historial de salidas de insumos</h2>

    <?php if (!empty($salidas)): ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha de salida</th>
                    <th>Insumo</th>
                    <th>Cantidad</th>
                    <th>Realizado por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salidas as $fila): ?>
                    <tr>
                        <td><?= date('d-m-Y H:i', strtotime($fila['fecha_salida'])) ?></td>
                        <td><?= htmlspecialchars($fila['insumo']) ?></td>
                        <td><?= $fila['cantidad'] ?></td>
                        <td><?= htmlspecialchars($fila['realizado_por']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay registros de salidas.</p>
    <?php endif; ?>

    <br>
    </div>
</body>
</html>
<script>
    // Función para mostrar/ocultar el menú de usuario
    function toggleAccountInfo() {
        const info = document.getElementById('accountInfo');
        info.style.display = info.style.display === 'none' ? 'block' : 'none';
    }

    // Cerrar el menú al hacer clic fuera de él
    document.addEventListener('click', function(event) {
        const accountBtn = document.getElementById('cuenta-btn');
        const accountInfo = document.getElementById('accountInfo');
        
        if (!accountBtn.contains(event.target) && !accountInfo.contains(event.target)){
            accountInfo.style.display = 'none';
        }
    });
    function toggleAccountInfo() {
        const info = document.getElementById('accountInfo');
        info.style.display = info.style.display === 'none' ? 'block' : 'none';
    }
</script>