<?php

declare(strict_types=1);

/*
 * Punto de entrada web. Todas las rutas llegan aquí (php -S sin router
 * script) y las atiende App\Http\Api: la API JSON en /api/... y /salud
 * para comprobar que el servidor responde.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Http\Api;
use App\RepositorioInventario;

// Hora local de la tienda (Campeche) para las fechas de los movimientos.
date_default_timezone_set(getenv('APP_ZONA_HORARIA') ?: 'America/Merida');

$api = new Api(RepositorioInventario::desdeEntorno());

$api->atender(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
    $_GET,
    (string) file_get_contents('php://input'),
)->enviar();
