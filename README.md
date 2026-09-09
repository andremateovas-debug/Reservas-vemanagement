# Sistema de Reservas y Panel de Administración

Sistema web de reservaciones online y panel de administración interno, desarrollado en PHP puro para negocios de restauración. Permite a los clientes hacer reservas desde la web y al personal gestionar todo desde un panel protegido con roles, envío automático de correos, calendario interactivo y panel lateral de historial.

> **Versión pública** — Las credenciales reales han sido eliminadas. Ver la sección de [instalación](#instalación) para configurar el sistema en tu propio entorno.

---

## Qué hace este sistema

- **Formulario público de reservas** en 2 pasos (fecha/hora + datos de contacto), con selección de horarios disponibles en tiempo real
- **Verificación de disponibilidad** en vivo: si una hora ya está reservada, el sistema sugiere automáticamente la siguiente disponible
- **Soporte bilingüe** (ES/EN) en el formulario público sin recarga de página
- **Envío automático de correos** al cliente y al restaurante al crear una reserva, al confirmarla y al cancelarla
- **Panel de administración** con autenticación por sesión y 3 niveles de rol: `admin`, `supervisor`, `recepcion`
- **Vista lista y calendario** (FullCalendar) del mismo panel con toggle entre ambas
- **Panel lateral de historial** (drawer) con línea de tiempo de los últimos 60 días + próximos 30, con conteo de reservas por estado por día
- **Gestión de estados**: `pendiente` → `confirmada` → `completada` / `cancelada`
- **Registro del total cobrado** al completar una reserva
- **Historial de penalizaciones**: se acumula un reporte por cada cancelación del cliente; al llegar a 3 se marca como penalizado y se muestra en los detalles de la reserva
- **Auto-completar reservas pasadas**: las confirmadas con fecha/hora ya pasada se completan automáticamente al cargar el panel
- **Módulo de correos manuales**: enviar un mensaje personalizado a cualquier reserva del día o a un email libre, con vista previa en tiempo real
- **Gestión de usuarios del panel**: crear nuevos administradores con rol desde el propio panel (solo rol `admin`)

---

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.1+ (MySQLi + PDO) |
| Base de datos | MySQL 8.0+ / MariaDB 10.5+ |
| Frontend | HTML / CSS / JavaScript vanilla |
| Calendario | FullCalendar 5 (CDN) |
| Correos | Función nativa `mail()` con plantillas HTML |
| Hosting objetivo | cPanel (shared hosting) |

---

## Estructura del proyecto

El proyecto está pensado para vivir dentro de `public_html/` en el servidor:

```
public_html/
├── config/
│   ├── config.php                 ← Configuración central: BD, constantes, funciones globales
│   └── schema.sql                 ← Schema de BD sin datos (para recrear desde cero)
│
├── reservas/                      ← Formulario público de reservaciones
│   ├── reservacion.php            ← Página principal del formulario (2 pasos, bilingüe)
│   ├── check_availability.php     ← Endpoint AJAX: devuelve fechas ocupadas para una hora
│   ├── procesar_reserva.php       ← Procesa el POST, guarda la reserva y envía correos
│   └── .htaccess                  ← Bloquea listado de directorio y archivos sensibles
│
├── panel/                         ← Panel de administración (requiere login)
│   ├── login_panel.php            ← Login del panel
│   ├── panel_reservas.php         ← Dashboard: lista, filtros, calendario, historial drawer
│   ├── panel_correos.php          ← Envío manual de correos a clientes
│   ├── ajax_calendario.php        ← Eventos para FullCalendar (JSON)
│   ├── ajax_cambiar_estado.php    ← Cambia estado + envía correo (POST)
│   ├── ajax_comentario.php        ← Guarda comentario de admin (POST)
│   ├── ajax_completar_reserva.php ← Completa reserva con monto cobrado (POST)
│   ├── ajax_detalles.php          ← HTML con detalles de reserva para modal
│   ├── ajax_historial.php         ← Historial de días con reservas para drawer (JSON)
│   ├── correos.php                ← Funciones reutilizables de envío de correo
│   ├── crear_admin.php            ← Crear nuevos usuarios del panel (solo rol admin)
│   ├── logout.php                 ← Cierre de sesión
│   ├── verificar_penalizacion.php ← Verifica penalizaciones por teléfono (JSON)
│   └── .htaccess                  ← Seguridad: bloquea php_errorlog y archivos sensibles
│
└── api/                           ← API JSON alternativa (consumida via fetch)
    ├── config_api.php             ← Incluye config.php + helper de autenticación
    ├── reservas.php               ← Lista reservas para FullCalendar (GET)
    ├── filtrar.php                ← Filtra reservas por fecha (GET)
    ├── auth/
    │   ├── login.php              ← Login API (POST)
    │   └── logout.php             ← Logout API (POST)
    └── reserva/
        ├── comentario.php         ← Actualiza comentario (POST)
        ├── detalles.php           ← Obtiene detalles de una reserva (GET)
        └── estado.php             ← Cambia estado (POST)
```

---

## Base de datos

**4 tablas** (sin datos de ejemplo — solo estructura):

| Tabla | Descripción |
|-------|-------------|
| `reservas` | Reservaciones. Estados: `pendiente`, `confirmada`, `cancelada`, `completada`. Incluye campo `total` para el monto cobrado al completar. |
| `clientes` | Registro único por email/celular con contador de visitas (`visitas`). |
| `clientes_historial` | Historial de cancelaciones por teléfono. `reportes` se incrementa con cada cancelación; `penalizada = 1` cuando llega a 3. PK: `telefono`. |
| `usuarios_admin` | Cuentas del panel. Roles: `admin`, `supervisor`, `recepcion`. Contraseñas almacenadas con `password_hash()`. |

Para crear la base de datos desde cero:
1. Crear la base de datos manualmente en cPanel / phpMyAdmin
2. Importar `config/schema.sql`

---

## Instalación

### 1. Subir los archivos

Sube el contenido de este repositorio a una carpeta dentro de `public_html/`. El sistema está pensado para funcionar en la raíz del dominio o en una subcarpeta, por ejemplo:

```
public_html/          → tudominio.com/
```

Si lo pones en una subcarpeta (e.g. `public_html/reservas/`), actualiza `SITE_URL` en `config/config.php`.

### 2. Crear la base de datos

1. Desde cPanel o tu gestor de bases de datos, crea una base de datos MySQL vacía
2. Crea un usuario MySQL y asígnale todos los permisos sobre esa base de datos
3. Importa el archivo `config/schema.sql`

### 3. Configurar credenciales

Edita `config/config.php` y reemplaza los placeholders:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'TU_USUARIO_BD');       // ← tu usuario de MySQL
define('DB_PASS', 'TU_CONTRASENA_BD');    // ← tu contraseña
define('DB_NAME', 'TU_NOMBRE_DE_BD');     // ← nombre de la base de datos

define('SITE_NAME', 'Tu Restaurante');             // ← nombre del negocio
define('ADMIN_EMAIL', 'reservaciones@tudominio.com'); // ← email para recibir notificaciones
define('SITE_URL', 'https://tudominio.com');       // ← URL raíz sin barra final
```

> El archivo `config/config.php` **nunca debe subirse con credenciales reales a un repositorio público**.

### 4. Ajustar timezone (opcional)

```php
define('SITE_TIMEZONE', 'America/Mazatlan');
```

Zonas comunes para México:
- `America/Mexico_City` (Ciudad de México)
- `America/Mazatlan` (Sinaloa, Nayarit)
- `America/Tijuana` (Baja California)

### 5. Crear el primer usuario administrador

Accede directamente a `tudominio.com/panel/crear_admin.php` la primera vez. Crea un usuario con rol `admin`. A partir de ahí, todos los usuarios adicionales se crean desde el panel.

### 6. Verificar correos

El sistema usa la función nativa `mail()` de PHP. En la mayoría de hostings compartidos con cPanel funciona directamente. Si los correos no llegan:
- Verifica que el servidor tenga `sendmail` configurado
- Revisa que `ADMIN_EMAIL` y el `From:` en `correos.php` usen un dominio alojado en el mismo servidor
- Algunos hostings requieren usar SMTP externo (considera usar PHPMailer como mejora)

---

## Requisitos del servidor

| Requisito | Mínimo |
|-----------|--------|
| PHP | 8.1+ |
| MySQL / MariaDB | 8.0+ / 10.5+ |
| Extensiones PHP | `mysqli`, `pdo_mysql`, `json` |
| Apache | `mod_rewrite` habilitado |
| `AllowOverride` | `All` (para que funcione el `.htaccess`) |

En la mayoría de hostings compartidos (cPanel) estos requisitos ya están cubiertos.

---

## Panel de administración

### Roles

| Rol | Acceso |
|-----|--------|
| `admin` | Total: gestión de reservas, correos, creación de usuarios |
| `supervisor` | Gestión de reservas y correos |
| `recepcion` | Ver y cambiar estados de reservas |

### Flujo de una reserva

```
Cliente llena el formulario
        ↓
Reserva creada con estado "pendiente"
        ↓
Correo automático al cliente (solicitud recibida) + notificación al restaurante
        ↓
Admin confirma o cancela desde el panel
        ↓
Correo automático al cliente según la acción
        ↓ (si se cancela)
+1 reporte en clientes_historial → penalizado si llega a 3
        ↓ (si se confirma)
Espera a la fecha/hora de la reserva
        ↓
Auto-completar: reservas confirmadas pasadas se marcan como completadas al cargar el panel
        ↓ (o manualmente)
Admin registra el total cobrado → estado "completada"
```

### Panel lateral de historial

El dashboard incluye un drawer lateral accesible desde el botón **HISTORIAL** en el borde izquierdo. Muestra una línea de tiempo con todos los días (últimos 60 días + próximos 30) que tienen reservas, con chips de conteo por estado. Al hacer clic en una fecha navega directamente a esa vista. Se carga una sola vez (lazy load) y hace scroll automático al día de hoy al abrirse.

---

## Seguridad implementada

- Prepared statements en todas las queries (sin concatenación de SQL)
- `password_hash()` / `password_verify()` para contraseñas de administradores
- `htmlspecialchars()` en todos los outputs al HTML
- `display_errors = 0` en producción (errores solo al log del servidor)
- `Content-Type: application/json` al inicio de todos los endpoints AJAX antes de cualquier output
- Verificación de `$_SESSION['admin_id']` en cada archivo del panel y la API
- Guard de rol `admin` en `crear_admin.php`
- `.htaccess` en `/panel/` y `/reservas/` bloqueando listado de directorio y archivos `.env`, `.log`, `.sql`, `.bak`, `.sh`
- Whitelist de horas válidas en `check_availability.php` antes de consultar la BD

---

## Personalización

### Cambiar horarios disponibles

En `reservas/reservacion.php`, edita los arrays `morningTimes` y `eveningTimes` en el JavaScript, y actualiza la whitelist en `reservas/check_availability.php`.

### Cambiar el máximo de personas

En `config/config.php`:

```php
define('MAX_PERSONAS', 12);
```

Y actualiza el `<select>` de personas en `reservas/reservacion.php` acorde.

### Modificar las plantillas de correo

Todas las funciones de correo están centralizadas en `panel/correos.php`. Cada función tiene su plantilla HTML inline que puedes personalizar libremente.

### Agregar/quitar idiomas del formulario

El formulario público soporta ES/EN mediante el objeto `translations` en `reservas/reservacion.php`. Agrega nuevas claves al objeto y un botón adicional en `.lang-switcher` para soportar más idiomas.

---

## Tecnologías utilizadas

| Tecnología | Uso |
|-----------|-----|
| PHP 8.1+ | Backend, endpoints AJAX, lógica de negocio |
| MySQL / MariaDB | Almacenamiento de reservas, clientes y usuarios |
| HTML / CSS / JS vanilla | Frontend sin frameworks |
| FullCalendar 5 | Calendario interactivo en el panel |
| `mail()` de PHP | Envío de correos con plantillas HTML |

---

## Licencia

**Licencia Propietaria / Proprietary License**
© 2026 Andre Mateo Vasquez Mosqueda / AM Devs. Todos los derechos reservados.

Puedes consultar, estudiar y usar este código como **referencia** para tus propios proyectos, siempre que des crédito al autor:

> "Andre Mateo Vasquez Mosqueda / AM Devs"

No está permitido redistribuir el código completo ni presentarlo como propio. El uso comercial requiere autorización previa por escrito.

Lee el archivo [`LICENSE`](LICENSE) para los términos completos en español e inglés.
