<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';

requirePermission('reservas.ver');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$telefono = normalizarTelefono(trim($_GET['telefono'] ?? ''));

if (empty($telefono)) {
    echo json_encode(['penalizada' => false, 'reportes' => 0]);
    exit;
}

$conn = conectarDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexion']);
    exit;
}

$sql  = "SELECT reportes, penalizada FROM clientes_historial WHERE telefono = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $telefono);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $cliente = $result->fetch_assoc();
    echo json_encode([
        'penalizada' => (bool)$cliente['penalizada'],
        'reportes'   => (int)$cliente['reportes']
    ]);
} else {
    echo json_encode(['penalizada' => false, 'reportes' => 0]);
}

$stmt->close();
$conn->close();
