<?php
require_once '../config_api.php';
verificarAutenticacion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $id        = $data['id']        ?? null;
    $comentario= $data['comentario']?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta el ID de la reserva']);
        exit;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    $sql  = "UPDATE reservas SET comentario_admin = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $comentario, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar comentario']);
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
