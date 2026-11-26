<?php

namespace App\Models;

use App\Casts\HoraColombia;
use App\Models\Concerns\DelComponente;
use App\Models\Scopes\ComponenteScope;
use App\Models\Scopes\DependenciaScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DOC-27: el paso de una persona por una sede de un componente, de su ingreso
 * a su egreso. Lleva el filtro de dependencia y el de componente; las consultas
 * que cruzan componentes pasan por entreComponentes() y por nada más.
 */
#[ScopedBy([DependenciaScope::class])]
class Estadia extends Model
{
    use DelComponente, HasFactory;

    public const ACTIVA = 'activa';

    public const EGRESADA = 'egresada';

    public const ANULADA = 'anulada';

    // Códigos con efecto (plan D9): no se retiran ni se les cambia el código; el texto sí.
    public const PROCEDENCIA_CALLE = 'intervencion_calle';

    public const MOTIVO_DERIVACION = 'derivacion';

    public const MOTIVO_FALLECIMIENTO = 'fallecimiento';

    protected $table = 'estadias';

    // componente_id y las columnas de estado y autoría no van aquí: las pone el servicio (IYE-04).
    protected $fillable = [
        'dependencia_id',
        'usuario_hc_id',
        'sede_id',
        'sede_nombre',
        'ingreso_en',
        'procedencia',
        'procedencia_texto',
        'comuna',
        'comuna_texto',
        'proyeccion',
        'proyeccion_texto',
        'orientacion',
        'orientacion_texto',
        'observacion_ingreso',
        'egreso_en',
        'motivo_egreso',
        'motivo_egreso_texto',
        'destino',
        'destino_texto',
        'observacion_egreso',
    ];

    protected $attributes = [
        'estado' => self::ACTIVA,
        'revision' => 0,
    ];

    protected function casts(): array
    {
        return [
            'ingreso_en' => HoraColombia::class,
            'egreso_en' => HoraColombia::class,
            'egreso_registrado_en' => 'datetime',
            'anulada_en' => 'datetime',
            'revision' => 'integer',
        ];
    }

    /**
     * ÚNICO salto de ComponenteScope de las estadías (plan D1, spec §8): «ya tiene
     * una activa en X», cruces, reabrir, UHC-19/20. Devuelve la consulta, no
     * resultados; el filtro de dependencia sigue puesto. De una estadía de otro
     * componente solo sale su nombre.
     */
    public function scopeEntreComponentes(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ComponenteScope::class);
    }

    /** Salto de ComponenteScope a propósito (el segundo y último): el nombre del componente. */
    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class)->withoutGlobalScope(ComponenteScope::class);
    }

    public function usuarioHc(): BelongsTo
    {
        return $this->belongsTo(UsuarioHc::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaEstadia::class);
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(CambioEstadia::class);
    }

    /**
     * Días calendario con permanencia, contando el de ingreso y el de salida, en
     * hora de Colombia (plan D4). Salida igual al ingreso: 0. Sigue activa: hasta
     * $hoy. Se calcula en PHP: DATEDIFF y julianday difieren entre MySQL y SQLite.
     */
    public static function diasDeEstadia(CarbonInterface $ingreso, ?CarbonInterface $egreso, ?CarbonInterface $hoy = null): int
    {
        if ($egreso?->equalTo($ingreso)) {
            return 0;
        }

        $zona = config('repositorio.horario.zona');
        $dia = fn (CarbonInterface $hora): Carbon => Carbon::parse($hora->copy()->setTimezone($zona)->toDateString(), 'UTC');
        $fin = $egreso ?? $hoy ?? now($zona);

        return (int) $dia($ingreso)->diffInDays($dia($fin)) + 1;
    }

    /** Los días de la estadía; null si está anulada (no se cuenta). */
    protected function dias(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->estado === self::ANULADA
            ? null
            : self::diasDeEstadia($this->ingreso_en, $this->egreso_en));
    }
}
