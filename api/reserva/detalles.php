<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config_api.php';

requirePermission('reservas.ver');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de reserva inválido']);
    exit;
}

$conn = conectarDB();

$stmt = $conn->prepare('SELECT * FROM reservas WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    echo json_encode($result->fetch_assoc());
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Reserva no encontrada']);
}

$stmt->close();
$conn->close();
