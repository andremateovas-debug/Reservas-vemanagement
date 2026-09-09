<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'TU_USUARIO_BD');
if (!defined('DB_PASS')) define('DB_PASS', 'TU_CONTRASENA_BD');
if (!defined('DB_NAME')) define('DB_NAME', 'TU_NOMBRE_DE_BD');

define('SITE_NAME', 'Tu Restaurante');
define('ADMIN_EMAIL', 'reservaciones@tudominio.com');
define('SITE_URL', 'https://tudominio.com');

if (!defined('SITE_TIMEZONE')) {
    define('SITE_TIMEZONE', 'America/Mazatlan');
}
date_default_timezone_set(SITE_TIMEZONE);

define('MAX_PERSONAS', 12);
define('HORARIO_APERTURA', '07:30:00');
define('HORARIO_CIERRE', '22:30:00');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function conectarDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    $conn->query("SET time_zone = 'America/Mazatlan'");
    return $conn;
}

function conectarPDO() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión PDO: " . $e->getMessage());
    }
}

function verificarLoginAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        if (strpos($_SERVER['PHP_SELF'], '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit;
        } else {
            header('Location: ' . SITE_URL . '/panel/login_panel.php');
            exit;
        }
    }
}

function obtenerUsuarioActual() {
    if (isset($_SESSION['admin_id'])) {
        return [
            'id'      => $_SESSION['admin_id'],
            'usuario' => $_SESSION['admin_usuario'] ?? '',
            'email'   => $_SESSION['admin_email'] ?? '',
            'nombre'  => $_SESSION['admin_nombre'] ?? '',
            'rol'     => $_SESSION['admin_rol'] ?? 'recepcion'
        ];
    }
    return null;
}

function obtenerEstadisticasRapidas($conn) {
    $sql = "SELECT
                COUNT(*) as total_hoy,
                SUM(CASE WHEN estado = 'pendiente'  AND fecha = CURDATE() THEN 1 ELSE 0 END) as pendientes_hoy,
                SUM(CASE WHEN estado = 'confirmada' AND fecha = CURDATE() THEN 1 ELSE 0 END) as confirmadas_hoy,
                SUM(CASE WHEN estado = 'cancelada'  AND fecha = CURDATE() THEN 1 ELSE 0 END) as canceladas_hoy
            FROM reservas
            WHERE fecha = CURDATE()";

    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : [
        'total_hoy'      => 0,
        'pendientes_hoy' => 0,
        'confirmadas_hoy'=> 0,
        'canceladas_hoy' => 0
    ];
}
