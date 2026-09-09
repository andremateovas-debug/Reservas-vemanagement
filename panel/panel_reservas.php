<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login_panel.php');
    exit;
}

$conn = conectarDB();

$sql_autocompletar = "UPDATE reservas
                      SET estado = 'completada'
                      WHERE estado = 'confirmada'
                      AND TIMESTAMP(fecha, hora) < NOW()";
$conn->query($sql_autocompletar);

$filtro           = $_GET['filtro'] ?? 'hoy';
$fecha_especifica = $_GET['fecha']  ?? '';

$where       = "";
$params      = [];
$param_types = "";

switch ($filtro) {
    case 'hoy':
        $where  = "fecha = CURDATE()";
        $titulo = "Hoy - " . date('d/m/Y');
        break;
    case 'manana':
        $where  = "fecha = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        $titulo = "Manana - " . date('d/m/Y', strtotime('+1 day'));
        break;
    case 'semana':
        $where  = "fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $titulo = "Proxima semana";
        break;
    case 'fecha':
        if (!empty($fecha_especifica)) {
            $where        = "fecha = ?";
            $params[]     = $fecha_especifica;
            $param_types  = "s";
            $titulo       = date('d/m/Y', strtotime($fecha_especifica));
        } else {
            $where  = "fecha = CURDATE()";
            $titulo = "Hoy - " . date('d/m/Y');
        }
        break;
    default:
        $where  = "fecha = CURDATE()";
        $titulo = "Hoy - " . date('d/m/Y');
}

$sql  = "SELECT id, fecha, hora, nombre, personas, estado, telefono, email
         FROM reservas
         WHERE $where
         ORDER BY fecha, hora";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result   = $stmt->get_result();
$reservas = [];
while ($row = $result->fetch_assoc()) {
    $reservas[] = $row;
}

$sql_stats = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente'  THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
                SUM(CASE WHEN estado = 'cancelada'  THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas
            FROM reservas
            WHERE $where";

$stmt_stats = $conn->prepare($sql_stats);
if (!empty($params)) {
    $stmt_stats->bind_param($param_types, ...$params);
}
$stmt_stats->execute();
$stats_result = $stmt_stats->get_result();
$estadisticas = $stats_result->fetch_assoc() ?? [
    'total'      => 0,
    'pendientes' => 0,
    'confirmadas'=> 0,
    'canceladas' => 0,
    'completadas'=> 0
];
$stmt_stats->close();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas - Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <style>
        :root {
            --bg: #fafafa;
            --surface: #ffffff;
            --text: #333333;
            --text-light: #666666;
            --primary: #000000;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #e5e7eb;
            --info: #3b82f6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        .header {
            background: var(--primary);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .header h1 { font-size: 1.1rem; font-weight: 500; }

        .user-menu { display: flex; align-items: center; gap: 0.5rem; }

        .logout-btn, .refresh-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s;
        }

        .logout-btn:hover, .refresh-btn:hover { background: rgba(255,255,255,0.2); }

        .container { max-width: 1200px; margin: 0 auto; padding: 1rem; }

        .filtros {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            align-items: center;
            background: var(--surface);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .filtro-btn {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            color: var(--text);
            text-decoration: none;
            white-space: nowrap;
            min-height: 40px;
            display: inline-flex;
            align-items: center;
        }

        .filtro-btn:hover { background: var(--bg); border-color: var(--primary); }
        .filtro-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .fecha-custom {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
            flex: 1 1 200px;
        }

        .fecha-custom input {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 0;
            flex: 1 1 130px;
            height: 40px;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-card-mini {
            background: var(--surface);
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            text-align: center;
        }

        .stat-value { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.25rem; }
        .stat-label { font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-pendiente  { color: var(--warning); }
        .stat-confirmada { color: var(--success); }
        .stat-cancelada  { color: var(--danger); }
        .stat-completada { color: var(--info); }

        .view-toggle {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1rem;
            background: var(--surface);
            padding: 0.35rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            width: 100%;
            max-width: 280px;
        }

        .view-btn {
            flex: 1;
            padding: 0.55rem 0.75rem;
            background: transparent;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
            color: var(--text);
            white-space: nowrap;
            min-height: 40px;
        }

        .view-btn:hover { background: var(--bg); }
        .view-btn.active { background: var(--primary); color: white; }

        .reservas-lista {
            background: var(--surface);
            border-radius: 8px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .reserva-item {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--border);
            display: grid;
            grid-template-columns: 72px 1fr auto;
            gap: 0.75rem;
            align-items: start;
            transition: background 0.2s;
            border-left: 3px solid transparent;
        }

        .reserva-item:hover { background: var(--bg); }
        .reserva-item:last-child { border-bottom: none; }

        .reserva-item.estado-cancelada {
            background: #fff5f5;
            border-left-color: var(--danger);
        }

        .reserva-item.estado-cancelada:hover { background: #fee2e2; }

        .reserva-item.estado-cancelada .reserva-nombre {
            text-decoration: line-through;
            color: var(--text-light);
        }

        .reserva-item.estado-completada {
            background: #f0f9ff;
            border-left-color: var(--info);
            opacity: 0.8;
        }

        .reserva-item.estado-completada:hover { background: #dbeafe; opacity: 1; }
        .reserva-item.estado-confirmada { border-left-color: var(--success); }
        .reserva-item.estado-pendiente  { border-left-color: var(--warning); }

        .reserva-hora { font-weight: 600; font-size: 1rem; padding-top: 0.1rem; line-height: 1.3; }

        .reserva-info { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }

        .reserva-nombre {
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
        }

        .reserva-detalles { font-size: 0.82rem; color: var(--text-light); }

        .reserva-acciones { display: flex; flex-direction: column; gap: 0.35rem; align-items: stretch; }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pendiente  { background: #fef3c7; color: #92400e; }
        .badge-confirmada { background: #d1fae5; color: #065f46; }
        .badge-cancelada  { background: #fee2e2; color: #991b1b; }
        .badge-completada { background: #dbeafe; color: #1e40af; }

        .btn {
            padding: 0.45rem 0.75rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.82rem;
            transition: all 0.2s;
            font-weight: 500;
            white-space: nowrap;
            min-height: 34px;
            text-align: center;
        }

        .reserva-acciones .btn { min-height: 36px; font-size: 0.8rem; padding: 0.4rem 0.6rem; }

        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger  { background: var(--danger);  color: white; }
        .btn-danger:hover  { background: #dc2626; }
        .btn-info    { background: var(--info);    color: white; }
        .btn-info:hover    { background: #2563eb; }

        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-light); }
        .empty-state svg { width: 64px; height: 64px; margin: 0 auto 1rem; opacity: 0.5; }

        #calendarioReservas {
            background: var(--surface);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .fc {
            --fc-border-color: var(--border);
            --fc-button-bg-color: var(--primary);
            --fc-button-border-color: var(--primary);
            --fc-button-hover-bg-color: #333333;
            --fc-button-hover-border-color: #333333;
            --fc-button-active-bg-color: #1a1a1a;
            --fc-button-active-border-color: #1a1a1a;
            --fc-event-bg-color: var(--info);
            --fc-event-border-color: var(--info);
            min-width: 0;
        }

        .fc .fc-button { text-transform: capitalize; font-size: 0.85rem; padding: 0.4rem 0.65rem; min-height: 36px; }
        .fc-event { border-radius: 4px; padding: 2px 4px; font-size: 0.82rem; cursor: pointer; }
        .fc .fc-toolbar { flex-wrap: wrap; gap: 0.5rem; }
        .fc .fc-toolbar-chunk { display: flex; align-items: center; flex-wrap: wrap; gap: 0.25rem; }
        .fc .fc-toolbar-title { font-size: 1rem; }
        .fc .fc-footer-toolbar { margin-top: 0.5rem; justify-content: center; }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .detail-row:last-of-type { border-bottom: none; }
        .detail-label { font-weight: 600; color: var(--text-light); flex-shrink: 0; }

        .reserva-status { font-weight: 600; }
        .status-pendiente  { color: #92400e; }
        .status-confirmada { color: #065f46; }
        .status-cancelada  { color: #991b1b; }
        .status-completada { color: #1e40af; }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
        }

        .modal-content {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: 12px;
            max-width: 560px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-header { margin-bottom: 1.25rem; padding-bottom: 0.875rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { font-size: 1.2rem; font-weight: 600; padding-right: 2rem; }

        .close-btn {
            position: absolute;
            top: 0.875rem; right: 0.875rem;
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: var(--text-light);
            width: 36px; height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .close-btn:hover { background: var(--bg); }

        .form-group { margin-bottom: 1rem; }

        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.875rem; }

        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
        }

        .form-group textarea { min-height: 90px; }

        .modal-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.25rem;
            flex-wrap: wrap;
        }

        .historial-toggle {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            z-index: 200;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0 8px 8px 0;
            padding: .6rem .45rem;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .3rem;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .5px;
            line-height: 1.3;
            transition: background .2s, left .35s;
            box-shadow: 2px 0 8px rgba(0,0,0,.15);
            min-width: 28px;
        }

        .historial-toggle:hover { background: #333; }
        .historial-toggle svg { width: 16px; height: 16px; flex-shrink: 0; }
        .historial-toggle .toggle-label { writing-mode: vertical-lr; text-orientation: mixed; transform: rotate(180deg); font-size: .65rem; letter-spacing: 1px; }

        .historial-drawer {
            position: fixed;
            left: -320px;
            top: 0;
            width: 300px;
            height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            z-index: 199;
            display: flex;
            flex-direction: column;
            transition: left .35s cubic-bezier(.4,0,.2,1);
            box-shadow: 4px 0 20px rgba(0,0,0,.08);
        }

        .historial-drawer.open { left: 0; }
        body.drawer-open .historial-toggle { left: 300px; }

        .drawer-head {
            background: var(--primary);
            color: white;
            padding: .85rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .drawer-head h3 { font-size: .95rem; font-weight: 600; margin: 0; }

        .drawer-close {
            background: none; border: none; color: white; cursor: pointer;
            font-size: 1.3rem; line-height: 1; padding: .1rem .3rem;
            border-radius: 3px; transition: background .15s;
        }

        .drawer-close:hover { background: rgba(255,255,255,.15); }
        .drawer-body { overflow-y: auto; flex: 1; padding: .5rem 0; }

        .timeline { position: relative; padding: .5rem 0 .5rem 2.5rem; }

        .timeline::before {
            content: '';
            position: absolute;
            left: 1.15rem; top: 0; bottom: 0;
            width: 2px;
            background: var(--border);
        }

        .tl-item { position: relative; margin-bottom: .15rem; }

        .tl-dot {
            position: absolute;
            left: -1.6rem; top: .75rem;
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--border);
            border: 2px solid var(--surface);
            z-index: 1;
        }

        .tl-item.hoy .tl-dot    { background: var(--primary); width: 13px; height: 13px; left: -1.72rem; top: .65rem; }
        .tl-item.futuro .tl-dot { background: var(--info); }
        .tl-item.pasado .tl-dot { background: #d1d5db; }

        .tl-btn {
            width: 100%;
            background: none; border: none; text-align: left;
            padding: .5rem .75rem .5rem .35rem;
            border-radius: 6px; cursor: pointer;
            transition: background .15s; display: block;
        }

        .tl-btn:hover { background: var(--bg); }
        .tl-item.hoy .tl-btn { background: #f8f8f8; }

        .tl-fecha { font-size: .78rem; font-weight: 600; color: var(--text); display: block; }
        .tl-item.hoy .tl-fecha   { color: var(--primary); }
        .tl-item.futuro .tl-fecha { color: var(--info); }
        .tl-item.pasado .tl-fecha { color: var(--text-light); }

        .tl-dia { font-size: .7rem; color: var(--text-light); display: block; text-transform: capitalize; margin-bottom: .2rem; }

        .tl-chips { display: flex; gap: .25rem; flex-wrap: wrap; margin-top: .2rem; }

        .tl-chip { font-size: .65rem; padding: .1rem .4rem; border-radius: 8px; font-weight: 600; }
        .tl-chip.pend { background: #fef3c7; color: #92400e; }
        .tl-chip.conf { background: #d1fae5; color: #065f46; }
        .tl-chip.comp { background: #dbeafe; color: #1e40af; }
        .tl-chip.canc { background: #fee2e2; color: #991b1b; }
        .tl-chip.tot  { background: #f3f4f6; color: #374151; }

        .tl-loading { text-align: center; padding: 2rem 1rem; color: var(--text-light); font-size: .85rem; }

        .drawer-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.35);
            z-index: 198;
        }

        body.drawer-open .drawer-overlay { display: block; }

        @media (max-width: 640px) {
            .container { padding: 0.5rem; }
            .header h1 { font-size: 0.95rem; }
            .refresh-btn { display: none; }
            .filtros { padding: 0.6rem; gap: 0.4rem; }
            .filtro-btn { font-size: 0.82rem; padding: 0.4rem 0.7rem; min-height: 38px; }
            .stats-mini { grid-template-columns: repeat(5, 1fr); gap: 0.4rem; }
            .stat-card-mini { padding: 0.5rem 0.25rem; }
            .stat-value { font-size: 1.2rem; }
            .stat-label { font-size: 0.65rem; }
            .view-toggle { max-width: 100%; }
            .reserva-item { grid-template-columns: 1fr; gap: 0.5rem; padding: 0.875rem; }
            .reserva-hora { font-size: 0.95rem; font-weight: 700; color: var(--text-light); }
            .reserva-nombre { font-size: 1rem; }
            .reserva-acciones { flex-direction: row; flex-wrap: wrap; }
            .reserva-acciones .btn { flex: 1 1 auto; min-width: 80px; min-height: 42px; font-size: 0.82rem; }
            .modal { padding: 0.5rem; align-items: flex-end; }
            .modal-content { padding: 1.25rem; border-radius: 12px 12px 0 0; max-height: 88vh; margin-bottom: 0; }
            .modal-buttons { justify-content: stretch; }
            .modal-buttons .btn { flex: 1; min-height: 44px; font-size: 0.9rem; }
        }

        @media (max-width: 768px) {
            .historial-drawer { width: 280px; left: -295px; }
            body.drawer-open .historial-toggle { left: 280px; }
        }

        @media (max-width: 640px) {
            .historial-toggle { top: auto; bottom: 5rem; transform: none; border-radius: 0 8px 8px 0; }
            body.drawer-open .historial-toggle { left: 280px; }
        }
    </style>
</head>
<body>
    <div class="drawer-overlay" onclick="toggleDrawer(false)"></div>

    <div class="historial-drawer" id="historialDrawer">
        <div class="drawer-head">
            <h3>Historial</h3>
            <button class="drawer-close" onclick="toggleDrawer(false)" title="Cerrar">&times;</button>
        </div>
        <div class="drawer-body">
            <div class="timeline" id="timelineContenido">
                <div class="tl-loading">Cargando...</div>
            </div>
        </div>
    </div>

    <button class="historial-toggle" id="historialToggle" onclick="toggleDrawer()" title="Historial de reservas">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <span class="toggle-label">HISTORIAL</span>
    </button>

    <div class="header">
        <h1>Panel de Reservas</h1>
        <div class="user-menu">
            <button class="refresh-btn" onclick="location.reload()">Actualizar</button>
            <a href="logout.php" class="logout-btn">Salir</a>
        </div>
    </div>

    <div class="container">
        <div class="filtros">
            <a href="?filtro=hoy" class="filtro-btn <?php echo $filtro === 'hoy' ? 'active' : ''; ?>">Hoy</a>
            <a href="?filtro=manana" class="filtro-btn <?php echo $filtro === 'manana' ? 'active' : ''; ?>">Manana</a>
            <a href="?filtro=semana" class="filtro-btn <?php echo $filtro === 'semana' ? 'active' : ''; ?>">Esta semana</a>
            <div class="fecha-custom">
                <input type="date" id="fechaEspecifica" value="<?php echo $fecha_especifica; ?>">
                <button class="filtro-btn" onclick="irAFecha()">Buscar</button>
            </div>
        </div>

        <div class="stats-mini">
            <div class="stat-card-mini">
                <div class="stat-value"><?php echo $estadisticas['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card-mini">
                <div class="stat-value stat-pendiente"><?php echo $estadisticas['pendientes']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-card-mini">
                <div class="stat-value stat-confirmada"><?php echo $estadisticas['confirmadas']; ?></div>
                <div class="stat-label">Confirmadas</div>
            </div>
            <div class="stat-card-mini">
                <div class="stat-value stat-completada"><?php echo $estadisticas['completadas']; ?></div>
                <div class="stat-label">Completadas</div>
            </div>
            <div class="stat-card-mini">
                <div class="stat-value stat-cancelada"><?php echo $estadisticas['canceladas']; ?></div>
                <div class="stat-label">Canceladas</div>
            </div>
        </div>

        <div class="view-toggle">
            <button class="view-btn active" onclick="mostrarLista()">Lista</button>
            <button class="view-btn" onclick="mostrarCalendario()">Calendario</button>
        </div>

        <div id="listaReservas">
            <h2 style="margin-bottom: 1rem; font-size: 1.2rem; font-weight: 600;"><?php echo $titulo; ?></h2>

            <?php if (empty($reservas)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p>No hay reservas para este periodo</p>
                </div>
            <?php else: ?>
                <div class="reservas-lista">
                    <?php foreach ($reservas as $reserva): ?>
                        <div class="reserva-item estado-<?php echo $reserva['estado']; ?>">
                            <div class="reserva-hora"><?php echo date('g:i A', strtotime($reserva['hora'])); ?></div>
                            <div class="reserva-info">
                                <div class="reserva-nombre">
                                    <?php echo htmlspecialchars($reserva['nombre']); ?>
                                    <span class="badge badge-<?php echo $reserva['estado']; ?>">
                                        <?php echo ucfirst($reserva['estado']); ?>
                                    </span>
                                </div>
                                <div class="reserva-detalles">
                                    <?php echo $reserva['personas']; ?> personas
                                    <?php if ($reserva['telefono']): ?>
                                        &bull; <?php echo htmlspecialchars($reserva['telefono']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="reserva-acciones">
                                <?php if ($reserva['estado'] === 'pendiente'): ?>
                                    <button class="btn btn-success" onclick="mostrarModalComentario(<?php echo $reserva['id']; ?>, 'confirmada')">Confirmar</button>
                                    <button class="btn btn-danger"  onclick="mostrarModalComentario(<?php echo $reserva['id']; ?>, 'cancelada')">Cancelar</button>
                                <?php elseif ($reserva['estado'] === 'confirmada'): ?>
                                    <button class="btn btn-danger"  onclick="mostrarModalComentario(<?php echo $reserva['id']; ?>, 'cancelada')">Cancelar</button>
                                <?php endif; ?>
                                <button class="btn btn-info" onclick="verDetalles(<?php echo $reserva['id']; ?>)">Ver</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="calendarioReservas" style="display: none;">
            <div id="calendar"></div>
        </div>
    </div>

    <div id="modalDetalles" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="cerrarModal()">&times;</button>
            <div id="detallesContenido"></div>
        </div>
    </div>

    <div id="modalCompletar" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="cerrarModalCompletar()">&times;</button>
            <div class="modal-header"><h2>Completar Reserva</h2></div>
            <p style="margin-bottom: 1rem; color: var(--text-light);">Ingresa el total cobrado para cerrar la reserva.</p>
            <div class="form-group">
                <label for="totalCobrado">Total cobrado ($):</label>
                <input type="number" id="totalCobrado" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="modal-buttons">
                <button class="btn" style="background: var(--border); color: var(--text);" onclick="cerrarModalCompletar()">Cancelar</button>
                <button class="btn btn-info" id="btnConfirmarCompletar">Completar</button>
            </div>
        </div>
    </div>

    <div id="modalComentario" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="cerrarModalComentario()">&times;</button>
            <div class="modal-header"><h2 id="modalComentarioTitulo"></h2></div>
            <p id="modalComentarioMensaje" style="margin-bottom: 1rem; color: var(--text-light);"></p>
            <div class="form-group">
                <label for="comentarioAdmin">Comentario (opcional):</label>
                <textarea id="comentarioAdmin" placeholder="Agrega un comentario interno sobre esta accion..."></textarea>
            </div>
            <div class="modal-buttons">
                <button class="btn" style="background: var(--border); color: var(--text);" onclick="cerrarModalComentario()">Cancelar</button>
                <button class="btn btn-info" id="btnConfirmarAccion">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js"></script>
    <script>
        let calendar;
        let reservaActualId = null;
        let accionActual    = null;

        function irAFecha() {
            const fecha = document.getElementById('fechaEspecifica').value;
            if (fecha) window.location.href = `?filtro=fecha&fecha=${fecha}`;
        }

        function mostrarLista() {
            document.getElementById('listaReservas').style.display      = 'block';
            document.getElementById('calendarioReservas').style.display = 'none';
            document.querySelectorAll('.view-btn')[0].classList.add('active');
            document.querySelectorAll('.view-btn')[1].classList.remove('active');
        }

        function mostrarCalendario() {
            document.getElementById('listaReservas').style.display      = 'none';
            document.getElementById('calendarioReservas').style.display = 'block';
            document.querySelectorAll('.view-btn')[0].classList.remove('active');
            document.querySelectorAll('.view-btn')[1].classList.add('active');
            if (!calendar) initCalendar();
        }

        function initCalendar() {
            const calendarEl = document.getElementById('calendar');
            const esMovil    = window.innerWidth < 600;

            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: esMovil ? 'listMonth' : 'dayGridMonth',
                locale: 'es',
                headerToolbar: esMovil ? {
                    left: 'prev,next', center: 'title', right: 'today'
                } : {
                    left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth'
                },
                footerToolbar: esMovil ? { center: 'dayGridMonth,listMonth' } : false,
                buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista' },
                views: { listMonth: { buttonText: 'Lista' } },
                events: 'ajax_calendario.php',
                eventClick: function(info) { verDetalles(info.event.id); },
                eventDisplay: 'block',
                eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
                listDayFormat: { weekday: 'long', day: 'numeric', month: 'long' },
                listDaySideFormat: false,
                height: 'auto',
                navLinks: true,
                dayMaxEvents: true,
                noEventsText: 'Sin reservas en este periodo',
                eventDidMount: function(info) {
                    info.el.title = info.event.title + ' - ' +
                                    info.event.extendedProps.hora + ' - ' +
                                    info.event.extendedProps.estado;
                }
            });

            calendar.render();
        }

        function mostrarModalComentario(id, estado) {
            reservaActualId = id;
            accionActual    = estado;

            const titulo   = document.getElementById('modalComentarioTitulo');
            const mensaje  = document.getElementById('modalComentarioMensaje');
            const comentario = document.getElementById('comentarioAdmin');

            if (estado === 'confirmada') {
                titulo.textContent  = 'Confirmar Reserva';
                mensaje.textContent = 'Confirmar esta reserva? Se enviara un correo al cliente.';
            } else if (estado === 'cancelada') {
                titulo.textContent  = 'Cancelar Reserva';
                mensaje.textContent = 'Cancelar esta reserva? Se enviara un correo al cliente y se sumara un reporte.';
            }

            comentario.value = '';
            document.getElementById('modalComentario').style.display = 'flex';
            document.getElementById('btnConfirmarAccion').onclick = ejecutarCambioEstado;
        }

        function cerrarModalComentario() {
            document.getElementById('modalComentario').style.display = 'none';
            reservaActualId = null;
            accionActual    = null;
        }

        function ejecutarCambioEstado() {
            if (!reservaActualId || !accionActual) return;

            const comentario = document.getElementById('comentarioAdmin').value.trim();
            const formData   = new FormData();
            formData.append('reserva_id',      reservaActualId);
            formData.append('estado',          accionActual);
            formData.append('comentario_admin',comentario);

            fetch('ajax_cambiar_estado.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalComentario();
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Error desconocido'));
                    }
                })
                .catch(() => alert('Error al actualizar'));
        }

        function verDetalles(id) {
            fetch(`ajax_detalles.php?id=${id}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('detallesContenido').innerHTML = html;
                    document.getElementById('modalDetalles').style.display = 'flex';
                })
                .catch(() => {
                    document.getElementById('detallesContenido').innerHTML = '<p>Error al cargar detalles</p>';
                    document.getElementById('modalDetalles').style.display = 'flex';
                });
        }

        function cerrarModal() { document.getElementById('modalDetalles').style.display = 'none'; }

        function mostrarModalCompletar(id) {
            reservaActualId = id;
            document.getElementById('totalCobrado').value = '';
            document.getElementById('modalCompletar').style.display = 'flex';
            document.getElementById('btnConfirmarCompletar').onclick = ejecutarCompletar;
        }

        function cerrarModalCompletar() {
            document.getElementById('modalCompletar').style.display = 'none';
            reservaActualId = null;
        }

        function ejecutarCompletar() {
            if (!reservaActualId) return;

            const total    = parseFloat(document.getElementById('totalCobrado').value) || 0;
            const formData = new FormData();
            formData.append('reserva_id', reservaActualId);
            formData.append('total', total);

            fetch('ajax_completar_reserva.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        cerrarModalCompletar();
                        cerrarModal();
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Error desconocido'));
                    }
                })
                .catch(() => alert('Error al completar la reserva'));
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) event.target.style.display = 'none';
        };

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
                cerrarModalComentario();
                cerrarModalCompletar();
                toggleDrawer(false);
            }
        });

        let historialCargado = false;

        function toggleDrawer(forzar) {
            const drawer = document.getElementById('historialDrawer');
            const abrir  = (forzar === undefined) ? !drawer.classList.contains('open') : forzar;

            if (abrir) {
                drawer.classList.add('open');
                document.body.classList.add('drawer-open');
                if (!historialCargado) cargarHistorial();
            } else {
                drawer.classList.remove('open');
                document.body.classList.remove('drawer-open');
            }
        }

        function cargarHistorial() {
            fetch('ajax_historial.php')
                .then(r => r.json())
                .then(dias => { historialCargado = true; renderTimeline(dias); })
                .catch(() => {
                    document.getElementById('timelineContenido').innerHTML =
                        '<div class="tl-loading">Error al cargar.</div>';
                });
        }

        const DIAS_ES  = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
        const MESES_ES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

        function renderTimeline(dias) {
            const contenedor = document.getElementById('timelineContenido');
            if (!dias.length) {
                contenedor.innerHTML = '<div class="tl-loading">Sin reservas en los ultimos 60 dias.</div>';
                return;
            }

            let html = '';
            dias.forEach(d => {
                const dt     = new Date(d.fecha + 'T12:00:00');
                const diaSem = DIAS_ES[dt.getDay()];
                const mes    = MESES_ES[dt.getMonth()];
                const clase  = d.es_hoy ? 'hoy' : (d.es_futuro ? 'futuro' : 'pasado');
                const hoyTag = d.es_hoy ? ' - HOY' : '';

                let chips = `<span class="tl-chip tot">${d.total} reserva${d.total !== 1 ? 's' : ''}</span>`;
                if (d.pendientes)  chips += `<span class="tl-chip pend">${d.pendientes} pend.</span>`;
                if (d.confirmadas) chips += `<span class="tl-chip conf">${d.confirmadas} conf.</span>`;
                if (d.completadas) chips += `<span class="tl-chip comp">${d.completadas} comp.</span>`;
                if (d.canceladas)  chips += `<span class="tl-chip canc">${d.canceladas} canc.</span>`;

                html += `
                <div class="tl-item ${clase}">
                    <div class="tl-dot"></div>
                    <button class="tl-btn" onclick="irAFechaDrawer('${d.fecha}')">
                        <span class="tl-dia">${diaSem} ${dt.getDate()} ${mes}${hoyTag}</span>
                        <div class="tl-chips">${chips}</div>
                    </button>
                </div>`;
            });

            contenedor.innerHTML = html;

            const hoyEl = contenedor.querySelector('.tl-item.hoy');
            if (hoyEl) setTimeout(() => hoyEl.scrollIntoView({ block: 'center', behavior: 'smooth' }), 120);
        }

        function irAFechaDrawer(fecha) {
            toggleDrawer(false);
            window.location.href = `?filtro=fecha&fecha=${fecha}`;
        }
    </script>
</body>
</html>
