<?php

namespace App\Models;

use App\Models\Concerns\DelComponente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sin DependenciaScope a propósito: no tiene dependencia_id. Se llega a un
 * nodo siempre por su componente ($componente->nodos), que sí está aislado.
 */
class Nodo extends Model
{
    use DelComponente;
    use HasFactory;

    protected $table = 'nodos';

    protected $fillable = ['componente_id', 'nombre', 'orden', 'lat', 'lng', 'radio_m', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'radio_m' => 'integer',
        ];
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    /** Con centro y radio se puede juzgar si una marca quedó fuera de zona. */
    public function tieneZona(): bool
    {
        return $this->lat !== null && $this->lng !== null && $this->radio_m !== null;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
