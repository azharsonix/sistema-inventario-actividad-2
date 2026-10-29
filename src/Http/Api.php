<?php

declare(strict_types=1);

namespace App\Http;

use App\CodigoDuplicadoException;
use App\Inventario;
use App\InventarioException;
use App\Movimiento;
use App\Producto;
use App\ProductoNoEncontradoException;
use App\ReporteInventario;
use App\RepositorioInventario;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * API JSON REST del inventario. Recibe método, ruta, consulta y cuerpo y
 * devuelve una Respuesta; no depende de las variables globales de PHP, así
 * que se puede probar sin levantar un servidor.
 */
class Api
{
    /** Ruta (expresión regular) => [método => manejador]. */
    private const RUTAS = [
        '#^/$#' => ['GET' => 'interfaz'],
        '#^/salud$#' => ['GET' => 'salud'],
        '#^/api/productos$#' => ['GET' => 'listarProductos', 'POST' => 'crearProducto'],
        '#^/api/productos/([^/]+)$#' => [
            'GET' => 'verProducto',
            'PUT' => 'modificarProducto',
            'DELETE' => 'eliminarProducto',
        ],
        '#^/api/productos/([^/]+)/entradas$#' => ['POST' => 'registrarEntrada'],
        '#^/api/productos/([^/]+)/salidas$#' => ['POST' => 'registrarSalida'],
        '#^/api/movimientos$#' => ['GET' => 'listarMovimientos'],
        '#^/api/categorias$#' => ['GET' => 'listarCategorias'],
        '#^/api/reporte$#' => ['GET' => 'reporte'],
    ];

    /** Nombres legibles de los campos para los mensajes de validación. */
    private const CAMPOS = [
        'codigo' => 'el código',
        'nombre' => 'el nombre',
        'categoria' => 'la categoría',
        'precio' => 'el precio',
        'existencia' => 'la existencia',
        'stockMinimo' => 'el stock mínimo',
        'cantidad' => 'la cantidad',
        'motivo' => 'el motivo',
        'limite' => 'el límite',
    ];

    private const LIMITE_MOVIMIENTOS = 100;

    public function __construct(
        private readonly RepositorioInventario $repositorio,
        private readonly ?string $rutaInterfaz = null,
    ) {
    }

    /**
     * @param array<string, mixed> $consulta
     */
    public function atender(string $metodo, string $ruta, array $consulta = [], string $cuerpo = ''): Respuesta
    {
        $peticion = new Peticion($metodo, $ruta, $consulta, $cuerpo);

        try {
            return $this->despachar($peticion);
        } catch (ErrorHttp $e) {
            return Respuesta::json(['error' => $e->getMessage()], $e->estado, $e->cabeceras);
        } catch (ProductoNoEncontradoException $e) {
            return Respuesta::json(['error' => $e->getMessage()], 404);
        } catch (CodigoDuplicadoException $e) {
            return Respuesta::json(['error' => $e->getMessage()], 409);
        } catch (InventarioException $e) {
            return Respuesta::json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('[inventario] ' . $e);

            return Respuesta::json(['error' => 'Ocurrió un error interno. Intenta de nuevo más tarde.'], 500);
        }
    }

    private function despachar(Peticion $peticion): Respuesta
    {
        foreach (self::RUTAS as $patron => $metodos) {
            if (!preg_match($patron, $peticion->ruta, $coincidencias)) {
                continue;
            }

            $manejador = $metodos[$peticion->metodo] ?? null;
            if ($manejador === null) {
                $permitidos = implode(', ', array_keys($metodos));
                throw new ErrorHttp(
                    405,
                    "Método {$peticion->metodo} no permitido en {$peticion->ruta}. Usa: {$permitidos}.",
                    ['Allow' => $permitidos],
                );
            }

            $peticion->parametros = array_map('rawurldecode', array_slice($coincidencias, 1));

            return $this->{$manejador}($peticion);
        }

        throw new ErrorHttp(404, "No existe la ruta {$peticion->ruta}.");
    }

    // --- Manejadores -------------------------------------------------------

    private function interfaz(): Respuesta
    {
        if ($this->rutaInterfaz !== null && is_file($this->rutaInterfaz)) {
            return Respuesta::html((string) file_get_contents($this->rutaInterfaz));
        }

        return Respuesta::html(
            "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><title>TecnoStock</title></head>"
            . "<body><h1>TecnoStock</h1><p>La interfaz no está instalada todavía. "
            . "La API está disponible en <code>/api/productos</code>.</p></body></html>\n"
        );
    }

    private function salud(): Respuesta
    {
        return Respuesta::json(['estado' => 'ok', 'version' => getenv('APP_VERSION') ?: 'dev']);
    }

    private function listarProductos(Peticion $peticion): Respuesta
    {
        $productos = $this->ordenados($this->repositorio->cargar());

        $q = self::normalizar($peticion->consulta('q'));
        if ($q !== '') {
            $productos = array_filter($productos, fn (Producto $p) => str_contains(
                self::normalizar("{$p->codigo} {$p->nombre} {$p->categoria}"),
                $q,
            ));
        }

        $categoria = self::normalizar($peticion->consulta('categoria'));
        if ($categoria !== '') {
            $productos = array_filter($productos, fn (Producto $p) => self::normalizar($p->categoria) === $categoria);
        }

        return Respuesta::json(['productos' => array_values(array_map(self::producto(...), $productos))]);
    }

    private function crearProducto(Peticion $peticion): Respuesta
    {
        $datos = $peticion->json();
        $codigo = self::texto($datos, 'codigo', true);
        $nombre = self::texto($datos, 'nombre', true);
        $categoria = self::texto($datos, 'categoria', true);
        $precio = self::numero($datos, 'precio', true);
        $existencia = self::entero($datos, 'existencia') ?? 0;
        $stockMinimo = self::entero($datos, 'stockMinimo') ?? 0;

        $producto = $this->repositorio->actualizar(
            fn (Inventario $inv) => $inv->registrarProducto($codigo, $nombre, $categoria, $precio, $existencia, $stockMinimo)
        );

        return Respuesta::json(['producto' => self::producto($producto)], 201);
    }

    private function verProducto(Peticion $peticion): Respuesta
    {
        $producto = $this->repositorio->cargar()->consultarProducto($peticion->parametros[0]);

        return Respuesta::json(['producto' => self::producto($producto)]);
    }

    private function modificarProducto(Peticion $peticion): Respuesta
    {
        $datos = $peticion->json();
        $cambios = array_filter([
            'nombre' => self::texto($datos, 'nombre'),
            'categoria' => self::texto($datos, 'categoria'),
            'precio' => self::numero($datos, 'precio'),
            'stockMinimo' => self::entero($datos, 'stockMinimo'),
        ], fn ($valor) => $valor !== null);
        if (($cambios['categoria'] ?? null) === '') {
            throw self::falta('categoria');
        }

        $producto = $this->repositorio->actualizar(
            fn (Inventario $inv) => $inv->modificarProducto($peticion->parametros[0], $cambios)
        );

        return Respuesta::json(['producto' => self::producto($producto)]);
    }

    private function eliminarProducto(Peticion $peticion): Respuesta
    {
        $codigo = $this->repositorio->actualizar(function (Inventario $inv) use ($peticion) {
            $codigo = $inv->consultarProducto($peticion->parametros[0])->codigo;
            $inv->eliminarProducto($codigo);

            return $codigo;
        });

        return Respuesta::json(['eliminado' => $codigo]);
    }

    private function registrarEntrada(Peticion $peticion): Respuesta
    {
        return $this->registrarMovimiento($peticion, Movimiento::ENTRADA);
    }

    private function registrarSalida(Peticion $peticion): Respuesta
    {
        return $this->registrarMovimiento($peticion, Movimiento::SALIDA);
    }

    private function registrarMovimiento(Peticion $peticion, string $tipo): Respuesta
    {
        $datos = $peticion->json();
        $cantidad = self::entero($datos, 'cantidad', true);
        $motivo = self::texto($datos, 'motivo') ?? '';
        if ($motivo === '') {
            $motivo = $tipo === Movimiento::ENTRADA ? 'Compra a proveedor' : 'Venta';
        }

        [$movimiento, $producto] = $this->repositorio->actualizar(function (Inventario $inv) use ($peticion, $tipo, $cantidad, $motivo) {
            $codigo = $peticion->parametros[0];
            $movimiento = $tipo === Movimiento::ENTRADA
                ? $inv->registrarEntrada($codigo, $cantidad, $motivo)
                : $inv->registrarSalida($codigo, $cantidad, $motivo);

            return [$movimiento, $inv->consultarProducto($codigo)];
        });

        return Respuesta::json([
            'movimiento' => self::movimiento($movimiento, $producto->nombre),
            'producto' => self::producto($producto),
        ], 201);
    }

    private function listarMovimientos(Peticion $peticion): Respuesta
    {
        $inventario = $this->repositorio->cargar();

        $limite = self::LIMITE_MOVIMIENTOS;
        if ($peticion->consulta('limite') !== '') {
            $limite = self::entero(['limite' => $peticion->consulta('limite')], 'limite');
            if ($limite === null || $limite < 1) {
                throw new ErrorHttp(422, 'El límite debe ser un número entero mayor que cero.');
            }
        }

        $movimientos = array_reverse($inventario->movimientos());
        $codigo = strtoupper($peticion->consulta('codigo'));
        if ($codigo !== '') {
            $movimientos = array_filter($movimientos, fn (Movimiento $m) => $m->codigo === $codigo);
        }
        $movimientos = array_slice(array_values($movimientos), 0, $limite);

        $nombres = [];
        foreach ($inventario->listarProductos() as $p) {
            $nombres[$p->codigo] = $p->nombre;
        }

        return Respuesta::json(['movimientos' => array_map(
            fn (Movimiento $m) => self::movimiento($m, $nombres[$m->codigo] ?? null),
            $movimientos,
        )]);
    }

    private function listarCategorias(): Respuesta
    {
        return Respuesta::json(['categorias' => $this->repositorio->cargar()->categorias()]);
    }

    private function reporte(): Respuesta
    {
        $reporte = (new ReporteInventario($this->repositorio->cargar()))->generar();
        $reporte['generado'] = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);

        return Respuesta::json($reporte);
    }

    // --- Representación JSON -----------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function producto(Producto $p): array
    {
        return [
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'categoria' => $p->categoria,
            'precio' => $p->precio,
            'existencia' => $p->existencia,
            'stockMinimo' => $p->stockMinimo,
            'valor' => $p->valorTotal(),
            'bajoStockMinimo' => $p->bajoStockMinimo(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function movimiento(Movimiento $m, ?string $nombre): array
    {
        return [
            'tipo' => $m->tipo,
            'codigo' => $m->codigo,
            'nombre' => $nombre,
            'cantidad' => $m->cantidad,
            'existenciaResultante' => $m->existenciaResultante,
            'motivo' => $m->motivo,
            'fecha' => $m->fecha->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return list<Producto>
     */
    private function ordenados(Inventario $inventario): array
    {
        $productos = $inventario->listarProductos();
        usort($productos, fn (Producto $a, Producto $b) => strcmp($a->codigo, $b->codigo));

        return $productos;
    }

    // --- Validación de entrada ---------------------------------------------

    /**
     * Texto recortado; null si el campo no viene (o es null y no es obligatorio).
     *
     * @param array<string, mixed> $datos
     */
    private static function texto(array $datos, string $campo, bool $obligatorio = false): ?string
    {
        $valor = $datos[$campo] ?? null;
        if ($valor === null) {
            return $obligatorio ? throw self::falta($campo) : null;
        }
        if (!is_string($valor)) {
            throw new ErrorHttp(422, ucfirst(self::etiqueta($campo)) . ' debe ser texto.');
        }
        $valor = trim($valor);
        if ($valor === '' && $obligatorio) {
            throw self::falta($campo);
        }

        return $valor;
    }

    /**
     * Número (entero o decimal); acepta también texto numérico como "12.5".
     *
     * @param array<string, mixed> $datos
     */
    private static function numero(array $datos, string $campo, bool $obligatorio = false): ?float
    {
        $valor = $datos[$campo] ?? null;
        if ($valor === null || $valor === '') {
            return $obligatorio ? throw self::falta($campo) : null;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (is_string($valor) && is_numeric(trim($valor))) {
            return (float) trim($valor);
        }

        throw new ErrorHttp(422, ucfirst(self::etiqueta($campo)) . ' debe ser un número.');
    }

    /**
     * Número entero; acepta 5, 5.0 o "5", pero no 2.5, "abc" ni true.
     *
     * @param array<string, mixed> $datos
     */
    private static function entero(array $datos, string $campo, bool $obligatorio = false): ?int
    {
        $valor = $datos[$campo] ?? null;
        if ($valor === null || $valor === '') {
            return $obligatorio ? throw self::falta($campo) : null;
        }
        if (is_int($valor)) {
            return $valor;
        }
        if (is_float($valor) && floor($valor) === $valor && abs($valor) < PHP_INT_MAX) {
            return (int) $valor;
        }
        if (is_string($valor) && preg_match('/^\s*-?\d{1,18}\s*$/', $valor)) {
            return (int) trim($valor);
        }

        throw new ErrorHttp(422, ucfirst(self::etiqueta($campo)) . ' debe ser un número entero.');
    }

    private static function falta(string $campo): ErrorHttp
    {
        return new ErrorHttp(422, 'Falta ' . self::etiqueta($campo) . '.');
    }

    private static function etiqueta(string $campo): string
    {
        return self::CAMPOS[$campo] ?? "el campo {$campo}";
    }

    /**
     * Minúsculas y sin acentos, para comparar búsquedas.
     */
    private static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower(trim($texto), 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}
