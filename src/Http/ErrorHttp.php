<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Error de la petición con su código de estado HTTP (400, 404, 405, 422...).
 */
class ErrorHttp extends RuntimeException
{
    /**
     * @param array<string, string> $cabeceras
     */
    public function __construct(
        public readonly int $estado,
        string $mensaje,
        public readonly array $cabeceras = [],
    ) {
        parent::__construct($mensaje);
    }
}
