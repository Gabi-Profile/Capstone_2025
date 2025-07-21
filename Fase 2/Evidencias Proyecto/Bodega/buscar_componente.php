<?php
include 'db.php';

$codigo = $_GET['codigo'] ?? '';

$response = ['encontrado' => false];

if ($codigo) {
    $stmt = $conn->prepare("SELECT * FROM componentes WHERE codigo = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($row = $resultado->fetch_assoc()) {
        $response = [
            'encontrado' => true,
            'insumo' => $row['insumo'],
            'codigo' => $row['codigo'],
            'especialidad' => $row['especialidad'],
            'formato' => $row['formato'],
            'ubicacion' => $row['ubicacion']
        ];
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
