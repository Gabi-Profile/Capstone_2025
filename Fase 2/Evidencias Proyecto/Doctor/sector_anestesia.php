<?php
session_start();
include 'db.php';

// Configuración de paginación
$cantidad_por_pagina = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 20;
$cantidad_por_pagina = in_array($cantidad_por_pagina, [10, 20, 30, 40, 50]) ? $cantidad_por_pagina : 20;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $cantidad_por_pagina;

// Consulta para contar todos los insumos de 'sutura'
$sql_total = "SELECT COUNT(*) as total FROM componentes WHERE especialidad = 'anestesia'";
$total_resultado = mysqli_query($conn, $sql_total);
$total_filas = mysqli_fetch_assoc($total_resultado)['total'];
$total_paginas = ceil($total_filas / $cantidad_por_pagina);

// Consulta con paginación para mostrar todos los insumos
$sql_final = "SELECT * FROM componentes WHERE especialidad = 'anestesia' ORDER BY fecha_ingreso DESC LIMIT $cantidad_por_pagina OFFSET $offset";
$resultado = mysqli_query($conn, $sql_final);
$insumos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos de Disgestivo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Solicitar insumos médicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <button id="cuenta-btn" onclick="toggleAccountInfo()"><?php echo $_SESSION['nombre']; ?></button>
        <div id="accountInfo" style="display: none;">
            <p><strong>Usuario: </strong><?php echo $_SESSION['nombre']; ?></p>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Salir</button>
            </form>
            <button type="button" class="volver-btn" onclick="window.location.href='principal.php'">Volver</button>
        </div>
    </div>
    <style>
        .container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .insumo-card {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .insumo-card:hover {
            transform: scale(1.05);
        }

        .insumo-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .insumo-card h3 {
            margin: 10px 0;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (count($insumos) > 0): ?>
            <?php foreach ($insumos as $componente): ?>
                <div class="insumo-card">
                    <h3><?= htmlspecialchars($componente['insumo']) ?></h3>
                    <button class="add-to-cart" data-insumo="<?= htmlspecialchars($componente['insumo']) ?>">Agregar al Carrito</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No se encontraron insumos.</p>
        <?php endif; ?>
        <div class="pagination-container">
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <a href="?pagina=<?= $i ?>&cantidad=<?= $cantidad_por_pagina ?>" class="<?= $pagina_actual == $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>

        <div id="carrito">
            <h2>Carrito de Compras</h2>
            <ul id="carrito-items"></ul>
            <button onclick="window.location.href='carrito_insumos.php'">Finalizar Compra</button>
        </div>
        </div>
    <script>
        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Delegación de eventos para agregar insumo al carrito
            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('add-to-cart')) {
                    const insumo = event.target.dataset.insumo;
                    manejarCarrito('add', insumo);
                }
                
                if (event.target.classList.contains('remove-from-cart')) {
                    const insumo = event.target.dataset.insumo;
                    manejarCarrito('remove', insumo);
                }
            });

            // Función para manejar el carrito (agregar o quitar)
            function manejarCarrito(action, insumo) {
                fetch('carrito_insumos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action, insumo })
                })
                .then(response => response.json())
                .then(data => actualizarCarrito(data))
                .catch(error => console.error('Error en la petición:', error));
            }

            // Actualizar visualización del carrito
            function actualizarCarrito(carrito) {
                const carritoContainer = document.querySelector('#carrito-items');
                carritoContainer.innerHTML = '';

                if (Object.keys(carrito).length > 0) {
                    for (const insumo in carrito) {
                        const listItem = document.createElement('li');
                        listItem.innerHTML = `
                            ${insumo} (x${carrito[insumo]}) 
                            <button class='remove-from-cart' data-insumo='${insumo}'>Eliminar</button>
                        `;
                        carritoContainer.appendChild(listItem);
                    }
                } else {
                    carritoContainer.innerHTML = '<li>El carrito está vacío</li>';
                }
            }
        });
    </script>
</body>
</html>