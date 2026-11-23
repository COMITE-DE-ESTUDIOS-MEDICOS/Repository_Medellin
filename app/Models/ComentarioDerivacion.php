<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DOC-23: un comentario en un caso. Lo ve quien puede ver el caso; nunca la vía pública. */
class ComentarioDerivacion extends Model
{
    protected $table = 'comentarios_derivacion';

    protected $fillable = ['derivacion_id', 'user_id', 'texto'];

    public function derivacion(): BelongsTo
    {
        return $this->belongsTo(Derivacion::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
