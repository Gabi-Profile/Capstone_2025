<?php
require('libs/fpdf/fpdf.php');
include 'db.php';

if (!isset($_GET['id'])) {
    die("ID de cirugía no proporcionado.");
}

$id = intval($_GET['id']);

$sql = "SELECT * FROM cirugias WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No se encontró la cirugía.");
}

$cirugia = $result->fetch_assoc();

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);

$pdf->Cell(0, 10, utf8_decode('Resumen de Cirugía'), 0, 1, 'C');
$pdf->Ln(5);
$pdf->SetFont('Arial', '', 12);

$pdf->Cell(50, 10, utf8_decode('Paciente:'), 0, 0);       $pdf->Cell(0, 10, utf8_decode($cirugia['rut_paciente']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Cirugía:'), 0, 0);        $pdf->Cell(0, 10, utf8_decode($cirugia['cirugia']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Pabellón:'), 0, 0);       $pdf->Cell(0, 10, utf8_decode($cirugia['pabellon']), 0, 1);
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Equipo Quirúrgico:'), 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, utf8_decode('Médico cirujano:'), 0, 0);     $pdf->Cell(0, 10, utf8_decode($cirugia['medico_cirujano']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Anestesista:'), 0, 0);         $pdf->Cell(0, 10, utf8_decode($cirugia['medico_anestesia']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Arsenalero(a):'), 0, 0);       $pdf->Cell(0, 10, utf8_decode($cirugia['arsenalero']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Pabellonero(a):'), 0, 0);      $pdf->Cell(0, 10, utf8_decode($cirugia['pabellonero']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Enfermero(a):'), 0, 0);        $pdf->Cell(0, 10, utf8_decode($cirugia['enfermero']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Auxiliar:'), 0, 0);            $pdf->Cell(0, 10, utf8_decode($cirugia['auxiliar']), 0, 1);
$pdf->Ln(5);

$pdf->Cell(50, 10, utf8_decode('Registrado por:'), 0, 0);      $pdf->Cell(0, 10, utf8_decode($cirugia['rut_usuario']), 0, 1);
$pdf->Cell(50, 10, utf8_decode('Fecha de solicitud:'), 0, 0);  $pdf->Cell(0, 10, utf8_decode($cirugia['fecha_sol']), 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Insumos utilizados'), 0, 1);
$pdf->SetFont('Arial', '', 12);

$insumos_array = explode(',', $cirugia['insumos']);
$pdf->Cell(120, 10, utf8_decode('Insumo'), 1, 0, 'C');
$pdf->Cell(60, 10, utf8_decode('Cantidad Entregada'), 1, 1, 'C');

foreach ($insumos_array as $item) {
    $item = trim($item);

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $anchoInsumo = 120;
    $anchoCantidad = 60;

    $lineHeight = 10;
    $nbLines = $pdf->GetStringWidth(utf8_decode($item)) / ($anchoInsumo - 2); // -2 por padding
    $nbLines = ceil($nbLines);
    $cellHeight = $nbLines * $lineHeight;

    $pdf->MultiCell($anchoInsumo, $lineHeight, utf8_decode($item), 1);

    $pdf->SetXY($x + $anchoInsumo, $y);
    $pdf->Cell($anchoCantidad, $cellHeight, '', 1, 1);
}

$pdf->Output('I', 'cirugia_'.$id.'.pdf');

