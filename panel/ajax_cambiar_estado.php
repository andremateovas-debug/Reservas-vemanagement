<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../config/config.php';
require_once 'correos.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

$reserva_id      = $_POST['reserva_id']      ?? 0;
$estado          = $_POST['estado']          ?? '';
$comentario_admin= trim($_POST['comentario_admin'] ?? '');

if (!$reserva_id || !in_array($estado, ['pendiente', 'confirmada', 'cancelada', 'completada'])) {
    echo json_encode(['success' => false, 'message' => 'Datos invalidos']);
    exit;
}

$conn = conectarDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexion']);
    exit;
}

function cambiarEstadoReservaConCorreo($conn, $reserva_id, $estado, $comentario_admin) {
    $stmt = $conn->prepare("SELECT * FROM reservas WHERE id = ?");
    $stmt->bind_param("i", $reserva_id);
    $stmt->execute();
    $reserva = $stmt->get_result()->fetch_assoc();

    if (!$reserva) return false;

    if (!empty($comentario_admin)) {
        $stmt = $conn->prepare("UPDATE reservas SET estado = ?, comentario_admin = ? WHERE id = ?");
        $stmt->bind_param("ssi", $estado, $comentario_admin, $reserva_id);
    } else {
        $stmt = $conn->prepare("UPDATE reservas SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $estado, $reserva_id);
    }

    if (!$stmt->execute()) return false;

    if ($estado === 'cancelada' && !empty($reserva['telefono'])) {
        actualizarHistorialCliente($conn, $reserva['telefono']);
    }

    if ($estado === 'confirmada') enviarCorreoConfirmacion($reserva);
    if ($estado === 'cancelada')  enviarCorreoCancelacion($reserva);

    return true;
}

function actualizarHistorialCliente($conn, $telefono) {
    $stmt = $conn->prepare("SELECT reportes FROM clientes_historial WHERE telefono = ?");
    $stmt->bind_param("s", $telefono);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $cliente        = $result->fetch_assoc();
        $nuevos_reportes= $cliente['reportes'] + 1;
        $penalizada     = ($nuevos_reportes >= 3) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE clientes_historial SET reportes = ?, penalizada = ? WHERE telefono = ?");
        $stmt->bind_param("iis", $nuevos_reportes, $penalizada, $telefono);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO clientes_historial (telefono, reportes, penalizada) VALUES (?, 1, FALSE)");
        $stmt->bind_param("s", $telefono);
        $stmt->execute();
    }

    $stmt->close();
}

$success = cambiarEstadoReservaConCorreo($conn, $reserva_id, $estado, $comentario_admin);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Estado actualizado y correo enviado']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
}

$conn->close();
