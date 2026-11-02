<?php
require_once '../config/config.php';

emitirCabecerasSeguridad();
requirePermission('correos.enviar');

$conn = conectarDB();

$filtro       = $_GET['filtro'] ?? 'hoy';
$fecha_custom = $_GET['fecha']  ?? '';

$where_param = null;
$where_type  = null;

switch ($filtro) {
    case 'manana':
        $where  = "fecha = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        $titulo = "Manana - " . date('d/m/Y', strtotime('+1 day'));
        break;
    case 'semana':
        $where  = "fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $titulo = "Esta semana";
        break;
    case 'fecha':
        if (!empty($fecha_custom)) {
            $where       = "fecha = ?";
            $where_param = $fecha_custom;
            $where_type  = "s";
            $titulo      = date('d/m/Y', strtotime($fecha_custom));
        } else {
            $where  = "fecha = CURDATE()";
            $titulo = "Hoy - " . date('d/m/Y');
        }
        break;
    default:
        $filtro = 'hoy';
        $where  = "fecha = CURDATE()";
        $titulo = "Hoy - " . date('d/m/Y');
}

$msg_resultado = '';
$msg_tipo      = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!validarCsrf()) {
        $msg_resultado = 'Solicitud no válida. Por favor recarga la página.';
        $msg_tipo      = 'err';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($msg_resultado)) {
    $modo   = $_POST['modo']    ?? '';
    $asunto = trim($_POST['asunto']  ?? 'Mensaje de ' . SITE_NAME);
    $texto  = trim($_POST['mensaje'] ?? '');

    if (!$texto) {
        $msg_resultado = 'El mensaje no puede estar vacio.';
        $msg_tipo      = 'err';

    } elseif ($modo === 'reserva' && !empty($_POST['reserva_id'])) {
        $rid  = (int)$_POST['reserva_id'];
        $stmt = $conn->prepare(
            "SELECT id, nombre, email, fecha, hora, personas FROM reservas WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($r) {
            $ok = enviarCorreoConNota($r, $asunto, $texto);
            $msg_resultado = $ok
                ? "Correo enviado a {$r['nombre']} ({$r['email']})"
                : "Error al enviar. Revisa la configuracion de mail.";
            if (!$ok) $msg_tipo = 'err';
        }

    } elseif ($modo === 'libre') {
        $dest_email  = trim($_POST['dest_email']  ?? '');
        $dest_nombre = trim($_POST['dest_nombre'] ?? 'Cliente');

        if (!filter_var($dest_email, FILTER_VALIDATE_EMAIL)) {
            $msg_resultado = 'El email ingresado no es valido.';
            $msg_tipo      = 'err';
        } else {
            $r_libre = [
                'id'       => '-',
                'nombre'   => $dest_nombre,
                'email'    => $dest_email,
                'fecha'    => '',
                'hora'     => '',
                'personas' => '',
            ];
            $ok = enviarCorreoConNota($r_libre, $asunto, $texto, true);
            $msg_resultado = $ok
                ? "Correo enviado a {$dest_nombre} ({$dest_email})"
                : "Error al enviar. Revisa la configuracion de mail.";
            if (!$ok) $msg_tipo = 'err';
        }
    }
}

function enviarCorreoConNota(array $r, string $asunto, string $texto, bool $libre = false): bool
{
    // Escape contextual correcto de todos los datos del cliente (Hallazgo I)
    $nombre_safe = htmlspecialchars($r['nombre'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $txt_html    = nl2br(htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $asunto_safe = htmlspecialchars($asunto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $bloque_reserva = '';
    if (!$libre && !empty($r['fecha'])) {
        $fecha_fmt   = date('d/m/Y', strtotime($r['fecha']));
        $hora_fmt    = htmlspecialchars(substr($r['hora'], 0, 5), ENT_QUOTES, 'UTF-8') . ' hrs';
        $personas    = (int)($r['personas'] ?? 0);
        $id_reserva  = (int)($r['id'] ?? 0);

        $bloque_reserva = "
        <div style='background:#f8f9fa; padding:15px; margin:20px 0;'>
            <h3 style='margin:0 0 10px;'>Detalles de tu reserva:</h3>
            <p style='margin:4px 0;'><strong>ID:</strong> #" . $id_reserva . "</p>
            <p style='margin:4px 0;'><strong>Fecha:</strong> " . htmlspecialchars($fecha_fmt, ENT_QUOTES, 'UTF-8') . "</p>
            <p style='margin:4px 0;'><strong>Hora:</strong> $hora_fmt</p>
            <p style='margin:4px 0;'><strong>Personas:</strong> $personas</p>
            <p style='margin:4px 0;'><strong>Estado:</strong>
                <span style='background:#28a745;color:white;padding:4px 10px;border-radius:4px;font-weight:bold;'>
                    Confirmada
                </span>
            </p>
        </div>";
    }

    $site_name_safe = htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8');

    $body = "
<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family:Arial,sans-serif; line-height:1.6; background:#f8f9fa; }
    .container { max-width:600px; margin:0 auto; }
    .header { background:#000; color:white; padding:20px; text-align:center; }
    .content { background:white; padding:30px; }
    .nota {
        background:#fff8e1; border-left:4px solid #f59e0b;
        padding:15px 18px; margin:20px 0; border-radius:0 6px 6px 0;
        font-size:15px; color:#333;
    }
    .nota strong { display:block; margin-bottom:8px; }
</style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h1>Reserva Confirmada</h1>
        <p>$site_name_safe</p>
    </div>
    <div class='content'>
        <h2>Hola $nombre_safe!</h2>
        <p>Tu reserva ha sido confirmada. Favor de presentarse 10 minutos antes de la hora acordada.</p>

        $bloque_reserva

        <div class='nota'>
            <strong>Nota del restaurante:</strong>
            $txt_html
        </div>

        <p style='margin-top:30px;font-size:12px;color:#666;'>
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

    return mail($r['email'], $asunto_safe, $body, $headers);
}

$sql    = "SELECT id, fecha, hora, nombre, personas, estado, email, telefono
           FROM reservas WHERE $where AND estado != 'cancelada'
           ORDER BY fecha, hora";
$stmt_r = $conn->prepare($sql);
if ($where_param !== null) {
    $stmt_r->bind_param($where_type, $where_param);
}
$stmt_r->execute();
$result   = $stmt_r->get_result();
$reservas = [];
while ($row = $result->fetch_assoc()) $reservas[] = $row;
$stmt_r->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correos - Panel</title>
    <style>
        :root {
            --bg: #fafafa; --surface: #ffffff; --text: #333; --light: #666;
            --primary: #000; --success: #10b981; --warning: #f59e0b;
            --border: #e5e7eb; --info: #3b82f6;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
            background:var(--bg); color:var(--text); line-height:1.5;
        }

        .header {
            background:var(--primary); color:white; padding:1rem;
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:100;
        }

        .header h1 { font-size:1.1rem; font-weight:500; }

        .header a {
            background:rgba(255,255,255,.1); color:white; border:none;
            padding:.5rem 1rem; border-radius:4px; text-decoration:none;
            font-size:.9rem; transition:background .2s;
        }

        .header a:hover { background:rgba(255,255,255,.2); }

        .container { max-width:1100px; margin:0 auto; padding:1rem; }

        .tabs {
            display:flex; gap:0; margin-bottom:1rem;
            background:var(--surface); border:1px solid var(--border);
            border-radius:8px; overflow:hidden;
        }

        .tab {
            flex:1; padding:.75rem 1rem; text-align:center; cursor:pointer;
            font-size:.95rem; font-weight:500; border:none; background:transparent;
            color:var(--light); transition:all .2s; border-right:1px solid var(--border);
        }

        .tab:last-child { border-right:none; }
        .tab:hover { background:var(--bg); color:var(--text); }
        .tab.active { background:var(--primary); color:white; }

        .filtros {
            display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;
            background:var(--surface); padding:.75rem 1rem; border-radius:8px;
            border:1px solid var(--border); margin-bottom:1rem;
        }

        .filtro-btn {
            padding:.5rem 1rem; background:white; border:1px solid var(--border);
            border-radius:6px; cursor:pointer; font-size:.9rem; color:var(--text);
            text-decoration:none; transition:all .2s; white-space:nowrap;
        }

        .filtro-btn:hover { background:var(--bg); border-color:var(--primary); }
        .filtro-btn.active { background:var(--primary); color:white; border-color:var(--primary); }

        .fecha-custom { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
        .fecha-custom input { padding:.5rem; border:1px solid var(--border); border-radius:6px; font-size:.9rem; min-width:0; flex:1 1 120px; }

        .alerta { padding:.85rem 1rem; border-radius:8px; margin-bottom:1rem; font-weight:500; font-size:.95rem; }
        .alerta.ok  { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
        .alerta.err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

        .seccion-titulo { font-size:1.1rem; font-weight:600; margin-bottom:.75rem; }

        .reservas-lista { background:var(--surface); border-radius:8px; border:1px solid var(--border); overflow:hidden; margin-bottom:1rem; }

        .reserva-item {
            padding:1rem 1.25rem; border-bottom:1px solid var(--border);
            display:grid; grid-template-columns:80px 1fr auto;
            gap:1rem; align-items:center; transition:background .15s; cursor:pointer;
        }

        .reserva-item:last-child { border-bottom:none; }
        .reserva-item:hover { background:#f0f9ff; }
        .reserva-item.seleccionado { background:#eff6ff; border-left:3px solid var(--info); }

        .reserva-hora { font-weight:600; font-size:1.05rem; }
        .reserva-info-wrap { display:flex; flex-direction:column; gap:.2rem; }
        .reserva-nombre { font-weight:500; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
        .reserva-det { font-size:.82rem; color:var(--light); }

        .badge { display:inline-block; padding:.2rem .6rem; border-radius:12px; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
        .badge-pendiente  { background:#fef3c7; color:#92400e; }
        .badge-confirmada { background:#d1fae5; color:#065f46; }
        .badge-completada { background:#dbeafe; color:#1e40af; }

        .check-circulo {
            width:28px; height:28px; border-radius:50%; border:2px solid var(--border);
            display:flex; align-items:center; justify-content:center;
            font-size:.95rem; transition:all .2s; flex-shrink:0; color:transparent;
        }

        .reserva-item.seleccionado .check-circulo { background:var(--info); border-color:var(--info); color:white; }

        .redactar-panel { background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
        .redactar-head { padding:1rem 1.25rem; border-bottom:1px solid var(--border); background:var(--bg); }
        .redactar-head h2 { font-size:1rem; font-weight:600; }
        .redactar-head p  { font-size:.82rem; color:var(--light); margin-top:.15rem; }
        .redactar-body { display:grid; grid-template-columns:1fr 1fr; }
        .form-lado { padding:1.25rem; border-right:1px solid var(--border); }
        .preview-lado { padding:1.25rem; overflow:hidden; }

        .form-group { margin-bottom:1rem; }
        .form-group label { display:block; margin-bottom:.4rem; font-weight:500; font-size:.88rem; }
        .form-group input[type=text],
        .form-group input[type=email],
        .form-group textarea {
            width:100%; padding:.65rem .85rem; border:1px solid var(--border);
            border-radius:6px; font-family:inherit; font-size:.9rem; transition:border-color .2s;
        }
        .form-group textarea { resize:vertical; min-height:140px; }
        .form-group input:focus,
        .form-group textarea:focus { outline:none; border-color:var(--info); }

        .form-actions { display:flex; justify-content:flex-end; margin-top:1.25rem; }

        .btn-enviar {
            padding:.6rem 1.4rem; background:var(--success); color:white;
            border:none; border-radius:6px; cursor:pointer; font-size:.9rem;
            font-weight:600; transition:all .2s; width:100%; text-align:center;
        }

        @media(min-width:600px) { .btn-enviar { width:auto; } }
        .btn-enviar:hover { background:#059669; transform:translateY(-1px); }
        .btn-enviar:disabled { background:#9ca3af; cursor:not-allowed; transform:none; }

        .preview-label { font-size:.78rem; font-weight:600; color:var(--light); text-transform:uppercase; letter-spacing:.5px; margin-bottom:.6rem; }
        .preview-email { border:1px solid var(--border); border-radius:8px; overflow:hidden; font-size:.8rem; }
        .pe-header { background:#000; color:white; padding:14px; text-align:center; }
        .pe-header h3 { margin:0 0 2px; font-size:15px; }
        .pe-header p  { margin:0; font-size:11px; opacity:.75; }
        .pe-body { background:white; padding:16px 18px; }
        .pe-body h4 { margin:0 0 6px; font-size:13px; }
        .pe-body > p { font-size:.75rem; color:#555; margin-bottom:4px; }
        .pe-info { background:#f8f9fa; border:1px solid #e5e7eb; border-radius:5px; padding:10px 12px; margin:10px 0; font-size:.75rem; }
        .pe-info p { margin:2px 0; }
        .pe-nota { background:#fff8e1; border-left:4px solid #f59e0b; padding:10px 13px; margin:10px 0; border-radius:0 5px 5px 0; font-size:.8rem; color:#333; min-height:44px; white-space:pre-wrap; word-break:break-word; }
        .pe-nota em { color:#bbb; font-style:italic; }
        .pe-pie { font-size:.7rem; color:#9ca3af; margin-top:10px; }

        .libre-panel { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1.25rem; }
        .libre-panel h2 { font-size:1rem; font-weight:600; margin-bottom:1.25rem; padding-bottom:.75rem; border-bottom:1px solid var(--border); }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }

        .empty-state { text-align:center; padding:2.5rem 1rem; color:var(--light); }
        .empty-state svg { width:50px; height:50px; margin:0 auto .75rem; opacity:.35; }

        @media(max-width:800px) {
            .redactar-body { grid-template-columns:1fr; }
            .form-lado { border-right:none; border-bottom:1px solid var(--border); }
            .preview-lado { display:none; }
            .grid-2 { grid-template-columns:1fr; }
            .reserva-item { grid-template-columns:60px 1fr 28px; gap:.5rem; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Panel de Correos</h1>
    <a href="panel_reservas.php">Volver a Reservas</a>
</div>

<div class="container">

    <?php if ($msg_resultado): ?>
        <div class="alerta <?= $msg_tipo ?>">
            <?= htmlspecialchars($msg_resultado) ?>
        </div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab active" id="tab-reserva" onclick="cambiarTab('reserva')">Desde una reserva del dia</button>
        <button class="tab" id="tab-libre" onclick="cambiarTab('libre')">A un correo especifico</button>
    </div>

    <div id="panel-reserva">
        <div class="filtros">
            <a href="?filtro=hoy"    class="filtro-btn <?= $filtro==='hoy'    ? 'active':'' ?>">Hoy</a>
            <a href="?filtro=manana" class="filtro-btn <?= $filtro==='manana' ? 'active':'' ?>">Manana</a>
            <a href="?filtro=semana" class="filtro-btn <?= $filtro==='semana' ? 'active':'' ?>">Esta semana</a>
            <div class="fecha-custom">
                <input type="date" id="fechaEspecifica" value="<?= h($fecha_custom) ?>">
                <button class="filtro-btn" onclick="irAFecha()">Buscar</button>
            </div>
        </div>

        <p class="seccion-titulo"><?= h($titulo) ?> - <?= count($reservas) ?> reserva<?= count($reservas)!==1?'s':'' ?></p>

        <?php if (empty($reservas)): ?>
            <div class="reservas-lista">
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <p>No hay reservas para este periodo</p>
                </div>
            </div>
        <?php else: ?>
            <div class="reservas-lista">
                <?php foreach ($reservas as $r): ?>
                    <div class="reserva-item" id="item-<?= (int)$r['id'] ?>"
                         data-id="<?= (int)$r['id'] ?>"
                         data-nombre="<?= h($r['nombre']) ?>"
                         data-email="<?= h($r['email']) ?>"
                         data-fecha="<?= h(date('d/m/Y', strtotime($r['fecha']))) ?>"
                         data-hora="<?= h(substr($r['hora'],0,5)) ?> hrs"
                         data-personas="<?= (int)$r['personas'] ?>"
                         onclick="seleccionarItem(this)">
                        <div class="reserva-hora"><?= date('g:i A', strtotime($r['hora'])) ?></div>
                        <div class="reserva-info-wrap">
                            <div class="reserva-nombre">
                                <?= htmlspecialchars($r['nombre']) ?>
                                <span class="badge badge-<?= $r['estado'] ?>"><?= ucfirst($r['estado']) ?></span>
                            </div>
                            <div class="reserva-det">
                                <?= $r['personas'] ?> personas
                                &bull; <?= htmlspecialchars($r['email']) ?>
                            </div>
                        </div>
                        <div class="check-circulo">&#10003;</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" id="form-reserva">
                <?= csrfField() ?>
                <input type="hidden" name="modo" value="reserva">
                <input type="hidden" name="reserva_id" id="campo-reserva-id" value="">

                <div class="redactar-panel">
                    <div class="redactar-head">
                        <h2>Redactar mensaje</h2>
                        <p id="label-destino">Selecciona una reserva arriba para elegir el destinatario</p>
                    </div>

                    <div class="redactar-body">
                        <div class="form-lado">
                            <div class="form-group">
                                <label>Asunto del correo</label>
                                <input type="text" name="asunto" value="Informacion sobre tu reserva en <?= SITE_NAME ?>">
                            </div>
                            <div class="form-group">
                                <label>Tu mensaje (aparece en la nota)</label>
                                <textarea name="mensaje" id="txt-mensaje"
                                          placeholder="Escribe aqui lo que quieres comunicarle al cliente..."
                                          oninput="actualizarPreview('pe-texto', this.value)"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-enviar" id="btn-enviar-reserva" disabled>
                                    Enviar correo
                                </button>
                            </div>
                        </div>

                        <div class="preview-lado">
                            <p class="preview-label">Vista previa</p>
                            <div class="preview-email">
                                <div class="pe-header">
                                    <h3>Reserva Confirmada</h3>
                                    <p><?= SITE_NAME ?></p>
                                </div>
                                <div class="pe-body">
                                    <h4 id="pe-saludo">Hola!</h4>
                                    <p>Tu reserva ha sido confirmada. Favor de presentarse 10 minutos antes.</p>
                                    <div class="pe-info" id="pe-info-reserva">
                                        <p><strong>ID:</strong> -</p>
                                        <p><strong>Fecha:</strong> -</p>
                                        <p><strong>Hora:</strong> -</p>
                                        <p><strong>Personas:</strong> -</p>
                                    </div>
                                    <div class="pe-nota">
                                        <strong style="display:block;margin-bottom:5px;">Nota del restaurante:</strong>
                                        <em id="pe-texto">Tu mensaje aparecera aqui...</em>
                                    </div>
                                    <p class="pe-pie">Correo automatico - <?= SITE_NAME ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div id="panel-libre" style="display:none;">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="modo" value="libre">

            <div class="libre-panel">
                <h2>Enviar a un correo especifico</h2>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Nombre del destinatario</label>
                        <input type="text" name="dest_nombre" placeholder="Ej: Juan Garcia">
                    </div>
                    <div class="form-group">
                        <label>Email del destinatario *</label>
                        <input type="email" name="dest_email" placeholder="correo@ejemplo.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Asunto del correo</label>
                    <input type="text" name="asunto" value="Informacion sobre tu reserva en <?= SITE_NAME ?>">
                </div>

                <div class="form-group">
                    <label>Tu mensaje</label>
                    <textarea name="mensaje" placeholder="Escribe aqui lo que quieres comunicarle..." style="min-height:160px;"></textarea>
                </div>

                <div class="form-actions" style="margin-top:1.5rem;">
                    <button type="submit" class="btn-enviar">Enviar correo</button>
                </div>
            </div>
        </form>
    </div>

</div>

<script>
    function cambiarTab(tab) {
        document.getElementById('panel-reserva').style.display = tab === 'reserva' ? 'block' : 'none';
        document.getElementById('panel-libre').style.display   = tab === 'libre'   ? 'block' : 'none';
        document.getElementById('tab-reserva').classList.toggle('active', tab === 'reserva');
        document.getElementById('tab-libre').classList.toggle('active',   tab === 'libre');
    }

    function irAFecha() {
        const f = document.getElementById('fechaEspecifica').value;
        if (f) window.location.href = `?filtro=fecha&fecha=${f}`;
    }

    let seleccionado = null;

    // Reemplaza la función anterior que recibía datos como parámetros de onclick.
    // Ahora lee los datos desde atributos data-* (seguro contra XSS — Hallazgo A1).
    function seleccionarItem(el) {
        const id       = parseInt(el.dataset.id, 10);
        const nombre   = el.dataset.nombre   || '';
        const email    = el.dataset.email    || '';
        const fecha    = el.dataset.fecha    || '';
        const hora     = el.dataset.hora     || '';
        const personas = el.dataset.personas || '';

        if (seleccionado) {
            document.getElementById('item-' + seleccionado)?.classList.remove('seleccionado');
        }

        if (seleccionado === id) {
            seleccionado = null;
            document.getElementById('campo-reserva-id').value = '';
            document.getElementById('btn-enviar-reserva').disabled = true;
            document.getElementById('label-destino').textContent = 'Selecciona una reserva arriba para elegir el destinatario';
            document.getElementById('pe-saludo').textContent = 'Hola!';
            document.getElementById('pe-info-reserva').innerHTML = resetInfo();
            return;
        }

        seleccionado = id;
        el.classList.add('seleccionado');
        document.getElementById('campo-reserva-id').value = id;
        document.getElementById('btn-enviar-reserva').disabled = false;

        // Usar textContent para datos dinámicos (seguro contra XSS)
        const labelDestino = document.getElementById('label-destino');
        labelDestino.textContent = '';
        const strong = document.createElement('strong');
        strong.textContent = nombre;
        labelDestino.append('Enviando a: ', strong, ' — ', email);

        document.getElementById('pe-saludo').textContent = `Hola ${nombre}!`;

        const infoEl = document.getElementById('pe-info-reserva');
        infoEl.innerHTML = '';
        const filas = [['ID', '#' + id], ['Fecha', fecha], ['Hora', hora], ['Personas', personas]];
        filas.forEach(([lbl, val]) => {
            const p = document.createElement('p');
            const b = document.createElement('strong');
            b.textContent = lbl + ':';
            p.appendChild(b);
            p.append(' ' + val);
            infoEl.appendChild(p);
        });
    }

    function resetInfo() {
        return `<p><strong>ID:</strong> -</p>
                <p><strong>Fecha:</strong> -</p>
                <p><strong>Hora:</strong> -</p>
                <p><strong>Personas:</strong> -</p>`;
    }

    function actualizarPreview(targetId, val) {
        const el = document.getElementById(targetId);
        if (!el) return;
        if (val.trim()) {
            el.outerHTML = `<span id="${targetId}" style="white-space:pre-wrap;">${escHtml(val)}</span>`;
        } else {
            el.outerHTML = `<em id="${targetId}">Tu mensaje aparecera aqui...</em>`;
        }
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }

    <?php if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['modo']??'')==='libre'): ?>
    cambiarTab('libre');
    <?php endif; ?>
</script>
</body>
</html>
