<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DOC-27 (plan D1): una opción elegida en una lista de varias de la estadía, con su código y el texto que se vio. */
class RespuestaEstadia extends Model
{
    protected $table = 'estadia_respuestas';

    protected $fillable = ['estadia_id', 'campo', 'fuente', 'opcion', 'texto'];

    public function estadia(): BelongsTo
    {
        return $this->belongsTo(Estadia::class);
    }
}
