<?php
require_once 'config_api.php';
verificarAutenticacion();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$filtro           = $_GET['filtro'] ?? 'hoy';
$fecha_especifica = $_GET['fecha']  ?? null;

switch ($filtro) {
    case 'hoy':
        $fecha = date('Y-m-d');
        break;
    case 'manana':
        $fecha = date('Y-m-d', strtotime('+1 day'));
        break;
    case 'fecha':
        $fecha = $fecha_especifica ? $fecha_especifica : date('Y-m-d');
        break;
    default:
        $fecha = date('Y-m-d');
}

$sql  = "SELECT * FROM reservas WHERE fecha = ? ORDER BY hora";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $fecha);
$stmt->execute();
$result = $stmt->get_result();

$reservas = [];
while ($row = $result->fetch_assoc()) {
    $reservas[] = $row;
}

echo json_encode($reservas);

$stmt->close();
$conn->close();
