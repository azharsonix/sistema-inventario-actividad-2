<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Datos de una petición HTTP ya separados: método, ruta, consulta y cuerpo.
 */
class Peticion
{
    public readonly string $metodo;
    public readonly string $ruta;

    /** @var list<string> Segmentos variables de la ruta (p. ej. el código). */
    public array $parametros = [];

    /**
     * @param array<string, mixed> $consulta Parámetros de la URL (?q=...).
     */
    public function __construct(
        string $metodo,
        string $ruta,
        public readonly array $consulta = [],
        public readonly string $cuerpo = '',
    ) {
        $this->metodo = strtoupper($metodo);
        $ruta = '/' . trim($ruta, '/');
        $this->ruta = $ruta;
    }

    /**
     * Cuerpo decodificado como objeto JSON. Un cuerpo vacío equivale a {}.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if (trim($this->cuerpo) === '') {
            return [];
        }

        $datos = json_decode($this->cuerpo, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ErrorHttp(400, 'El cuerpo de la petición no es un JSON válido.');
        }
        if (!is_array($datos) || (array_is_list($datos) && $datos !== [])) {
            throw new ErrorHttp(400, 'El cuerpo de la petición debe ser un objeto JSON.');
        }

        return $datos;
    }

    /**
     * Valor de texto de la consulta; cadena vacía si no viene o no es texto.
     */
    public function consulta(string $nombre): string
    {
        $valor = $this->consulta[$nombre] ?? '';

        return is_string($valor) ? trim($valor) : '';
    }
}
