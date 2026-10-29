<?php

declare(strict_types=1);

namespace Tests\Unitarias;

use App\Inventario;
use App\InventarioException;
use App\Movimiento;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * RF-03 Actualizar la existencia con cada venta. Prueba Inventario::registrarSalida aislado.
 */
class VentaTest extends TestCase
{
    private Inventario $inventario;

    protected function setUp(): void
    {
        $this->inventario = new Inventario();
        $this->inventario->registrarProducto('LAP-001', 'Laptop Lenovo', 'Laptops', 12500.00, 10, 2);
    }

    #[TestDox('CP-03 [RF-03] Venta con cantidad disponible descuenta la existencia y registra el movimiento')]
    public function testVentaDescuentaExistencia(): void
    {
        $movimiento = $this->inventario->registrarSalida('LAP-001', 4);

        $this->assertSame(6, $this->inventario->consultarProducto('LAP-001')->existencia);
        $this->assertSame(Movimiento::SALIDA, $movimiento->tipo);
        $this->assertSame(4, $movimiento->cantidad);
        $this->assertSame(6, $movimiento->existenciaResultante);
        $this->assertSame('Venta', $movimiento->motivo);
    }

    #[TestDox('CP-04 [RF-03] Venta mayor a la existencia se rechaza y la existencia no cambia')]
    public function testVentaMayorALaExistencia(): void
    {
        try {
            $this->inventario->registrarSalida('LAP-001', 50);
            $this->fail('Se esperaba InventarioException por existencia insuficiente.');
        } catch (InventarioException $e) {
            $this->assertStringContainsString('Existencia insuficiente de LAP-001', $e->getMessage());
        }

        $this->assertSame(10, $this->inventario->consultarProducto('LAP-001')->existencia);
        $this->assertSame([], $this->inventario->movimientos());
    }
}
