<?php
session_start();
include 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
$rut_usuario = $_SESSION['rut'];
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

function formatearRUT($rut) {
    $rut = str_replace(array("."), "", $rut);
    return $rut;
}

function validarRUT($rut) {
    $rut = str_replace(".", "", $rut);

    if (!preg_match("/^[0-9]{7,8}-[0-9kK]{1}$/", $rut)) {
        return false;
    }

    list($rut_numeros, $rut_dv) = explode("-", $rut);

    $suma = 0;
    $factor = 2;
    for ($i = strlen($rut_numeros) - 1; $i >= 0; $i--) {
        $suma += $rut_numeros[$i] * $factor;
        $factor = ($factor == 7) ? 2 : $factor + 1;
    }

    $dv_calculado = 11 - ($suma % 11);
    if ($dv_calculado == 11) {
        $dv_calculado = '0';
    } elseif ($dv_calculado == 10) {
        $dv_calculado = 'K';
    }

    return strtoupper($dv_calculado) == strtoupper($rut_dv);
}

if (isset($_POST['send_email'])) {
            $campos = ['rut_paciente', 'cirugia', 'pabellon', 'medico_cirujano', 'medico_anestesia', 'arsenalero', 'pabellonero', 'enfermero', 'auxiliar'];
            foreach ($campos as $campo) {
                if (empty($_POST[$campo])) {
                    echo "<script>alert('Por favor completa todos los campos del formulario.'); window.location.href=window.location.href;</script>";
                    exit();
                }
            }

            if (empty($_SESSION['carrito'])) {
                echo "<script>alert('No se puede finalizar el pedido. El carrito está vacío.'); window.location.href=window.location.href;</script>";
                exit();
            }
    $medico_cirujano = $_POST['medico_cirujano'];
    $medico_anestesia = $_POST['medico_anestesia'];
    $arsenalero = $_POST['arsenalero'];
    $pabellonero = $_POST['pabellonero'];
    $enfermero = $_POST['enfermero'];
    $auxiliar = $_POST['auxiliar'];
    $paciente = $_POST['rut_paciente'];
    $cirugia = $_POST['cirugia'];
    $pabellon = $_POST['pabellon'];
    $insumos_usados = [];
    $fecha_sol = date('Y-m-d H:i:s');
    foreach ($_SESSION['carrito'] as $insumo => $cantidad) {
        $insumos_usados[] = "$insumo (x$cantidad)";
    }

    $insumos_str = implode(', ', $insumos_usados);

    $stmt = $conn->prepare("INSERT INTO cirugias 
    (rut_paciente, cirugia, pabellon, insumos, medico_cirujano, medico_anestesia, arsenalero, pabellonero, enfermero, auxiliar, estado, rut_usuario, fecha_sol) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en proceso', ?, ?)");

    $stmt->execute([
        $paciente,
        $cirugia,
        $pabellon,
        $insumos_str,
        $medico_cirujano,
        $medico_anestesia,
        $arsenalero,
        $pabellonero,
        $enfermero,
        $auxiliar,
        $rut_usuario,
        $fecha_sol
    ]);
    $result = $conn->query("SELECT correo FROM usuarios WHERE correo IS NOT NULL AND correo != ''");

    if ($result->num_rows === 0) {
        echo "<script>alert('Error: No hay usuarios con correo registrado.'); window.location.href=window.location.href;</script>";
        exit();
    }

    while ($row = $result->fetch_assoc()) {
        $correo = $row['correo'];

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'valdiviaalejandro2001@gmail.com';
            $mail->Password = 'pely xdjc ufal yyyz';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->setFrom('valdiviaalejandro2001@gmail.com', 'Hospital Clínico Félix Bulnes');
            $mail->addAddress($correo);

            $mail->Subject = 'Lista de Insumos para Cirugía';
            $mail->Body = "Se ha programado una cirugía con los siguientes detalles:\n\n";
            $mail->Body .= "Paciente: $paciente\n";
            $mail->Body .= "Cirugía: $cirugia\n";
            $mail->Body .= "Pabellón: $pabellon\n";
            $mail->Body .= "Insumos requeridos: $insumos_str\n";
            $mail->Body .= "Equipo Médico:\n";
            $mail->Body .= "- Cirujano: $medico_cirujano\n";
            $mail->Body .= "- Anestesista: $medico_anestesia\n";
            $mail->Body .= "- Arsenalero(a): $arsenalero\n";
            $mail->Body .= "- Pabellonero(a): $pabellonero\n";
            $mail->Body .= "- Enfermero(a): $enfermero\n";
            $mail->Body .= "- Auxiliar: $auxiliar\n\n";
            $mail->Body .= "Responsable del registro: $rut_usuario\n\n";
            $mail->Body .= "Atentamente,\nSistema de Cirugías - Hospital Clínico Félix Bulnes";

            $mail->send();

        } catch (Exception $e) {
            echo "<script>alert('Error al enviar el correo a: $correo. Error: {$mail->ErrorInfo}'); window.location.href=window.location.href;</script>";
            exit();
        }
    }

    $_SESSION['carrito'] = [];
    header("Location: ".$_SERVER['PHP_SELF']."?success=1");
    exit();
}

if (isset($_POST['action'])) {
    $insumo = $_POST['insumo'];
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;

    switch ($_POST['action']) {
        case 'add':
            if (isset($_SESSION['carrito'][$insumo])) {
                $_SESSION['carrito'][$insumo] += $cantidad;
            } else {
                $_SESSION['carrito'][$insumo] = $cantidad;
            }
            break;

        case 'remove':
            unset($_SESSION['carrito'][$insumo]);
            break;

        case 'decrease':
            if (isset($_SESSION['carrito'][$insumo])) {
                $_SESSION['carrito'][$insumo]--;
                if ($_SESSION['carrito'][$insumo] <= 0) {
                    unset($_SESSION['carrito'][$insumo]);
                }
            }
            break;
    }

    echo json_encode($_SESSION['carrito']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administración de Insumos de Urologia</title>
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
    <script>carrito.js</script>
</head>
<body>
    <div class="container">
        <div class="selection-container">
            <h2>Carrito de Compras</h2>
                <ul id="carrito-items">
                    <?php if (!empty($_SESSION['carrito'])): ?>
                        <?php foreach ($_SESSION['carrito'] as $insumo => $cantidad): ?>
                            <li>
                                <span><?= htmlspecialchars($insumo) ?> (x<?= $cantidad ?>)</span>
                                <button class="decrease-qty" data-insumo="<?= htmlspecialchars($insumo) ?>">-</button>
                                <button class="increase-qty" data-insumo="<?= htmlspecialchars($insumo) ?>">+</button>
                                <button class="remove-from-cart" data-insumo="<?= htmlspecialchars($insumo) ?>">Eliminar</button>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>El carrito está vacío</li>
                    <?php endif; ?>
                </ul>
            <form method="post">
                <h3>Datos de cirugía</h3>
                <input type="text" name="rut_paciente" placeholder="RUT del paciente (Sin puntos ni guion)" required id="rut" onblur="validarRUTInput()" oninput="limpiarRut()">
                <input type="text" name="cirugia" placeholder="Tipo de cirugía" required>
                <input type="text" name="pabellon" placeholder="Pabellón" required>
                <h3>Datos del equipo médico</h3>
                <input type="text" name="medico_cirujano" placeholder="Médico cirujano" required>
                <input type="text" name="medico_anestesia" placeholder="Médico anestesista" required>
                <input type="text" name="arsenalero" placeholder="Arsenalero(a)" required>
                <input type="text" name="pabellonero" placeholder="Pabellonero(a)" required>
                <input type="text" name="enfermero" placeholder="Enfermero(a)" required>
                <input type="text" name="auxiliar" placeholder="Auxiliar" required>
                <button type="submit" name="send_email">Finalizar Pedido</button>
            </form>
        </div>
    </div>
</body>
<style>
        .error-message {
            color: red;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
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
            font-size: 14px;
            font-weight: bold;
        }
        #carrito-items li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 8px;
            background-color: #f9f9f9;
            font-size: 14px;
        }

        #carrito-items li span {
            flex: 1;
        }

        #carrito-items button {
            padding: 4px 8px;
            font-size: 13px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            transition: background-color 0.2s;
        }

        button.decrease-qty {
            background-color: #f0ad4e;
        }
        button.decrease-qty:hover {
            background-color: #ec971f;
        }

        button.increase-qty {
            background-color: #5cb85c;
        }
        button.increase-qty:hover {
            background-color: #449d44;
        }

        button.remove-from-cart {
            background-color: #d9534f;
        }
        button.remove-from-cart:hover {
            background-color: #c9302c;
        }

        .carrito-container {
            display: flex;
            flex-wrap: wrap;
            padding: 12px;
            gap: 15px;
        }

        .carrito-item {
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 5px;
        }

        .mini-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
        .error-message {
            color: red;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }   
</style>
    <script>
        function validarRUTInput() {
            const rutInput = document.getElementById("rut").value;
            let rut = rutInput.replace(/\./g, "").replace("-", "");
            
            const regex = /^[0-9]{7,8}[0-9kK]{1}$/;
            if (!regex.test(rut)) {
                mostrarError("El RUT ingresado no tiene un formato válido.");
                return false;
            }

            const rut_numeros = rut.slice(0, -1);
            const rut_dv = rut.slice(-1).toUpperCase();
            
            let suma = 0;
            let factor = 2;
            for (let i = rut_numeros.length - 1; i >= 0; i--) {
                suma += parseInt(rut.charAt(i)) * factor;
                factor = (factor === 7) ? 2 : factor + 1;
            }

            const dv_calculado = 11 - (suma % 11);
            let dv_final;
            if (dv_calculado === 11) {
                dv_final = '0';
            } else if (dv_calculado === 10) {
                dv_final = 'K';
            } else {
                dv_final = dv_calculado.toString();
            }

            if (dv_final !== rut_dv) {
                mostrarError("El RUT ingresado es incorrecto.");
                return false;
            }
            return true;
        }

        function limpiarRut() {
            const rutInput = document.getElementById("rut");
            let rut = rutInput.value;
            rut = rut.replace(/\./g, "");
            rut = rut.replace(/-/g, "");
            rutInput.value = rut;
        }
        
        function validarFormulario(event) {
            if (!validarRUTInput()) {
                event.preventDefault();
            }
        }

        document.addEventListener('click', function(event) {
            const insumo = event.target.dataset.insumo;

            if (event.target.classList.contains('remove-from-cart')) {
                actualizar('remove', insumo);
            }

            if (event.target.classList.contains('decrease-qty')) {
                actualizar('decrease', insumo);
            }

            if (event.target.classList.contains('increase-qty')) {
                actualizar('add', insumo);
            }
        });

        function actualizar(action, insumo) {
            fetch('carrito_insumos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action, insumo })
            })
            .then(response => response.json())
            .then(data => actualizarCarrito(data));
        }

        function toggleAccountInfo() {
            const info = document.getElementById('accountInfo');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }

        function actualizarCarrito(carrito) {
            const carritoItems = document.getElementById('carrito-items');
            carritoItems.innerHTML = '';

            if (Object.keys(carrito).length === 0) {
                carritoItems.innerHTML = '<li>El carrito está vacío</li>';
                return;
            }

            for (const insumo in carrito) {
                const cantidad = carrito[insumo];

                const li = document.createElement('li');
                li.innerHTML = `
                    <span>${insumo} (x${cantidad})</span>
                    <button class="decrease-qty" data-insumo="${insumo}">-</button>
                    <button class="increase-qty" data-insumo="${insumo}">+</button>
                    <button class="remove-from-cart" data-insumo="${insumo}">Eliminar</button>
                `;
                carritoItems.appendChild(li);
            }
        }
    </script>
</html>
<?php
exit();
}
?>