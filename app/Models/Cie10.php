<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DOC-26 (plan D9): catálogo CIE-10 de SISPRO, de solo lectura y sin
 * dependencia (PA-13). Lo llena una vez el comando de carga.
 */
class Cie10 extends Model
{
    public $timestamps = false;

    protected $table = 'catalogo_cie10';

    protected $fillable = ['codigo', 'nombre', 'habilitado'];

    protected function casts(): array
    {
        return ['habilitado' => 'boolean'];
    }

    /** Solo al guardar por el modelo: una inserción por tandas tiene que calcularlo ella misma. */
    protected static function booted(): void
    {
        static::saving(function (self $codigo) {
            $codigo->nombre_busqueda = UsuarioHc::normalizarTexto($codigo->nombre) ?: null;
        });
    }
}
