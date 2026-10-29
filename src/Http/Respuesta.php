<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Respuesta HTTP armada por la API: estado, cabeceras y cuerpo.
 */
class Respuesta
{
    /**
     * @param array<string, string> $cabeceras
     */
    public function __construct(
        public readonly int $estado,
        public readonly string $cuerpo,
        public readonly array $cabeceras = [],
    ) {
    }

    /**
     * @param array<string, string> $cabeceras
     */
    public static function json(mixed $datos, int $estado = 200, array $cabeceras = []): self
    {
        $cuerpo = json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );

        return new self($estado, $cuerpo, ['Content-Type' => 'application/json; charset=utf-8'] + $cabeceras);
    }

    public static function html(string $html, int $estado = 200): self
    {
        return new self($estado, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Contenido JSON decodificado (útil en las pruebas).
     */
    public function datos(): mixed
    {
        return json_decode($this->cuerpo, true);
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        foreach ($this->cabeceras as $nombre => $valor) {
            header("{$nombre}: {$valor}");
        }
        echo $this->cuerpo;
    }
}
