<?php
session_start();
include 'db.php';

$sql = "SELECT * FROM cirugias WHERE estado = 'en devolucion'";
$resultado = $conn->query($sql);
$cirugias = $resultado->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $accion = $_POST['accion'];

    if ($accion === 'aceptar') {
        $stmt = $conn->prepare("SELECT insumos_devueltos FROM cirugias WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();

        if (!empty($data['insumos_devueltos'])) {
            $insumos_array = explode(',', $data['insumos_devueltos']);
            foreach ($insumos_array as $insumo_cantidad) {
                if (preg_match('/^(.*?)\s*\(x(\d+)\)$/i', trim($insumo_cantidad), $matches)) {
                    $nombre_insumo = trim($matches[1]);
                    $cantidad = (int)$matches[2];

                    $stmt_update = $conn->prepare("UPDATE componentes SET stock = stock + ? WHERE LOWER(insumo) = LOWER(?)");
                    $stmt_update->bind_param("is", $cantidad, $nombre_insumo);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            }
        }

        $stmt = $conn->prepare("UPDATE cirugias SET estado = 'terminada' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $fecha = date('Y-m-d H:i:s');
        $stmt_hist = $conn->prepare("INSERT INTO historial (id_solicitud, estado, fecha) VALUES (?, 'devuelta', ?)");
        $stmt_hist->bind_param("is", $id, $fecha);
        $stmt_hist->execute();

    } elseif ($accion === 'rechazar') {
        $stmt = $conn->prepare("UPDATE cirugias SET estado = 'en devolucion' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $fecha = date('Y-m-d H:i:s');
        $stmt_hist = $conn->prepare("INSERT INTO historial (id_solicitud, estado, fecha) VALUES (?, 'rechazada', ?)");
        $stmt_hist->bind_param("is", $id, $fecha);
        $stmt_hist->execute();
    }

    header("Location: devolucion_insumos.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administracion de insumos del Hospital Clinico Félix Bulnes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Devoluciones de insumos medicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
                <button type="button" class="volver-btn" onclick="window.location.href='peticiones.php'">Volver</button>
            </form>
        </div>
    </div>
</head>
<body>
<div class="container">
    <h2>Solicitudes en Devolución</h2>
    <?php if (!empty($cirugias)): ?>
        <table>
            <tr>
                <th>Código Cirugía</th>
                <th>Cirugía</th>
                <th>Insumos Devueltos</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($cirugias as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['cod_cirugia']) ?></td>
                    <td><?= htmlspecialchars($c['cirugia']) ?></td>
                    <td><?= htmlspecialchars($c['insumos_devueltos']) ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="accion" value="aceptar">
                            <button type="submit" class="aceptar-btn-table">Aceptar</button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <button type="submit" class="rechazar-btn-table">Rechazar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No hay solicitudes en estado de devolución.</p>
    <?php endif; ?>
</div>
<script>
        function toggleAccountInfo() {
        const info = document.getElementById('accountInfo');
        info.style.display = info.style.display === 'none' ? 'block' : 'none';
    }
</script>
</body>
</html>
