# AUDITORÍA DE SEGURIDAD — Reservas Vemanagement

**Fecha:** 2026-11-02  
**Versión:** 1.0  
**Auditor:** Intervención técnica automatizada

---

## Vulnerabilidades encontradas, corregidas y estado de pruebas

### A1 — XSS Almacenado en atributo onclick
| Campo | Valor |
|-------|-------|
| **Archivo** | `panel/panel_correos.php` |
| **Severidad** | High |
| **Riesgo** | Un nombre/email/teléfono con caracteres especiales podía cerrar el string JS y ejecutar código arbitrario en el navegador del administrador |
| **Código original** | `onclick="seleccionar(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre'],ENT_QUOTES) ?>', ...)"` |
| **Problema** | `htmlspecialchars` no es suficiente en contexto JavaScript; caracteres como `\`, `</`, etc. pueden romper el contexto |
| **Solución implementada** | Eliminada la interpolación en `onclick`. Los datos se transmiten via atributos `data-*` y se leen con `el.dataset.*` en JavaScript. Se usan `textContent` y `createElement` para actualizar el DOM |
| **Estado** | ✅ Corregido |
| **Prueba** | `php -l panel/panel_correos.php` → sin errores. Revisión manual del patrón `onclick` en el HTML generado |

---

### A2 — XSS Reflejado en parámetro GET fecha
| Campo | Valor |
|-------|-------|
| **Archivo** | `panel/panel_reservas.php`, `panel/panel_correos.php` |
| **Severidad** | Medium |
| **Riesgo** | El valor de `$_GET['fecha']` se insertaba directamente en el atributo `value` del input sin validar el formato |
| **Solución implementada** | Validación estricta de formato `YYYY-MM-DD` con expresión regular antes de usar el valor. Escape con `h()` en el output HTML |
| **Estado** | ✅ Corregido |

---

### B — Sin protección CSRF
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | `ajax_cambiar_estado.php`, `ajax_completar_reserva.php`, `ajax_comentario.php`, `crear_admin.php`, `panel_correos.php`, `api/reserva/estado.php`, `api/reserva/comentario.php` |
| **Severidad** | High |
| **Riesgo** | Un atacante podía engañar a un administrador autenticado para que ejecutase acciones maliciosas (cambiar estados, cancelar reservas, crear usuarios) |
| **Solución implementada** | Token CSRF generado con `random_bytes(32)` y almacenado en sesión. Validación con `hash_equals()` en todos los endpoints que modifican datos. Formularios incluyen `csrfField()`. Peticiones AJAX envían el token en el header `X-CSRF-Token` |
| **Estado** | ✅ Corregido |

---

### C — Sin regeneración de ID de sesión
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | `panel/login_panel.php`, `api/auth/login.php` |
| **Severidad** | High |
| **Riesgo** | Session fixation: un atacante podía fijar el ID de sesión y secuestrarla tras el login |
| **Solución implementada** | `session_regenerate_id(true)` inmediatamente después de autenticar correctamente |
| **Estado** | ✅ Corregido |

---

### C2 — Política de cookies insegura
| Campo | Valor |
|-------|-------|
| **Archivo** | `config/config.php` |
| **Severidad** | High |
| **Riesgo** | Cookies de sesión accesibles via JavaScript (XSS puede robar sesiones), sin SameSite |
| **Solución implementada** | `configurarSesionSegura()` en `config/security.php` establece `HttpOnly=true`, `Secure=true` (detectado automáticamente), `SameSite=Lax` |
| **Estado** | ✅ Corregido |

---

### C3 — Sin expiración de sesión por inactividad
| Campo | Valor |
|-------|-------|
| **Archivo** | `config/security.php` |
| **Severidad** | Medium |
| **Riesgo** | Una sesión abandonada podía ser utilizada indefinidamente |
| **Solución implementada** | Verificación de `_last_activity` en `verificarExpiracionSesion()`. Tiempo configurable con `SESSION_TIMEOUT` (2 horas por defecto) |
| **Estado** | ✅ Corregido |

---

### C4 — Sin rate limiting de login
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | `panel/login_panel.php`, `api/auth/login.php` |
| **Severidad** | High |
| **Riesgo** | Ataques de fuerza bruta sin limitación |
| **Solución implementada** | Máximo 5 intentos fallidos por IP+usuario. Bloqueo de 15 minutos. Implementado con archivos temporales (compatible con hosting compartido) |
| **Estado** | ✅ Corregido |

---

### D — Autorización incompleta (solo verificaba admin_id)
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | Todos los archivos del panel y la API |
| **Severidad** | High |
| **Riesgo** | Un usuario con rol `recepcion` podía acceder a funcionalidades exclusivas de `admin` o `supervisor` |
| **Solución implementada** | Sistema centralizado de permisos en `config/security.php` con `requirePermission()`, `requireRole()` y `can()`. Cada archivo verifica el permiso específico que necesita |
| **Estado** | ✅ Corregido |

---

### E — Race condition en reservas simultáneas
| Campo | Valor |
|-------|-------|
| **Archivo** | `reservas/procesar_reserva.php` |
| **Severidad** | High |
| **Riesgo** | Dos clientes podían reservar el mismo slot exactamente al mismo tiempo, resultando en dos reservas para la misma hora |
| **Solución implementada** | Transacción con `BEGIN_TRANSACTION` + `SELECT ... FOR UPDATE` en el slot fecha/hora antes de insertar. El bloqueo a nivel de filas garantiza atomicidad |
| **Estado** | ✅ Corregido |

---

### F — Inconsistencia en MAX_PERSONAS
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | `config/config.php`, `reservas/procesar_reserva.php`, formulario público |
| **Severidad** | Medium |
| **Riesgo** | El servidor aceptaba hasta 20 personas pero el README y la config decían 12 |
| **Solución implementada** | `MAX_PERSONAS = 12` en `config/config.php` como única fuente de verdad. `procesar_reserva.php` usa `MAX_PERSONAS` en la validación |
| **Estado** | ✅ Corregido |

---

### G — Auto-completar reservas vencidas sin verificar asistencia
| Campo | Valor |
|-------|-------|
| **Archivo** | `panel/panel_reservas.php` |
| **Severidad** | Medium |
| **Riesgo** | Reservas marcadas como completadas automáticamente sin importar si el cliente asistió; impide registrar el total cobrado correctamente |
| **Solución implementada** | Eliminado el `UPDATE` automático. Las reservas deben completarse manualmente. Se documenta la necesidad de un estado `no_asistio` como decisión de negocio pendiente |
| **Estado** | ✅ Corregido |

---

### H — Penalizaciones duplicadas
| Campo | Valor |
|-------|-------|
| **Archivo** | `panel/ajax_cambiar_estado.php`, `api/reserva/estado.php` |
| **Severidad** | High |
| **Riesgo** | Cambiar una reserva ya cancelada a `cancelada` nuevamente incrementaba el contador de reportes |
| **Solución implementada** | Se guarda el estado anterior antes de actualizar. La penalización solo se aplica cuando `estado_anterior !== 'cancelada'`. Operación protegida con transacción |
| **Estado** | ✅ Corregido |

---

### I — Inyección HTML en plantillas de correo
| Campo | Valor |
|-------|-------|
| **Archivo** | `panel/correos.php`, `panel/panel_correos.php` |
| **Severidad** | Medium |
| **Riesgo** | Datos del cliente (nombre, email, teléfono, comentarios) insertados directamente en HTML sin escape podían inyectar contenido malicioso en los correos |
| **Solución implementada** | Función `_he()` interna aplica `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` a todos los campos dinámicos. Números enteros casteados con `(int)` |
| **Estado** | ✅ Corregido |

---

### J1 — Contraseña mínima débil en crear_admin.php
| Campo | Valor |
|-------|-------|
| **Archivo** | `panel/crear_admin.php` |
| **Severidad** | Medium |
| **Riesgo** | Contraseñas de 6 caracteres son trivialmente fáciles de bruteforcear |
| **Solución implementada** | Mínimo 10 caracteres (configurable con `PASSWORD_MIN_LENGTH`). Requerimiento de mayúsculas, minúsculas y números |
| **Estado** | ✅ Corregido |

---

### J2 — Logout API sin verificar método HTTP
| Campo | Valor |
|-------|-------|
| **Archivo** | `api/auth/logout.php` |
| **Severidad** | Medium |
| **Riesgo** | Una petición GET podía destruir la sesión de un usuario activo (CSRF via imagen/enlace) |
| **Solución implementada** | Solo acepta POST. Verifica sesión activa antes de destruir |
| **Estado** | ✅ Corregido |

---

### J3 — Exposición de errores internos de BD
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | `config/config.php`, `panel/crear_admin.php` |
| **Severidad** | Medium |
| **Riesgo** | Mensajes de error de conexión y operación exponían detalles internos |
| **Solución implementada** | Errores se envían al log del servidor (`error_log()`). Usuario recibe mensaje genérico |
| **Estado** | ✅ Corregido |

---

### J4 — Inconsistencia de lógica entre API AJAX y AJAX panel
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | `api/reserva/estado.php` vs `panel/ajax_cambiar_estado.php` |
| **Severidad** | High |
| **Riesgo** | La API cambiaba el estado sin verificar el estado anterior, sin enviar correos y sin actualizar penalizaciones |
| **Solución implementada** | Ambos endpoints comparten la misma lógica de negocio completa: verificación de estado anterior, penalizaciones con anti-duplicado, transacciones, correos |
| **Estado** | ✅ Corregido |

---

### J5 — Normalización de teléfonos
| Campo | Valor |
|-------|-------|
| **Archivos afectados** | `panel/ajax_cambiar_estado.php`, `api/reserva/estado.php`, `reservas/procesar_reserva.php`, `panel/verificar_penalizacion.php` |
| **Severidad** | Low |
| **Riesgo** | El mismo teléfono en formatos distintos podía generar múltiples registros de historial |
| **Solución implementada** | Función `normalizarTelefono()` centralizada en `security.php`. Se aplica al guardar y al consultar historial |
| **Estado** | ✅ Corregido |

---

## Pruebas ejecutadas

### Pruebas estáticas (sin servidor)
| Prueba | Resultado |
|--------|-----------|
| `php -l` en 26 archivos PHP | ✅ Sin errores de sintaxis |
| Búsqueda de `eval/exec/system` peligrosos | ✅ No encontrado |
| Búsqueda de SQL con concatenación de `$_GET/$_POST` | ✅ No encontrado |
| Verificación de `display_errors` | ✅ = 0 |
| Verificación de `htmlspecialchars` en outputs HTML | ✅ Aplicado |
| Búsqueda de `session_start()` sin configuración segura | ✅ Centralizado |

### Pruebas que requieren entorno externo (MySQL + servidor web)

> ⚠️ Las siguientes pruebas **no pudieron ejecutarse** en este entorno porque requieren un servidor MySQL activo y un servidor web con PHP:

| Prueba | Motivo pendiente |
|--------|-----------------|
| Login con credenciales correctas | Requiere BD con usuario activo |
| Rate limiting: 6 intentos → bloqueo 15 min | Requiere BD + HTTP |
| CSRF: enviar POST sin token → 403 | Requiere servidor web |
| CSRF: enviar POST con token correcto → éxito | Requiere servidor web |
| Session fixation: verificar que el ID cambia tras login | Requiere servidor web |
| Expiración de sesión: esperar 2h o modificar `_last_activity` | Requiere servidor web |
| Doble reserva simultánea: dos requests concurrentes → solo una guardada | Requiere servidor web + BD |
| Cancelar reserva ya cancelada: contador no incrementa | Requiere BD activa |
| MAX_PERSONAS: enviar personas=13 → error 422 | Requiere servidor web |
| Fecha pasada: enviar fecha de ayer → error | Requiere servidor web |
| Hora inválida: enviar `hora=15:00` (no en whitelist) → error | Requiere servidor web |
| Rol recepcion intentando acceder a panel_correos.php → 403 | Requiere servidor web |
| IDOR: acceder a reserva de otro usuario con ID manipulado | Requiere servidor web + BD |
| XSS: nombre con `<script>alert(1)</script>` → escapado | Requiere navegador |
| Correo enviado al completar con total → verificar importe | Requiere BD + servidor mail |

---

## Riesgos residuales

| ID | Descripción | Severidad | Justificación |
|----|-------------|-----------|---------------|
| R1 | `mail()` sin autenticación SMTP | Medium | Requiere PHPMailer, fuera del alcance mínimo |
| R2 | Rate limiting basado en archivos temporales | Low | Compatible con hosting compartido; mejorable con Redis |
| R3 | CSP con `unsafe-inline` | Low | Requerido por FullCalendar inline y estilos PHP heredados |
| R4 | Estado `no_asistio` no implementado | Low | Decisión de negocio pendiente |
| R5 | Sin 2FA para administradores | Medium | Requiere integración TOTP, fuera del alcance |
| R6 | Sin tabla de auditoría de acciones | Low | Requiere nueva tabla, fuera del alcance |
