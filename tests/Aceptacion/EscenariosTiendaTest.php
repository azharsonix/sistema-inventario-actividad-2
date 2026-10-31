<?php

declare(strict_types=1);

namespace Tests\Aceptacion;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Soporte\ServidorPrueba;

/**
 * Escenarios de la cliente (dueña de la tienda) escritos como
 * Dado / Cuando / Entonces y ejecutados contra el sistema en marcha.
 */
class EscenariosTiendaTest extends TestCase
{
    private ServidorPrueba $servidor;

    protected function setUp(): void
    {
        // Dado un inventario con tres productos
        $this->servidor = new ServidorPrueba([
            ['ACC-002', 'Cable HDMI 2 m', 'Accesorios', 129.00, 12, 5],
            ['MOU-010', 'Mouse inalámbrico', 'Periféricos', 250.00, 30, 10],
            ['RED-001', 'Router TP-Link', 'Redes', 849.00, 6, 4],
        ]);
    }

    protected function tearDown(): void
    {
        $this->servidor->detener();
    }

    #[TestDox('CP-09 [RF-05] Al cierre del día el reporte lista para resurtir los productos que quedaron en o bajo su mínimo')]
    public function testReporteDeResurtido(): void
    {
        // Cuando se registran las ventas del día
        foreach ([['ACC-002', 7], ['RED-001', 2], ['MOU-010', 5]] as [$codigo, $cantidad]) {
            $venta = $this->servidor->pedir('POST', "/api/productos/{$codigo}/salidas", ['cantidad' => $cantidad]);
            $this->assertSame(201, $venta['estado']);
        }

        // Entonces el reporte marca ACC-002 (5 de mínimo 5) y RED-001 (4 de mínimo 4), y no MOU-010 (25 de mínimo 10)
        $reporte = $this->servidor->pedir('GET', '/api/reporte')['datos'];
        $this->assertSame(
            ['ACC-002', 'RED-001'],
            $reporte['bajoStockMinimo'],
            'Productos a resurtir: se esperaban ACC-002 (5 de mínimo 5) y RED-001 (4 de mínimo 4).',
        );
        $this->assertSame(3, $reporte['totalProductos']);
        $this->assertSame(34, $reporte['totalUnidades']);
        $this->assertEqualsWithDelta(5 * 129 + 25 * 250 + 4 * 849, $reporte['valorTotal'], 0.001);
    }

    #[TestDox('CP-10 [RF-02, RF-04] Llega mercancía y se vende en mostrador: la consulta de existencias y el historial cuadran')]
    public function testEntradaYVentaCuadran(): void
    {
        // Cuando llegan 10 mouses del proveedor y se venden 3 en mostrador
        $this->servidor->pedir('POST', '/api/productos/MOU-010/entradas', ['cantidad' => 10]);
        $this->servidor->pedir('POST', '/api/productos/MOU-010/salidas', ['cantidad' => 3, 'motivo' => 'Venta en mostrador']);

        // Entonces la existencia es 30 + 10 - 3 = 37 y el historial muestra ambos movimientos (el más reciente primero)
        $productos = $this->servidor->pedir('GET', '/api/productos')['datos']['productos'];
        $this->assertSame(
            ['ACC-002' => 12, 'MOU-010' => 37, 'RED-001' => 6],
            array_column($productos, 'existencia', 'codigo'),
        );

        $historial = $this->servidor->pedir('GET', '/api/movimientos?codigo=MOU-010')['datos']['movimientos'];
        $this->assertSame(
            [['salida', 3, 37, 'Venta en mostrador'], ['entrada', 10, 40, 'Compra a proveedor']],
            array_map(fn (array $m) => [$m['tipo'], $m['cantidad'], $m['existenciaResultante'], $m['motivo']], $historial),
        );
    }

    #[TestDox('CP-15 [RF-01, RF-02, RF-03, RF-04, RF-05] Proceso completo: alta, consulta, entrada, venta y reporte de resurtido de un producto nuevo')]
    public function testProcesoCompleto(): void
    {
        // RF-01: la cliente da de alta el teclado; un alta con precio negativo se rechaza
        $alta = $this->servidor->pedir('POST', '/api/productos', [
            'codigo' => 'TEC-020', 'nombre' => 'Teclado USB', 'categoria' => 'Periféricos',
            'precio' => 250, 'existencia' => 20, 'stockMinimo' => 5,
        ]);
        $this->assertSame(201, $alta['estado']);
        $invalida = $this->servidor->pedir('POST', '/api/productos', [
            'codigo' => 'TEC-021', 'nombre' => 'Teclado inalámbrico', 'categoria' => 'Periféricos',
            'precio' => -250, 'existencia' => 10, 'stockMinimo' => 2,
        ]);
        $this->assertSame(422, $invalida['estado']);

        // RF-02: lo consulta por su código
        $consulta = $this->servidor->pedir('GET', '/api/productos/tec-020');
        $this->assertSame(200, $consulta['estado']);
        $this->assertSame(20, $consulta['datos']['producto']['existencia']);

        // RF-04: llegan 5 piezas del proveedor
        $entrada = $this->servidor->pedir('POST', '/api/productos/TEC-020/entradas', ['cantidad' => 5]);
        $this->assertSame(201, $entrada['estado']);
        $this->assertSame(25, $entrada['datos']['producto']['existencia']);

        // RF-03: vende 20 piezas; después intenta vender 6 cuando solo quedan 5
        $venta = $this->servidor->pedir('POST', '/api/productos/TEC-020/salidas', ['cantidad' => 20]);
        $this->assertSame(201, $venta['estado']);
        $this->assertSame(5, $venta['datos']['producto']['existencia']);
        $excedida = $this->servidor->pedir('POST', '/api/productos/TEC-020/salidas', ['cantidad' => 6]);
        $this->assertSame(422, $excedida['estado'], 'La venta de 6 piezas de TEC-020 (existencia 5) debía responder 422.');

        // RF-05: el reporte marca TEC-020 para resurtir (5 de mínimo 5) y los totales cuadran
        $reporte = $this->servidor->pedir('GET', '/api/reporte')['datos'];
        $this->assertSame(['TEC-020'], $reporte['bajoStockMinimo'], 'Productos a resurtir: se esperaba TEC-020 (5 de mínimo 5).');
        $this->assertSame(4, $reporte['totalProductos']);
        $this->assertSame(12 + 30 + 6 + 5, $reporte['totalUnidades']);
        $this->assertEqualsWithDelta(12 * 129 + 30 * 250 + 6 * 849 + 5 * 250, $reporte['valorTotal'], 0.001);

        $historial = $this->servidor->pedir('GET', '/api/movimientos?codigo=TEC-020')['datos']['movimientos'];
        $this->assertSame(
            [['salida', 20, 5], ['entrada', 5, 25]],
            array_map(fn (array $m) => [$m['tipo'], $m['cantidad'], $m['existenciaResultante']], $historial),
        );
    }
}
