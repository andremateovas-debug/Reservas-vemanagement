<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';
require_once 'correos.php';

requirePermission('reservas.confirmar'); // Mínimo rol requerido
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$reserva_id       = filter_input(INPUT_POST, 'reserva_id', FILTER_VALIDATE_INT);
$estado           = trim($_POST['estado'] ?? '');
$comentario_admin = trim($_POST['comentario_admin'] ?? '');

if (!$reserva_id || $reserva_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido']);
    exit;
}

// Solo estados permitidos desde el panel (no completada — esa tiene su propio endpoint)
$estadosPermitidos = ['confirmada', 'cancelada'];
if (!in_array($estado, $estadosPermitidos, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estado no permitido']);
    exit;
}

// Verificar permiso específico por acción
if ($estado === 'cancelada' && !can('reservas.cancelar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para cancelar reservas']);
    exit;
}
if ($estado === 'confirmada' && !can('reservas.confirmar')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para confirmar reservas']);
    exit;
}

$conn = conectarDB();

// Obtener la reserva antes de modificar (para detectar el estado anterior)
$stmt_get = $conn->prepare('SELECT * FROM reservas WHERE id = ? LIMIT 1');
$stmt_get->bind_param('i', $reserva_id);
$stmt_get->execute();
$reserva = $stmt_get->get_result()->fetch_assoc();
$stmt_get->close();

if (!$reserva) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
    $conn->close();
    exit;
}

$estado_anterior = $reserva['estado'];

// Iniciar transacción para operación atómica
$conn->begin_transaction();

try {
    // Actualizar estado de la reserva
    if (!empty($comentario_admin)) {
        $stmt_up = $conn->prepare('UPDATE reservas SET estado = ?, comentario_admin = ? WHERE id = ?');
        $stmt_up->bind_param('ssi', $estado, $comentario_admin, $reserva_id);
    } else {
        $stmt_up = $conn->prepare('UPDATE reservas SET estado = ? WHERE id = ?');
        $stmt_up->bind_param('si', $estado, $reserva_id);
    }

    if (!$stmt_up->execute()) {
        throw new RuntimeException('Error al actualizar estado');
    }
    $stmt_up->close();

    // Actualizar historial de penalizaciones SOLO si:
    //  - El nuevo estado es 'cancelada'
    //  - El estado anterior NO era ya 'cancelada' (evitar penalizaciones duplicadas - Hallazgo H)
    //  - La reserva tiene teléfono
    if ($estado === 'cancelada' && $estado_anterior !== 'cancelada' && !empty($reserva['telefono'])) {
        actualizarHistorialCliente($conn, $reserva['telefono'], $reserva['nombre'], $reserva['email'] ?? '');
    }

    $conn->commit();

    // Enviar correo DESPUÉS de confirmar la transacción
    if ($estado === 'confirmada') {
        enviarCorreoConfirmacion($reserva);
    } elseif ($estado === 'cancelada') {
        enviarCorreoCancelacion($reserva);
    }

    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Error en ajax_cambiar_estado: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al actualizar el estado']);
}

$conn->close();

// ─────────────────────────────────────────────────────────────
// Función de historial con protección anti-duplicados
// ─────────────────────────────────────────────────────────────

function actualizarHistorialCliente(mysqli $conn, string $telefono, string $nombre, string $email): void
{
    $telefono = normalizarTelefono($telefono);

    if (empty($telefono)) {
        return;
    }

    $stmt = $conn->prepare('SELECT reportes FROM clientes_historial WHERE telefono = ?');
    $stmt->bind_param('s', $telefono);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $cliente         = $result->fetch_assoc();
        $nuevos_reportes = $cliente['reportes'] + 1;
        $penalizada      = ($nuevos_reportes >= 3) ? 1 : 0;

        $stmt_up = $conn->prepare('UPDATE clientes_historial SET reportes = ?, penalizada = ? WHERE telefono = ?');
        $stmt_up->bind_param('iis', $nuevos_reportes, $penalizada, $telefono);
        $stmt_up->execute();
        $stmt_up->close();
    } else {
        // Primer registro: normalizar nombre y email antes de guardar
        $nombre = mb_substr(trim($nombre), 0, 100);
        $email  = mb_substr(trim($email), 0, 100);

        $stmt_ins = $conn->prepare(
            'INSERT INTO clientes_historial (telefono, nombre, email, reportes, penalizada) VALUES (?, ?, ?, 1, 0)'
        );
        $stmt_ins->bind_param('sss', $telefono, $nombre, $email);
        $stmt_ins->execute();
        $stmt_ins->close();
    }
}
