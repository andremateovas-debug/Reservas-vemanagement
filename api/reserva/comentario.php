<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config_api.php';

requirePermission('reservas.comentar');
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$id         = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$comentario = trim($data['comentario'] ?? '');

if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de reserva inválido']);
    exit;
}

$conn = conectarDB();

// Verificar que la reserva existe (anti-IDOR)
$stmt_check = $conn->prepare('SELECT id FROM reservas WHERE id = ? LIMIT 1');
$stmt_check->bind_param('i', $id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Reserva no encontrada']);
    $stmt_check->close();
    $conn->close();
    exit;
}
$stmt_check->close();

$valor = empty($comentario) ? null : mb_substr($comentario, 0, 1000);
$stmt  = $conn->prepare('UPDATE reservas SET comentario_admin = ? WHERE id = ?');
$stmt->bind_param('si', $valor, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar comentario']);
}

$stmt->close();
$conn->close();
