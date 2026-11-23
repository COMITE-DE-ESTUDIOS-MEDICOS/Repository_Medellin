<?php

namespace App\Models\Scopes;

use App\Services\ContextoDependencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * DOC-25: cada usuario ve solo los datos de su componente, como DependenciaScope
 * con la dependencia. Sin contexto establecido (consola, colas, vía pública,
 * seeders) no filtra; establecido sin componente no deja ver nada (CA-15).
 *
 * Quien se lo salte a propósito usa withoutGlobalScope(ComponenteScope::class),
 * nunca withoutGlobalScopes(), para no perder también el de dependencia.
 */
class ComponenteScope implements Scope
{
    public function __construct(protected string $columna = 'componente_id') {}

    public function apply(Builder $builder, Model $model): void
    {
        $contexto = app(ContextoDependencia::class);

        if (! $contexto->filtraComponente()) {
            return;
        }

        $id = $contexto->componenteId();

        // Calificada con la tabla: las consultas con juntas fallan en MySQL si no.
        $id === null
            ? $builder->whereRaw('1 = 0')
            : $builder->where($model->getTable().'.'.$this->columna, $id);
    }
}
