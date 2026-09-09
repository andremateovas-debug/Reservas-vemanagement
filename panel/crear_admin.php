<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_rol'] ?? '') !== 'admin') {
    header('Location: login_panel.php');
    exit;
}

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario          = trim($_POST['usuario']          ?? '');
    $email            = trim($_POST['email']            ?? '');
    $password         = $_POST['password']         ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nombre           = trim($_POST['nombre']           ?? '');
    $rol              = $_POST['rol'] ?? 'recepcion';

    if (empty($usuario) || empty($email) || empty($password) || empty($nombre)) {
        $error = 'Todos los campos son obligatorios';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contrasenas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contrasena debe tener al menos 6 caracteres';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no valido';
    } else {
        $conn = conectarDB();

        $sql_check  = "SELECT id FROM usuarios_admin WHERE usuario = ? OR email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $usuario, $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = 'El usuario o email ya estan registrados';
            $stmt_check->close();
        } else {
            $stmt_check->close();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql_insert  = "INSERT INTO usuarios_admin (usuario, email, password_hash, nombre, rol)
                            VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("sssss", $usuario, $email, $password_hash, $nombre, $rol);

            if ($stmt_insert->execute()) {
                $mensaje  = 'Administrador creado exitosamente';
                $usuario  = $email = $nombre = '';
            } else {
                $error = 'Error al crear el administrador: ' . $conn->error;
            }

            $stmt_insert->close();
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Administrador</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }

        .header {
            background: #000;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 { margin: 0; }

        .header a {
            color: white;
            text-decoration: none;
            background: #666;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .header a:hover { background: #333; }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h2 { margin-bottom: 20px; color: #333; padding-bottom: 10px; border-bottom: 2px solid #000; }

        .form-group { margin-bottom: 20px; }

        label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus { outline: none; border-color: #000; }

        button {
            background: #000;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        button:hover { background: #333; }

        .mensaje { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .error   { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }

        .password-strength { margin-top: 5px; font-size: 14px; }
        .strength-weak   { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #666; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Crear Administrador</h1>
        <a href="panel_reservas.php">Volver al Panel</a>
    </div>

    <div class="container">
        <h2>Nuevo Administrador</h2>

        <?php if ($mensaje): ?>
            <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">Usuario *</label>
                <input type="text" id="usuario" name="usuario"
                       value="<?php echo htmlspecialchars($usuario ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email"
                       value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="nombre">Nombre Completo *</label>
                <input type="text" id="nombre" name="nombre"
                       value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Contrasena *</label>
                <input type="password" id="password" name="password" required onkeyup="checkPasswordStrength()">
                <div class="password-strength" id="passwordStrength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Contrasena *</label>
                <input type="password" id="confirm_password" name="confirm_password" required onkeyup="checkPasswordMatch()">
                <div class="password-strength" id="passwordMatch"></div>
            </div>

            <div class="form-group">
                <label for="rol">Rol *</label>
                <select id="rol" name="rol" required>
                    <option value="recepcion" <?php echo ($rol ?? 'recepcion') == 'recepcion' ? 'selected' : ''; ?>>Recepcionista</option>
                    <option value="supervisor" <?php echo ($rol ?? 'recepcion') == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                    <option value="admin" <?php echo ($rol ?? 'recepcion') == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                </select>
            </div>

            <button type="submit">Crear Administrador</button>
        </form>

        <div class="back-link">
            <a href="panel_reservas.php">Volver al Panel Principal</a>
        </div>
    </div>

    <script>
        function checkPasswordStrength() {
            const password    = document.getElementById('password').value;
            const strengthDiv = document.getElementById('passwordStrength');

            if (password.length === 0) { strengthDiv.innerHTML = ''; return; }

            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            let message = '', className = '';
            switch (strength) {
                case 0: case 1: message = 'Debil';  className = 'strength-weak';   break;
                case 2: case 3: message = 'Media';  className = 'strength-medium'; break;
                case 4:         message = 'Fuerte'; className = 'strength-strong'; break;
            }

            strengthDiv.innerHTML = `<span class="${className}">${message}</span>`;
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm  = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');

            if (confirm.length === 0) { matchDiv.innerHTML = ''; return; }

            if (password === confirm) {
                matchDiv.innerHTML = '<span class="strength-strong">Las contrasenas coinciden</span>';
            } else {
                matchDiv.innerHTML = '<span class="strength-weak">Las contrasenas no coinciden</span>';
            }
        }
    </script>
</body>
</html>
