<?php

namespace App\Models;

use App\Enums\OrigenMarcacion;
use App\Enums\TipoMarcacion;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Una entrada o una salida. Inmutable de verdad, no por convención: el
 * modelo no deja actualizarla ni borrarla. Una corrección es otra fila con
 * origen 'manual', motivo y autor (Marcador::registrarManual()).
 *
 * Ojo: un update masivo por query builder (Marcacion::where(...)->update())
 * no pasa por estos eventos. No se usa en ninguna parte y no debe usarse.
 */
#[ScopedBy([DependenciaScope::class])]
class Marcacion extends Model
{
    use HasFactory;

    protected $table = 'marcaciones';

    protected $fillable = [
        'dependencia_id',
        'colaborador_id',
        'componente_id',
        'nodo_id',
        'enlace_turno_id',
        'tipo_turno_id',
        'punto_critico_id',
        'minutos_tarde',
        'tipo',
        'marcada_at',
        'declarada_at',
        'lat',
        'lng',
        'precision_m',
        'municipio',
        'documento_id',
        'origen',
        'motivo',
        'registrada_por',
        'fuera_de_zona',
        'fuera_de_turno',
        'sin_ubicacion',
        'epp_recibidos',
        'ubicacion_imprecisa',
        'ip',
        'agente',
        'anulada_at',
        'anulada_por',
        'motivo_anulacion',
        'reemplazada_por_id',
    ];

    /** Lo único que se le puede cambiar a una marcación: anularla, una vez. */
    public const CAMPOS_DE_ANULACION = ['anulada_at', 'anulada_por', 'motivo_anulacion', 'reemplazada_por_id', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMarcacion::class,
            'origen' => OrigenMarcacion::class,
            'marcada_at' => 'datetime',
            'declarada_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'precision_m' => 'integer',
            'fuera_de_zona' => 'boolean',
            'fuera_de_turno' => 'boolean',
            'sin_ubicacion' => 'boolean',
            'epp_recibidos' => 'boolean',
            'ubicacion_imprecisa' => 'boolean',
            'anulada_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Ni la hora, ni el tipo, ni nada de lo que pasó se edita. Lo único
        // permitido es anularla una vez, al corregirla (Marcador::corregir):
        // la original queda con quién, cuándo y por qué, y la nueva la reemplaza.
        static::updating(function (self $marcacion) {
            $soloAnula = array_diff(array_keys($marcacion->getDirty()), self::CAMPOS_DE_ANULACION) === [];

            if (! $soloAnula || $marcacion->getOriginal('anulada_at') !== null) {
                throw new LogicException('Una marcación no se edita: se corrige con Marcador::corregir(), que la anula y la reemplaza.');
            }
        });
        static::deleting(fn () => throw new LogicException('Una marcación no se borra: es parte de la auditoría.'));
    }

    public function esEntrada(): bool
    {
        return $this->tipo === TipoMarcacion::Entrada;
    }

    /**
     * Quién está en turno ahora: la última marca de cada persona, cuando es
     * una entrada. Una sola consulta para toda la lista, no una por persona.
     *
     * «Última» por marcada_at y no por id: una corrección manual se inserta
     * después pero puede ser de una hora anterior.
     */
    public function scopeEntradasAbiertas(Builder $query): Builder
    {
        return $query
            ->vigentes()
            ->where('tipo', TipoMarcacion::Entrada->value)
            ->whereRaw('marcada_at = (select max(m2.marcada_at) from marcaciones m2 where m2.colaborador_id = marcaciones.colaborador_id and m2.anulada_at is null)');
    }

    /** Las que cuentan: una anulada quedó reemplazada por su corrección. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('marcaciones.anulada_at');
    }

    public function estaAnulada(): bool
    {
        return $this->anulada_at !== null;
    }

    /** Minutos desde que marcó: para «lleva 5 h 20 min» y para saber si pasó de la duración máxima. */
    public function minutosDesde(): int
    {
        return (int) $this->marcada_at->diffInMinutes(now());
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    /** El turno que el sistema reconoció por la hora de la entrada. */
    public function tipoTurno(): BelongsTo
    {
        return $this->belongsTo(TipoTurno::class);
    }

    public function puntoCritico(): BelongsTo
    {
        return $this->belongsTo(PuntoCritico::class);
    }

    public function enlace(): BelongsTo
    {
        return $this->belongsTo(EnlaceTurno::class, 'enlace_turno_id');
    }

    /** La foto de evidencia, guardada como documento del repositorio. */
    public function foto(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function reemplazo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplazada_por_id');
    }
}
