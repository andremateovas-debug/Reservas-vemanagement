-- ============================================================
-- MIGRACIÓN DE SEGURIDAD v1.0
-- Reservas Vemanagement
-- Fecha: 2026-11-02
-- Descripción: No requiere cambios en el esquema de tablas
--              para esta iteración de seguridad.
--
-- Los cambios son exclusivamente en el código PHP.
-- El esquema actual es compatible con todas las correcciones.
--
-- IMPORTANTE: Hacer backup antes de ejecutar cualquier migración.
-- ============================================================

-- INSTRUCCIONES DE RESPALDO:
-- mysqldump -u USUARIO -p NOMBRE_BD > backup_antes_migracion_$(date +%Y%m%d).sql

-- VERIFICACIONES POST-MIGRACIÓN:
-- 1. Verificar que la tabla clientes_historial tiene columnas nombre y email:
SHOW COLUMNS FROM clientes_historial;

-- Resultado esperado:
-- +----------+--------------+------+-----+---------+-------+
-- | Field    | Type         | Null | Key | Default | Extra |
-- +----------+--------------+------+-----+---------+-------+
-- | telefono | varchar(20)  | NO   | PRI | NULL    |       |
-- | nombre   | varchar(100) | NO   |     |         |       |
-- | email    | varchar(100) | NO   |     |         |       |
-- | reportes | int          | YES  |     | 0       |       |
-- | penalizada | tinyint(1) | YES  |     | 0       |       |
-- +----------+--------------+------+-----+---------+-------+

-- 2. Verificar índices de reservas para soportar FOR UPDATE eficientemente:
SHOW INDEX FROM reservas;

-- 3. El campo `estado` de reservas mantiene los mismos valores:
--    'pendiente', 'confirmada', 'cancelada', 'completada'
--    NO se agrega 'no_asistio' en esta versión (decisión de negocio pendiente)

-- ============================================================
-- NOTA SOBRE ESTADO 'no_asistio':
-- El README documenta que las reservas confirmadas pasadas se
-- completaban automáticamente. Ese comportamiento fue eliminado
-- (Hallazgo G). Si se desea agregar un estado 'no_asistio',
-- ejecutar la siguiente migración OPCIONAL:
-- ============================================================

-- MIGRACIÓN OPCIONAL — Estado no_asistio
-- (NO ejecutar sin validar el impacto en el panel y los correos)
--
-- ALTER TABLE reservas
--   MODIFY COLUMN estado
--   ENUM('pendiente','confirmada','cancelada','completada','no_asistio')
--   DEFAULT 'pendiente';
--
-- REVERSIÓN:
-- ALTER TABLE reservas
--   MODIFY COLUMN estado
--   ENUM('pendiente','confirmada','cancelada','completada')
--   DEFAULT 'pendiente';
-- (Solo si no existen registros con estado = 'no_asistio')
