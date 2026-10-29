<?php

declare(strict_types=1);

namespace App;

/**
 * Reporte básico de inventario: existencias, valor y productos por surtir.
 */
class ReporteInventario
{
    public function __construct(private readonly Inventario $inventario)
    {
    }

    /**
     * @return array{
     *     totalProductos: int,
     *     totalUnidades: int,
     *     valorTotal: float,
     *     bajoStockMinimo: list<string>,
     *     detalle: list<array{
     *         codigo: string, nombre: string, categoria: string, precio: float,
     *         existencia: int, stockMinimo: int, valor: float, bajoStockMinimo: bool
     *     }>
     * }
     */
    public function generar(): array
    {
        $productos = $this->inventario->listarProductos();
        usort($productos, fn (Producto $a, Producto $b) => strcmp($a->codigo, $b->codigo));

        return [
            'totalProductos' => count($productos),
            'totalUnidades' => array_sum(array_map(fn (Producto $p) => $p->existencia, $productos)),
            'valorTotal' => (float) array_sum(array_map(fn (Producto $p) => $p->valorTotal(), $productos)),
            'bajoStockMinimo' => array_values(array_map(
                fn (Producto $p) => $p->codigo,
                array_filter($productos, fn (Producto $p) => $p->bajoStockMinimo()),
            )),
            'detalle' => array_map(fn (Producto $p) => [
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'categoria' => $p->categoria,
                'precio' => $p->precio,
                'existencia' => $p->existencia,
                'stockMinimo' => $p->stockMinimo,
                'valor' => $p->valorTotal(),
                'bajoStockMinimo' => $p->bajoStockMinimo(),
            ], $productos),
        ];
    }
}
