<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/config.php';

requirePermission('historial.ver');

$conn = conectarDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

$sql = "SELECT
            fecha,
            COUNT(*) AS total,
            SUM(CASE WHEN estado = 'pendiente'  THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) AS confirmadas,
            SUM(CASE WHEN estado = 'cancelada'  THEN 1 ELSE 0 END) AS canceladas,
            SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) AS completadas,
            SUM(personas) AS total_personas
        FROM reservas
        WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                        AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        GROUP BY fecha
        ORDER BY fecha DESC";

$result = $conn->query($sql);
$dias   = [];
$hoy    = date('Y-m-d');

while ($row = $result->fetch_assoc()) {
    $f     = $row['fecha'];
    $esHoy = ($f === $hoy);
    $esFut = ($f > $hoy);

    $dias[] = [
        'fecha'          => $f,
        'es_hoy'         => $esHoy,
        'es_futuro'      => $esFut,
        'total'          => (int)$row['total'],
        'pendientes'     => (int)$row['pendientes'],
        'confirmadas'    => (int)$row['confirmadas'],
        'canceladas'     => (int)$row['canceladas'],
        'completadas'    => (int)$row['completadas'],
        'total_personas' => (int)$row['total_personas'],
    ];
}

$conn->close();
echo json_encode($dias);
