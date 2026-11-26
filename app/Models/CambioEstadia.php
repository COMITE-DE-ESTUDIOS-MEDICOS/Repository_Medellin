<?php

namespace App\Models;

use App\Models\Scopes\ComponenteScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DOC-27 (plan D1): una fila del historial de la estadía, por campo cambiado. Es el registro de verdad. */
class CambioEstadia extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'estadia_cambios';

    protected $fillable = [
        'estadia_id',
        'user_id',
        'componente_id',
        'accion',
        'campo',
        'codigo_antes',
        'codigo_despues',
        'antes',
        'despues',
        'motivo',
    ];

    public function estadia(): BelongsTo
    {
        return $this->belongsTo(Estadia::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Salto de ComponenteScope a propósito, como CambioUsuarioHc: dice desde qué componente se cambió. */
    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class)->withoutGlobalScope(ComponenteScope::class);
    }
}
