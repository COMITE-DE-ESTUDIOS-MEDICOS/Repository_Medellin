<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOC-26 (plan D10): una foto de la persona, siempre JPEG, fuera de la web.
 * Cambiarla crea otra fila; ninguna fila ni archivo se borra (CA-22).
 */
class FotoUsuarioHc extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'usuario_hc_fotos';

    protected $fillable = ['usuario_hc_id', 'ruta', 'tamano', 'subida_por'];

    protected function casts(): array
    {
        return ['tamano' => 'integer'];
    }

    public function usuarioHc(): BelongsTo
    {
        return $this->belongsTo(UsuarioHc::class);
    }

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por');
    }
}
