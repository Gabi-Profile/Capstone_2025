<?php
session_start();
include 'db.php';



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
            <div class="main-title">Insumos Medicos</div>
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
        <div class="selection-container">
            <?php 
                $sectors = [
                    ["name" => "Insumos Urología", "image" => "urologia.jpg", "link" => "sector_urologia.php"],
                    ["name" => "Insumos Digestivo", "image" => "digestivo.jpg", "link" => "sector_digestivo.php"],
                    ["name" => "Insumos Ginecología", "image" => "ginecologia.jpg", "link" => "sector_ginecologia.php"],
                    ["name" => "Insumos Coloproctología", "image" => "coloproctologia.jpg", "link" => "sector_coloproctologia.php"],
                    ["name" => "Insumos Traumatología", "image" => "traumatologia.jpg", "link" => "sector_traumatologia.php"],
                    ["name" => "Insumos Neurología", "image" => "neurologia.jpg", "link" => "sector_neurologia.php"],
                    ["name" => "Insumos Anestesia", "image" => "anestesia.jpg", "link" => "sector_anestesia.php"],
                    ["name" => "Insumos Generales", "image" => "generales.jpg", "link" => "sector_generales.php"],
                    ["name" => "Equipos Médicos", "image" => "equipos_medicos.jpg", "link" => "sector_equipos_medicos.php"],
                    ["name" => "Suturas", "image" => "suturas.jpg", "link" => "sector_suturas.php"]
                ];

                foreach ($sectors as $sector): ?>
                    <div class="selection-box" style="background-image: url('asset/<?php echo $sector['image']; ?>');">
                        <a href="<?php echo $sector['link']; ?>" class="selection-link">
                            <h3><?php echo $sector['name']; ?></h3>
                        </a>
                    </div>
                <?php endforeach; ?>
        </div>
    </div>
    <script>
        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
