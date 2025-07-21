<!-- devolver_insumos.php -->
<?php
session_start();
include 'db.php';

if (!isset($_GET['id'])) {
    die("ID de cirugía no proporcionado.");
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT insumos FROM cirugias WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Cirugía no encontrada.");
}

$fila = $result->fetch_assoc();
$lista_insumos = $fila['insumos'];
$insumos_array = explode(',', $lista_insumos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Devolución de Insumos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Devolución de insumos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
            <button type="button" class="volver-btn" onclick="window.location.href='peticiones.php'">Volver</button>
        </div>
    </div>
</head>
<body>
<div class="container">
    <h2>Devolver Insumos</h2>
    <form method="POST" action="procesar_devolucion.php">
        <input type="hidden" name="id_cirugia" value="<?= $id ?>">
        <table>
            <tr>
                <th>Insumo</th>
                <th>Cantidad Solicitada</th>
                <th>Cantidad Devuelta</th>
            </tr>
            <?php foreach ($insumos_array as $insumo_cantidad): ?>
                <?php
                if (preg_match('/^(.*?)\s*\(x(\d+)\)$/i', trim($insumo_cantidad), $matches)) {
                    $nombre_insumo = trim($matches[1]);
                    $cantidad_solicitada = (int)$matches[2];
                ?>
                <tr>
                    <td><?= htmlspecialchars($nombre_insumo) ?></td>
                    <td><?= $cantidad_solicitada ?></td>
                    <td>
                        <input type="number" name="devoluciones[<?= htmlspecialchars($nombre_insumo) ?>]" min="0" max="<?= $cantidad_solicitada ?>" value="0" required>
                    </td>
                </tr>
                <?php } ?>
            <?php endforeach; ?>
        </table>
        <button type="submit" class="btn-dashboarda">Confirmar Devolución</button>
        <button type="button" class="btn-dashboard" onclick="window.location='peticiones.php'">Cancelar</button>
    </form>
</div>
<script>
    function toggleAccountInfo() {
        const info = document.getElementById('accountInfo');
        info.style.display = info.style.display === 'none' ? 'block' : 'none';
    }
</script>
</body>
</html>