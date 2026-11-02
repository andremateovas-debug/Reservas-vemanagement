<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config_api.php';

requirePermission('reservas.ver');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$conn = conectarDB();

$filtro           = $_GET['filtro'] ?? 'hoy';
$fecha_especifica = trim($_GET['fecha'] ?? '');

// Validar formato de fecha especifica
if ($fecha_especifica !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_especifica)) {
    $fecha_especifica = '';
}

switch ($filtro) {
    case 'hoy':
        $fecha = date('Y-m-d');
        break;
    case 'manana':
        $fecha = date('Y-m-d', strtotime('+1 day'));
        break;
    case 'fecha':
        $fecha = !empty($fecha_especifica) ? $fecha_especifica : date('Y-m-d');
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
