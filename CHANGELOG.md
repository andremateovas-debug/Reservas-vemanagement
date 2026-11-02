# CHANGELOG

## [Security Audit 1.0] — 2026-11-02

### 🔒 Seguridad — Crítico/High

- **[FIX] Session Fixation**: Añadido `session_regenerate_id(true)` tras login exitoso en `login_panel.php` y `api/auth/login.php`
- **[FIX] CSRF**: Implementado sistema completo de tokens CSRF en `config/security.php`. Todos los formularios del panel y endpoints AJAX/API que modifican datos ahora requieren token válido
- **[FIX] Autorización**: Reemplazada verificación superficial `isset($_SESSION['admin_id'])` por sistema de permisos granular con `requirePermission()` y `requireRole()`
- **[FIX] Rate Limiting**: Añadido límite de 5 intentos de login (panel y API) con bloqueo de 15 minutos
- **[FIX] Penalizaciones duplicadas (Hallazgo H)**: La lógica de historial ahora verifica el estado anterior antes de incrementar reportes. Operación protegida con transacción
- **[FIX] Race condition reservas (Hallazgo E)**: Verificación de disponibilidad + inserción ahora son atómicas mediante `BEGIN TRANSACTION` + `SELECT ... FOR UPDATE`
- **[FIX] Auto-completar estados falsos (Hallazgo G)**: Eliminado el `UPDATE` automático que marcaba reservas confirmadas como completadas sin verificar asistencia
- **[FIX] Inconsistencia API vs AJAX (Hallazgo J4)**: `api/reserva/estado.php` ahora aplica la misma lógica completa que `ajax_cambiar_estado.php` (penalizaciones, correos, transacciones)

### 🔒 Seguridad — Medium

- **[FIX] XSS Almacenado (Hallazgo A1)**: Eliminada interpolación de datos en atributos `onclick` de `panel_correos.php`. Datos transmitidos via `data-*` y leídos con `dataset.*`
- **[FIX] XSS Reflejado (Hallazgo A2)**: Validación estricta de formato de fecha en `$_GET['fecha']` + escape con `h()` en `panel_reservas.php` y `panel_correos.php`
- **[FIX] Inyección HTML en correos (Hallazgo I)**: Todos los campos de cliente en `correos.php` y `panel_correos.php` escapados con `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`
- **[FIX] Cookies de sesión inseguras**: Configuración centralizada con `HttpOnly=true`, `SameSite=Lax`, `Secure` automático
- **[FIX] Expiración de sesión**: Implementado timeout de inactividad (2 horas)
- **[FIX] Política de contraseñas débil (Hallazgo J1)**: Mínimo 10 caracteres con mayúsculas, minúsculas y números en `crear_admin.php`
- **[FIX] Logout API sin método HTTP (Hallazgo J2)**: `api/auth/logout.php` ahora solo acepta POST
- **[FIX] Exposición de errores internos (Hallazgo J3)**: Errores de BD enviados a `error_log()`, mensajes genéricos al usuario
- **[FIX] Validaciones de entrada (Hallazgo F)**: `procesar_reserva.php` usa `MAX_PERSONAS` de `config.php`, valida fecha no pasada, hora en whitelist, formato de teléfono, longitud de campos
- **[FIX] Normalización de teléfonos (Hallazgo J5)**: Función `normalizarTelefono()` centralizada en `security.php`

### 🔒 Seguridad — Low

- **[FIX] IDOR en ajax_comentario.php**: Verificación de existencia de reserva antes de actualizar
- **[FIX] Cabeceras HTTP**: Añadidas `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Content-Security-Policy`

### 🏗️ Arquitectura

- **[NEW] `config/security.php`**: Núcleo centralizado de seguridad con autenticación, autorización, CSRF, rate limiting, helpers de escape y normalización
- **[MOD] `config/config.php`**: Integrado con `security.php`. Ahora llama a `configurarSesionSegura()` antes de `session_start()`. Constante `PASSWORD_MIN_LENGTH`
- **[NEW] `config/migration_security_v1.sql`**: Archivo de migración (sin cambios de esquema requeridos, con instrucciones para `no_asistio` opcional)

### 📁 Archivos modificados

| Archivo | Cambios |
|---------|---------|
| `config/config.php` | Integración security.php, PASSWORD_MIN_LENGTH, PDO con ATTR_EMULATE_PREPARES=false |
| `config/security.php` | **NUEVO** — núcleo de seguridad centralizado |
| `panel/login_panel.php` | session_regenerate_id, CSRF, rate limiting, cabeceras seguridad |
| `panel/logout.php` | Invalidación completa, prevención caché |
| `panel/panel_reservas.php` | requirePermission, CSRF en JS, validación fecha GET, eliminado auto-completar |
| `panel/panel_correos.php` | requirePermission, CSRF forms, data-* en lugar de onclick, escape correcto |
| `panel/ajax_cambiar_estado.php` | requirePermission, CSRF, transacciones, anti-duplicado penalizaciones |
| `panel/ajax_completar_reserva.php` | requirePermission, CSRF, AND estado='confirmada' en UPDATE |
| `panel/ajax_comentario.php` | requirePermission, CSRF, verificación IDOR |
| `panel/ajax_calendario.php` | requirePermission |
| `panel/ajax_historial.php` | requirePermission |
| `panel/ajax_detalles.php` | requirePermission |
| `panel/verificar_penalizacion.php` | requirePermission, normalización teléfono, método GET |
| `panel/crear_admin.php` | requireRole(admin), CSRF, política contraseñas mejorada |
| `panel/correos.php` | Escape completo ENT_QUOTES+UTF-8 en todas las plantillas, error_log |
| `api/config_api.php` | Content-Type header, delega a requireAuth() |
| `api/auth/login.php` | session_regenerate_id, rate limiting, CSRF |
| `api/auth/logout.php` | Solo POST, verificación sesión activa |
| `api/reserva/estado.php` | requirePermission, CSRF, lógica completa (penalizaciones, correos, transacciones) |
| `api/reserva/comentario.php` | requirePermission, CSRF, verificación IDOR |
| `api/reserva/detalles.php` | requirePermission, método GET, filter_input |
| `api/reservas.php` | requirePermission, validación fechas |
| `api/filtrar.php` | requirePermission, validación fecha |
| `reservas/procesar_reserva.php` | MAX_PERSONAS de config, validación completa servidor, transacción+FOR UPDATE |

### 📁 Archivos nuevos

| Archivo | Descripción |
|---------|-------------|
| `config/security.php` | Núcleo centralizado de seguridad |
| `config/migration_security_v1.sql` | Documentación de migración |
| `SECURITY.md` | Política de seguridad |
| `AUDITORIA_SEGURIDAD.md` | Reporte de auditoría |
| `CHANGELOG.md` | Este archivo |
