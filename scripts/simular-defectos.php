<?php

/**
 * Prueba de regresión con defectos inyectados.
 *
 * Para cada defecto hipotético (DEF-01 y DEF-02) copia src/ a una carpeta
 * temporal, aplica el cambio que lo provoca y ejecuta las cuatro suites
 * contra esa copia (A2_SRC). El código original no se toca. Se espera que
 * fallen los casos que cubren ese requisito. Al final ejecuta la suite
 * completa contra el código corregido (el original) y espera 10 de 10.
 *
 * Evidencia en evidencias/regresion/:
 *   DEF-0X/junit.xml   resultado con el defecto presente
 *   corregido/junit.xml  ronda de regresión con el código corregido
 *   resumen.json / resumen.md
 *
 * Uso: php scripts/simular-defectos.php   (o composer regresion)
 * Termina con código 1 si algún defecto no se detecta o si la ronda final falla.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$defectos = [
    'DEF-01' => [
        'titulo' => 'La venta permite vender más unidades de las que hay y deja la existencia negativa',
        'prioridad' => 'Alta',
        'archivo' => 'Inventario.php',
        'buscar' => 'if ($cantidad > $producto->existencia) {',
        'reemplazar' => 'if (false && $cantidad > $producto->existencia) {',
        'esperados' => ['CP-04', 'CP-06', 'CP-08', 'CP-15'],
    ],
    'DEF-02' => [
        'titulo' => 'El reporte no marca para resurtir un producto cuya existencia es igual a su stock mínimo',
        'prioridad' => 'Media',
        'archivo' => 'Producto.php',
        'buscar' => 'return $this->existencia <= $this->stockMinimo;',
        'reemplazar' => 'return $this->existencia < $this->stockMinimo;',
        'esperados' => ['CP-09', 'CP-15'],
    ],
];

$dir = RAIZ . '/evidencias/regresion';
@mkdir($dir, 0777, true);
$ok = true;
$resumen = ['fecha' => ahora()->format(DateTimeInterface::ATOM), 'ambiente' => ambiente(), 'defectos' => []];

foreach ($defectos as $id => $d) {
    $copia = sys_get_temp_dir() . "/a2-{$id}-" . bin2hex(random_bytes(3));
    copiarCarpeta(RAIZ . '/src', "{$copia}/src");

    $archivo = "{$copia}/src/{$d['archivo']}";
    $codigo = file_get_contents($archivo);
    if (substr_count($codigo, $d['buscar']) !== 1) {
        fwrite(STDERR, "{$id}: no se encontró el fragmento a modificar en {$d['archivo']}.\n");
        borrarCarpeta($copia);
        exit(2);
    }
    file_put_contents($archivo, str_replace($d['buscar'], $d['reemplazar'], $codigo));

    echo "\n=== {$id}: {$d['titulo']} ===\n";
    echo "    src/{$d['archivo']}:  {$d['buscar']}  ->  {$d['reemplazar']}\n";

    @mkdir("{$dir}/{$id}", 0777, true);
    ejecutarPhpunit([], "{$dir}/{$id}/junit.xml", "{$copia}/src", false);
    borrarCarpeta($copia);

    $casos = leerJunit("{$dir}/{$id}/junit.xml");
    $fallidos = array_values(array_column(array_filter($casos, fn ($c) => $c['estado'] === 'No aprobada'), 'id'));
    $noDetectados = array_values(array_diff($d['esperados'], $fallidos));
    $detectado = $fallidos !== [] && $noDetectados === [];
    $ok = $ok && $detectado;

    foreach (array_filter($casos, fn ($c) => $c['estado'] === 'No aprobada') as $c) {
        echo "    ✘ {$c['id']}  {$c['mensaje']}\n";
    }
    printf("    %s  fallaron %d de %d casos (esperados: %s)\n", $detectado ? 'DETECTADO' : 'NO DETECTADO',
        count($fallidos), count($casos), implode(', ', $d['esperados']));

    $resumen['defectos'][] = [
        'id' => $id,
        'titulo' => $d['titulo'],
        'prioridad' => $d['prioridad'],
        'cambio' => "src/{$d['archivo']}: `{$d['buscar']}` → `{$d['reemplazar']}`",
        'casosEsperados' => $d['esperados'],
        'casosFallidos' => $fallidos,
        'mensajes' => array_values(array_map(
            fn ($c) => ['id' => $c['id'], 'mensaje' => $c['mensaje']],
            array_filter($casos, fn ($c) => $c['estado'] === 'No aprobada'),
        )),
        'detectado' => $detectado,
    ];
}

echo "\n=== Ronda de regresión con el código corregido ===\n";
@mkdir("{$dir}/corregido", 0777, true);
ejecutarPhpunit([], "{$dir}/corregido/junit.xml", null, false);
$final = leerJunit("{$dir}/corregido/junit.xml");
$aprobados = count(array_filter($final, fn ($c) => $c['estado'] === 'Aprobada'));
$ok = $ok && $aprobados === count($final) && $final !== [];
printf("    %d de %d casos aprobados\n", $aprobados, count($final));
$resumen['corregido'] = ['total' => count($final), 'aprobadas' => $aprobados];
$resumen['resultado'] = $ok ? 'Las pruebas detectan los dos defectos y pasan con el código corregido' : 'Revisar: algún defecto no se detectó o la ronda final falló';

file_put_contents("{$dir}/resumen.json", json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

$md = "## Regresión con defectos inyectados\n\n| Defecto | Prioridad | Casos que fallaron | Esperados | ¿Detectado? |\n|---|---|---|---|---|\n";
foreach ($resumen['defectos'] as $d) {
    $md .= sprintf("| %s %s | %s | %s | %s | %s |\n", $d['id'], $d['titulo'], $d['prioridad'],
        implode(', ', $d['casosFallidos']) ?: '—', implode(', ', $d['casosEsperados']), $d['detectado'] ? 'Sí' : 'No');
}
$md .= "\n**Código corregido:** {$aprobados} de " . count($final) . " casos aprobados.\n";
file_put_contents("{$dir}/resumen.md", $md);
resumenGithub($md);

echo "\n" . $resumen['resultado'] . "\n";
exit($ok ? 0 : 1);
