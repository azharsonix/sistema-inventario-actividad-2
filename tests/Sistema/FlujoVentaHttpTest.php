<?php

declare(strict_types=1);

namespace Tests\Sistema;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Soporte\ServidorPrueba;

/**
 * El sistema completo en ejecución (servidor web + API + archivo de datos),
 * probado por HTTP de punta a punta.
 */
class FlujoVentaHttpTest extends TestCase
{
    private ServidorPrueba $servidor;

    protected function setUp(): void
    {
        $this->servidor = new ServidorPrueba([
            ['LAP-001', 'Laptop Lenovo', 'Laptops', 12500.00, 10, 2],
        ]);
    }

    protected function tearDown(): void
    {
        $this->servidor->detener();
    }

    #[TestDox('CP-07 [RF-01, RF-02, RF-03] Registrar producto, consultarlo y venderlo por HTTP actualiza la existencia')]
    public function testFlujoRegistroConsultaVenta(): void
    {
        $alta = $this->servidor->pedir('POST', '/api/productos', [
            'codigo' => 'TEC-005', 'nombre' => 'Teclado mecánico', 'categoria' => 'Periféricos',
            'precio' => 799, 'existencia' => 14, 'stockMinimo' => 5,
        ]);
        $this->assertSame(201, $alta['estado']);

        $consulta = $this->servidor->pedir('GET', '/api/productos/tec-005');
        $this->assertSame(200, $consulta['estado']);
        $this->assertSame(14, $consulta['datos']['producto']['existencia']);

        $venta = $this->servidor->pedir('POST', '/api/productos/TEC-005/salidas', ['cantidad' => 3]);
        $this->assertSame(201, $venta['estado']);
        $this->assertSame(11, $venta['datos']['producto']['existencia']);
        $this->assertSame('Venta', $venta['datos']['movimiento']['motivo']);

        $despues = $this->servidor->pedir('GET', '/api/productos/TEC-005');
        $this->assertSame(11, $despues['datos']['producto']['existencia']);
    }

    #[TestDox('CP-08 [RF-03] Ventas con cantidad cero, negativa o mayor a la existencia responden 422 y no cambian la existencia')]
    public function testVentasInvalidas(): void
    {
        foreach ([0, -2, 11] as $cantidad) {
            $respuesta = $this->servidor->pedir('POST', '/api/productos/LAP-001/salidas', ['cantidad' => $cantidad]);
            $this->assertSame(422, $respuesta['estado'], "La venta de {$cantidad} unidades de LAP-001 (existencia 10) debía responder 422.");
            $this->assertNotEmpty($respuesta['datos']['error']);
        }

        $producto = $this->servidor->pedir('GET', '/api/productos/LAP-001')['datos']['producto'];
        $this->assertSame(10, $producto['existencia']);
        $this->assertSame([], $this->servidor->pedir('GET', '/api/movimientos')['datos']['movimientos']);
    }
}
