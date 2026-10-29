<?php

declare(strict_types=1);

/*
 * Bootstrap de PHPUnit y del servidor de pruebas.
 *
 * Si la variable A2_SRC apunta a otra carpeta src/ (la copia con un defecto
 * inyectado que arma scripts/simular-defectos.php), las clases App\ se cargan
 * de ahí antes que del código original.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$src = getenv('A2_SRC');
if ($src !== false && $src !== '' && is_dir($src)) {
    spl_autoload_register(static function (string $clase) use ($src): void {
        if (str_starts_with($clase, 'App\\')) {
            $archivo = $src . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
            if (is_file($archivo)) {
                require $archivo;
            }
        }
    }, true, true);
}
