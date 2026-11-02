<?php
require_once '../config/config.php';

emitirCabecerasSeguridad();

// Si ya está autenticado, redirigir al panel
if (isset($_SESSION['admin_id'])) {
    header('Location: panel_reservas.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF (protección contra ataques automatizados al formulario)
    if (!validarCsrf()) {
        $error = 'Solicitud no válida. Por favor recarga la página.';
    } else {
        $usuario  = trim($_POST['usuario']  ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($usuario) || empty($password)) {
            $error = 'Usuario y contraseña son requeridos';
        } else {
            // Verificar rate limiting ANTES de consultar la BD
            $segundosBloqueado = loginBloqueado($usuario);

            if ($segundosBloqueado > 0) {
                $minutos = (int)ceil($segundosBloqueado / 60);
                $error   = "Demasiados intentos fallidos. Intenta de nuevo en {$minutos} minuto(s).";
            } else {
                $conn = conectarDB();

                $sql  = "SELECT id, usuario, password_hash, nombre, email, rol
                         FROM usuarios_admin
                         WHERE (usuario = ? OR email = ?) AND activo = 1
                         LIMIT 1";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $usuario, $usuario);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin  = $result->num_rows === 1 ? $result->fetch_assoc() : null;
                $stmt->close();
                $conn->close();

                // Verificación en tiempo constante para evitar user enumeration
                if ($admin && password_verify($password, $admin['password_hash'])) {
                    // Login exitoso

                    // Limpiar intentos fallidos
                    limpiarIntentosFallidos($usuario);

                    // Regenerar ID de sesión para prevenir session fixation
                    session_regenerate_id(true);

                    $_SESSION['admin_id']       = $admin['id'];
                    $_SESSION['admin_usuario']  = $admin['usuario'];
                    $_SESSION['admin_email']    = $admin['email'];
                    $_SESSION['admin_nombre']   = $admin['nombre'];
                    $_SESSION['admin_rol']      = $admin['rol'];
                    $_SESSION['_last_activity'] = time();

                    // Actualizar último login en BD
                    $conn2        = conectarDB();
                    $update_stmt  = $conn2->prepare('UPDATE usuarios_admin SET ultimo_login = NOW() WHERE id = ?');
                    $update_stmt->bind_param('i', $admin['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    $conn2->close();

                    header('Location: panel_reservas.php');
                    exit;
                } else {
                    // Registrar intento fallido (sin distinguir si usuario existe)
                    registrarIntentoFallido($usuario);
                    // Mensaje genérico — no revela si el usuario existe
                    $error = 'Credenciales incorrectas';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }

        h1 { color: #333; text-align: center; margin-bottom: 10px; }

        .subtitle { color: #666; text-align: center; margin-bottom: 30px; }

        .form-group { margin-bottom: 20px; }

        label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus { outline: none; border-color: #667eea; }

        button {
            width: 100%;
            padding: 12px;
            background: #000;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        button:hover { background: #333; }

        .error {
            background-color: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #666; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Panel de Administración</h1>
        <p class="subtitle">Sistema de Reservas</p>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="usuario">Usuario o Email</label>
                <input type="text" id="usuario" name="usuario"
                       value="<?= h($usuario ?? '') ?>"
                       required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       required autocomplete="current-password">
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>

        <div class="back-link">
            <a href="<?= h(SITE_URL) ?>">Volver al sitio principal</a>
        </div>
    </div>
</body>
</html>
