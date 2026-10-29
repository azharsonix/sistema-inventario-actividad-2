<?php

declare(strict_types=1);

namespace App;

/**
 * Se intentó registrar un producto con un código que ya existe.
 */
class CodigoDuplicadoException extends InventarioException
{
}
