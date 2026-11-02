# SECURITY.md — Reservas Vemanagement

## Medidas de seguridad implementadas

### Autenticación y gestión de sesiones
- Contraseñas almacenadas con `password_hash()` (bcrypt, PHP default)
- `session_regenerate_id(true)` al autenticar correctamente (previene Session Fixation)
- Cookies de sesión configuradas con `HttpOnly`, `SameSite=Lax` y `Secure` (cuando hay HTTPS)
- Expiración de sesión por inactividad (2 horas, configurable con `SESSION_TIMEOUT`)
- Invalidación completa de sesión al cerrar sesión (panel y API)
- Rate limiting de intentos de login: 5 intentos máximos, bloqueo de 15 minutos
- Mensajes de error genéricos que no revelan si un usuario existe

### Autorización basada en roles
- Sistema centralizado de permisos en `config/security.php`
- Tres roles: `admin`, `supervisor`, `recepcion`
- Todas las páginas del panel, endpoints AJAX y API validan permisos en el servidor
- Función `requirePermission()` y `requireRole()` reutilizables
- Protección IDOR: verificación de existencia de reserva antes de modificar

### Protección CSRF
- Tokens CSRF criptográficamente seguros (`random_bytes(32)`)
- Validación en todos los formularios del panel y endpoints que modifican datos
- Tokens transmitidos por:
  - Campo `hidden` en formularios HTML
  - Header `X-CSRF-Token` en peticiones AJAX/fetch
- Comparación con `hash_equals()` para prevenir timing attacks

### Prevención de XSS
- Escape contextual con `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` en todos los outputs HTML
- Eliminación de interpolación directa de datos en atributos `onclick`
- Datos de reservas en `panel_correos.php` transmitidos via atributos `data-*`
- Plantillas de correo con escape completo de todos los campos del cliente
- Función `h()` centralizada para escape consistente
- Función `jsValue()` para incrustación segura en JavaScript
- Función `jsonSafe()` para JSON embebido en HTML
- Validación estricta de formato de fecha en parámetros GET (`panel_reservas.php`)

### Inyección SQL
- Todas las consultas usan prepared statements (MySQLi y PDO)
- Sin concatenación de valores externos en queries SQL

### Integridad y concurrencia
- Transacciones (`BEGIN`/`COMMIT`/`ROLLBACK`) en operaciones críticas:
  - Procesamiento de reservas
  - Cambio de estados con actualización de historial
- `SELECT ... FOR UPDATE` en verificación de disponibilidad para prevenir doble reserva
- Detección de transición de estado antes de aplicar penalizaciones (evita duplicados)

### Cabeceras de seguridad HTTP
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy` básico (compatible con FullCalendar CDN)

### Configuración defensiva
- `display_errors = 0` en producción
- `log_errors = 1` (errores al log del servidor, no al cliente)
- Errores de BD no exponen detalles al usuario

---

## Configuración recomendada para producción

### HTTPS (obligatorio)
```
# .htaccess en la raíz del proyecto
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

El flag `Secure` en las cookies de sesión se activa automáticamente cuando se detecta HTTPS.

### PHP (php.ini o .htaccess)
```ini
display_errors = Off
log_errors = On
session.use_strict_mode = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax
expose_php = Off
```

### MySQL
- Crear un usuario MySQL con permisos mínimos (solo SELECT, INSERT, UPDATE, DELETE sobre la BD)
- No usar root para la aplicación

---

## Gestión de credenciales
- `config/config.php` **nunca debe subirse a un repositorio público**
- Agregar a `.gitignore`:
  ```
  config/config.php
  ```
- En producción, las credenciales pueden definirse como variables de entorno del servidor

---

## Procedimientos de respaldo
```bash
# Respaldo completo de BD
mysqldump -u USUARIO -p NOMBRE_BD > backup_$(date +%Y%m%d_%H%M%S).sql

# Respaldo de archivos
tar -czf files_backup_$(date +%Y%m%d).tar.gz /ruta/public_html/
```

---

## Política de reporte de vulnerabilidades
Si encuentras una vulnerabilidad de seguridad:
1. **No la divulgues públicamente** hasta que haya sido corregida
2. Envía un reporte detallado al propietario del proyecto
3. Incluye: descripción, pasos para reproducir, impacto estimado

---

## Requisitos HTTPS
El sistema está diseñado para operar bajo HTTPS. Sin HTTPS:
- Las cookies de sesión no se marcan como `Secure`
- Los datos del formulario público viajan sin cifrar
- Se recomienda certificado SSL/TLS (Let's Encrypt es gratuito en la mayoría de hostings)

---

## Riesgos residuales documentados

| ID | Descripción | Severidad | Estado |
|----|-------------|-----------|--------|
| R1 | `mail()` de PHP sin autenticación SMTP | Medium | Pendiente - requiere PHPMailer |
| R2 | Rate limiting basado en archivos temporales | Low | Funcional, mejorable con Redis/DB |
| R3 | CSP con `unsafe-inline` (requerido por FullCalendar) | Low | Documentado - inherente al stack |
| R4 | Estado `no_asistio` no implementado | Low | Decisión de negocio pendiente |
| R5 | Sin 2FA para administradores | Medium | Fuera del alcance actual |
| R6 | Logs de auditoría sin tabla dedicada | Low | Fuera del alcance actual |
