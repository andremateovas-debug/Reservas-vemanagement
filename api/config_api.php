<?php
require_once __DIR__ . '/../config/config.php';

function verificarAutenticacion() {
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
}
