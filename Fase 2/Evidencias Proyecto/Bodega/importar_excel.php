<?php
require 'vendor/autoload.php';
include 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

ini_set('max_execution_time', 300); // hasta 5 minutos
ini_set('memory_limit', '512M'); // aumentar memoria

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_excel'])) {
    $archivo = $_FILES['archivo_excel']['tmp_name'];

    if (is_uploaded_file($archivo)) {
        try {
            $reader = IOFactory::createReaderForFile($archivo);
            $reader->setReadDataOnly(true); // mejora rendimiento
            $spreadsheet = $reader->load($archivo);
            $hoja = $spreadsheet->getActiveSheet();

            $fila = 4; // saltar encabezado
            $filas_insertadas = 0;

            while (true) {
                $codigo       = trim($hoja->getCell('A' . $fila)->getValue());
                $insumo       = trim($hoja->getCell('B' . $fila)->getValue());
                $especialidad = strtolower(trim($hoja->getCell('C' . $fila)->getValue()));
                $formato      = strtolower(trim($hoja->getCell('D' . $fila)->getValue()));
                $ubicacion    = strtolower(trim($hoja->getCell('E' . $fila)->getValue()));                

                // Si está vacía la fila completa, salimos
                if (empty($codigo) && empty($insumo)) break;

                $fecha_ingreso = date('Y-m-d H:i:s');

                $stmt = $conn->prepare("INSERT INTO componentes (codigo, insumo, stock, especialidad, formato, ubicacion, fecha_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissss", $codigo, $insumo, $stock, $especialidad, $formato, $ubicacion, $fecha_ingreso);
                $stmt->execute();
                $filas_insertadas++;
                $fila++;
            }

            header("Location: agregarcomp.php?importado=$filas_insertadas");
            exit();

        } catch (Exception $e) {
            echo "Error al procesar el archivo: " . $e->getMessage();
        }
    } else {
        echo "Error al subir el archivo.";
    }
} else {
    echo "Acceso no autorizado.";
}
?>
