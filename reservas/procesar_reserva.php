<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: reservacion.php");
    exit;
}

$conn = conectarDB();

$errores = [];

$campos = ['nombre', 'telefono', 'fecha', 'hora', 'personas'];
foreach ($campos as $campo) {
    if (empty($_POST[$campo])) {
        $errores[] = ucfirst($campo) . " es requerido";
    }
}

if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errores[] = "Email invalido";
}

$personas = intval($_POST['personas'] ?? 0);
if ($personas < 1 || $personas > 20) {
    $errores[] = "Personas debe ser 1-20";
}

if (!empty($errores)) {
    mostrarError(implode('<br>', array_map('htmlspecialchars', $errores)));
}

$nombre      = trim($_POST['nombre']);
$email       = trim($_POST['email'] ?? '');
$telefono    = trim($_POST['telefono']);
$fecha       = $_POST['fecha'];
$hora        = $_POST['hora'];
$comentarios = trim($_POST['comentarios'] ?? '');
$es_alergico = intval($_POST['es_alergico'] ?? 0);
$es_evento   = ($personas > 19) ? 1 : 0;

$hora_completa = (strlen($hora) == 5) ? $hora . ':00' : $hora;

$sql_check  = "SELECT COUNT(*) as total
               FROM reservas
               WHERE fecha = ?
               AND hora = ?
               AND estado IN ('pendiente', 'confirmada')";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ss", $fecha, $hora_completa);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$row_check    = $result_check->fetch_assoc();

if ($row_check['total'] > 0) {
    mostrarError("Ya existe una reserva para $fecha a las $hora. Por favor selecciona otra hora.");
}

$sql  = "INSERT INTO reservas
         (nombre, email, telefono, fecha, hora, personas, comentarios, es_alergico, es_evento, estado, fecha_creacion)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssisii", $nombre, $email, $telefono, $fecha, $hora_completa, $personas, $comentarios, $es_alergico, $es_evento);

if ($stmt->execute()) {
    $sql_cliente  = "SELECT id, visitas FROM clientes WHERE email = ? OR no_celular = ?";
    $stmt_cliente = $conn->prepare($sql_cliente);
    $stmt_cliente->bind_param("ss", $email, $telefono);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();

    if ($result_cliente->num_rows > 0) {
        $row             = $result_cliente->fetch_assoc();
        $nuevas_visitas  = $row['visitas'] + 1;
        $sql_update      = "UPDATE clientes SET visitas = ? WHERE id = ?";
        $stmt_update     = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $nuevas_visitas, $row['id']);
        $stmt_update->execute();
    } else {
        $sql_insert_cliente = "INSERT INTO clientes (nombre, email, no_celular, visitas) VALUES (?, ?, ?, 1)";
        $stmt_insert        = $conn->prepare($sql_insert_cliente);
        $stmt_insert->bind_param("sss", $nombre, $email, $telefono);
        $stmt_insert->execute();
    }

    $reserva_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();

    enviarCorreosReserva($reserva_id, $nombre, $email, $telefono, $fecha, $hora, $personas, $comentarios, $es_alergico, $es_evento);
    mostrarConfirmacion($reserva_id, $nombre, $fecha, $hora, $personas, $comentarios, $es_alergico, $es_evento);

} else {
    mostrarError("Error al guardar: " . $conn->error);
}

function mostrarError($mensaje) {
    echo "<script>
            alert('Error:\\n" . addslashes(strip_tags($mensaje)) . "');
            window.history.back();
          </script>";
    exit;
}

function enviarCorreosReserva($id, $nombre, $email, $telefono, $fecha, $hora, $personas, $comentarios, $es_alergico = 0, $es_evento = 0) {
    require_once __DIR__ . '/../panel/correos.php';

    $reserva_data = [
        'id'          => $id,
        'nombre'      => $nombre,
        'email'       => $email,
        'telefono'    => $telefono,
        'fecha'       => $fecha,
        'hora'        => $hora,
        'personas'    => $personas,
        'comentarios' => $comentarios,
        'es_alergico' => $es_alergico,
        'es_evento'   => $es_evento
    ];

    enviarCorreoCliente($reserva_data);
    enviarCorreoRestaurante($reserva_data);
}

function mostrarConfirmacion($id, $nombre, $fecha, $hora, $personas, $comentarios, $es_alergico = 0, $es_evento = 0) {
    $fecha_mostrar = date('d/m/Y', strtotime($fecha));

    if ($es_alergico && $es_evento) {
        $titulo_especial = "Reserva Especial (Evento con Alergias)";
    } elseif ($es_alergico) {
        $titulo_especial = "Reserva con Alergias";
    } elseif ($es_evento) {
        $titulo_especial = "Reserva de Evento";
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Reserva Exitosa</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: #ffffff;
                margin: 0;
                padding: 20px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #000000;
            }

            .success-card {
                background: #ffffff;
                padding: 2.5rem;
                border-radius: 24px;
                max-width: 500px;
                width: 100%;
                text-align: center;
                border: 1px solid #e0e0e0;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                position: relative;
                overflow: hidden;
            }

            .success-card::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 3px;
                background: #000000;
            }

            .success-icon { font-size: 64px; margin-bottom: 1.5rem; }

            h1 { margin-bottom: 0.5rem; font-size: 1.8rem; color: #000000; font-weight: 700; }

            .reserva-id {
                background: #000000;
                color: #ffffff;
                display: inline-block;
                padding: 0.75rem 1.5rem;
                border-radius: 25px;
                margin: 1rem 0;
                font-weight: bold;
                font-size: 1.1rem;
                letter-spacing: 1px;
            }

            .subtitle { color: #666666; margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.5; }

            .detalles {
                background: #f5f5f5;
                padding: 1.5rem;
                border-radius: 12px;
                margin: 1.5rem 0;
                text-align: left;
                border: 1px solid #e0e0e0;
            }

            .detalles p {
                margin: 0.75rem 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                color: #000000;
            }

            .detalles strong { color: #666666; min-width: 100px; font-weight: 600; }
            .detalles span   { color: #000000; text-align: right; flex: 1; font-weight: 500; }

            .nota-especial {
                background: #f5f5f5;
                border-left: 4px solid #000000;
                padding: 1.2rem;
                margin: 1.5rem 0;
                text-align: left;
                border-radius: 8px;
                color: #000000;
                font-size: 0.95rem;
                line-height: 1.5;
            }

            .nota-especial strong { display: block; margin-bottom: 0.5rem; font-weight: 600; }

            .botones { display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap; }

            .btn {
                flex: 1;
                padding: 0.9rem 1.8rem;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s;
                text-align: center;
                min-width: 140px;
                cursor: pointer;
                font-size: 1rem;
                display: inline-block;
            }

            .btn-primary { background: #000000; color: #ffffff; border: none; }
            .btn-primary:hover { background: #333333; transform: translateY(-2px); }

            .btn-secondary { background: #ffffff; color: #000000; border: 1px solid #e0e0e0; }
            .btn-secondary:hover { background: #f5f5f5; border-color: #000000; transform: translateY(-2px); }

            @media (max-width: 480px) {
                .botones { flex-direction: column; }
                .btn { width: 100%; }
                .detalles p { flex-direction: column; align-items: flex-start; gap: 5px; }
                .detalles span { text-align: left; }
            }
        </style>
    </head>
    <body>
        <div class="success-card">
            <div class="success-icon">&#10003;</div>
            <h1>Reserva Recibida!</h1>

            <?php if (isset($titulo_especial)): ?>
            <p style="color: #000000; margin-bottom: 1rem; font-weight: 600;"><?php echo $titulo_especial; ?></p>
            <?php endif; ?>

            <div class="reserva-id">#<?php echo $id; ?></div>

            <p class="subtitle">
                Hemos recibido tu solicitud. Te enviaremos un email de confirmacion.
            </p>

            <div class="detalles">
                <p><strong>Nombre:</strong> <span><?php echo htmlspecialchars($nombre); ?></span></p>
                <p><strong>Fecha:</strong>  <span><?php echo $fecha_mostrar; ?></span></p>
                <p><strong>Hora:</strong>   <span><?php echo $hora; ?></span></p>
                <p><strong>Personas:</strong><span><?php echo $personas; ?></span></p>
            </div>

            <?php if ($es_evento): ?>
            <div class="nota-especial">
                <strong>Reserva especial</strong>
                Por ser mas de 6 personas, nos contactaremos personalmente contigo para confirmar todos los detalles.
            </div>
            <?php endif; ?>

            <?php if ($es_alergico): ?>
            <div class="nota-especial">
                <strong>Atencion especial</strong>
                Hemos detectado que mencionas alergias. Nuestro equipo tomara medidas especiales para garantizar tu seguridad.
            </div>
            <?php endif; ?>

            <div class="botones">
                <a href="<?php echo SITE_URL; ?>" class="btn btn-primary">Volver al Inicio</a>
                <a href="<?php echo SITE_URL; ?>/reservas/reservacion.php" class="btn btn-secondary">Nueva Reserva</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
