<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

$conn = conectarDB();

$hora = $_GET['hora'] ?? '';

if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora)) {
    echo json_encode([]);
    exit;
}

if (strlen($hora) == 5) {
    $hora = $hora . ':00';
}

$horas_validas = [
    '07:30:00', '08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00',
    '10:30:00', '11:00:00', '11:30:00', '12:00:00', '12:30:00', '17:00:00',
    '17:30:00', '18:00:00', '18:30:00', '19:00:00', '19:30:00', '20:00:00',
    '20:30:00', '21:00:00', '21:30:00', '22:00:00', '22:30:00'
];

if (!in_array($hora, $horas_validas)) {
    echo json_encode([]);
    exit;
}

$sql  = "SELECT fecha FROM reservas WHERE hora = ? AND estado IN ('pendiente', 'confirmada')";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([]);
    exit;
}

$stmt->bind_param("s", $hora);
$stmt->execute();
$result = $stmt->get_result();

$unavailableDates = [];
while ($row = $result->fetch_assoc()) {
    $unavailableDates[] = $row['fecha'];
}

echo json_encode($unavailableDates);

$stmt->close();
$conn->close();
