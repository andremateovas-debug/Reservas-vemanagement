<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config_api.php';

requirePermission('reservas.ver');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$start_raw = trim($_GET['start'] ?? date('Y-m-01'));
$end_raw   = trim($_GET['end']   ?? date('Y-m-t'));

// Validar formato de fechas para evitar inyección
$start = preg_match('/^\d{4}-\d{2}-\d{2}/', $start_raw) ? substr($start_raw, 0, 10) : date('Y-m-01');
$end   = preg_match('/^\d{4}-\d{2}-\d{2}/', $end_raw)   ? substr($end_raw, 0, 10)   : date('Y-m-t');

$conn = conectarDB();

$sql = "SELECT
            id,
            CONCAT(nombre, ' - ', personas, ' personas') as title,
            CONCAT(fecha, 'T', hora) as start,
            DATE_ADD(CONCAT(fecha, ' ', hora), INTERVAL 2 HOUR) as end,
            estado,
            CASE estado
                WHEN 'confirmada' THEN 'green'
                WHEN 'pendiente'  THEN 'orange'
                WHEN 'cancelada'  THEN 'red'
                WHEN 'completada' THEN 'blue'
            END as color,
            nombre,
            personas,
            email,
            telefono,
            comentarios,
            comentario_admin,
            fecha,
            hora
        FROM reservas
        WHERE fecha BETWEEN ? AND ?
        ORDER BY fecha, hora";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$reservas = [];
while ($row = $result->fetch_assoc()) {
    $reservas[] = $row;
}

echo json_encode($reservas);

$stmt->close();
$conn->close();
