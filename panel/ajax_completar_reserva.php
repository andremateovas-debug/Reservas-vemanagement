<?php
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
$total      = filter_input(INPUT_POST, 'total', FILTER_VALIDATE_FLOAT);

if (!$reserva_id || $reserva_id < 1) {
    echo json_encode(['success' => false, 'message' => 'ID de reserva invalido']);
    exit;
}

if ($total === false || $total < 0) {
    echo json_encode(['success' => false, 'message' => 'Total invalido']);
    exit;
}

$conn = conectarDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexion']);
    exit;
}

$sql_check  = "SELECT id, estado FROM reservas WHERE id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $reserva_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
    $stmt_check->close();
    $conn->close();
    exit;
}

$reserva = $result_check->fetch_assoc();

if ($reserva['estado'] !== 'confirmada') {
    echo json_encode(['success' => false, 'message' => 'Solo se pueden completar reservas confirmadas']);
    $stmt_check->close();
    $conn->close();
    exit;
}

$stmt_check->close();

$sql_update  = "UPDATE reservas SET estado = 'completada', total = ? WHERE id = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("di", $total, $reserva_id);

if ($stmt_update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Reserva marcada como completada', 'total' => $total]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al completar la reserva']);
}

$stmt_update->close();
$conn->close();
