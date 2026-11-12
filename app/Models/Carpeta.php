<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[ScopedBy([DependenciaScope::class])]
class Carpeta extends Model
{
    use HasFactory;

    protected $table = 'carpetas';

    protected $fillable = [
        'dependencia_id',
        'carpeta_id',
        'grupo_custodia_id',
        'nombre',
        'descripcion',
        'activa',
        'creado_por',
    ];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $carpeta) {
            $carpeta->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carpeta_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'carpeta_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function lideres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'carpeta_lider')->withTimestamps();
    }

    public function esLideradaPor(User $usuario): bool
    {
        return $this->lideres()->whereKey($usuario->id)->exists();
    }

    /**
     * Ids de las carpetas retiradas de la vista: las inactivas y todo lo que
     * cuelga de ellas. Sin heredar, una subcarpeta activa dentro de una
     * inactiva —y sus documentos— seguían saliendo en la búsqueda.
     *
     * ponytail: lee todas las carpetas de la dependencia en cada llamada;
     * si llegan a ser miles, memorizarlo por petición.
     *
     * @return list<int>
     */
    public static function idsRetiradas(?int $dependenciaId): array
    {
        $hijas = [];
        $retiradas = [];

        foreach (self::withoutGlobalScopes()->where('dependencia_id', $dependenciaId)->get(['id', 'carpeta_id', 'activa']) as $c) {
            $hijas[$c->carpeta_id][] = $c->id;

            if (! $c->activa) {
                $retiradas[] = $c->id;
            }
        }

        for ($i = 0; $i < count($retiradas); $i++) {
            array_push($retiradas, ...($hijas[$retiradas[$i]] ?? []));
        }

        return array_values(array_unique($retiradas));
    }

    /**
     * Las carpetas a las que se puede copiar o mover, con su ruta completa
     * («Evidencias / Octubre»): dos «Octubre» en carpetas distintas se
     * distinguen. Una sola consulta, sin una por carpeta.
     *
     * @return array<int, string> id => ruta, en orden alfabético
     */
    public static function destinosLegibles(): array
    {
        $todas = self::query()->get(['id', 'carpeta_id', 'nombre', 'activa', 'dependencia_id'])->keyBy('id');
        $dependenciaId = app(\App\Services\ContextoDependencia::class)->id();
        $retiradas = array_flip([
            ...self::idsRetiradas($dependenciaId),
            ...self::idsVedadasPara(auth()->user(), $dependenciaId),
        ]);
        $rutas = [];

        foreach ($todas as $carpeta) {
            if (isset($retiradas[$carpeta->id])) {
                continue;
            }

            $partes = [];
            $actual = $carpeta;

            for ($saltos = 0; $actual && $saltos < 50; $saltos++) {
                array_unshift($partes, $actual->nombre);
                $actual = $todas->get($actual->carpeta_id);
            }

            $rutas[$carpeta->id] = implode(' / ', $partes);
        }

        asort($rutas, SORT_NATURAL | SORT_FLAG_CASE);

        return $rutas;
    }

    public function estaRetirada(): bool
    {
        return in_array($this->id, self::idsRetiradas($this->dependencia_id), true);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopeRaiz(Builder $query): Builder
    {
        return $query->whereNull('carpeta_id');
    }

    public function scopeVisiblesPara(Builder $query, ?User $usuario): Builder
    {
        $dependenciaId = app(\App\Services\ContextoDependencia::class)->id();

        if ($usuario?->puedeGestionarEn($dependenciaId)) {
            return $query;
        }

        return $query->where('activa', true)->whereNotIn('id', [
            ...self::idsRetiradas($dependenciaId),
            ...self::idsVedadasPara($usuario, $dependenciaId),
        ]);
    }

    /**
     * El grupo que custodia cada carpeta: el suyo o, si no tiene, el de la
     * carpeta más cercana hacia arriba. Lo que cuelga de una carpeta
     * custodiada queda custodiado sin marcarlo una por una.
     *
     * @return array<int, int> id de carpeta => id del grupo que la custodia
     */
    public static function custodiaEfectiva(?int $dependenciaId): array
    {
        $todas = self::withoutGlobalScopes()->where('dependencia_id', $dependenciaId)
            ->get(['id', 'carpeta_id', 'grupo_custodia_id'])->keyBy('id');
        $custodia = [];

        foreach ($todas as $carpeta) {
            $actual = $carpeta;

            for ($saltos = 0; $actual && $saltos < 50; $saltos++) {
                if ($actual->grupo_custodia_id) {
                    $custodia[$carpeta->id] = $actual->grupo_custodia_id;
                    break;
                }

                $actual = $todas->get($actual->carpeta_id);
            }
        }

        return $custodia;
    }

    /**
     * Las carpetas custodiadas por grupos a los que esa persona no pertenece:
     * no las ve, no las toca y no puede meter nada en ellas. Coordinación y
     * Administración no tienen vedada ninguna.
     *
     * @return list<int>
     */
    public static function idsVedadasPara(?User $usuario, ?int $dependenciaId): array
    {
        if ($usuario?->puedeGestionarEn($dependenciaId)) {
            return [];
        }

        $custodia = self::custodiaEfectiva($dependenciaId);

        if ($custodia === []) {
            return [];
        }

        $suyos = $usuario
            ? \Illuminate\Support\Facades\DB::table('grupo_custodia_usuario')->where('user_id', $usuario->id)->pluck('grupo_custodia_id')->all()
            : [];

        return array_keys(array_filter($custodia, fn (int $grupo) => ! in_array($grupo, $suyos)));
    }

    /** El grupo que custodia esta carpeta (el suyo o el heredado), o null. */
    public function custodiaDe(): ?GrupoCustodia
    {
        $grupoId = self::custodiaEfectiva($this->dependencia_id)[$this->id] ?? null;

        return $grupoId ? GrupoCustodia::withoutGlobalScopes()->find($grupoId) : null;
    }

    public function grupoCustodia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GrupoCustodia::class);
    }

    /**
     * Migas de pan desde la raíz hasta esta carpeta.
     *
     * @return Collection<int, self>
     */
    public function ruta(): Collection
    {
        $ruta = collect();
        $actual = $this;
        $saltos = 0;

        while ($actual !== null && $saltos < 50) {
            $ruta->prepend($actual);
            $actual = $actual->padre;
            $saltos++;
        }

        return $ruta;
    }

    /** La ruta como se lee: «Calle / Nodo 2 / Octubre». */
    public function rutaTexto(): string
    {
        return $this->ruta()->pluck('nombre')->join(' / ');
    }

    /** Evita mover una carpeta dentro de sí misma o de una descendiente. */
    public function esAncestroDe(self $posibleDescendiente): bool
    {
        $actual = $posibleDescendiente->padre;
        $saltos = 0;

        while ($actual !== null && $saltos < 50) {
            if ($actual->id === $this->id) {
                return true;
            }

            $actual = $actual->padre;
            $saltos++;
        }

        return false;
    }
}
