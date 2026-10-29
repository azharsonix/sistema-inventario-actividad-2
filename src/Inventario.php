<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Operaciones del Sistema de Control de Inventario.
 *
 * Trabaja en memoria; RepositorioInventario se encarga de guardarlo y
 * recuperarlo de disco con aArreglo() / desdeArreglo().
 */
class Inventario
{
    /** @var array<string, Producto> */
    private array $productos = [];

    /** @var list<Movimiento> */
    private array $movimientos = [];

    public function registrarProducto(
        string $codigo,
        string $nombre,
        string $categoria,
        float $precio,
        int $existencia = 0,
        int $stockMinimo = 0,
    ): Producto {
        $codigo = strtoupper(trim($codigo));
        if (isset($this->productos[$codigo])) {
            throw new CodigoDuplicadoException("Ya existe un producto con el código {$codigo}.");
        }

        $producto = new Producto($codigo, trim($nombre), trim($categoria), $precio, $existencia, $stockMinimo);
        $this->productos[$codigo] = $producto;

        return $producto;
    }

    public function consultarProducto(string $codigo): Producto
    {
        $codigo = strtoupper(trim($codigo));
        if (!isset($this->productos[$codigo])) {
            throw new ProductoNoEncontradoException("No existe un producto con el código {$codigo}.");
        }

        return $this->productos[$codigo];
    }

    /**
     * @param array{nombre?: string, categoria?: string, precio?: float, stockMinimo?: int} $datos
     */
    public function modificarProducto(string $codigo, array $datos): Producto
    {
        $producto = $this->consultarProducto($codigo);

        $nombre = $datos['nombre'] ?? $producto->nombre;
        $precio = (float) ($datos['precio'] ?? $producto->precio);
        $stockMinimo = (int) ($datos['stockMinimo'] ?? $producto->stockMinimo);
        Producto::validar($producto->codigo, $nombre, $precio, $producto->existencia, $stockMinimo);

        $producto->nombre = trim($nombre);
        $producto->categoria = trim($datos['categoria'] ?? $producto->categoria);
        $producto->precio = $precio;
        $producto->stockMinimo = $stockMinimo;

        return $producto;
    }

    public function eliminarProducto(string $codigo): void
    {
        $producto = $this->consultarProducto($codigo);
        unset($this->productos[$producto->codigo]);
    }

    /**
     * @return list<Producto>
     */
    public function listarProductos(): array
    {
        return array_values($this->productos);
    }

    /**
     * Existencia actual de cada producto, indexada por código.
     *
     * @return array<string, int>
     */
    public function consultarExistencias(): array
    {
        return array_map(fn (Producto $p) => $p->existencia, $this->productos);
    }

    public function registrarEntrada(string $codigo, int $cantidad, string $motivo = 'Compra a proveedor'): Movimiento
    {
        $producto = $this->consultarProducto($codigo);
        self::validarCantidad($cantidad);

        $producto->existencia += $cantidad;

        return $this->movimientos[] = new Movimiento(
            Movimiento::ENTRADA,
            $producto->codigo,
            $cantidad,
            $producto->existencia,
            $motivo,
        );
    }

    public function registrarSalida(string $codigo, int $cantidad, string $motivo = 'Venta'): Movimiento
    {
        $producto = $this->consultarProducto($codigo);
        self::validarCantidad($cantidad);
        if ($cantidad > $producto->existencia) {
            throw new InventarioException(
                "Existencia insuficiente de {$producto->codigo}: hay {$producto->existencia} y se pidieron {$cantidad}."
            );
        }

        $producto->existencia -= $cantidad;

        return $this->movimientos[] = new Movimiento(
            Movimiento::SALIDA,
            $producto->codigo,
            $cantidad,
            $producto->existencia,
            $motivo,
        );
    }

    /**
     * @return list<Movimiento>
     */
    public function movimientos(): array
    {
        return $this->movimientos;
    }

    /**
     * Categorías registradas, sin repetir y en orden alfabético.
     *
     * @return list<string>
     */
    public function categorias(): array
    {
        $categorias = array_unique(array_map(fn (Producto $p) => $p->categoria, $this->productos));
        $categorias = array_values(array_filter($categorias, fn (string $c) => $c !== ''));
        sort($categorias, SORT_STRING | SORT_FLAG_CASE);

        return $categorias;
    }

    /**
     * Representación serializable del inventario (productos y movimientos).
     *
     * @return array{productos: list<array<string, mixed>>, movimientos: list<array<string, mixed>>}
     */
    public function aArreglo(): array
    {
        return [
            'productos' => array_map(fn (Producto $p) => [
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'categoria' => $p->categoria,
                'precio' => $p->precio,
                'existencia' => $p->existencia,
                'stockMinimo' => $p->stockMinimo,
            ], array_values($this->productos)),
            'movimientos' => array_map(fn (Movimiento $m) => [
                'tipo' => $m->tipo,
                'codigo' => $m->codigo,
                'cantidad' => $m->cantidad,
                'existenciaResultante' => $m->existenciaResultante,
                'motivo' => $m->motivo,
                'fecha' => $m->fecha->format(DateTimeInterface::ATOM),
            ], $this->movimientos),
        ];
    }

    /**
     * Reconstruye un inventario a partir de lo que devolvió aArreglo().
     *
     * @param array{productos?: list<array<string, mixed>>, movimientos?: list<array<string, mixed>>} $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $inventario = new self();

        foreach ($datos['productos'] ?? [] as $p) {
            $producto = new Producto(
                (string) $p['codigo'],
                (string) $p['nombre'],
                (string) ($p['categoria'] ?? ''),
                (float) $p['precio'],
                (int) ($p['existencia'] ?? 0),
                (int) ($p['stockMinimo'] ?? 0),
            );
            $inventario->productos[$producto->codigo] = $producto;
        }

        foreach ($datos['movimientos'] ?? [] as $m) {
            $inventario->movimientos[] = new Movimiento(
                (string) $m['tipo'],
                (string) $m['codigo'],
                (int) $m['cantidad'],
                (int) $m['existenciaResultante'],
                (string) ($m['motivo'] ?? ''),
                new DateTimeImmutable((string) $m['fecha']),
            );
        }

        return $inventario;
    }

    private static function validarCantidad(int $cantidad): void
    {
        if ($cantidad <= 0) {
            throw new InventarioException('La cantidad debe ser un número entero mayor que cero.');
        }
    }
}
