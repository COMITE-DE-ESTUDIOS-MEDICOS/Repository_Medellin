<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una zona a la que se asigna personal en la malla («Parque de Berrío – Plaza Botero»). */
class PuntoCritico extends Model
{
    protected $table = 'puntos_criticos';

    protected $fillable = ['componente_id', 'nodo_id', 'nombre', 'lat', 'lng', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'lat' => 'float', 'lng' => 'float'];
    }

    /** El mismo enlace a OpenStreetMap que usan las marcaciones. */
    public function urlMapa(): ?string
    {
        return $this->lat !== null && $this->lng !== null
            ? "https://www.openstreetmap.org/?mlat={$this->lat}&mlon={$this->lng}#map=17/{$this->lat}/{$this->lng}"
            : null;
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
