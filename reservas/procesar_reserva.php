<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reservacion.php');
    exit;
}

// ─────────────────────────────────────────────────────────────
// Whitelist de horas válidas (idéntica a check_availability.php)
// ─────────────────────────────────────────────────────────────
const HORAS_VALIDAS = [
    '07:30:00', '08:00:00', '08:30:00', '09:00:00', '09:30:00', '10:00:00',
    '10:30:00', '11:00:00', '11:30:00', '12:00:00', '12:30:00', '17:00:00',
    '17:30:00', '18:00:00', '18:30:00', '19:00:00', '19:30:00', '20:00:00',
    '20:30:00', '21:00:00', '21:30:00', '22:00:00', '22:30:00',
];

// ─────────────────────────────────────────────────────────────
// Recoger y sanear entradas
// ─────────────────────────────────────────────────────────────

$nombre      = trim($_POST['nombre']      ?? '');
$email       = trim($_POST['email']       ?? '');
$telefono    = trim($_POST['telefono']    ?? '');
$fecha       = trim($_POST['fecha']       ?? '');
$hora        = trim($_POST['hora']        ?? '');
$comentarios = mb_substr(trim($_POST['comentarios'] ?? ''), 0, 500);
$es_alergico = in_array($_POST['es_alergico'] ?? '0', ['0', '1'], true) ? (int)$_POST['es_alergico'] : 0;
$personas    = intval($_POST['personas']  ?? 0);

// Normalizar teléfono
$telefono = normalizarTelefono($telefono);

// Normalizar hora (puede venir como HH:MM sin segundos)
if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
    $hora = $hora . ':00';
}

// ─────────────────────────────────────────────────────────────
// Validaciones de servidor (fuente de verdad)
// ─────────────────────────────────────────────────────────────

$errores = [];

// Nombre
if (empty($nombre)) {
    $errores[] = 'El nombre es requerido';
} elseif (mb_strlen($nombre) > 100) {
    $errores[] = 'El nombre es demasiado largo (máximo 100 caracteres)';
}

// Email (opcional — el formulario lo acepta vacío)
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El email no es válido';
} elseif (mb_strlen($email) > 100) {
    $errores[] = 'El email es demasiado largo';
}

// Teléfono (obligatorio)
if (empty($telefono)) {
    $errores[] = 'El teléfono es requerido';
} elseif (mb_strlen($telefono) < 7 || mb_strlen($telefono) > 20) {
    $errores[] = 'El teléfono debe tener entre 7 y 20 dígitos';
} elseif (!preg_match('/^\+?\d{7,20}$/', $telefono)) {
    $errores[] = 'El formato del teléfono no es válido';
}

// Fecha
if (empty($fecha)) {
    $errores[] = 'La fecha es requerida';
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $errores[] = 'Formato de fecha inválido';
} else {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
        $errores[] = 'Fecha inválida';
    } elseif ($fechaObj < new DateTime('today')) {
        $errores[] = 'No se pueden hacer reservas en fechas pasadas';
    }
}

// Hora
if (empty($hora)) {
    $errores[] = 'La hora es requerida';
} elseif (!in_array($hora, HORAS_VALIDAS, true)) {
    $errores[] = 'La hora seleccionada no está disponible';
}

// Personas — MAX_PERSONAS es la fuente de verdad (config.php)
if ($personas < 1) {
    $errores[] = 'El número de personas es requerido';
} elseif ($personas > MAX_PERSONAS) {
    $errores[] = 'El máximo de personas es ' . MAX_PERSONAS;
}

// Comentarios: longitud máxima ya aplicada arriba con mb_substr

if (!empty($errores)) {
    mostrarError(implode('<br>', array_map('htmlspecialchars', $errores)));
}

// ─────────────────────────────────────────────────────────────
// es_evento: se activa si personas > 6 (criterio de negocio)
// ─────────────────────────────────────────────────────────────
$es_evento = ($personas > 6) ? 1 : 0;

// ─────────────────────────────────────────────────────────────
// Verificar disponibilidad y guardar con protección de concurrencia
// ─────────────────────────────────────────────────────────────

$conn = conectarDB();

// Iniciar transacción para operación atómica (Hallazgo E)
$conn->begin_transaction();

try {
    // Verificar disponibilidad y bloquear el slot de manera atómica (Hallazgo E)
    // Usando FOR UPDATE para prevenir race conditions
    $stmt_lock = $conn->prepare(
        "SELECT COUNT(*) as total FROM reservas
         WHERE fecha = ? AND hora = ? AND estado IN ('pendiente', 'confirmada')
         FOR UPDATE"
    );
    $stmt_lock->bind_param('ss', $fecha, $hora);
    $stmt_lock->execute();
    $row_check = $stmt_lock->get_result()->fetch_assoc();
    $stmt_lock->close();

    if ($row_check['total'] > 0) {
        $conn->rollback();
        $conn->close();
        mostrarError("Ya existe una reserva para $fecha a las $hora. Por favor selecciona otra hora.");
    }

    // Insertar la reserva
    $stmt = $conn->prepare(
        "INSERT INTO reservas
         (nombre, email, telefono, fecha, hora, personas, comentarios, es_alergico, es_evento, estado, fecha_creacion)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())"
    );
    $stmt->bind_param(
        'sssssisii',
        $nombre, $email, $telefono, $fecha, $hora,
        $personas, $comentarios, $es_alergico, $es_evento
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Error al insertar reserva: ' . $stmt->error);
    }

    $reserva_id = $stmt->insert_id;
    $stmt->close();

    // Actualizar registro de cliente
    if (!empty($email)) {
        $stmt_c = $conn->prepare('SELECT id, visitas FROM clientes WHERE email = ? OR no_celular = ? LIMIT 1');
        $stmt_c->bind_param('ss', $email, $telefono);
        $stmt_c->execute();
        $row_c = $stmt_c->get_result()->fetch_assoc();
        $stmt_c->close();

        if ($row_c) {
            $nuevas_visitas = $row_c['visitas'] + 1;
            $stmt_u = $conn->prepare('UPDATE clientes SET visitas = ? WHERE id = ?');
            $stmt_u->bind_param('ii', $nuevas_visitas, $row_c['id']);
            $stmt_u->execute();
            $stmt_u->close();
        } else {
            $stmt_i = $conn->prepare('INSERT INTO clientes (nombre, email, no_celular, visitas) VALUES (?, ?, ?, 1)');
            $stmt_i->bind_param('sss', $nombre, $email, $telefono);
            $stmt_i->execute();
            $stmt_i->close();
        }
    }

    $conn->commit();
    $conn->close();

    // Enviar correos FUERA de la transacción
    enviarCorreosReserva($reserva_id, $nombre, $email, $telefono, $fecha, $hora, $personas, $comentarios, $es_alergico, $es_evento);
    mostrarConfirmacion($reserva_id, $nombre, $fecha, $hora, $personas, $comentarios, $es_alergico, $es_evento);

} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    error_log('Error en procesar_reserva: ' . $e->getMessage());
    mostrarError('Error al procesar la reserva. Por favor intenta de nuevo.');
}

// ─────────────────────────────────────────────────────────────
// Funciones auxiliares
// ─────────────────────────────────────────────────────────────

function mostrarError(string $mensaje): never
{
    $msg_safe = htmlspecialchars(strip_tags($mensaje), ENT_QUOTES, 'UTF-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
    echo "<script>alert('Error:\\n$msg_safe'); window.history.back();</script>";
    echo "</body></html>";
    exit;
}

function enviarCorreosReserva(
    int $id, string $nombre, string $email, string $telefono,
    string $fecha, string $hora, int $personas, string $comentarios,
    int $es_alergico, int $es_evento
): void {
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
        'es_evento'   => $es_evento,
    ];

    // Correo al cliente solo si tiene email
    if (!empty($email)) {
        enviarCorreoCliente($reserva_data);
    }
    enviarCorreoRestaurante($reserva_data);
}

function mostrarConfirmacion(
    int $id, string $nombre, string $fecha, string $hora,
    int $personas, string $comentarios, int $es_alergico, int $es_evento
): never {
    $fecha_mostrar = date('d/m/Y', strtotime($fecha));
    $titulo_especial = null;

    if ($es_alergico && $es_evento) {
        $titulo_especial = 'Reserva Especial (Evento con Alergias)';
    } elseif ($es_alergico) {
        $titulo_especial = 'Reserva con Alergias';
    } elseif ($es_evento) {
        $titulo_especial = 'Reserva de Evento';
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <title>Reserva Exitosa</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #ffffff;
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
                background: #000000; color: #ffffff;
                display: inline-block; padding: 0.75rem 1.5rem;
                border-radius: 25px; margin: 1rem 0;
                font-weight: bold; font-size: 1.1rem; letter-spacing: 1px;
            }

            .subtitle { color: #666666; margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.5; }

            .detalles {
                background: #f5f5f5; padding: 1.5rem;
                border-radius: 12px; margin: 1.5rem 0;
                text-align: left; border: 1px solid #e0e0e0;
            }

            .detalles p {
                margin: 0.75rem 0;
                display: flex; justify-content: space-between;
                align-items: center; color: #000000;
            }

            .detalles strong { color: #666666; min-width: 100px; font-weight: 600; }
            .detalles span   { color: #000000; text-align: right; flex: 1; font-weight: 500; }

            .nota-especial {
                background: #f5f5f5; border-left: 4px solid #000000;
                padding: 1.2rem; margin: 1.5rem 0;
                text-align: left; border-radius: 8px;
                color: #000000; font-size: 0.95rem; line-height: 1.5;
            }

            .nota-especial strong { display: block; margin-bottom: 0.5rem; font-weight: 600; }

            .botones { display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap; }

            .btn {
                flex: 1; padding: 0.9rem 1.8rem;
                border-radius: 12px; text-decoration: none;
                font-weight: 600; transition: all 0.3s;
                text-align: center; min-width: 140px;
                cursor: pointer; font-size: 1rem; display: inline-block;
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
            <h1>¡Reserva Recibida!</h1>

            <?php if ($titulo_especial): ?>
            <p style="color: #000000; margin-bottom: 1rem; font-weight: 600;"><?= htmlspecialchars($titulo_especial) ?></p>
            <?php endif; ?>

            <div class="reserva-id">#<?= (int)$id ?></div>

            <p class="subtitle">
                Hemos recibido tu solicitud. Te enviaremos un email de confirmación.
            </p>

            <div class="detalles">
                <p><strong>Nombre:</strong>   <span><?= htmlspecialchars($nombre) ?></span></p>
                <p><strong>Fecha:</strong>    <span><?= htmlspecialchars($fecha_mostrar) ?></span></p>
                <p><strong>Hora:</strong>     <span><?= htmlspecialchars(substr($hora, 0, 5)) ?></span></p>
                <p><strong>Personas:</strong> <span><?= (int)$personas ?></span></p>
            </div>

            <?php if ($es_evento): ?>
            <div class="nota-especial">
                <strong>Reserva especial</strong>
                Por ser más de 6 personas, nos contactaremos personalmente contigo para confirmar todos los detalles.
            </div>
            <?php endif; ?>

            <?php if ($es_alergico): ?>
            <div class="nota-especial">
                <strong>Atención especial</strong>
                Hemos detectado que mencionas alergias. Nuestro equipo tomará medidas especiales para garantizar tu seguridad.
            </div>
            <?php endif; ?>

            <div class="botones">
                <a href="<?= htmlspecialchars(SITE_URL) ?>" class="btn btn-primary">Volver al Inicio</a>
                <a href="<?= htmlspecialchars(SITE_URL) ?>/reservas/reservacion.php" class="btn btn-secondary">Nueva Reserva</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
