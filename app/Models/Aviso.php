<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso enviado por la campana. Sin DependenciaScope: los de versión
 * van a todas las dependencias, y el historial se filtra a mano.
 */
class Aviso extends Model
{
    protected $table = 'avisos';

    protected $fillable = ['dependencia_id', 'titulo', 'mensaje', 'version', 'roles', 'destinatarios', 'enviado_por'];

    protected function casts(): array
    {
        return ['roles' => 'array', 'destinatarios' => 'integer'];
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
