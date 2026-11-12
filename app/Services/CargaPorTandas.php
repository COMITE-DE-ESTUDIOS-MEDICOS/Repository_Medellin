<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Una carga grande (cientos de archivos) llega en varias peticiones de 50: el
 * navegador las parte (resources/js/carga-tandas.js) y manda con cada una
 * lote, lote_parte, lote_partes y lote_archivos. Esto recuerda, entre una
 * tanda y la siguiente, lo que hace falta para que todas juntas se comporten
 * como una sola carga:
 *
 *  - qué archivos ya entraron, para que reintentar una tanda no los duplique
 *    (solo dentro de esta carga: subir dos veces la misma foto a propósito,
 *    en cargas distintas, sigue siendo posible);
 *  - si ya empezó, para que un enlace de «1 uso» no se cierre en la tanda 1;
 *  - los nombres recibidos, para la confirmación final.
 *
 * Vive en caché unas horas: si la carga se abandona, se olvida sola.
 */
class CargaPorTandas
{
    protected const HORAS = 6;

    protected function __construct(
        public readonly string $lote,
        public readonly int $parte,
        public readonly int $partes,
        public readonly int $archivos,
        protected string $ambito,
    ) {
    }

    /**
     * La carga a la que pertenece esta petición, o null si llegó sola (el
     * formulario de siempre, sin tandas). El ámbito separa las cargas de un
     * enlace o de un usuario de las de otro.
     */
    public static function desde(Request $peticion, string $ambito): ?self
    {
        $lote = (string) $peticion->input('lote');

        if (! Str::isUuid($lote)) {
            return null;
        }

        $partes = max(1, (int) $peticion->input('lote_partes'));

        return new self(
            $lote,
            min($partes, max(1, (int) $peticion->input('lote_parte'))),
            $partes,
            max(0, (int) $peticion->input('lote_archivos')),
            $ambito,
        );
    }

    public function esUltima(): bool
    {
        return $this->parte >= $this->partes;
    }

    /** ¿Ya entró alguna tanda de esta carga? */
    public function yaEmpezo(): bool
    {
        return Cache::has($this->clave('empezo'));
    }

    public function marcarEmpezada(): void
    {
        Cache::put($this->clave('empezo'), true, now()->addHours(self::HORAS));
    }

    public function yaRecibido(string $hash): bool
    {
        return in_array($hash, Cache::get($this->clave('hashes'), []), true);
    }

    /**
     * Lo que entró en esta tanda: sus hashes (contra duplicados al
     * reintentar) y sus nombres (para la confirmación final).
     *
     * @param  list<string>  $hashes
     * @param  list<string>  $nombres
     */
    public function anotar(array $hashes, array $nombres): void
    {
        $vence = now()->addHours(self::HORAS);

        Cache::put($this->clave('hashes'), array_values(array_unique([...Cache::get($this->clave('hashes'), []), ...$hashes])), $vence);
        Cache::put($this->clave('nombres'), [...Cache::get($this->clave('nombres'), []), ...$nombres], $vence);
    }

    /** @return list<string> todo lo recibido en la carga, de todas sus tandas */
    public function nombres(): array
    {
        return Cache::get($this->clave('nombres'), []);
    }

    /** Las reglas de los campos de la tanda, para sumar a la validación. */
    /**
     * Cuántos archivos por petición. Nunca más de lo que deja PHP
     * (max_file_uploads, 20 de fábrica): lo que pasa de ahí PHP lo tira sin
     * avisar, y la carga parecería haber «borrado» archivos.
     */
    public static function porTanda(): int
    {
        $php = (int) ini_get('max_file_uploads') ?: 20;

        return max(1, min((int) config('repositorio.carga.por_tanda'), $php));
    }

    /**
     * Para 'archivo': el formulario manda un nombre por tarjeta, así que si
     * llegan menos archivos que nombres, PHP se quedó con parte de la carga.
     * Mejor un error claro que guardar la mitad en silencio.
     */
    public static function reglaCompleta(): \Closure
    {
        return function (string $atributo, $archivos, \Closure $fallar) {
            $esperados = count((array) request()->input('nombres', []));
            $recibidos = count((array) $archivos);

            if ($esperados > $recibidos) {
                $fallar("El servidor recibió {$recibidos} de {$esperados} archivos y no se guardó nada. Vuelve a intentarlo con menos archivos por vez, o avisa a soporte (límite de PHP: max_file_uploads).");
            }
        };
    }

    public static function reglas(): array
    {
        return [
            'lote' => ['nullable', 'uuid'],
            'lote_parte' => ['nullable', 'integer', 'min:1', 'max:100'],
            'lote_partes' => ['nullable', 'integer', 'min:1', 'max:100'],
            'lote_archivos' => ['nullable', 'integer', 'min:1', 'max:'.config('repositorio.carga.maximo_total')],
        ];
    }

    protected function clave(string $que): string
    {
        return "carga-tandas:{$this->ambito}:{$this->lote}:{$que}";
    }
}
