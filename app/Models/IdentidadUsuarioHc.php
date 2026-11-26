<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOC-26 (plan D5): un nombre, documento o ficha que la persona tuvo antes.
 * Sigue siendo buscable y lleva a la ficha vigente (CA-04). Sin scope: se lee
 * siempre por su ficha, después de la Policy.
 */
class IdentidadUsuarioHc extends Model
{
    public const NOMBRE = 'nombre';

    public const DOCUMENTO = 'documento';

    public const FICHA = 'ficha';

    public const UPDATED_AT = null;

    protected $table = 'usuario_hc_identidades';

    protected $fillable = ['usuario_hc_id', 'campo', 'valor', 'user_id', 'componente_id'];

    /** El nombre se busca por palabras; documento y ficha, como código sin signos (D5). */
    protected static function booted(): void
    {
        static::saving(function (self $identidad) {
            $normal = $identidad->campo === self::NOMBRE
                ? UsuarioHc::normalizarTexto($identidad->valor)
                : UsuarioHc::normalizarCodigo($identidad->valor);

            $identidad->valor_busqueda = $normal === '' ? null : $normal;
        });
    }

    public function usuarioHc(): BelongsTo
    {
        return $this->belongsTo(UsuarioHc::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
