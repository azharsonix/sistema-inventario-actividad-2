<?php

declare(strict_types=1);

namespace Tests\Sistema;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Soporte\ServidorPrueba;

/**
 * RF-02 Consultar existencias, con el sistema completo en ejecución.
 */
class ConsultaHttpTest extends TestCase
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

    #[TestDox('CP-11 [RF-02] Consultar un producto que no existe responde 404 con mensaje claro y no cambia el inventario')]
    public function testProductoInexistente(): void
    {
        $respuesta = $this->servidor->pedir('GET', '/api/productos/xyz-999');
        $this->assertSame(404, $respuesta['estado']);
        $this->assertSame('No existe un producto con el código XYZ-999.', $respuesta['datos']['error']);

        $productos = $this->servidor->pedir('GET', '/api/productos')['datos']['productos'];
        $this->assertSame(['LAP-001'], array_column($productos, 'codigo'));
    }
}
