<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DOC-26 (plan D9): países de la tabla «Pais» de SISPRO, de solo lectura y
 * sin dependencia (PA-13). nombre es el que se muestra (con las correcciones
 * de P-15); nombre_oficial, el de SISPRO tal cual.
 */
class Pais extends Model
{
    public $timestamps = false;

    protected $table = 'catalogo_paises';

    protected $fillable = ['codigo', 'nombre', 'nombre_oficial', 'habilitado'];

    protected function casts(): array
    {
        return ['habilitado' => 'boolean'];
    }

    /** Solo al guardar por el modelo: una inserción por tandas tiene que calcularlo ella misma. */
    protected static function booted(): void
    {
        static::saving(function (self $pais) {
            $pais->nombre_busqueda = UsuarioHc::normalizarTexto($pais->nombre) ?: null;
        });
    }
}
