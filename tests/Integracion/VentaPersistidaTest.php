<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Inventario;
use App\RepositorioInventario;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Inventario + RepositorioInventario + archivo JSON trabajando juntos.
 */
class VentaPersistidaTest extends TestCase
{
    private string $ruta;

    protected function setUp(): void
    {
        $this->ruta = sys_get_temp_dir() . '/inv-a2-' . bin2hex(random_bytes(4)) . '.json';

        $inventario = new Inventario();
        $inventario->registrarProducto('LAP-001', 'Laptop Lenovo', 'Laptops', 12500.00, 10, 2);
        $inventario->registrarProducto('MOU-010', 'Mouse inalámbrico', 'Periféricos', 250.00, 30, 5);
        (new RepositorioInventario($this->ruta))->guardar($inventario);
    }

    protected function tearDown(): void
    {
        @unlink($this->ruta);
    }

    #[TestDox('CP-05 [RF-02, RF-03] Venta guardada en el archivo se conserva al volver a abrir el inventario')]
    public function testVentaSeConserva(): void
    {
        (new RepositorioInventario($this->ruta))
            ->actualizar(fn (Inventario $inv) => $inv->registrarSalida('MOU-010', 7));

        // Un repositorio nuevo lee el archivo desde cero, como otra petición del sistema.
        $recargado = (new RepositorioInventario($this->ruta))->cargar();

        $this->assertSame(['LAP-001' => 10, 'MOU-010' => 23], $recargado->consultarExistencias());
        $this->assertCount(1, $recargado->movimientos());
        $this->assertSame(23, $recargado->movimientos()[0]->existenciaResultante);
    }

    #[TestDox('CP-06 [RF-03] Venta rechazada dentro de una actualización no modifica el archivo de datos')]
    public function testVentaRechazadaNoGuarda(): void
    {
        $antes = file_get_contents($this->ruta);

        try {
            (new RepositorioInventario($this->ruta))
                ->actualizar(fn (Inventario $inv) => $inv->registrarSalida('LAP-001', 11));
            $this->fail('Se esperaba que la venta se rechazara.');
        } catch (\App\InventarioException) {
        }

        $this->assertSame($antes, file_get_contents($this->ruta));
        $this->assertSame(10, (new RepositorioInventario($this->ruta))->cargar()->consultarProducto('LAP-001')->existencia);
    }
}
