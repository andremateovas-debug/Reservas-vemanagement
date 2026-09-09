<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['penalizada' => false, 'reportes' => 0]);
    exit;
}

$telefono = trim($_GET['telefono'] ?? '');

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
