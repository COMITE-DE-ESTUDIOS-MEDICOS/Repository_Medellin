<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un comentario sobre un archivo. Lo ve quien puede ver el archivo. */
class ComentarioDocumento extends Model
{
    protected $table = 'comentarios_documento';

    protected $fillable = ['documento_id', 'user_id', 'texto'];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
