<?php
require_once '../config_api.php';

session_destroy();
echo json_encode(['success' => true]);
