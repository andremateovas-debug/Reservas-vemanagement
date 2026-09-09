<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo '<p>No autorizado</p>';
    exit;
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    echo '<p>ID invalido</p>';
    exit;
}

$conn = conectarDB();
if (!$conn) {
    echo '<p>Error de conexion</p>';
    exit;
}

$sql  = "SELECT * FROM reservas WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result  = $stmt->get_result();
$reserva = $result->fetch_assoc();

if (!$reserva) {
    echo "<p>Reserva no encontrada.</p>";
    $stmt->close();
    $conn->close();
    exit;
}

$penalizado = false;
$reportes   = 0;

if (!empty($reserva['telefono'])) {
    $sql_historial  = "SELECT reportes, penalizada FROM clientes_historial WHERE telefono = ?";
    $stmt_historial = $conn->prepare($sql_historial);
    $stmt_historial->bind_param("s", $reserva['telefono']);
    $stmt_historial->execute();
    $result_historial = $stmt_historial->get_result();

    if ($result_historial->num_rows > 0) {
        $historial  = $result_historial->fetch_assoc();
        $penalizado = (bool)$historial['penalizada'];
        $reportes   = (int)$historial['reportes'];
    }
    $stmt_historial->close();
}

$stmt->close();
$conn->close();
?>

<div class="detail-row">
    <span class="detail-label">ID:</span>
    <span>#<?php echo $reserva['id']; ?></span>
</div>

<div class="detail-row">
    <span class="detail-label">Fecha:</span>
    <span><?php echo date('d/m/Y', strtotime($reserva['fecha'])); ?></span>
</div>

<div class="detail-row">
    <span class="detail-label">Hora:</span>
    <span><?php echo date('g:i A', strtotime($reserva['hora'])); ?></span>
</div>

<div class="detail-row">
    <span class="detail-label">Cliente:</span>
    <span><?php echo htmlspecialchars($reserva['nombre']); ?></span>
</div>

<div class="detail-row">
    <span class="detail-label">Email:</span>
    <span><?php echo htmlspecialchars($reserva['email']); ?></span>
</div>

<div class="detail-row">
    <span class="detail-label">Telefono:</span>
    <span>
        <?php echo htmlspecialchars($reserva['telefono'] ?? 'N/A'); ?>
        <?php if ($penalizado): ?>
            <span style="background: #ef4444; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; margin-left: 10px;">
                PENALIZADO (<?php echo $reportes; ?> reportes)
            </span>
        <?php elseif ($reportes > 0): ?>
            <span style="background: #f59e0b; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; margin-left: 10px;">
                <?php echo $reportes; ?> reporte<?php echo $reportes > 1 ? 's' : ''; ?>
            </span>
        <?php endif; ?>
    </span>
</div>

<div class="detail-row">
    <span class="detail-label">Personas:</span>
    <span><?php echo $reserva['personas']; ?></span>
</div>

<div class="detail-row">
    <span class="detail-label">Estado:</span>
    <span class="reserva-status status-<?php echo $reserva['estado']; ?>">
        <?php echo ucfirst($reserva['estado']); ?>
    </span>
</div>

<?php if ($reserva['estado'] == 'completada' && $reserva['total'] > 0): ?>
<div class="detail-row">
    <span class="detail-label">Total cobrado:</span>
    <span style="font-weight: bold; color: #10b981;">$<?php echo number_format($reserva['total'], 2); ?></span>
</div>
<?php endif; ?>

<?php if (!empty($reserva['comentarios'])): ?>
<div class="detail-row">
    <span class="detail-label">Comentario cliente:</span>
    <span><?php echo nl2br(htmlspecialchars($reserva['comentarios'])); ?></span>
</div>
<?php endif; ?>

<?php if (!empty($reserva['comentario_admin'])): ?>
<div class="detail-row">
    <span class="detail-label">Comentario admin:</span>
    <span style="background: #fff3cd; padding: 8px; border-radius: 4px; display: block; margin-top: 5px;">
        <?php echo nl2br(htmlspecialchars($reserva['comentario_admin'])); ?>
    </span>
</div>
<?php endif; ?>

<div class="detail-row">
    <span class="detail-label">Creado:</span>
    <span><?php
        $fecha_creacion = $reserva['fecha_creacion'] ?? '';
        echo !empty($fecha_creacion) ? date('d/m/Y H:i', strtotime($fecha_creacion)) : 'N/A';
    ?></span>
</div>

<div style="margin-top: 1.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <?php if ($reserva['estado'] == 'pendiente'): ?>
        <button onclick="mostrarModalComentario(<?php echo $reserva['id']; ?>, 'confirmada')"
                style="background: #10b981; color: white; border: none; padding: 0.75rem 1rem; border-radius: 4px; cursor: pointer; flex: 1;">
            Confirmar
        </button>
        <button onclick="mostrarModalComentario(<?php echo $reserva['id']; ?>, 'cancelada')"
                style="background: #ef4444; color: white; border: none; padding: 0.75rem 1rem; border-radius: 4px; cursor: pointer; flex: 1;">
            Cancelar
        </button>
    <?php endif; ?>

    <?php if ($reserva['estado'] == 'confirmada'): ?>
        <button onclick="mostrarModalCompletar(<?php echo $reserva['id']; ?>)"
                style="background: #3b82f6; color: white; border: none; padding: 0.75rem 1rem; border-radius: 4px; cursor: pointer; flex: 1;">
            Completar
        </button>
        <button onclick="mostrarModalComentario(<?php echo $reserva['id']; ?>, 'cancelada')"
                style="background: #ef4444; color: white; border: none; padding: 0.75rem 1rem; border-radius: 4px; cursor: pointer; flex: 1;">
            Cancelar
        </button>
    <?php endif; ?>

    <button onclick="cerrarModal()"
            style="background: #6b7280; color: white; border: none; padding: 0.75rem 1rem; border-radius: 4px; cursor: pointer; flex: 1;">
        Cerrar
    </button>
</div>
