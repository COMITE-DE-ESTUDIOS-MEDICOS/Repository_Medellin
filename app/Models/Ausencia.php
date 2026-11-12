<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un rango de días en que la persona no trabaja: descanso (D), vacaciones
 * (VAC), incapacidad (I), permiso (P)… Esos días no se la espera, así que no
 * sale como «no registró entrada».
 */
#[ScopedBy([DependenciaScope::class])]
class Ausencia extends Model
{
    protected $table = 'ausencias';

    protected $fillable = ['dependencia_id', 'colaborador_id', 'tipo_turno_id', 'desde', 'hasta', 'motivo', 'registrada_por'];

    protected function casts(): array
    {
        return ['desde' => 'date', 'hasta' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    /** El código de la ausencia (D, VAC, I…): un tipo de turno que no es de trabajo. */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoTurno::class, 'tipo_turno_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    public function scopeEn(Builder $query, CarbonInterface $dia): Builder
    {
        return $query->whereDate('desde', '<=', $dia)->whereDate('hasta', '>=', $dia);
    }

    /** «12/10 al 18/10», o «12/10» si es un solo día. */
    public function rango(): string
    {
        return $this->desde->equalTo($this->hasta)
            ? $this->desde->format('d/m/Y')
            : $this->desde->format('d/m').' al '.$this->hasta->format('d/m/Y');
    }
}
