<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';

requirePermission('reservas.completar');
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$reserva_id = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
$total      = filter_input(INPUT_POST, 'total', FILTER_VALIDATE_FLOAT);

if (!$reserva_id || $reserva_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
    exit;
}

if ($total === false || $total === null || $total < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Total inválido']);
    exit;
}

$conn = conectarDB();

$stmt_check = $conn->prepare('SELECT id, estado FROM reservas WHERE id = ? LIMIT 1');
$stmt_check->bind_param('i', $reserva_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
    $stmt_check->close();
    $conn->close();
    exit;
}

$reserva = $result_check->fetch_assoc();
$stmt_check->close();

if ($reserva['estado'] !== 'confirmada') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Solo se pueden completar reservas confirmadas']);
    $conn->close();
    exit;
}

$stmt_update = $conn->prepare("UPDATE reservas SET estado = 'completada', total = ? WHERE id = ? AND estado = 'confirmada'");
$stmt_update->bind_param('di', $total, $reserva_id);

if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Reserva completada', 'total' => $total]);
} else {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'No se pudo completar (estado ya cambió)']);
}

$stmt_update->close();
$conn->close();
