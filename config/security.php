<?php
/**
 * security.php — Núcleo centralizado de seguridad
 *
 * Proporciona:
 *  - Configuración segura de cookies de sesión
 *  - Autenticación y autorización basada en roles
 *  - Generación y validación de tokens CSRF
 *  - Rate limiting de intentos de login
 *  - Helpers de escape contextual
 */

// ─────────────────────────────────────────────────────────────
// 1. CONFIGURACIÓN SEGURA DE SESIÓN
// Debe llamarse ANTES de session_start()
// ─────────────────────────────────────────────────────────────

function configurarSesionSegura(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return; // Ya iniciada, no reconfigurar
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

    session_set_cookie_params([
        'lifetime' => 0,                  // Cookie de sesión (expira al cerrar navegador)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,           // Solo HTTPS cuando esté disponible
        'httponly' => true,               // Inaccesible via JavaScript
        'samesite' => 'Lax',             // Protección CSRF básica
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '7200'); // 2 horas de inactividad máxima
}

// ─────────────────────────────────────────────────────────────
// 2. EXPIRACIÓN POR INACTIVIDAD
// ─────────────────────────────────────────────────────────────

define('SESSION_TIMEOUT', 7200); // 2 horas en segundos

function verificarExpiracionSesion(): void
{
    if (!isset($_SESSION['admin_id'])) {
        return;
    }

    $ahora = time();

    if (isset($_SESSION['_last_activity'])) {
        if (($ahora - $_SESSION['_last_activity']) > SESSION_TIMEOUT) {
            // Sesión expirada por inactividad
            session_unset();
            session_destroy();
            configurarSesionSegura();
            session_start();
            return;
        }
    }

    $_SESSION['_last_activity'] = $ahora;
}

// ─────────────────────────────────────────────────────────────
// 3. AUTENTICACIÓN
// ─────────────────────────────────────────────────────────────

/**
 * Verifica que el usuario esté autenticado.
 * En contextos API devuelve JSON 401; en panel redirige al login.
 */
function requireAuth(): void
{
    verificarExpiracionSesion();

    if (!isset($_SESSION['admin_id'])) {
        $esApi = (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false);
        if ($esApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit;
        } else {
            header('Location: ' . SITE_URL . '/panel/login_panel.php');
            exit;
        }
    }
}

// ─────────────────────────────────────────────────────────────
// 4. AUTORIZACIÓN POR ROLES
// ─────────────────────────────────────────────────────────────

/**
 * Jerarquía de roles: admin > supervisor > recepcion
 *
 * Permisos definidos:
 *   admin      : todo
 *   supervisor : reservas.ver, reservas.confirmar, reservas.cancelar,
 *                reservas.completar, correos.enviar, historial.ver
 *   recepcion  : reservas.ver, reservas.confirmar, reservas.cancelar,
 *                historial.ver
 */
const ROLE_PERMISSIONS = [
    'admin' => [
        'reservas.ver', 'reservas.confirmar', 'reservas.cancelar',
        'reservas.completar', 'reservas.comentar',
        'correos.enviar', 'historial.ver',
        'usuarios.crear', 'panel.acceso',
    ],
    'supervisor' => [
        'reservas.ver', 'reservas.confirmar', 'reservas.cancelar',
        'reservas.completar', 'reservas.comentar',
        'correos.enviar', 'historial.ver',
        'panel.acceso',
    ],
    'recepcion' => [
        'reservas.ver', 'reservas.confirmar', 'reservas.cancelar',
        'reservas.comentar', 'historial.ver',
        'panel.acceso',
    ],
];

/**
 * Devuelve el rol actual del usuario autenticado.
 */
function rolActual(): string
{
    return $_SESSION['admin_rol'] ?? 'recepcion';
}

/**
 * Comprueba si el usuario actual tiene un permiso específico.
 */
function can(string $permiso): bool
{
    $rol = rolActual();
    return in_array($permiso, ROLE_PERMISSIONS[$rol] ?? [], true);
}

/**
 * Exige que el usuario tenga uno de los roles indicados.
 * Si no, responde 403.
 */
function requireRole(array $rolesPermitidos): void
{
    requireAuth();
    $rolUsuario = rolActual();

    if (!in_array($rolUsuario, $rolesPermitidos, true)) {
        $esApi = (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false);
        if ($esApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado']);
            exit;
        } else {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso Denegado</title></head>';
            echo '<body><h1>403 – Acceso denegado</h1><p>No tienes permisos para realizar esta acción.</p>';
            echo '<a href="' . SITE_URL . '/panel/panel_reservas.php">Volver al panel</a></body></html>';
            exit;
        }
    }
}

/**
 * Exige el permiso específico; si no, 403.
 */
function requirePermission(string $permiso): void
{
    requireAuth();

    if (!can($permiso)) {
        $esApi = (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false);
        if ($esApi) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado']);
            exit;
        } else {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso Denegado</title></head>';
            echo '<body><h1>403 – Acceso denegado</h1><p>No tienes permisos para realizar esta acción.</p>';
            echo '<a href="' . SITE_URL . '/panel/panel_reservas.php">Volver al panel</a></body></html>';
            exit;
        }
    }
}

// ─────────────────────────────────────────────────────────────
// 5. TOKENS CSRF
// ─────────────────────────────────────────────────────────────

/**
 * Genera (o recupera) el token CSRF de la sesión actual.
 * El token es único por sesión.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF recibido contra el de la sesión.
 * Acepta el token por POST o por header X-CSRF-Token (fetch/AJAX).
 */
function validarCsrf(): bool
{
    $tokenEsperado = $_SESSION['csrf_token'] ?? '';

    if (empty($tokenEsperado)) {
        return false;
    }

    // Token por POST
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    // Token por header (fetch AJAX)
    if (empty($tokenRecibido)) {
        $tokenRecibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    return hash_equals($tokenEsperado, $tokenRecibido);
}

/**
 * Valida CSRF y aborta con error si falla.
 * Usar en todos los endpoints que modifican datos.
 */
function requireCsrf(): void
{
    if (!validarCsrf()) {
        $esApi = (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false)
              || (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')
              || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

        if ($esApi || str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido o ausente']);
            exit;
        }

        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error CSRF</title></head>';
        echo '<body><h1>403 – Petición no válida</h1><p>Token de seguridad inválido. Por favor recarga la página.</p>';
        echo '<a href="javascript:history.back()">Volver</a></body></html>';
        exit;
    }
}

/**
 * Emite el campo hidden CSRF para formularios HTML.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// ─────────────────────────────────────────────────────────────
// 6. RATE LIMITING DE LOGIN (basado en sesión PHP + archivo)
// ─────────────────────────────────────────────────────────────

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900); // 15 minutos

/**
 * Construye la clave de rate-limit para un identificador.
 * Usa IP + identificador para evitar bloqueos globales.
 */
function _loginRateKey(string $identificador): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return 'login_' . md5($ip . '_' . strtolower(trim($identificador)));
}

/**
 * Devuelve la ruta del archivo temporal de rate-limit.
 * Se almacena en sys_get_temp_dir() para compatibilidad con hosting compartido.
 */
function _loginRateFile(string $key): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.rl';
}

/**
 * Comprueba si el identificador está bloqueado por intentos fallidos.
 * Devuelve los segundos restantes del bloqueo, o 0 si no está bloqueado.
 */
function loginBloqueado(string $identificador): int
{
    $file = _loginRateFile(_loginRateKey($identificador));

    if (!file_exists($file)) {
        return 0;
    }

    $data = @json_decode(file_get_contents($file), true);

    if (!$data || !isset($data['attempts'], $data['last_attempt'])) {
        return 0;
    }

    // Si ya pasó el periodo de bloqueo, limpiar
    $tiempoTranscurrido = time() - $data['last_attempt'];
    if ($tiempoTranscurrido > LOGIN_LOCKOUT_SECONDS) {
        @unlink($file);
        return 0;
    }

    if ($data['attempts'] >= LOGIN_MAX_ATTEMPTS) {
        return LOGIN_LOCKOUT_SECONDS - $tiempoTranscurrido;
    }

    return 0;
}

/**
 * Registra un intento fallido de login.
 */
function registrarIntentoFallido(string $identificador): void
{
    $key  = _loginRateKey($identificador);
    $file = _loginRateFile($key);

    $data = ['attempts' => 0, 'last_attempt' => time()];

    if (file_exists($file)) {
        $prev = @json_decode(file_get_contents($file), true);
        if ($prev && (time() - ($prev['last_attempt'] ?? 0)) <= LOGIN_LOCKOUT_SECONDS) {
            $data['attempts'] = ($prev['attempts'] ?? 0) + 1;
        }
    } else {
        $data['attempts'] = 1;
    }

    $data['last_attempt'] = time();
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Limpia el contador de intentos fallidos tras login exitoso.
 */
function limpiarIntentosFallidos(string $identificador): void
{
    $file = _loginRateFile(_loginRateKey($identificador));
    if (file_exists($file)) {
        @unlink($file);
    }
}

// ─────────────────────────────────────────────────────────────
// 7. HELPERS DE ESCAPE CONTEXTUAL
// ─────────────────────────────────────────────────────────────

/**
 * Escape para contexto HTML (atributos y contenido de texto).
 */
function h(mixed $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape para incluir un valor PHP dentro de un string JavaScript.
 * Produce un literal JS seguro (sin las comillas externas).
 * Uso: var x = '<?= jsValue($dato) ?>';
 */
function jsValue(mixed $valor): string
{
    // json_encode produce un string JSON válido incluyendo las comillas
    // Eliminamos las comillas externas para que el código JS las ponga
    $json = json_encode((string)$valor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    // Quitar comillas externas del string JSON
    return mb_substr($json, 1, -1);
}

/**
 * Produce un bloque JSON seguro para incrustar en HTML/JS.
 * Uso: var datos = <?= jsonSafe($array) ?>;
 */
function jsonSafe(mixed $valor): string
{
    return json_encode($valor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

// ─────────────────────────────────────────────────────────────
// 8. NORMALIZACIÓN DE TELÉFONO
// ─────────────────────────────────────────────────────────────

/**
 * Normaliza un número telefónico: solo dígitos y +.
 * Elimina espacios, guiones, paréntesis, etc.
 */
function normalizarTelefono(string $telefono): string
{
    // Conservar + al inicio si existe
    $plus  = str_starts_with($telefono, '+') ? '+' : '';
    $clean = preg_replace('/[^\d]/', '', $telefono);
    return $plus . $clean;
}

// ─────────────────────────────────────────────────────────────
// 9. CABECERAS DE SEGURIDAD HTTP
// ─────────────────────────────────────────────────────────────

/**
 * Emite cabeceras de seguridad recomendadas.
 * Llamar al inicio de cada página antes de cualquier output.
 */
function emitirCabecerasSeguridad(bool $esJson = false): void
{
    if ($esJson) {
        return; // Las páginas JSON no necesitan CSP ni X-Frame-Options
    }

    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    // CSP permisivo pero con protecciones básicas (CDN de FullCalendar permitido)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'");
}
