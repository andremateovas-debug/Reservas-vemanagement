<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

$reserva_id = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
$comentario = trim($_POST['comentario'] ?? '');

if (!$reserva_id || $reserva_id < 1) {
    echo json_encode(['success' => false, 'message' => 'ID de reserva invalido']);
    exit;
}

$conn = conectarDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexion']);
    exit;
}

$sql   = "UPDATE reservas SET comentario_admin = ? WHERE id = ?";
$stmt  = $conn->prepare($sql);
$valor = empty($comentario) ? null : $comentario;
$stmt->bind_param("si", $valor, $reserva_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al guardar comentario']);
}

$stmt->close();
$conn->close();
