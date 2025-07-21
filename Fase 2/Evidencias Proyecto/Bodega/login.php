<?php
session_start();
include 'db.php';

function validarRUT($rut) {
    $rut = str_replace(array(".", "-"), "", $rut);
    
    if (!preg_match("/^[0-9]{7,8}[0-9kK]{1}$/", $rut)) {
        return false;
    }

    $rut_numeros = substr($rut, 0, -1);
    $rut_dv = strtoupper(substr($rut, -1));

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

    return $dv_calculado == $rut_dv;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitar'])) {
    $rut = $_POST['rut'];
    $pass = $_POST['pass'];

    $rut = str_replace(array(".", "-"), "", $rut);

    if (validarRUT($rut)) {
        $sql = "SELECT * FROM usuarios WHERE rut = '$rut' AND pass = '$pass' AND rol ='bodeguero'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $_SESSION['rut'] = $rut;
            $_SESSION['nombre'] = $result->fetch_assoc()['nombre'];
            header("Location: eleccion.php");
            exit();
        } else {
            $error = "Credenciales incorrectas. Intenta nuevamente.";
        }
    } else {
        $error = "RUT no válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Login administración de insumos del Hospital Clínico Félix Bulnes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        function mostrarError(message) {
            const errorMessage = document.querySelector(".error-message");
            errorMessage.textContent = message;
            errorMessage.style.display = "block";
        }

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
                suma += parseInt(rut_numeros.charAt(i)) * factor;
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
            rut = rut.replace(/\./g, "").replace("-", "");
            rutInput.value = rut;
        }

        function validarFormulario(event) {
            if (!validarRUTInput()) {
                event.preventDefault();
            }
        }
    </script>
</head>
<body>
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Ingreso a bodega de Insumos médicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <form action="logout.php" method="POST">
        <button type="submit" class="volver-btn">Volver</button>
        </form>
    </div>
    <div class="container">
        <h2>Iniciar sesión</h2>

        <div class="error-message" style="<?php echo isset($error) ? 'display: block;' : 'display: none;'; ?>">
            <?php echo isset($error) ? $error : ''; ?>
        </div>

        <form method="POST" action="" onsubmit="validarFormulario(event)">
            <input type="text" name="rut" placeholder="RUT (sin puntos ni guion)" required id="rut" onblur="validarRUT()" oninput="limpiarRut()">
            <input type="password" name="pass" placeholder="Contraseña" required>
            <button type="submit" name="solicitar">INGRESAR</button>
        </form>
    </div>
    <style>
        .container {
            padding: 20px;       
            width: 30%;    
            margin: 300px auto 0 auto;
            border-radius: 15px; 
            background-color:rgb(255, 255, 255);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            padding: 30px 10px;           
            width: 70%;              
            margin: 3px auto 0 auto; 
            border-radius: 15px; 
            background-color: #e8f0fe;
            box-shadow: 0 0 15px rgba(0,0,0,0.15);
            text-align: center;
        }

        .error-message {
            color: red;
            background-color:rgb(255, 255, 255);
            border: 1px solidrgb(255, 255, 255);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</body>
</html>
