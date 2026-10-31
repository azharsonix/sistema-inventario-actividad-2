<?php

declare(strict_types=1);

namespace Tests\Integracion;

use App\Inventario;
use App\Movimiento;
use App\RepositorioInventario;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * RF-04 Registrar entradas de mercancía: Inventario + RepositorioInventario + archivo JSON.
 */
class EntradaPersistidaTest extends TestCase
{
    private string $ruta;

    protected function setUp(): void
    {
        $this->ruta = sys_get_temp_dir() . '/inv-a2-' . bin2hex(random_bytes(4)) . '.json';

        $inventario = new Inventario();
        $inventario->registrarProducto('LAP-001', 'Laptop Lenovo', 'Laptops', 12500.00, 10, 2);
        (new RepositorioInventario($this->ruta))->guardar($inventario);
    }

    protected function tearDown(): void
    {
        @unlink($this->ruta);
    }

    #[TestDox('CP-13 [RF-04] Entrada de mercancía guardada en el archivo suma la existencia y queda en el historial')]
    public function testEntradaSeConserva(): void
    {
        (new RepositorioInventario($this->ruta))
            ->actualizar(fn (Inventario $inv) => $inv->registrarEntrada('LAP-001', 15));

        // Un repositorio nuevo lee el archivo desde cero, como otra petición del sistema.
        $recargado = (new RepositorioInventario($this->ruta))->cargar();

        $this->assertSame(25, $recargado->consultarProducto('LAP-001')->existencia);
        $this->assertCount(1, $recargado->movimientos());
        $movimiento = $recargado->movimientos()[0];
        $this->assertSame(Movimiento::ENTRADA, $movimiento->tipo);
        $this->assertSame(15, $movimiento->cantidad);
        $this->assertSame(25, $movimiento->existenciaResultante);
        $this->assertSame('Compra a proveedor', $movimiento->motivo);
    }
}
