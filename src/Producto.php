<?php

declare(strict_types=1);

namespace App;

/**
 * Producto del inventario con sus datos y su existencia actual.
 */
class Producto
{
    public function __construct(
        public readonly string $codigo,
        public string $nombre,
        public string $categoria,
        public float $precio,
        public int $existencia = 0,
        public int $stockMinimo = 0,
    ) {
        self::validar($codigo, $nombre, $precio, $existencia, $stockMinimo);
    }

    public static function validar(
        string $codigo,
        string $nombre,
        float $precio,
        int $existencia,
        int $stockMinimo,
    ): void {
        if (!preg_match('/^[A-Z0-9-]{3,20}$/', $codigo)) {
            throw new InventarioException('El código debe tener de 3 a 20 caracteres (A-Z, 0-9 o guion).');
        }
        if (trim($nombre) === '') {
            throw new InventarioException('El nombre del producto es obligatorio.');
        }
        if ($precio < 0) {
            throw new InventarioException('El precio no puede ser negativo.');
        }
        if ($existencia < 0 || $stockMinimo < 0) {
            throw new InventarioException('La existencia y el stock mínimo no pueden ser negativos.');
        }
    }

    public function valorTotal(): float
    {
        return $this->existencia * $this->precio;
    }

    public function bajoStockMinimo(): bool
    {
        return $this->existencia <= $this->stockMinimo;
    }
}
