<?php
session_start();
require_once '../config/config.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: panel_reservas.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario  = trim($_POST['usuario']  ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        $error = 'Usuario y contrasena son requeridos';
    } else {
        $conn = conectarDB();

        $sql  = "SELECT id, usuario, password_hash, nombre, email, rol
                 FROM usuarios_admin
                 WHERE (usuario = ? OR email = ?) AND activo = 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $usuario, $usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id']      = $admin['id'];
                $_SESSION['admin_usuario'] = $admin['usuario'];
                $_SESSION['admin_email']   = $admin['email'];
                $_SESSION['admin_nombre']  = $admin['nombre'];
                $_SESSION['admin_rol']     = $admin['rol'];

                $update_sql  = "UPDATE usuarios_admin SET ultimo_login = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $admin['id']);
                $update_stmt->execute();

                header('Location: panel_reservas.php');
                exit;
            } else {
                $error = 'Credenciales incorrectas';
            }
        } else {
            $error = 'Credenciales incorrectas';
        }

        $stmt->close();
        $conn->close();
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
        <h1>Panel de Administracion</h1>
        <p class="subtitle">Sistema de Reservas</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">Usuario o Email</label>
                <input type="text" id="usuario" name="usuario"
                       value="<?php echo htmlspecialchars($usuario ?? ''); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contrasena</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Iniciar Sesion</button>
        </form>

        <div class="back-link">
            <a href="../index.html">Volver al sitio principal</a>
        </div>
    </div>
</body>
</html>
