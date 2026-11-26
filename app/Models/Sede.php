<?php

namespace App\Models;

use App\Models\Concerns\DelComponente;
use App\Models\Scopes\DependenciaScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** DOC-27: una sede de un componente. Las crea y cambia solo el superadministrador. */
#[ScopedBy([DependenciaScope::class])]
class Sede extends Model
{
    use DelComponente, HasFactory;

    protected $table = 'sedes';

    // componente_id no va aquí a propósito: lo pone el servidor desde el contexto.
    protected $fillable = ['dependencia_id', 'nombre', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function capacidades(): HasMany
    {
        return $this->hasMany(SedeCapacidad::class);
    }

    /**
     * La capacidad vigente ese día: la de mayor vigente_desde hasta él y, a igual
     * fecha, la última que se escribió. null antes de la primera (PA-11).
     * whereDate: SQLite guarda la columna date con hora, y comparar con la fecha
     * pelada dejaría fuera la capacidad que empieza ese mismo día.
     */
    public function capacidadEn(CarbonInterface $dia): ?int
    {
        $capacidad = $this->capacidades()
            ->whereDate('vigente_desde', '<=', $dia->toDateString())
            ->orderByDesc('vigente_desde')
            ->orderByDesc('id')
            ->value('capacidad');

        return $capacidad === null ? null : (int) $capacidad;
    }
}
