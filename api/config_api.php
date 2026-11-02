<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';

/**
 * @deprecated Usar requireAuth() de security.php
 * Mantenido para compatibilidad hacia atrás con los endpoints de la API.
 */
function verificarAutenticacion(): void
{
    requireAuth();
}
