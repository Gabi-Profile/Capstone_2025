<?php
include 'db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

function validarRUT($rut) {
    $rut = str_replace(".", "", $rut);

    if (strpos($rut, '-') === false) {
        $rut = substr($rut, 0, -1) . '-' . substr($rut, -1);
    }

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

function formatearRUT($rut) {
    $rut = str_replace(array("."), "", $rut);
    $dv = strtoupper(substr($rut, -1));
    $rut = substr($rut, 0, -1);
    $rut = strrev(implode(".", str_split(strrev($rut), 3)));
    return $rut . '-' . $dv;
}

function rutExists($rut, $conn) {
    $sql_check = "SELECT 1 FROM usuarios WHERE rut = ?";
    if ($stmt = $conn->prepare($sql_check)) {
        $stmt->bind_param("s", $rut);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    } else {
        echo "Error en la preparación de la consulta: " . $conn->error;
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ingresar'])) {
    $rut = $_POST['rut'];

    if (!validarRUT($rut)) {
        echo "El RUT ingresado no es válido.";
        exit();
    }

    $nombre = $_POST['nombre'];
    $pass = $_POST['pass'];
    $rol = $_POST['rol'];
    $correo = $_POST['correo'];

    if (rutExists($rut, $conn)) {
        $sql_update = "UPDATE usuarios 
                       SET nombre = ?, pass = ?, rol = ?, correo = ?
                       WHERE rut = ?";

        if ($stmt = $conn->prepare($sql_update)) {
            $stmt->bind_param("sssss", $nombre, $pass, $rol, $correo, $rut);

            if ($stmt->execute()) {
                echo "Usuario actualizado correctamente.";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } else {
                echo "Error: " . $sql_update . "<br>" . $conn->error;
            }

            $stmt->close();
        } else {
            echo "Error en la preparación de la consulta: " . $conn->error;
        }
    } else {
        $sql_insert = "INSERT INTO usuarios (rut, nombre, pass, rol, correo) 
                       VALUES ('$rut', '$nombre', '$pass', '$rol', '$correo')";

        if ($conn->query($sql_insert) === TRUE) {
            echo "Usuario registrado correctamente.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql_insert . "<br>" . $conn->error;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eliminar-usuario'])) {
    $rut = $_POST['rut'];    
    $sql_delete = "DELETE FROM usuarios WHERE rut = ?";

    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("s", $rut);

        if ($stmt->execute()) {
        } else {
            echo "Error al eliminar el usuario: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Error en la preparación de la consulta: " . $conn->error;
    }
}

$sql1 = "SELECT nombre, rut FROM usuarios WHERE rol = 'bodeguero'";
$result1 = $conn->query($sql1);
$solicitudes_result1 = [];
if ($result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        $solicitudes_result1[] = $row;
    }
}

$sql3 = "SELECT nombre, rut, rol FROM usuarios WHERE rol = 'admin'";
$result3 = $conn->query($sql3);
$solicitudes_result3 = [];
if ($result3->num_rows > 0) {
    while ($row = $result3->fetch_assoc()) {
        $solicitudes_result3[] = $row;
    }
}
$sql4 = "SELECT nombre, rut, rol FROM usuarios WHERE rol = 'doctor'";
$result4 = $conn->query($sql4);
$solicitudes_result4 = [];
if ($result4->num_rows > 0) {
    while ($row = $result4->fetch_assoc()) {
        $solicitudes_result4[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="asset/styles.css">
    <meta charset="UTF-8">
    <title>Administrador de administracion de insumos del Hospital Clinico Félix Bulnes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Administrador bodega Insumos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
        <form action="logout.php" method="POST">
            <button type="submit" class="logout-btn">Salir</button>
        </form>
    </div>
    <script>
        function toggleCorreo() {
            var rol = document.getElementById('rol').value;
            var correoInput = document.getElementById('correo');
            
            if (rol === 'admin') {
                correoInput.disabled = true;
            }
            else if (rol === 'doctor') {
                correoInput.disabled = true;
            } else {
                correoInput.disabled = false;
            }
        }

        function limpiarRut() {
            const rutInput = document.getElementById("rut");
            let rut = rutInput.value;
            rut = rut.replace(/\./g, "");
            rutInput.value = rut;
        }

        window.onload = function() {
            toggleCorreo();
        };
    </script>
</head>
<body>
    <div class="container">
        <form method="POST" action="">
            <select name="rol" required id="rol" onchange="toggleCorreo()">
                <option value="">Selecciona una opcion</option>
                <option value="bodeguero">Bodeguero</option>
                <option value="admin">Admin</option>
                <option value="doctor">Doctor</option>
            </select>
            <input type="text" name="rut" placeholder="RUT (sin puntos ni guion, solo con guion para ingresar usuario tipo administrador)" required id="rut" onblur="validarRUTInput()" oninput="limpiarRut()">
            <input type="text" name="nombre" placeholder="Nombre" required id="nombre">
            <input type="password" name="pass" placeholder="Contraseña" required id="pass">

            <input type="email" name="correo" placeholder="Correo" required id="correo">
            <button type="submit" class="btn-dashboard" name="ingresar">Registrar Usuario</button>
        </form>
        <?php if (!empty($solicitudes_result1)): ?>
            <h3>Bodegueros:</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result1 as $solicitud1): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud1['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud1['rut']); ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="rut" value="<?php echo $solicitud1['rut']; ?>">
                                    <button type="submit" name="eliminar-usuario" class="rechazar-btn-table">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($solicitudes_result3)): ?>
            <h3>Administradores:</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result3 as $solicitud3): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud3['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud3['rut']); ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="rut" value="<?php echo $solicitud3['rut']; ?>">
                                    <button type="submit" name="eliminar-usuario" class="rechazar-btn-table">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if (!empty($solicitudes_result4)): ?>
            <h3>Doctores:</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RUT</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes_result4 as $solicitud3): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud3['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($solicitud3['rut']); ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="rut" value="<?php echo $solicitud3['rut']; ?>">
                                    <button type="submit" name="eliminar-usuario" class="rechazar-btn-table">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$conn->close();
?>