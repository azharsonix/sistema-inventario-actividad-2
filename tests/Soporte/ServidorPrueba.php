<?php

declare(strict_types=1);

namespace Tests\Soporte;

use App\Inventario;
use App\RepositorioInventario;
use RuntimeException;

/**
 * Levanta el sistema real (php -S sobre public/) en un puerto libre y con un
 * archivo de datos temporal, para que las pruebas de sistema y de aceptación
 * hablen con la aplicación por HTTP igual que la interfaz web.
 */
final class ServidorPrueba
{
    /** @var resource|null */
    private $proceso = null;

    public readonly string $url;
    public readonly string $datos;

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: float, 4: int, 5: int}> $productos
     */
    public function __construct(array $productos)
    {
        $this->datos = sys_get_temp_dir() . '/inv-a2-srv-' . bin2hex(random_bytes(4)) . '.json';
        $inventario = new Inventario();
        foreach ($productos as $p) {
            $inventario->registrarProducto(...$p);
        }
        (new RepositorioInventario($this->datos))->guardar($inventario);

        $puerto = self::puertoLibre();
        $this->url = "http://127.0.0.1:{$puerto}";

        $entorno = getenv();
        $entorno['INVENTARIO_DATOS'] = $this->datos;
        $nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        // Con A2_SRC el servidor también carga la copia de src/ con el defecto inyectado.
        $comando = [PHP_BINARY];
        if ((string) getenv('A2_SRC') !== '') {
            $comando = [PHP_BINARY, '-d', 'auto_prepend_file=' . __DIR__ . '/bootstrap.php'];
        }

        $proceso = proc_open(
            [...$comando, '-S', "127.0.0.1:{$puerto}", '-t', dirname(__DIR__, 2) . '/public'],
            [0 => ['file', $nulo, 'r'], 1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']],
            $tuberias,
            dirname(__DIR__, 2),
            $entorno,
        );
        if ($proceso === false) {
            throw new RuntimeException('No se pudo iniciar php -S.');
        }
        $this->proceso = $proceso;

        $this->esperarSalud();
    }

    public function __destruct()
    {
        $this->detener();
    }

    public function detener(): void
    {
        if (is_resource($this->proceso)) {
            proc_terminate($this->proceso);
            proc_close($this->proceso);
        }
        $this->proceso = null;
        @unlink($this->datos);
    }

    /**
     * @param array<string, mixed>|null $cuerpo
     * @return array{estado: int, datos: mixed}
     */
    public function pedir(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $cuerpo === null ? '' : json_encode($cuerpo, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 5,
        ]]);

        $respuesta = @file_get_contents($this->url . $ruta, false, $contexto);
        if ($respuesta === false) {
            throw new RuntimeException("Sin respuesta de {$metodo} {$ruta}.");
        }

        // PHP 8.4+ expone las cabeceras con una función; en 8.2/8.3 sigue la variable mágica.
        $cabeceras = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : ($http_response_header ?? []);
        preg_match('#^HTTP/\S+ (\d{3})#', $cabeceras[0] ?? '', $m);

        return ['estado' => (int) ($m[1] ?? 0), 'datos' => json_decode($respuesta, true)];
    }

    private function esperarSalud(): void
    {
        $puerto = (int) parse_url($this->url, PHP_URL_PORT);
        $limite = microtime(true) + 10;
        while (microtime(true) < $limite) {
            $conexion = @fsockopen('127.0.0.1', $puerto, $errno, $error, 0.2);
            if ($conexion !== false) {
                fclose($conexion);
                if ($this->pedir('GET', '/salud')['estado'] === 200) {
                    return;
                }
            }
            usleep(100_000);
        }

        $this->detener();
        throw new RuntimeException("El servidor de pruebas no respondió en {$this->url}/salud.");
    }

    private static function puertoLibre(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException("No hay puerto libre: {$error}");
        }
        $nombre = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) strrchr($nombre, ':'), 1);
    }
}
