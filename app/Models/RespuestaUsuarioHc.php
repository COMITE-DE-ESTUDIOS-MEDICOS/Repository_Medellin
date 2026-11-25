<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOC-26 (§7, PA-28): la respuesta vigente de una lista, una fila por opción
 * elegida. Guarda el código (para contar aunque cambie de nombre) y el texto
 * que vio quien la registró (para mostrarla aunque se renombre o se retire).
 * La anterior queda completa en el historial (usuario_hc_cambios).
 */
class RespuestaUsuarioHc extends Model
{
    protected $table = 'usuario_hc_respuestas';

    protected $fillable = ['usuario_hc_id', 'campo', 'fuente', 'opcion', 'texto', 'principal'];

    protected function casts(): array
    {
        return ['principal' => 'boolean'];
    }

    public function usuarioHc(): BelongsTo
    {
        return $this->belongsTo(UsuarioHc::class);
    }
}
