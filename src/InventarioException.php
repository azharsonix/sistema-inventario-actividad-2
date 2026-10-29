<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Error de negocio del inventario: código duplicado, producto inexistente,
 * datos inválidos o existencia insuficiente.
 */
class InventarioException extends RuntimeException
{
}
