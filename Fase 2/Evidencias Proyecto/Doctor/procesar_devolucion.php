<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["devoluciones"], $_POST["id_cirugia"])) {
    $id_cirugia = (int)$_POST["id_cirugia"];
    $devoluciones = $_POST["devoluciones"];
    
    $insumos_devueltos = [];
    foreach ($devoluciones as $nombre_insumo => $cantidad_devuelta) {
        $cantidad_devuelta = (int)$cantidad_devuelta;
        if ($cantidad_devuelta > 0) {
            $insumos_devueltos[] = "$nombre_insumo (x$cantidad_devuelta)";
        }
    }
    $insumos_devueltos_str = implode(',', $insumos_devueltos);
  
    $stmt = $conn->prepare("UPDATE cirugias SET estado = 'en devolucion', insumos_devueltos = ? WHERE id = ?");
    $stmt->bind_param("si", $insumos_devueltos_str, $id_cirugia);
    $stmt->execute();
    $stmt->close();

    $fecha = date('Y-m-d H:i:s');
    $stmt_hist = $conn->prepare("INSERT INTO historial (id_solicitud, estado, fecha) VALUES (?, 'en devolucion', ?)");
    $stmt_hist->bind_param("is", $id_cirugia, $fecha);
    $stmt_hist->execute();
    $stmt_hist->close();

    header("Location: peticiones.php");
    exit();
} else {
    die("Solicitud no válida.");
}
?>
