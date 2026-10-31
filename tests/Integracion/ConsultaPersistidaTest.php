<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Inventario;
use App\RepositorioInventario;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * RF-02 Consultar existencias: Inventario + RepositorioInventario + archivo JSON.
 */
class ConsultaPersistidaTest extends TestCase
{
    private string $ruta;

    protected function setUp(): void
    {
        $this->ruta = sys_get_temp_dir() . '/inv-a2-' . bin2hex(random_bytes(4)) . '.json';

        $inventario = new Inventario();
        $inventario->registrarProducto('LAP-001', 'Laptop Lenovo', 'Laptops', 12500.00, 10, 2);
        $inventario->registrarProducto('MOU-010', 'Mouse inalámbrico', 'Periféricos', 250.00, 30, 5);
        $inventario->registrarProducto('TEC-020', 'Teclado USB', 'Periféricos', 250.00, 20, 5);
        (new RepositorioInventario($this->ruta))->guardar($inventario);
    }

    protected function tearDown(): void
    {
        @unlink($this->ruta);
    }

    #[TestDox('CP-14 [RF-02] Consultar un producto existente leído del archivo devuelve sus datos y su existencia sin modificar el archivo')]
    public function testConsultaProductoExistente(): void
    {
        $antes = file_get_contents($this->ruta);

        // Un repositorio nuevo lee el archivo desde cero, como otra petición del sistema.
        $inventario = (new RepositorioInventario($this->ruta))->cargar();
        $producto = $inventario->consultarProducto(' tec-020 ');

        $this->assertSame('TEC-020', $producto->codigo);
        $this->assertSame('Teclado USB', $producto->nombre);
        $this->assertSame('Periféricos', $producto->categoria);
        $this->assertSame(250.00, $producto->precio);
        $this->assertSame(20, $producto->existencia);
        $this->assertSame(5, $producto->stockMinimo);
        $this->assertSame(
            ['LAP-001' => 10, 'MOU-010' => 30, 'TEC-020' => 20],
            $inventario->consultarExistencias(),
        );

        // Consultar no escribe: el archivo queda idéntico.
        $this->assertSame($antes, file_get_contents($this->ruta));
    }
}
