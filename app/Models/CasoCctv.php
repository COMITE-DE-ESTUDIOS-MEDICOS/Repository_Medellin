<?php

namespace App\Models;

use App\Models\Concerns\DelComponente;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Seguridad CCTV (DOC-33): un caso que la oficina pasa a una persona de campo.
 * El tiempo corre desde requerido_at y se fija al atender. Nada se borra: se anula.
 */
#[ScopedBy([DependenciaScope::class])]
class CasoCctv extends Model
{
    use DelComponente;
    use HasFactory;

    protected $table = 'casos_cctv';

    public const ABIERTO = 'abierto';

    public const ATENDIDO = 'atendido';

    public const CERRADO = 'cerrado';

    public const ANULADO = 'anulado';

    public const ENTIDADES = [
        'camaras' => 'Cámaras',
        'telegram_123' => 'Telegram 123',
        'policia' => 'Policía',
        'espacio_publico' => 'Espacio público',
        'otras' => 'Otras',
    ];

    public const GRUPO_CUSTODIA = 'Responsables de Seguridad CCTV';

    protected $fillable = [
        'dependencia_id', 'componente_id', 'numero', 'clave_formulario', 'estado', 'entidad', 'entidad_otra',
        'requerido_at', 'colaborador_asignado_id', 'nodo_id', 'descripcion', 'ubicacion', 'comuna',
        'soporte_documento_id', 'evidencia1_documento_id', 'evidencia2_documento_id', 'evidencia3_documento_id',
        'creado_por', 'actualizado_por', 'colaborador_id', 'cedula', 'nombre', 'cargo', 'enlace_turno_id',
        'atendido_at', 'tiempo_respuesta_min', 'justificacion', 'cerrado_por', 'cerrado_at',
        'anulado_por', 'anulado_at', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'comuna' => 'integer',
            'tiempo_respuesta_min' => 'integer',
            'requerido_at' => 'datetime',
            'atendido_at' => 'datetime',
            'cerrado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    /** «CCTV-007»: al menos 3 dígitos, nunca se reinicia. */
    public static function codigoDe(int $numero): string
    {
        return 'CCTV-'.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }

    public function codigo(): string
    {
        return self::codigoDe($this->numero);
    }

    public function entidadLegible(): string
    {
        $nombre = self::ENTIDADES[$this->entidad] ?? $this->entidad;

        return $this->entidad === 'otras' && filled($this->entidad_otra) ? "{$nombre}: {$this->entidad_otra}" : $nombre;
    }

    public function nombreComuna(): string
    {
        $nombre = config('repositorio.comunas')[$this->comuna] ?? null;

        return $nombre === null ? '—' : "{$this->comuna} · {$nombre}";
    }

    /** Requerimiento del primer instante de «desde» al último de «hasta» (ambos días incluidos). */
    public function scopeRequeridoEntre(Builder $query, ?string $desde, ?string $hasta): Builder
    {
        return $query
            ->when($desde, fn (Builder $q, string $d): Builder => $q->where('requerido_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($hasta, fn (Builder $q, string $h): Builder => $q->where('requerido_at', '<=', Carbon::parse($h)->endOfDay()));
    }

    /** Minutos enteros entre el requerimiento y la atención (nunca negativos). */
    public static function minutosEntre(Carbon $requerido, Carbon $atendido): int
    {
        return max(0, (int) $requerido->diffInMinutes($atendido));
    }

    /** Abierto: lo que lleva hasta ahora. Atendido en adelante: lo que tardó. */
    public function minutos(): int
    {
        return $this->estado === self::ABIERTO
            ? self::minutosEntre($this->requerido_at, now())
            : (int) $this->tiempo_respuesta_min;
    }

    /** «1 h 25 min». */
    public function tiempoLegible(): string
    {
        return Derivacion::duracion($this->minutos());
    }

    /**
     * La carpeta «Seguridad CCTV · <componente>» bajo el grupo de custodia de los
     * responsables (D5): ahí caen las imágenes y solo ellos y Coordinación la ven.
     */
    public static function carpetaPara(Componente $componente): Carpeta
    {
        $grupo = GrupoCustodia::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $componente->dependencia_id,
            'nombre' => self::GRUPO_CUSTODIA,
        ]);

        return Carpeta::withoutGlobalScopes()->firstOrCreate(
            ['dependencia_id' => $componente->dependencia_id, 'carpeta_id' => null, 'nombre' => 'Seguridad CCTV · '.$componente->nombre],
            ['grupo_custodia_id' => $grupo->id],
        );
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    /** A quién se le pasó la novedad. */
    public function asignado(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_asignado_id');
    }

    /** Quién la atendió. */
    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function enlaceTurno(): BelongsTo
    {
        return $this->belongsTo(EnlaceTurno::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function soporte(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'soporte_documento_id');
    }

    public function evidencia1(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia1_documento_id');
    }

    public function evidencia2(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia2_documento_id');
    }

    public function evidencia3(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidencia3_documento_id');
    }

    /** @return array<string, ?Documento> campo => documento (el campo es el de la ruta `archivo`) */
    public function archivos(): array
    {
        return [
            'soporte' => $this->soporte,
            'evidencia1' => $this->evidencia1,
            'evidencia2' => $this->evidencia2,
            'evidencia3' => $this->evidencia3,
        ];
    }
}
