<?php
function enviarCorreoConfirmacion($reserva) {
    $to      = $reserva['email'];
    $subject = 'Confirmacion de reserva - ' . SITE_NAME;

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
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
                <p>" . SITE_NAME . "</p>
            </div>
            <div class='content'>
                <h2>Hola {$reserva['nombre']}!</h2>
                <p>Tu reserva ha sido confirmada. Favor de presentarse 10 minutos antes de la hora acordada.</p>

                <div class='reserva-info'>
                    <h3>Detalles:</h3>
                    <p><strong>ID:</strong> #{$reserva['id']}</p>
                    <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($reserva['fecha'])) . "</p>
                    <p><strong>Hora:</strong> " . substr($reserva['hora'], 0, 5) . " hrs</p>
                    <p><strong>Personas:</strong> {$reserva['personas']}</p>
                    <p><strong>Estado:</strong> <span style='background: #28a745; color: white; padding: 5px 10px;'>Confirmada</span></p>
                </div>

                <p style='margin-top: 30px; font-size: 12px; color: #666;'>
                    Este es un correo automatico. Para cambios en tu reserva, contactanos.
                </p>
            </div>
        </div>
    </body>
    </html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";

    return mail($to, $subject, $message, $headers);
}

function enviarCorreoCancelacion($reserva) {
    $to      = $reserva['email'];
    $subject = 'Reserva cancelada - ' . SITE_NAME;

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
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
                <h2>Hola {$reserva['nombre']},</h2>
                <p>Lamentamos informarte que tu reserva ha sido cancelada.</p>

                <div class='reserva-info'>
                    <h3>Detalles de la reserva cancelada:</h3>
                    <p><strong>ID:</strong> #{$reserva['id']}</p>
                    <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($reserva['fecha'])) . "</p>
                    <p><strong>Hora:</strong> " . substr($reserva['hora'], 0, 5) . " hrs</p>
                    <p><strong>Estado:</strong> <span style='color: #dc3545; font-weight: bold;'>Cancelada</span></p>
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

    return mail($to, $subject, $message, $headers);
}

function enviarCorreoCliente($reserva) {
    $to           = $reserva['email'];
    $subject      = 'Confirmacion de solicitud de reserva - ' . SITE_NAME;
    $fecha_mostrar= date('d/m/Y', strtotime($reserva['fecha']));
    $es_evento    = $reserva['personas'] > 6;

    $evento_mensaje = $es_evento ?
        "<p style='background: #fff3cd; padding: 10px; border-radius: 4px;'>
            <strong>Nota importante:</strong> Al ser una reserva de mas de 6 personas,
            nos contactaremos personalmente contigo para coordinar los detalles.
        </p>" : "";

    $message = "
    <!DOCTYPE html>
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
                <h1>" . SITE_NAME . "</h1>
            </div>

            <div class='content'>
                <h2>Hola {$reserva['nombre']}!</h2>
                <p>Gracias por tu solicitud de reserva. Hemos recibido la siguiente informacion:</p>

                <div class='reserva-box'>
                    <h3>Detalles de tu solicitud:</h3>
                    <p><strong>ID de reserva:</strong> #{$reserva['id']}</p>
                    <p><strong>Fecha:</strong> $fecha_mostrar</p>
                    <p><strong>Hora:</strong> {$reserva['hora']}</p>
                    <p><strong>Personas:</strong> {$reserva['personas']}</p>
                    <p><strong>Estado:</strong> <span class='status'>Pendiente de confirmacion</span></p>
                </div>

                $evento_mensaje

                <p>Revisaremos la disponibilidad y nos pondremos en contacto contigo en 1 a 24 horas.</p>

                <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                    Este es un correo automatico. No respondas este mensaje.
                </p>
            </div>
        </div>
    </body>
    </html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";

    return mail($to, $subject, $message, $headers);
}

function enviarCorreoRestaurante($reserva) {
    $to           = ADMIN_EMAIL;
    $subject      = "NUEVA SOLICITUD DE RESERVA #{$reserva['id']}";
    $fecha_mostrar= date('d/m/Y', strtotime($reserva['fecha']));
    $es_evento    = $reserva['personas'] > 6;

    $message = "
    <!DOCTYPE html>
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
                <h2>NUEVA SOLICITUD DE RESERVA" . ($es_evento ? " [EVENTO]" : "") . "</h2>
                <p>ID: #{$reserva['id']} &bull; " . date('d/m/Y H:i') . "</p>
            </div>

            <div class='content'>
                <div class='alert'>
                    Reserva pendiente de confirmacion
                </div>

                <div class='reserva-info'>
                    <h3>Fecha y hora:</h3>
                    <p><strong>Fecha:</strong> $fecha_mostrar</p>
                    <p><strong>Hora:</strong> {$reserva['hora']}</p>
                    <p><strong>Personas:</strong> {$reserva['personas']}</p>

                    <h3 style='margin-top: 20px;'>Informacion del cliente:</h3>
                    <p><strong>Nombre:</strong> {$reserva['nombre']}</p>
                    <p><strong>Email:</strong> {$reserva['email']}</p>
                    <p><strong>Telefono:</strong> {$reserva['telefono']}</p>";

    if (!empty($reserva['comentarios'])) {
        $message .= "<p><strong>Comentarios:</strong><br>" . nl2br($reserva['comentarios']) . "</p>";
    }

    $message .= "
                </div>

                <p><strong>Acciones requeridas:</strong></p>
                <ol>
                    <li>Verificar disponibilidad</li>
                    <li>Confirmar o rechazar la reserva en el panel</li>
                    <li>El sistema enviara automaticamente el correo al cliente</li>
                </ol>

                <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                    Panel de administracion: " . SITE_URL . "/panel/login_panel.php
                </p>
            </div>
        </div>
    </body>
    </html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: {$reserva['email']}\r\n";

    return mail($to, $subject, $message, $headers);
}
