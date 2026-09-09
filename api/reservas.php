<?php
require_once 'config_api.php';
verificarAutenticacion();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión']);
    exit;
}

$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-t');

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
