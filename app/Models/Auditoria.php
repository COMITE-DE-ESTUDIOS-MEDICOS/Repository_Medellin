<?php

namespace App\Models;

use App\Enums\AccionAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $fillable = [
        'dependencia_id',
        'componente_id',
        'user_id',
        'accion',
        'auditable_type',
        'auditable_id',
        'descripcion',
        'datos',
        'ip',
        'agente',
    ];

    protected function casts(): array
    {
        return ['datos' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getEtiquetaAccionAttribute(): string
    {
        return AccionAuditoria::tryFrom($this->accion)?->etiqueta() ?? $this->accion;
    }
}
