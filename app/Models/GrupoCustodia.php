<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Las personas que custodian unas carpetas (por ejemplo, «Líderes Nodo 6»).
 * Solo ellas —y Coordinación y Administración— ven y trabajan esas carpetas.
 * Lo que cada miembro puede hacer dentro lo sigue decidiendo su rol: un
 * miembro con Lectura revisa sin modificar.
 */
#[ScopedBy([DependenciaScope::class])]
class GrupoCustodia extends Model
{
    protected $table = 'grupos_custodia';

    protected $fillable = ['dependencia_id', 'nombre', 'descripcion'];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grupo_custodia_usuario')->withTimestamps();
    }

    public function carpetas(): HasMany
    {
        return $this->hasMany(Carpeta::class);
    }

    public function tieneA(User $usuario): bool
    {
        return $this->usuarios()->whereKey($usuario->id)->exists();
    }
}
