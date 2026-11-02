<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';

requirePermission('panel.acceso');

$conn = conectarDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-t');

$start = date('Y-m-d', strtotime($start));
$end   = date('Y-m-d', strtotime($end));

$sql = "SELECT
            id,
            fecha,
            hora,
            nombre,
            personas,
            estado,
            telefono,
            email
        FROM reservas
        WHERE fecha BETWEEN ? AND ?
        AND estado != 'cancelada'
        ORDER BY fecha, hora";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $color = match($row['estado']) {
        'pendiente'  => '#f59e0b',
        'confirmada' => '#10b981',
        'cancelada'  => '#ef4444',
        'completada' => '#3b82f6',
        default      => '#6b7280'
    };

    $hora_formato = date('g:i A', strtotime($row['hora']));
    $title        = $row['nombre'] . ' (' . $row['personas'] . 'p) ' . $hora_formato;
    $startDateTime= $row['fecha'] . 'T' . $row['hora'];

    $events[] = [
        'id'        => $row['id'],
        'title'     => $title,
        'start'     => $startDateTime,
        'color'     => $color,
        'textColor' => '#ffffff',
        'extendedProps' => [
            'estado'   => $row['estado'],
            'personas' => $row['personas'],
            'hora'     => date('g:i A', strtotime($row['hora'])),
            'telefono' => $row['telefono'] ?? '',
            'email'    => $row['email']    ?? ''
        ]
    ];
}

echo json_encode($events);
$stmt->close();
$conn->close();
