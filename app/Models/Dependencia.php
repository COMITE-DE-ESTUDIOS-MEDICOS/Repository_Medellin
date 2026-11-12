<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dependencia extends Model
{
    use HasFactory;

    protected $table = 'dependencias';

    protected $fillable = ['nombre', 'slug', 'descripcion', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dependencia_usuario')
            ->withPivot('rol', 'aprueba_novedades')
            ->withTimestamps();
    }

    public function carpetas(): HasMany
    {
        return $this->hasMany(Carpeta::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function tiposDocumento(): HasMany
    {
        return $this->hasMany(TipoDocumento::class);
    }

    public function etiquetas(): HasMany
    {
        return $this->hasMany(Etiqueta::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
