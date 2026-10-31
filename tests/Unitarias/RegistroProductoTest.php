<?php

declare(strict_types=1);

namespace Tests\Unitarias;

use App\CodigoDuplicadoException;
use App\Inventario;
use App\InventarioException;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * RF-01 Registrar productos. Prueba la clase Inventario aislada, en memoria.
 */
class RegistroProductoTest extends TestCase
{
    #[TestDox('CP-01 [RF-01] Registrar producto con datos válidos normaliza el código y guarda la existencia inicial')]
    public function testRegistrarProductoValido(): void
    {
        $inventario = new Inventario();

        $producto = $inventario->registrarProducto(' mou-010 ', 'Mouse inalámbrico', 'Periféricos', 250.00, 30, 5);

        $this->assertSame('MOU-010', $producto->codigo);
        $this->assertSame(30, $inventario->consultarProducto('MOU-010')->existencia);
        $this->assertCount(1, $inventario->listarProductos());
    }

    #[TestDox('CP-02 [RF-01] Registrar producto con código duplicado muestra error y no agrega otro producto')]
    public function testCodigoDuplicado(): void
    {
        $inventario = new Inventario();
        $inventario->registrarProducto('LAP-001', 'Laptop Lenovo', 'Laptops', 12500.00, 10, 2);

        try {
            $inventario->registrarProducto('lap-001', 'Otra laptop', 'Laptops', 9000.00, 1, 0);
            $this->fail('Se esperaba CodigoDuplicadoException.');
        } catch (CodigoDuplicadoException $e) {
            $this->assertSame('Ya existe un producto con el código LAP-001.', $e->getMessage());
        }

        $this->assertCount(1, $inventario->listarProductos());
        $this->assertSame('Laptop Lenovo', $inventario->consultarProducto('LAP-001')->nombre);
    }

    #[TestDox('CP-12 [RF-01] Registrar producto con nombre vacío, precio o existencia negativos o código inválido muestra error y no lo guarda')]
    public function testDatosInvalidos(): void
    {
        $inventario = new Inventario();
        $casos = [
            [['TEC-020', '', 250.00, 20], 'El nombre del producto es obligatorio.'],
            [['TEC-020', 'Teclado USB', -250.00, 20], 'El precio no puede ser negativo.'],
            [['TEC-020', 'Teclado USB', 250.00, -5], 'La existencia y el stock mínimo no pueden ser negativos.'],
            [['T#1', 'Teclado USB', 250.00, 20], 'El código debe tener de 3 a 20 caracteres (A-Z, 0-9 o guion).'],
        ];

        foreach ($casos as [[$codigo, $nombre, $precio, $existencia], $mensaje]) {
            try {
                $inventario->registrarProducto($codigo, $nombre, 'Periféricos', $precio, $existencia, 5);
                $this->fail("Se esperaba el error «{$mensaje}».");
            } catch (InventarioException $e) {
                $this->assertSame($mensaje, $e->getMessage());
            }
        }

        $this->assertCount(0, $inventario->listarProductos());
    }
}
