<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administracion de insumos del Hospital Clinico Félix Bulnes</title>
    <link rel="stylesheet" href="asset/styles.css">
</head>
<body class="index-page">
    <div class="header">
        <img src="asset/logo.png" alt="Logo">
        <div class="header-text">
            <div class="main-title">Solicitudes de Insumos Medicos</div>
            <div class="sub-title">Hospital Clínico Félix Bulnes</div>
        </div>
    </div>
    <div class="container">
        <h2>Selecciona tu usuario</h2>
        <form method="post">
            <button type="submit" name="role" value="admin" class="role-button admin">Admin</button>
            <button type="submit" name="role" value="prestador" class="role-button prestador">Bodega</button>
        </form>
        <?php
        if (isset($_POST['role'])) {
            session_start();
            $_SESSION['role'] = $_POST['role'];
            if ($_POST['role'] == 'prestador') {
                header("Location: login.php?role=prestador");
            } elseif ($_POST['role'] == 'admin') {
                if ($_SESSION['role'] == 'admin') {
                    header("Location: loginadmin.php?role=admin");
                } else {
                    echo '<p>No tienes permisos para acceder a esta página.</p>';
                }
            }
        }
        ?>
</body>
</html>
