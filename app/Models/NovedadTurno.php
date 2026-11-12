<?php

namespace App\Models;

use App\Enums\EstadoNovedad;
use App\Enums\TipoNovedad;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que se salió de lo esperado en un turno, esperando que Coordinación lo
 * apruebe o lo rechace. Las marcaciones no se tocan: el juicio vive aquí.
 */
#[ScopedBy([DependenciaScope::class])]
class NovedadTurno extends Model
{
    protected $table = 'novedades_turno';

    protected $fillable = [
        'dependencia_id',
        'colaborador_id',
        'componente_id',
        'marcacion_id',
        'entrada_id',
        'fecha',
        'tipo',
        'detalle',
        'minutos',
        'estado',
        'comentario',
        'revisada_por',
        'revisada_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNovedad::class,
            'estado' => EstadoNovedad::class,
            'minutos' => 'integer',
            'revisada_at' => 'datetime',
            'fecha' => 'date',
        ];
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoNovedad::Pendiente;
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoNovedad::Pendiente->value);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function marcacion(): BelongsTo
    {
        return $this->belongsTo(Marcacion::class);
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(Marcacion::class, 'entrada_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }
}
