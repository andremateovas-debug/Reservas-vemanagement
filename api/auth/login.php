<?php
require_once '../config_api.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $email    = $data['email']    ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email y contraseña son requeridos']);
        exit;
    }

    try {
        $pdo = conectarPDO();

        $sql = "SELECT id, usuario, password_hash, email, nombre, rol
                FROM usuarios_admin
                WHERE (email = :email OR usuario = :email)
                AND activo = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_id']     = $user['id'];
            $_SESSION['admin_email']  = $user['email'] ?? $user['usuario'];
            $_SESSION['admin_nombre'] = $user['nombre'] ?? 'Administrador';
            $_SESSION['admin_rol']    = $user['rol'] ?? 'admin';

            $updateSql  = "UPDATE usuarios_admin SET ultimo_login = NOW() WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([':id' => $user['id']]);

            echo json_encode([
                'success' => true,
                'user' => [
                    'id'     => $user['id'],
                    'email'  => $user['email'] ?? $user['usuario'],
                    'nombre' => $user['nombre'] ?? 'Administrador',
                    'rol'    => $user['rol'] ?? 'admin'
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciales incorrectas']);
        }
    } catch (PDOException $e) {
        error_log("Error en login: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error del servidor']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
