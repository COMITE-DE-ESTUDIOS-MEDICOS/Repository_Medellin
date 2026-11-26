<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * DOC-26: lo de la caracterización que no es una lista (fechas y tiempo en
 * calle), 1 a 1 con la ficha. En tabla aparte a propósito: la lista y la
 * búsqueda nunca tocan una columna sensible (CA-21).
 */
class CaracterizacionUsuarioHc extends Model
{
    public const SIN_INFORMACION = 'Sin información';

    protected $table = 'usuario_hc_caracterizaciones';

    protected $fillable = [
        'usuario_hc_id',
        'fecha_nacimiento',
        'fecha_ingreso_sistema',
        'calle_anios',
        'calle_meses',
        'calle_semanas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso_sistema' => 'date',
            'calle_anios' => 'integer',
            'calle_meses' => 'integer',
            'calle_semanas' => 'integer',
        ];
    }

    /**
     * CA-12: años cumplidos al día de hoy en Bogotá; sin fecha, «Sin información».
     * Se calcula, no se guarda. Una fecha futura la rechaza el formulario
     * (UHC-14); si llega por otro camino, tampoco da una edad negativa.
     */
    protected function edad(): Attribute
    {
        return Attribute::get(function (): int|string {
            if ($this->fecha_nacimiento === null) {
                return self::SIN_INFORMACION;
            }

            $zona = config('repositorio.horario.zona');
            $nacimiento = Carbon::parse($this->fecha_nacimiento->toDateString(), $zona);
            $hoy = Carbon::today($zona);

            if ($nacimiento->greaterThan($hoy)) {
                return self::SIN_INFORMACION;
            }

            return (int) $nacimiento->diffInYears($hoy);
        });
    }

    public function usuarioHc(): BelongsTo
    {
        return $this->belongsTo(UsuarioHc::class);
    }
}
