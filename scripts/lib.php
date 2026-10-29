<?php

/**
 * Funciones compartidas por ejecutar-pruebas.php y simular-defectos.php:
 * ejecutar PHPUnit, leer su JUnit y convertir cada prueba en un caso CP-xx.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\TestDox;

const RAIZ = __DIR__ . '/..';
const SUITES = ['Unitarias' => 'Unitaria', 'Integracion' => 'Integración', 'Sistema' => 'Sistema', 'Aceptacion' => 'Aceptación'];

require_once RAIZ . '/vendor/autoload.php';

/**
 * Ejecuta PHPUnit en un proceso aparte y deja el resultado en $junit.
 * Con $src las clases App\ se cargan de esa carpeta (defecto inyectado).
 *
 * @param list<string> $argumentos
 * @return array{codigo: int, segundos: float, salida: string}
 */
function ejecutarPhpunit(array $argumentos, string $junit, ?string $src = null, bool $mostrar = true): array
{
    @unlink($junit);
    $comando = array_merge(
        [PHP_BINARY, RAIZ . '/vendor/phpunit/phpunit/phpunit', '--configuration', RAIZ . '/phpunit.xml',
            '--colors=never', '--no-progress', '--log-junit', $junit],
        $argumentos,
    );

    $entorno = getenv();
    $entorno['A2_SRC'] = $src ?? '';

    $inicio = microtime(true);
    $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, RAIZ, $entorno);
    $salida = stream_get_contents($tuberias[1]) . stream_get_contents($tuberias[2]);
    fclose($tuberias[1]);
    fclose($tuberias[2]);
    $codigo = proc_close($proceso);

    if ($mostrar) {
        echo $salida;
    }

    return ['codigo' => $codigo, 'segundos' => microtime(true) - $inicio, 'salida' => $salida];
}

/**
 * Casos de prueba encontrados en un archivo JUnit.
 *
 * @return list<array{id: string, requisitos: list<string>, tipo: string, nombre: string,
 *     estado: string, segundos: float, mensaje: string, prueba: string}>
 */
function leerJunit(string $archivo): array
{
    if (!is_file($archivo)) {
        return [];
    }

    $xml = simplexml_load_file($archivo);
    $casos = [];
    foreach ($xml->xpath('//testcase') as $tc) {
        $clase = (string) $tc['class'];
        $metodo = (string) $tc['name'];
        $testdox = testdox($clase, $metodo) ?? $metodo;

        preg_match('/^(CP-\d+)\s*(?:\[([^\]]+)\])?\s*(.*)$/u', $testdox, $m);
        $carpeta = explode('\\', $clase)[1] ?? '';

        $mensaje = '';
        $estado = 'Aprobada';
        foreach (['failure' => 'No aprobada', 'error' => 'No aprobada', 'skipped' => 'Omitida'] as $nodo => $valor) {
            if (isset($tc->{$nodo})) {
                $estado = $valor;
                // La primera línea del JUnit repite Clase::metodo; el motivo viene en la siguiente.
                $lineas = array_values(array_filter(array_map('trim', explode("\n", (string) $tc->{$nodo}))));
                if (isset($lineas[0]) && str_contains($lineas[0], '::')) {
                    array_shift($lineas);
                }
                $mensaje = $lineas[0] ?? '';
                break;
            }
        }

        $casos[] = [
            'id' => $m[1] ?? $metodo,
            'requisitos' => isset($m[2]) && $m[2] !== '' ? array_map('trim', explode(',', $m[2])) : [],
            'tipo' => SUITES[$carpeta] ?? $carpeta,
            'nombre' => $m[3] ?? $testdox,
            'estado' => $estado,
            'segundos' => round((float) $tc['time'], 3),
            'mensaje' => $mensaje,
            'prueba' => "{$clase}::{$metodo}",
        ];
    }

    usort($casos, fn ($a, $b) => strnatcmp($a['id'], $b['id']));

    return $casos;
}

function testdox(string $clase, string $metodo): ?string
{
    if (!method_exists($clase, $metodo)) {
        return null;
    }
    $atributos = (new ReflectionMethod($clase, $metodo))->getAttributes(TestDox::class);

    return $atributos === [] ? null : $atributos[0]->newInstance()->text();
}

/**
 * @return array<string, string>
 */
function ambiente(): array
{
    $phpunit = class_exists(PHPUnit\Runner\Version::class) ? PHPUnit\Runner\Version::id() : '?';

    return [
        'Sistema operativo' => php_uname('s') . ' ' . php_uname('r'),
        'PHP' => PHP_VERSION,
        'PHPUnit' => $phpunit,
        'Ejecutado en' => getenv('GITHUB_ACTIONS') ? 'GitHub Actions (' . getenv('GITHUB_REF_NAME') . ' @ ' . substr((string) getenv('GITHUB_SHA'), 0, 7) . ')' : 'Equipo local',
    ];
}

function ahora(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('America/Merida'));
}

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function copiarCarpeta(string $origen, string $destino): void
{
    @mkdir($destino, 0777, true);
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($origen, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterador as $item) {
        $ruta = $destino . '/' . $iterador->getSubPathname();
        $item->isDir() ? @mkdir($ruta, 0777, true) : copy($item->getPathname(), $ruta);
    }
}

function borrarCarpeta(string $carpeta): void
{
    if (!is_dir($carpeta)) {
        return;
    }
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterador as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($carpeta);
}

/**
 * Agrega Markdown al resumen del job cuando corre en GitHub Actions.
 */
function resumenGithub(string $markdown): void
{
    $archivo = getenv('GITHUB_STEP_SUMMARY');
    if ($archivo) {
        file_put_contents($archivo, $markdown . "\n", FILE_APPEND);
    }
}
