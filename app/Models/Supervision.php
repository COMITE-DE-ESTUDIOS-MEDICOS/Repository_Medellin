<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una visita de supervisión territorial (DOC-11): el Apoyo al control
 * territorial califica a una dupla de dinamizadores en un punto crítico.
 * No se edita ni se borra: es el registro de lo que se vio ese día.
 */
#[ScopedBy([DependenciaScope::class])]
class Supervision extends Model
{
    protected $table = 'supervisiones';

    /** Los nueve criterios del Forms, en su orden: columna => pregunta. */
    public const CRITERIOS = [
        'c_horario' => 'Cumplimiento horario',
        'c_presencia' => 'Presencia en la zona asignada (ubicación y cumplimiento de la ruta)',
        'c_territorio' => 'Conocimiento del territorio (puntos críticos)',
        'c_aplicativos' => 'Uso adecuado de aplicativos y registros digitales',
        'c_protocolos' => 'Cumplimiento de protocolos, intervenciones y derivaciones',
        'c_epp' => 'Utiliza los EPP',
        'c_comunidad' => 'Manejo de relaciones con la comunidad y actores locales',
        'c_mediacion' => 'Capacidad para mediar, orientar y tranquilizar situaciones',
        'c_presentacion' => 'Presentación personal',
    ];

    public const INCIDENCIAS = [
        'ninguna' => 'Ninguna',
        'leve' => 'Leve',
        'relevante' => 'Relevante',
        'critica' => 'Crítica',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visita_at' => 'datetime',
            'inicio_at' => 'datetime',
            'casos_escalados' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /** Promedio de los nueve criterios, con un decimal. */
    public function promedio(): float
    {
        return round(collect(array_keys(self::CRITERIOS))->avg(fn ($c) => (int) $this->{$c}), 1);
    }

    public function puntoNombre(): string
    {
        return $this->puntoCritico?->nombre ?? ($this->punto_critico_otro ? 'Otro: '.$this->punto_critico_otro : '—');
    }

    public function mapa(): ?string
    {
        return $this->lat !== null ? "https://www.google.com/maps?q={$this->lat},{$this->lng}" : null;
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'supervisor_id');
    }

    public function dinamizador1(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'dinamizador1_id');
    }

    public function dinamizador2(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'dinamizador2_id');
    }

    public function puntoCritico(): BelongsTo
    {
        return $this->belongsTo(PuntoCritico::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function tipoTurno(): BelongsTo
    {
        return $this->belongsTo(TipoTurno::class);
    }

    public function evidencia1(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia1_documento_id');
    }

    public function evidencia2(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia2_documento_id');
    }
}
