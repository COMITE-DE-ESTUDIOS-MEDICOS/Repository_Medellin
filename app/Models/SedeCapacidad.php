<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DOC-27 (plan D1): una capacidad con su vigencia. Nunca se edita ni se borra: cada cambio es una fila. */
class SedeCapacidad extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sede_capacidades';

    protected $fillable = ['sede_id', 'capacidad', 'vigente_desde', 'motivo', 'user_id'];

    protected function casts(): array
    {
        return ['capacidad' => 'integer', 'vigente_desde' => 'date'];
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
