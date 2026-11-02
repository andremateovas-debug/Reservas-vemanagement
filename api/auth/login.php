<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$email    = trim($data['email']    ?? '');
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email y contraseña son requeridos']);
    exit;
}

// Rate limiting
$segundosBloqueado = loginBloqueado($email);
if ($segundosBloqueado > 0) {
    $minutos = (int)ceil($segundosBloqueado / 60);
    http_response_code(429);
    echo json_encode(['error' => "Demasiados intentos. Intenta en {$minutos} minuto(s)."]);
    exit;
}

try {
    $pdo = conectarPDO();

    $sql = "SELECT id, usuario, password_hash, email, nombre, rol
            FROM usuarios_admin
            WHERE (email = :email OR usuario = :email)
            AND activo = 1
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // Limpiar intentos fallidos
        limpiarIntentosFallidos($email);

        // Regenerar ID de sesión
        session_regenerate_id(true);

        $_SESSION['admin_id']       = $user['id'];
        $_SESSION['admin_usuario']  = $user['usuario'];
        $_SESSION['admin_email']    = $user['email'];
        $_SESSION['admin_nombre']   = $user['nombre'];
        $_SESSION['admin_rol']      = $user['rol'];
        $_SESSION['_last_activity'] = time();

        $updateStmt = $pdo->prepare('UPDATE usuarios_admin SET ultimo_login = NOW() WHERE id = :id');
        $updateStmt->execute([':id' => $user['id']]);

        echo json_encode([
            'success' => true,
            'user'    => [
                'id'     => $user['id'],
                'email'  => $user['email'],
                'nombre' => $user['nombre'],
                'rol'    => $user['rol'],
            ],
        ]);
    } else {
        registrarIntentoFallido($email);
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
    }
} catch (PDOException $e) {
    error_log('Error en API login: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
