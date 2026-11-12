<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Derivaciones y respuesta territorial.
 *
 * DOC-20 (con numero): la oficina crea el caso DC-NNN, el tiempo corre desde
 * entonces (fecha/hora_requerimiento = hora del servidor) y se detiene cuando
 * la persona de campo envía la atención (atendida_at). pendiente = Abierto,
 * solucionada = Atendido.
 *
 * Registro anterior (numero = null): la persona de campo lo escalaba y un
 * líder lo respondía en Documenta.
 */
#[ScopedBy([DependenciaScope::class])]
class Derivacion extends Model
{
    use SoftDeletes;

    protected $table = 'derivaciones';

    /** Pasado este tiempo de respuesta hay que justificar. */
    public const META_MINUTOS = 180;

    public const PENDIENTE = 'pendiente';

    public const SOLUCIONADA = 'solucionada';

    /** DOC-20: quién envía el caso. «otra» se acompaña del texto en entidad. */
    public const REMITENTES = [
        'alcaldia' => 'Alcaldía',
        'autoridades' => 'Autoridades locales del sector',
        'otra' => 'Otra',
    ];

    /** Minutos en que dos casos con la misma ubicación y remitente se avisan como posible duplicado. */
    public const VENTANA_DUPLICADO_MIN = 30;

    protected $fillable = [
        'dependencia_id', 'componente_id', 'enlace_turno_id', 'colaborador_id',
        'cedula', 'nombre', 'cargo', 'nodo_id',
        'fecha_requerimiento', 'hora_requerimiento', 'entidad', 'descripcion',
        'ubicacion', 'estado', 'respuesta', 'hora_respuesta', 'tiempo_respuesta_min', 'justificacion',
        'respondida_at', 'respondida_por', 'ultimo_aviso_at',
        'numero', 'remitente', 'creado_por', 'clave_formulario', 'atendida_at', 'cumplio_requerimiento', 'observacion',
        'soporte_documento_id', 'evidencia1_documento_id', 'evidencia2_documento_id', 'evidencia3_documento_id',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_requerimiento' => 'date', 'respondida_at' => 'datetime', 'ultimo_aviso_at' => 'datetime',
            'atendida_at' => 'datetime', 'cumplio_requerimiento' => 'boolean', 'numero' => 'integer',
        ];
    }

    /**
     * El requerimiento: lo que llena la persona de campo y lo que se corrige
     * en administración. La respuesta la da el líder (reglasRespuesta()).
     */
    public static function reglas(int $componenteId): array
    {
        return [
            'nodo_id' => ['required', Rule::exists('nodos', 'id')->where('componente_id', $componenteId)->where('activo', true)],
            'fecha_requerimiento' => ['required', 'date', 'before_or_equal:today'],
            'hora_requerimiento' => ['required', 'date_format:H:i'],
            'entidad' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'ubicacion' => ['required', 'string', 'max:500'],
        ];
    }

    /** Corregir la hora de una ya solucionada. */
    public const REGLAS_HORA_RESPUESTA = [
        'hora_respuesta' => ['required', 'date_format:H:i'],
        'justificacion' => ['nullable', 'string', 'max:5000'],
    ];

    public const ATRIBUTOS = [
        'nodo_id' => 'nodo que atiende',
        'fecha_requerimiento' => 'fecha del requerimiento',
        'hora_requerimiento' => 'hora del requerimiento',
        'entidad' => 'entidad que solicita',
        'descripcion' => 'descripción del caso',
        'ubicacion' => 'ubicación del requerimiento',
        'hora_respuesta' => 'hora de respuesta',
        'respuesta' => 'respuesta',
        'justificacion' => 'justificación',
    ];

    /**
     * El tiempo de respuesta de unos datos ya validados. Si pasa la meta y
     * no viene justificación, no se guarda.
     */
    public static function tiempoValidado(array $datos): int
    {
        $minutos = self::minutosEntre($datos['hora_requerimiento'], $datos['hora_respuesta']);

        if ($minutos > self::META_MINUTOS && blank($datos['justificacion'] ?? null)) {
            throw ValidationException::withMessages([
                'justificacion' => 'La respuesta tardó más de '.self::META_MINUTOS.' minutos: escribe la justificación.',
            ]);
        }

        return $minutos;
    }

    /**
     * Minutos entre el requerimiento y la respuesta. Una respuesta con hora
     * menor que la del requerimiento es del día siguiente (pasó la medianoche).
     */
    public static function minutosEntre(string $requerimiento, string $respuesta): int
    {
        $desde = Carbon::createFromFormat('H:i', substr($requerimiento, 0, 5));
        $hasta = Carbon::createFromFormat('H:i', substr($respuesta, 0, 5));

        if ($hasta->lt($desde)) {
            $hasta->addDay();
        }

        return (int) $desde->diffInMinutes($hasta);
    }

    /** DOC-20: un caso creado por la oficina; si no, es un registro anterior. */
    public function esCaso(): bool
    {
        return $this->numero !== null;
    }

    /** «DC-007», «DC-1250»: al menos 3 dígitos, nunca se reinicia. */
    public function codigo(): string
    {
        return $this->esCaso() ? self::codigoDe($this->numero) : '#'.$this->id;
    }

    public static function codigoDe(int $numero): string
    {
        return 'DC-'.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }

    /** Lo que se lee en pantalla y en el Excel. */
    public function estadoLegible(): string
    {
        return match (true) {
            ! $this->esCaso() => $this->estaPendiente() ? 'Pendiente' : 'Solucionada',
            $this->estaPendiente() => $this->cumplio() ? 'Abierto' : 'Abierto · vencido',
            default => 'Atendido',
        };
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    /** Cuándo se pidió: desde aquí corre el tiempo. */
    public function requeridaAt(): Carbon
    {
        return Carbon::parse($this->fecha_requerimiento->toDateString().' '.substr($this->hora_requerimiento, 0, 5));
    }

    /** Pendiente: lo que lleva hasta ahora. Solucionada: lo que tardó. */
    public function minutos(): int
    {
        return $this->estaPendiente()
            ? max(0, (int) $this->requeridaAt()->diffInMinutes(now()))
            : (int) $this->tiempo_respuesta_min;
    }

    public function cumplio(): bool
    {
        return $this->minutos() <= self::META_MINUTOS;
    }

    /** «1 h 25 min», como se lee en el reporte. */
    public function tiempoLegible(): string
    {
        return self::duracion($this->minutos());
    }

    public static function duracion(int $minutos): string
    {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return $h ? "{$h} h {$m} min" : "{$m} min";
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function respondedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondida_por');
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(Componente::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
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

    /** @return array<string, ?Documento> etiqueta => documento, en orden de columnas */
    public function archivos(): array
    {
        return [
            'Soporte' => $this->soporte,
            'Evidencia 1' => $this->evidencia1,
            'Evidencia 2' => $this->evidencia2,
            'Evidencia 3' => $this->evidencia3,
        ];
    }
}
