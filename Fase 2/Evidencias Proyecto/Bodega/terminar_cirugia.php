<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id"])) {
    $id = (int)$_POST["id"];
    $stmt_detalle = $conn->prepare("SELECT insumos FROM cirugias WHERE id = ?");
    $stmt_detalle->bind_param("i", $id);
    $stmt_detalle->execute();
    $result_detalle = $stmt_detalle->get_result();

    if ($fila = $result_detalle->fetch_assoc()) {
        $lista_insumos = $fila['insumos'];
        $insumos_array = explode(',', $lista_insumos);

        foreach ($insumos_array as $insumo_cantidad) {
            if (preg_match('/^(.*?)\s*\(x(\d+)\)$/i', trim($insumo_cantidad), $matches)) {
                $nombre_insumo = trim($matches[1]);
                $cantidad = (int)$matches[2];

                $stmt_resta_stock = $conn->prepare("UPDATE componentes SET stock = stock - ? WHERE LOWER(insumo) = LOWER(?)");
                $stmt_resta_stock->bind_param("is", $cantidad, $nombre_insumo);
                $stmt_resta_stock->execute();
                $stmt_resta_stock->close();
            }
        }
    }
    $stmt_detalle->close();
    $stmt_update = $conn->prepare("UPDATE cirugias SET estado = 'terminada' WHERE id = ?");
    $stmt_update->bind_param("i", $id);
    $stmt_update->execute();
    $stmt_update->close();
    $fecha = date('Y-m-d H:i:s');
    $stmt_hist = $conn->prepare("INSERT INTO historial (id_solicitud, estado, fecha) VALUES (?, 'terminada', ?)");
    $stmt_hist->bind_param("is", $id, $fecha);
    $stmt_hist->execute();
    $stmt_hist->close();

    header("Location: peticiones.php");
    exit();
} else {
    die("Solicitud no válida.");
}
