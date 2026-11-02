<?php
/**
 * correos.php — Funciones de envío de correo
 *
 * Todas las interpolaciones de datos dinámicos se escapan correctamente
 * con htmlspecialchars() + ENT_QUOTES + UTF-8 (Hallazgo I).
 */

// ─────────────────────────────────────────────────────────────
// Helper interno de escape
// ─────────────────────────────────────────────────────────────

function _he(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─────────────────────────────────────────────────────────────
// Correo al cliente: reserva confirmada
// ─────────────────────────────────────────────────────────────

function enviarCorreoConfirmacion(array $reserva): bool
{
    $to            = $reserva['email'] ?? '';
    $nombre        = _he($reserva['nombre'] ?? '');
    $id            = (int)($reserva['id'] ?? 0);
    $fecha_fmt     = _he(date('d/m/Y', strtotime($reserva['fecha'])));
    $hora_fmt      = _he(substr($reserva['hora'], 0, 5)) . ' hrs';
    $personas      = (int)($reserva['personas'] ?? 0);
    $site_name     = _he(SITE_NAME);
    $subject       = 'Confirmación de reserva - ' . SITE_NAME;

    $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; background: #f8f9fa; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #000; color: white; padding: 20px; text-align: center; }
        .content { background: white; padding: 30px; }
        .reserva-info { background: #f8f9fa; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Reserva Confirmada</h1>
            <p>$site_name</p>
        </div>
        <div class='content'>
            <h2>Hola $nombre!</h2>
            <p>Tu reserva ha sido confirmada. Favor de presentarse 10 minutos antes de la hora acordada.</p>
            <div class='reserva-info'>
                <h3>Detalles:</h3>
                <p><strong>ID:</strong> #$id</p>
                <p><strong>Fecha:</strong> $fecha_fmt</p>
                <p><strong>Hora:</strong> $hora_fmt</p>
                <p><strong>Personas:</strong> $personas</p>
                <p><strong>Estado:</strong>
                    <span style='background: #28a745; color: white; padding: 5px 10px;'>Confirmada</span>
                </p>
            </div>
            <p style='margin-top: 30px; font-size: 12px; color: #666;'>
                Este es un correo automático. Para cambios en tu reserva, contáctanos.
            </p>
        </div>
    </div>
</body>
</html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";

    $result = mail($to, $subject, $message, $headers);
    if (!$result) {
        error_log("Error al enviar correo de confirmación a: $to");
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────
// Correo al cliente: reserva cancelada
// ─────────────────────────────────────────────────────────────

function enviarCorreoCancelacion(array $reserva): bool
{
    $to        = $reserva['email'] ?? '';
    $nombre    = _he($reserva['nombre'] ?? '');
    $id        = (int)($reserva['id'] ?? 0);
    $fecha_fmt = _he(date('d/m/Y', strtotime($reserva['fecha'])));
    $hora_fmt  = _he(substr($reserva['hora'], 0, 5)) . ' hrs';
    $subject   = 'Reserva cancelada - ' . SITE_NAME;

    $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; background: #f8f9fa; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #000; color: white; padding: 20px; text-align: center; }
        .content { background: white; padding: 30px; }
        .reserva-info { background: #f8f9fa; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Reserva Cancelada</h1>
        </div>
        <div class='content'>
            <h2>Hola $nombre,</h2>
            <p>Lamentamos informarte que tu reserva ha sido cancelada.</p>
            <div class='reserva-info'>
                <h3>Detalles de la reserva cancelada:</h3>
                <p><strong>ID:</strong> #$id</p>
                <p><strong>Fecha:</strong> $fecha_fmt</p>
                <p><strong>Hora:</strong> $hora_fmt</p>
                <p><strong>Estado:</strong>
                    <span style='color: #dc3545; font-weight: bold;'>Cancelada</span>
                </p>
            </div>
            <p>Para una nueva reserva, visita nuestro sitio web.</p>
        </div>
    </div>
</body>
</html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";

    $result = mail($to, $subject, $message, $headers);
    if (!$result) {
        error_log("Error al enviar correo de cancelación a: $to");
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────
// Correo al cliente: solicitud recibida (estado pendiente)
// ─────────────────────────────────────────────────────────────

function enviarCorreoCliente(array $reserva): bool
{
    $to           = $reserva['email'] ?? '';
    $nombre       = _he($reserva['nombre'] ?? '');
    $id           = (int)($reserva['id'] ?? 0);
    $personas     = (int)($reserva['personas'] ?? 0);
    $hora_fmt     = _he($reserva['hora'] ?? '');
    $fecha_fmt    = _he(date('d/m/Y', strtotime($reserva['fecha'])));
    $site_name    = _he(SITE_NAME);
    $es_evento    = $personas > 6;
    $subject      = 'Confirmación de solicitud de reserva - ' . SITE_NAME;

    $evento_mensaje = $es_evento
        ? "<p style='background: #fff3cd; padding: 10px; border-radius: 4px;'>
            <strong>Nota importante:</strong> Al ser una reserva de más de 6 personas,
            nos contactaremos personalmente contigo para coordinar los detalles.
           </p>"
        : '';

    // Escape de comentarios del cliente antes de incluirlos
    $comentarios_html = '';
    if (!empty($reserva['comentarios'])) {
        $comentarios_html = '<p><strong>Comentarios:</strong> ' . _he($reserva['comentarios']) . '</p>';
    }

    $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #000; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .reserva-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd; }
        .status { display: inline-block; padding: 5px 10px; background: #f0ad4e; color: white; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>$site_name</h1>
        </div>
        <div class='content'>
            <h2>Hola $nombre!</h2>
            <p>Gracias por tu solicitud de reserva. Hemos recibido la siguiente información:</p>
            <div class='reserva-box'>
                <h3>Detalles de tu solicitud:</h3>
                <p><strong>ID de reserva:</strong> #$id</p>
                <p><strong>Fecha:</strong> $fecha_fmt</p>
                <p><strong>Hora:</strong> $hora_fmt</p>
                <p><strong>Personas:</strong> $personas</p>
                $comentarios_html
                <p><strong>Estado:</strong> <span class='status'>Pendiente de confirmación</span></p>
            </div>
            $evento_mensaje
            <p>Revisaremos la disponibilidad y nos pondremos en contacto contigo en 1 a 24 horas.</p>
            <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                Este es un correo automático. No respondas este mensaje.
            </p>
        </div>
    </div>
</body>
</html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";

    $result = mail($to, $subject, $message, $headers);
    if (!$result) {
        error_log("Error al enviar correo de solicitud a: $to");
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────
// Correo al restaurante: nueva reserva recibida
// ─────────────────────────────────────────────────────────────

function enviarCorreoRestaurante(array $reserva): bool
{
    $to         = ADMIN_EMAIL;
    $nombre     = _he($reserva['nombre']   ?? '');
    $email_c    = _he($reserva['email']    ?? '');
    $telefono   = _he($reserva['telefono'] ?? 'No proporcionado');
    $id         = (int)($reserva['id'] ?? 0);
    $personas   = (int)($reserva['personas'] ?? 0);
    $hora_fmt   = _he($reserva['hora']     ?? '');
    $fecha_fmt  = _he(date('d/m/Y', strtotime($reserva['fecha'])));
    $es_evento  = $personas > 6;
    $subject    = "NUEVA SOLICITUD DE RESERVA #$id";
    $panel_url  = _he(SITE_URL . '/panel/login_panel.php');

    // Escape de comentarios del cliente
    $comentarios_html = '';
    if (!empty($reserva['comentarios'])) {
        $comentarios_html = '<p><strong>Comentarios:</strong><br>' . nl2br(_he($reserva['comentarios'])) . '</p>';
    }

    $evento_tag = $es_evento ? ' [EVENTO]' : '';

    $message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #000; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; }
        .alert { background: #fff3cd; border-left: 4px solid #f0ad4e; padding: 10px; margin: 15px 0; }
        .reserva-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>NUEVA SOLICITUD DE RESERVA$evento_tag</h2>
            <p>ID: #$id &bull; " . _he(date('d/m/Y H:i')) . "</p>
        </div>
        <div class='content'>
            <div class='alert'>Reserva pendiente de confirmación</div>
            <div class='reserva-info'>
                <h3>Fecha y hora:</h3>
                <p><strong>Fecha:</strong> $fecha_fmt</p>
                <p><strong>Hora:</strong> $hora_fmt</p>
                <p><strong>Personas:</strong> $personas</p>
                <h3 style='margin-top: 20px;'>Información del cliente:</h3>
                <p><strong>Nombre:</strong> $nombre</p>
                <p><strong>Email:</strong> $email_c</p>
                <p><strong>Teléfono:</strong> $telefono</p>
                $comentarios_html
            </div>
            <p><strong>Acciones requeridas:</strong></p>
            <ol>
                <li>Verificar disponibilidad</li>
                <li>Confirmar o rechazar la reserva en el panel</li>
                <li>El sistema enviará automáticamente el correo al cliente</li>
            </ol>
            <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                Panel de administración: $panel_url
            </p>
        </div>
    </div>
</body>
</html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . _he($reserva['email'] ?? ADMIN_EMAIL) . "\r\n";

    $result = mail($to, $subject, $message, $headers);
    if (!$result) {
        error_log("Error al enviar correo de notificación al restaurante para reserva #$id");
    }
    return $result;
}
