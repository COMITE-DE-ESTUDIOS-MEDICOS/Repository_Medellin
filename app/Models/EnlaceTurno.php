<?php

namespace App\Models;

use App\Models\Concerns\TieneTokenSecreto;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * Enlace compartido (QR) de un componente o de un nodo. No lleva identidad:
 * quien lo abre dice quién es con su cédula. A diferencia del enlace de
 * carga no tiene usos: se usa todos los días, dos veces por persona.
 */
#[ScopedBy([DependenciaScope::class])]
class EnlaceTurno extends Model
{
    use HasFactory;
    use TieneTokenSecreto;

    protected $table = 'enlaces_turno';

    /** Para qué sirve el enlace. Mismo token, QR y cédula; cambia el formulario. */
    public const TIPOS = [
        'turno' => 'Turnos (entrada y salida)',
        'derivacion' => 'Derivaciones y respuesta territorial',
        'supervision' => 'Supervisión territorial',
    ];

    protected $fillable = [
        'token_hash',
        'token_cifrado',
        'dependencia_id',
        'tipo',
        'componente_id',
        'nodo_id',
        'punto_critico_id',
        'nombre',
        'activo',
        'expira_at',
        'creado_por',
    ];

    protected $hidden = ['token_hash', 'token_cifrado'];

    protected function casts(): array
    {
        return [
            'token_cifrado' => 'encrypted',
            'activo' => 'boolean',
            'expira_at' => 'datetime',
        ];
    }

    /**
     * La ruta 'turno.enlace' la define la vía pública (routes/turnos-publico.php)
     * en /t/{token}. Mientras no exista se arma esa misma URL a mano: así los
     * QR que se impriman antes siguen sirviendo cuando la pantalla llegue.
     */
    public function url(): string
    {
        if ($this->esDerivacion()) {
            return route('derivacion.enlace', ['token' => $this->token()]);
        }

        if ($this->esSupervision()) {
            return route('supervision.enlace', ['token' => $this->token()]);
        }

        return Route::has('turno.enlace')
            ? route('turno.enlace', ['token' => $this->token()])
            : url('t/'.$this->token());
    }

    public function esDerivacion(): bool
    {
        return $this->tipo === 'derivacion';
    }

    public function esSupervision(): bool
    {
        return $this->tipo === 'supervision';
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function haExpirado(): bool
    {
        return $this->expira_at !== null && $this->expira_at->isPast();
    }

    public function estaVigente(): bool
    {
        return $this->activo && ! $this->haExpirado();
    }

    public function revocar(): void
    {
        $this->update(['activo' => false]);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('activo', true)
            ->where(fn (Builder $q) => $q->whereNull('expira_at')->orWhere('expira_at', '>', now()));
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    /** El QR pegado en un punto crítico: quien marca con él, queda en ese punto ese día. */
    public function puntoCritico(): BelongsTo
    {
        return $this->belongsTo(PuntoCritico::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    public function derivaciones(): HasMany
    {
        return $this->hasMany(Derivacion::class);
    }

    public function supervisiones(): HasMany
    {
        return $this->hasMany(Supervision::class);
    }
}
