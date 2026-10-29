<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use RuntimeException;

/**
 * Guarda y recupera el inventario en un archivo JSON.
 *
 * La ruta se toma de la variable de entorno INVENTARIO_DATOS o, si no está
 * definida, de data/inventario.json. Todas las lecturas y escrituras usan
 * flock para que dos peticiones simultáneas no se pisen. Si el archivo no
 * existe (o está vacío) se siembra con datos de ejemplo.
 */
class RepositorioInventario
{
    public const RUTA_POR_DEFECTO = 'data/inventario.json';

    public function __construct(private readonly string $ruta)
    {
    }

    public static function desdeEntorno(): self
    {
        $raiz = dirname(__DIR__);
        $ruta = getenv('INVENTARIO_DATOS') ?: self::RUTA_POR_DEFECTO;

        // Las rutas relativas se toman desde la raíz del proyecto.
        if (!preg_match('#^([A-Za-z]:)?[/\\\\]#', $ruta)) {
            $ruta = $raiz . DIRECTORY_SEPARATOR . $ruta;
        }

        return new self($ruta);
    }

    public function ruta(): string
    {
        return $this->ruta;
    }

    public function cargar(): Inventario
    {
        $archivo = $this->abrir();
        try {
            flock($archivo, LOCK_SH);
            $contenido = stream_get_contents($archivo);
        } finally {
            flock($archivo, LOCK_UN);
            fclose($archivo);
        }

        if (trim((string) $contenido) === '') {
            // Primera ejecución: se siembra bajo bloqueo exclusivo.
            return $this->actualizar(fn (Inventario $inventario) => $inventario);
        }

        return self::decodificar((string) $contenido);
    }

    public function guardar(Inventario $inventario): void
    {
        $archivo = $this->abrir();
        try {
            flock($archivo, LOCK_EX);
            self::escribir($archivo, $inventario);
        } finally {
            flock($archivo, LOCK_UN);
            fclose($archivo);
        }
    }

    /**
     * Carga, aplica la operación y guarda, todo bajo un mismo bloqueo
     * exclusivo. Si la operación lanza una excepción no se guarda nada.
     *
     * @template T
     * @param callable(Inventario): T $operacion
     * @return T
     */
    public function actualizar(callable $operacion): mixed
    {
        $archivo = $this->abrir();
        try {
            flock($archivo, LOCK_EX);
            $contenido = (string) stream_get_contents($archivo);

            if (trim($contenido) === '') {
                $inventario = self::datosDeEjemplo();
                self::escribir($archivo, $inventario);
            } else {
                $inventario = self::decodificar($contenido);
            }

            $resultado = $operacion($inventario);
            self::escribir($archivo, $inventario);

            return $resultado;
        } finally {
            flock($archivo, LOCK_UN);
            fclose($archivo);
        }
    }

    /**
     * Inventario de ejemplo de una tienda de cómputo, con movimientos de los
     * últimos días y algunos productos en o bajo su stock mínimo.
     */
    public static function datosDeEjemplo(): Inventario
    {
        // codigo, nombre, categoria, precio, existencia inicial, stock mínimo
        $productos = [
            ['ACC-001', 'Mochila para laptop 15.6"', 'Accesorios', 459.00, 15, 5],
            ['ACC-002', 'Cable HDMI 2.0 de 2 m', 'Accesorios', 129.00, 10, 10],
            ['ALM-001', 'SSD Kingston NV2 1 TB NVMe', 'Almacenamiento', 1199.00, 10, 6],
            ['COM-001', 'Tarjeta de video NVIDIA RTX 4060 8 GB', 'Componentes', 6899.00, 5, 2],
            ['COM-002', 'Memoria RAM Kingston Fury 16 GB DDR4', 'Componentes', 899.00, 12, 8],
            ['COM-003', 'Procesador AMD Ryzen 5 5600', 'Componentes', 2499.00, 6, 4],
            ['LAP-001', 'Laptop Lenovo IdeaPad 3 Ryzen 5', 'Laptops', 12499.00, 4, 3],
            ['LAP-002', 'Laptop HP Pavilion 14 Core i5', 'Laptops', 15999.00, 4, 3],
            ['MON-001', 'Monitor Samsung 24" Full HD', 'Periféricos', 2799.00, 7, 3],
            ['MOU-010', 'Mouse inalámbrico Logitech M185', 'Periféricos', 249.00, 38, 10],
            ['RED-001', 'Router TP-Link Archer C6 AC1200', 'Redes', 849.00, 5, 4],
            ['TEC-005', 'Teclado mecánico Redragon Kumara', 'Periféricos', 799.00, 14, 5],
        ];

        // días atrás, hora, tipo, código, cantidad, motivo
        $movimientos = [
            [6, '09:30', Movimiento::ENTRADA, 'LAP-001', 5, 'Compra a proveedor'],
            [6, '11:10', Movimiento::ENTRADA, 'ALM-001', 10, 'Compra a proveedor'],
            [5, '12:45', Movimiento::SALIDA, 'MOU-010', 3, 'Venta'],
            [5, '17:20', Movimiento::SALIDA, 'LAP-002', 2, 'Venta'],
            [4, '10:05', Movimiento::SALIDA, 'COM-003', 2, 'Venta'],
            [3, '09:00', Movimiento::ENTRADA, 'COM-002', 12, 'Compra a proveedor'],
            [3, '16:35', Movimiento::SALIDA, 'ACC-002', 7, 'Venta'],
            [2, '13:15', Movimiento::SALIDA, 'LAP-001', 1, 'Venta'],
            [2, '18:40', Movimiento::SALIDA, 'MON-001', 1, 'Venta en mostrador'],
            [1, '10:25', Movimiento::ENTRADA, 'RED-001', 4, 'Compra a proveedor'],
            [1, '15:50', Movimiento::SALIDA, 'TEC-005', 2, 'Venta'],
            [0, null, Movimiento::SALIDA, 'ALM-001', 2, 'Venta'],
        ];

        $existencias = [];
        $datos = ['productos' => [], 'movimientos' => []];
        foreach ($productos as [$codigo, $nombre, $categoria, $precio, $existencia, $minimo]) {
            $existencias[$codigo] = $existencia;
            $datos['productos'][$codigo] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'categoria' => $categoria,
                'precio' => $precio,
                'existencia' => $existencia,
                'stockMinimo' => $minimo,
            ];
        }

        $ahora = new DateTimeImmutable();
        foreach ($movimientos as [$dias, $hora, $tipo, $codigo, $cantidad, $motivo]) {
            $existencias[$codigo] += $tipo === Movimiento::ENTRADA ? $cantidad : -$cantidad;
            $fecha = $hora === null
                ? $ahora->modify('-2 hours')
                : $ahora->modify("-{$dias} days")->setTime(...array_map('intval', explode(':', $hora)));

            $datos['movimientos'][] = [
                'tipo' => $tipo,
                'codigo' => $codigo,
                'cantidad' => $cantidad,
                'existenciaResultante' => $existencias[$codigo],
                'motivo' => $motivo,
                'fecha' => $fecha->format(DateTimeInterface::ATOM),
            ];
        }

        foreach ($existencias as $codigo => $existencia) {
            $datos['productos'][$codigo]['existencia'] = $existencia;
        }
        $datos['productos'] = array_values($datos['productos']);

        return Inventario::desdeArreglo($datos);
    }

    /**
     * @return resource
     */
    private function abrir()
    {
        $carpeta = dirname($this->ruta);
        if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new RuntimeException("No se pudo crear la carpeta de datos {$carpeta}.");
        }

        $archivo = @fopen($this->ruta, 'c+');
        if ($archivo === false) {
            throw new RuntimeException("No se pudo abrir el archivo de datos {$this->ruta}.");
        }

        return $archivo;
    }

    /**
     * @param resource $archivo
     */
    private static function escribir($archivo, Inventario $inventario): void
    {
        $json = json_encode(
            ['version' => 1] + $inventario->aArreglo(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );

        ftruncate($archivo, 0);
        rewind($archivo);
        fwrite($archivo, $json . "\n");
        fflush($archivo);
    }

    private static function decodificar(string $contenido): Inventario
    {
        try {
            $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('El archivo de datos del inventario está dañado.', 0, $e);
        }
        if (!is_array($datos)) {
            throw new RuntimeException('El archivo de datos del inventario está dañado.');
        }

        return Inventario::desdeArreglo($datos);
    }
}
