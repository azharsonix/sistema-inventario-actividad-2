<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/**
 * Entrada o salida de unidades de un producto.
 */
class Movimiento
{
    public const ENTRADA = 'entrada';
    public const SALIDA = 'salida';

    public readonly DateTimeImmutable $fecha;

    /**
     * La fecha es opcional: si no se indica se usa el momento actual
     * (se indica al recuperar movimientos guardados).
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $codigo,
        public readonly int $cantidad,
        public readonly int $existenciaResultante,
        public readonly string $motivo,
        ?DateTimeImmutable $fecha = null,
    ) {
        $this->fecha = $fecha ?? new DateTimeImmutable();
    }
}
