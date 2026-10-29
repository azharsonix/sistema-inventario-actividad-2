<?php

/**
 * Ejecuta las cuatro suites (Unitarias, Integración, Sistema y Aceptación) por
 * separado y registra los resultados de cada caso CP-xx en evidencias/:
 *
 *   junit-<Suite>.xml     resultado de PHPUnit en formato JUnit (lo lee el CI)
 *   salida-<Suite>.txt    salida de la consola de cada suite
 *   resultados.json       casos, estado, tiempo, requisito y ambiente
 *   resultados.md         tabla de resultados (también va al resumen de GitHub Actions)
 *   reporte-pruebas.html  reporte para entregar
 *
 * Uso:
 *   php scripts/ejecutar-pruebas.php                         las cuatro suites
 *   php scripts/ejecutar-pruebas.php --suite=Unitarias       una sola suite (un job del CI)
 *   php scripts/ejecutar-pruebas.php --solo-reporte          arma el reporte con los junit-*.xml existentes
 *   php scripts/ejecutar-pruebas.php --ronda="Regresión 1"   etiqueta de la ronda de ejecución
 *
 * Termina con código 1 si algún caso queda No aprobado.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$opciones = getopt('', ['suite:', 'solo-reporte', 'ronda:', 'salida:']);
$dir = $opciones['salida'] ?? RAIZ . '/evidencias';
$ronda = $opciones['ronda'] ?? 'Ejecución ' . ahora()->format('Y-m-d H:i');
@mkdir($dir, 0777, true);

$suites = array_keys(SUITES);
if (isset($opciones['suite'])) {
    if (!isset(SUITES[$opciones['suite']])) {
        fwrite(STDERR, "Suite desconocida. Opciones: " . implode(', ', $suites) . "\n");
        exit(2);
    }
    $suites = [$opciones['suite']];
}

$inicio = ahora();
$fallo = false;

if (!isset($opciones['solo-reporte'])) {
    foreach ($suites as $suite) {
        echo "\n=== Suite {$suite} (" . SUITES[$suite] . ") ===\n";
        $r = ejecutarPhpunit(['--testsuite', $suite, '--testdox'], "{$dir}/junit-{$suite}.xml");
        file_put_contents("{$dir}/salida-{$suite}.txt", $r['salida']);
        $fallo = $fallo || $r['codigo'] !== 0;
    }
}

// Con una sola suite basta el JUnit; el reporte completo lo arma el job final del CI.
if (isset($opciones['suite'])) {
    exit($fallo ? 1 : 0);
}

$casos = [];
foreach (array_keys(SUITES) as $suite) {
    array_push($casos, ...leerJunit("{$dir}/junit-{$suite}.xml"));
}
usort($casos, fn ($a, $b) => strnatcmp($a['id'], $b['id']));

if ($casos === []) {
    fwrite(STDERR, "No hay resultados en {$dir}/junit-*.xml.\n");
    exit(1);
}

$conteo = array_count_values(array_column($casos, 'estado')) + ['Aprobada' => 0, 'No aprobada' => 0, 'Omitida' => 0];
$fallo = $fallo || $conteo['No aprobada'] > 0;

$resultado = [
    'ronda' => $ronda,
    'fecha' => $inicio->format(DateTimeInterface::ATOM),
    'ambiente' => ambiente(),
    'resumen' => [
        'total' => count($casos),
        'aprobadas' => $conteo['Aprobada'],
        'noAprobadas' => $conteo['No aprobada'],
        'omitidas' => $conteo['Omitida'],
        'segundos' => round(array_sum(array_column($casos, 'segundos')), 3),
    ],
    'porTipo' => array_map(
        fn (string $tipo) => [
            'tipo' => $tipo,
            'casos' => array_values(array_column(array_filter($casos, fn ($c) => $c['tipo'] === $tipo), 'id')),
        ],
        array_values(SUITES),
    ),
    'casos' => $casos,
];
file_put_contents("{$dir}/resultados.json", json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

// --- Markdown -----------------------------------------------------------------
$md = "## Resultados de pruebas: {$ronda}\n\n";
$md .= "**{$resultado['resumen']['aprobadas']} de {$resultado['resumen']['total']} casos aprobados**"
    . " · {$resultado['resumen']['noAprobadas']} no aprobados · {$resultado['resumen']['segundos']} s\n\n";
$md .= "| ID | Tipo | Requisito | Caso | Estado | Tiempo |\n|---|---|---|---|---|---|\n";
foreach ($casos as $c) {
    $icono = $c['estado'] === 'Aprobada' ? '✅' : '❌';
    $md .= sprintf("| %s | %s | %s | %s | %s %s | %.3f s |\n", $c['id'], $c['tipo'], implode(', ', $c['requisitos']),
        str_replace('|', '\|', $c['nombre']), $icono, $c['estado'], $c['segundos']);
}
foreach (array_filter($casos, fn ($c) => $c['mensaje'] !== '') as $c) {
    $md .= "\n- **{$c['id']}**: " . str_replace("\n", ' ', $c['mensaje']);
}
file_put_contents("{$dir}/resultados.md", $md . "\n");
resumenGithub($md);

// --- HTML ---------------------------------------------------------------------
$filas = '';
foreach ($casos as $c) {
    $clase = $c['estado'] === 'Aprobada' ? 'ok' : 'mal';
    $detalle = $c['mensaje'] !== '' ? '<div class="msg">' . e($c['mensaje']) . '</div>' : '';
    $filas .= sprintf(
        '<tr><td class="id">%s</td><td>%s</td><td>%s</td><td>%s%s</td><td><span class="estado %s">%s</span></td><td class="num">%.3f s</td></tr>',
        e($c['id']), e($c['tipo']), e(implode(', ', $c['requisitos'])), e($c['nombre']), $detalle, $clase, e($c['estado']), $c['segundos'],
    );
}
$amb = '';
foreach ($resultado['ambiente'] as $k => $v) {
    $amb .= '<div><dt>' . e($k) . '</dt><dd>' . e($v) . '</dd></div>';
}
$r = $resultado['resumen'];
$rondaHtml = e($ronda);
$fechaHtml = e($inicio->format('d/m/Y H:i'));
$html = <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reporte de pruebas · Actividad 2</title>
<style>
  :root { --fondo:#f6f7f9; --papel:#fff; --texto:#1d2330; --tenue:#5d6575; --borde:#e2e5ea; --ok:#1f7a4d; --ok-f:#e3f4ea; --mal:#b42318; --mal-f:#fde8e6; }
  @media (prefers-color-scheme: dark) { :root { --fondo:#14171c; --papel:#1c2027; --texto:#e8eaee; --tenue:#a0a7b4; --borde:#2d333d; --ok:#5fd39a; --ok-f:#173527; --mal:#ff8a7e; --mal-f:#3d1d1a; } }
  * { box-sizing: border-box; }
  body { margin:0; font:15px/1.5 system-ui, "Segoe UI", sans-serif; background:var(--fondo); color:var(--texto); }
  main { max-width:1080px; margin:0 auto; padding:32px 16px 48px; }
  h1 { margin:0 0 4px; font-size:24px; } .sub { color:var(--tenue); margin:0 0 24px; }
  .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:24px; }
  .kpi { background:var(--papel); border:1px solid var(--borde); border-radius:10px; padding:14px 16px; }
  .kpi b { display:block; font-size:28px; } .kpi span { color:var(--tenue); font-size:13px; }
  .tabla { background:var(--papel); border:1px solid var(--borde); border-radius:10px; overflow-x:auto; }
  table { border-collapse:collapse; width:100%; min-width:720px; }
  th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--borde); vertical-align:top; }
  th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--tenue); }
  tr:last-child td { border-bottom:0; } .id { font-weight:600; white-space:nowrap; } .num { white-space:nowrap; font-variant-numeric:tabular-nums; }
  .estado { padding:2px 10px; border-radius:99px; font-size:13px; font-weight:600; white-space:nowrap; }
  .ok { background:var(--ok-f); color:var(--ok); } .mal { background:var(--mal-f); color:var(--mal); }
  .msg { margin-top:6px; font:12px/1.4 ui-monospace, Consolas, monospace; color:var(--mal); }
  dl { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:8px 16px; margin:24px 0 0; }
  dt { font-size:12px; color:var(--tenue); } dd { margin:0; }
</style>
</head>
<body>
<main>
  <h1>Reporte de pruebas · Sistema de control de inventario</h1>
  <p class="sub">{$rondaHtml} · {$fechaHtml}</p>
  <section class="kpis">
    <div class="kpi"><b>{$r['total']}</b><span>casos ejecutados</span></div>
    <div class="kpi"><b style="color:var(--ok)">{$r['aprobadas']}</b><span>aprobados</span></div>
    <div class="kpi"><b style="color:var(--mal)">{$r['noAprobadas']}</b><span>no aprobados</span></div>
    <div class="kpi"><b>{$r['segundos']} s</b><span>tiempo total</span></div>
  </section>
  <div class="tabla"><table>
    <thead><tr><th>ID</th><th>Tipo</th><th>Requisito</th><th>Caso</th><th>Estado</th><th>Tiempo</th></tr></thead>
    <tbody>{$filas}</tbody>
  </table></div>
  <dl>{$amb}</dl>
</main>
</body>
</html>
HTML;
file_put_contents("{$dir}/reporte-pruebas.html", $html);

printf("\n%d de %d casos aprobados. Evidencia en %s\n", $r['aprobadas'], $r['total'], realpath($dir));
exit($fallo ? 1 : 0);
