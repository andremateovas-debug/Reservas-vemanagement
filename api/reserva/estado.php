<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config_api.php';

requirePermission('reservas.confirmar');
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../panel/correos.php';

$data   = json_decode(file_get_contents('php://input'), true);
$id     = filter_var($data['id']     ?? null, FILTER_VALIDATE_INT);
$estado = trim($data['estado'] ?? '');

if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de reserva inválido']);
    exit;
}

// Solo estados que se pueden cambiar desde la API (sin 'completada' — esa tiene endpoint propio)
$estadosPermitidos = ['confirmada', 'cancelada'];
if (!in_array($estado, $estadosPermitidos, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Estado no válido']);
    exit;
}

if ($estado === 'cancelada' && !can('reservas.cancelar')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso para cancelar']);
    exit;
}
if ($estado === 'confirmada' && !can('reservas.confirmar')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso para confirmar']);
    exit;
}

$conn = conectarDB();

$stmt_get = $conn->prepare('SELECT * FROM reservas WHERE id = ? LIMIT 1');
$stmt_get->bind_param('i', $id);
$stmt_get->execute();
$reserva = $stmt_get->get_result()->fetch_assoc();
$stmt_get->close();

if (!$reserva) {
    http_response_code(404);
    echo json_encode(['error' => 'Reserva no encontrada']);
    $conn->close();
    exit;
}

$estado_anterior = $reserva['estado'];

$conn->begin_transaction();

try {
    $stmt_up = $conn->prepare('UPDATE reservas SET estado = ? WHERE id = ?');
    $stmt_up->bind_param('si', $estado, $id);
    if (!$stmt_up->execute()) {
        throw new RuntimeException('Error al actualizar');
    }
    $stmt_up->close();

    // Penalización solo en transición hacia cancelada (no duplicar — Hallazgo H)
    if ($estado === 'cancelada' && $estado_anterior !== 'cancelada' && !empty($reserva['telefono'])) {
        $telefono = normalizarTelefono($reserva['telefono']);
        $stmt_h   = $conn->prepare('SELECT reportes FROM clientes_historial WHERE telefono = ?');
        $stmt_h->bind_param('s', $telefono);
        $stmt_h->execute();
        $row_h = $stmt_h->get_result()->fetch_assoc();
        $stmt_h->close();

        if ($row_h) {
            $nuevos = $row_h['reportes'] + 1;
            $pen    = ($nuevos >= 3) ? 1 : 0;
            $stmt_hu = $conn->prepare('UPDATE clientes_historial SET reportes = ?, penalizada = ? WHERE telefono = ?');
            $stmt_hu->bind_param('iis', $nuevos, $pen, $telefono);
            $stmt_hu->execute();
            $stmt_hu->close();
        } else {
            $nombre = mb_substr($reserva['nombre'] ?? '', 0, 100);
            $email  = mb_substr($reserva['email']  ?? '', 0, 100);
            $stmt_hi = $conn->prepare(
                'INSERT INTO clientes_historial (telefono, nombre, email, reportes, penalizada) VALUES (?, ?, ?, 1, 0)'
            );
            $stmt_hi->bind_param('sss', $telefono, $nombre, $email);
            $stmt_hi->execute();
            $stmt_hi->close();
        }
    }

    $conn->commit();

    if ($estado === 'confirmada') enviarCorreoConfirmacion($reserva);
    if ($estado === 'cancelada')  enviarCorreoCancelacion($reserva);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Error en api/reserva/estado: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}

$conn->close();
