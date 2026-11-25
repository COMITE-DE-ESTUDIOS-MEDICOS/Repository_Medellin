<?php

namespace App\Models;

use App\Models\Scopes\ComponenteScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOC-26 (plan D7): una fila del historial de la ficha, por campo cambiado o
 * por opción que sale o entra. Es el registro de verdad (PA-28): aquí están
 * los valores, que la auditoría general no lleva (CA-30).
 */
class CambioUsuarioHc extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'usuario_hc_cambios';

    protected $fillable = [
        'usuario_hc_id',
        'user_id',
        'componente_id',
        'seccion',
        'campo',
        'codigo_antes',
        'codigo_despues',
        'antes',
        'despues',
        'motivo',
    ];

    public function usuarioHc(): BelongsTo
    {
        return $this->belongsTo(UsuarioHc::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Salto de ComponenteScope a propósito (plan D1): el historial dice desde qué componente se cambió, sea cual sea. */
    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class)->withoutGlobalScope(ComponenteScope::class);
    }
}
