<?php

namespace App\Models;

use App\Enums\OrigenColaborador;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Persona de campo: marca turno y envía evidencias por enlaces públicos, sin
 * cuenta en Documenta. La cédula es su llave y sus datos se llenan una vez.
 */
#[ScopedBy([DependenciaScope::class])]
class Colaborador extends Model
{
    use HasFactory;

    protected $table = 'colaboradores';

    protected $fillable = [
        'dependencia_id',
        'documento',
        'nombre',
        'correo',
        'telefono',
        'entidad',
        'cargo',
        'componente_id',
        'nodo_id',
        'tipo_turno_id',
        'punto_critico_id',
        'dias_descanso',
        'origen',
        'verificado_at',
        'verificado_por',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'origen' => OrigenColaborador::class,
            'verificado_at' => 'datetime',
            'activo' => 'boolean',
            'dias_descanso' => 'array',
        ];
    }

    /** Se guarda siempre normalizada, la escriba quien la escriba. */
    protected function documento(): Attribute
    {
        return Attribute::set(fn (?string $valor) => User::normalizarDocumento($valor));
    }

    /**
     * La busca por cédula, con o sin puntos. Aparta el scope porque la vía
     * pública no tiene dependencia en contexto: la dependencia la da el enlace.
     */
    public static function porDocumento(int $dependenciaId, string $cc): ?self
    {
        return static::withoutGlobalScope(DependenciaScope::class)
            ->where('dependencia_id', $dependenciaId)
            ->where('documento', User::normalizarDocumento($cc))
            ->first();
    }

    /**
     * Lo único que la vía pública puede mostrar de una persona: el enlace es
     * compartido, y cualquiera puede escribir una cédula ajena. Nunca el
     * nombre completo, el correo ni el teléfono.
     */
    public function saludo(): string
    {
        $primerNombre = Str::before(trim($this->nombre), ' ');

        return mb_strlen($primerNombre) > 3 ? mb_substr($primerNombre, 0, 3).'…' : $primerNombre;
    }

    public function estaVerificado(): bool
    {
        return $this->verificado_at !== null;
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

    /** Su turno de siempre (N1, T3…): con él se sabe a qué hora se le espera. */
    public function tipoTurno(): BelongsTo
    {
        return $this->belongsTo(TipoTurno::class);
    }

    /** Su punto crítico de siempre. Si marca con el QR de otro punto, vale ese ese día. */
    public function puntoCritico(): BelongsTo
    {
        return $this->belongsTo(PuntoCritico::class);
    }

    public function ausencias(): HasMany
    {
        return $this->hasMany(Ausencia::class);
    }

    public function verificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
