<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';

requirePermission('reservas.comentar');
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$reserva_id = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
$comentario = trim($_POST['comentario'] ?? '');

if (!$reserva_id || $reserva_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
    exit;
}

$conn = conectarDB();

// Verificar que la reserva existe antes de modificar (anti-IDOR)
$stmt_check = $conn->prepare('SELECT id FROM reservas WHERE id = ? LIMIT 1');
$stmt_check->bind_param('i', $reserva_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
    $stmt_check->close();
    $conn->close();
    exit;
}
$stmt_check->close();

$valor = empty($comentario) ? null : mb_substr($comentario, 0, 1000);
$stmt  = $conn->prepare('UPDATE reservas SET comentario_admin = ? WHERE id = ?');
$stmt->bind_param('si', $valor, $reserva_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar comentario']);
}

$stmt->close();
$conn->close();
