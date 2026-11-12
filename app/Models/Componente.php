<?php

namespace App\Models;

use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Calle, Básica, Estabilización… Una fila, no una rama del código: lo que
 * distingue a un componente de otro se pregunta con regla(), nunca con
 * if ($componente->slug === 'calle').
 */
#[ScopedBy([DependenciaScope::class])]
class Componente extends Model
{
    use HasFactory;

    protected $table = 'componentes';

    /**
     * Las reglas que puede ajustar cada componente, con su valor por defecto.
     * Una clave nueva se agrega aquí y ya vale para todos.
     */
    public const REGLAS = [
        'foto_obligatoria' => true,
        'ubicacion_obligatoria' => false,
        'tolerancia_min' => 15,
        // DOC-8/DOC-21: salir hasta tanto después del fin del turno no es hora extra,
        // y es el margen con que se cierra un turno sin salida (40 a 60).
        'tolerancia_extra_min' => 60,
        // Lo que debería durar un turno: más o menos que esto (con la
        // tolerancia) es una novedad de horas extra o de turno corto.
        'jornada_horas' => 8,
        // Descanso no remunerado, por tramos de permanencia (Talento Humano):
        //   hasta sin_descanso_hasta_h  → nada: una pausa corta (café,
        //                                  hidratación) se paga como trabajo;
        //   hasta descanso_completo_desde_h → descanso_parcial_min (desayuno);
        //   más que eso                  → descanso_min (desayuno + almuerzo).
        // Se resta de la permanencia: lo que cuenta contra la jornada es el
        // trabajo real, no el tiempo en el sitio.
        'descanso_min' => 60,
        'descanso_parcial_min' => 30,
        'sin_descanso_hasta_h' => 4,
        'descanso_completo_desde_h' => 6,
        'horas_max_turno' => 14,
        // DOC-21: cargo => horas. Para esos cargos, el turno sin salida se
        // cierra a tantas horas de la entrada en vez de horas_max_turno.
        'horas_max_por_cargo' => [],
        // DOC-17: una salida a menos de esto de la entrada es un reintento
        // (la entrada llegó y el teléfono no se enteró), no una salida.
        'salida_minima_min' => 30,
        // Cuántos turnos puede abrir una persona en un mismo día. Cumplidos,
        // el enlace ya no la deja marcar: lo que falte lo corrige Coordinación.
        'turnos_por_dia' => 1,
        // Turno automático: una entrada se reconoce como el turno cuyo inicio
        // está más cerca, si cae entre tantos minutos antes y tantos después.
        'reconocer_antes_min' => 90,
        'reconocer_despues_min' => 180,
        // Cuánto después del inicio esperado, sin entrada, sale «no registró entrada».
        'aviso_sin_entrada_min' => 30,
        'autoregistro' => true,
        // Los cargos que se pueden elegir para sus personas de campo.
        'cargos' => [],
        // Los que registran supervisiones territoriales (DOC-11, RN-39).
        'cargos_supervision' => [],
        // DOC-15 (RN-46): una ubicación con más error que esto (metros) es
        // imprecisa: no se juzga fuera de zona y queda como novedad.
        'precision_minima_m' => 500,
    ];

    protected $fillable = ['dependencia_id', 'nombre', 'slug', 'activo', 'config'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'config' => 'array',
        ];
    }

    /** Valor de una regla de este componente, o su defecto de REGLAS. */
    public function regla(string $clave, mixed $defecto = null): mixed
    {
        return $this->config[$clave] ?? self::REGLAS[$clave] ?? $defecto;
    }

    /**
     * DOC-8: el trabajo que se espera de un turno. Con un tipo de turno con
     * horario, su horario menos el descanso que le toca (T4 de 4 h → 4 h;
     * N1 de 7 h 30 min → 7 h con 30 min de descanso). Sin él, la jornada
     * del componente. Lo usan las novedades y el reporte: un solo cálculo.
     */
    public function jornadaPara(?TipoTurno $tipo): int
    {
        $horario = $tipo?->minutosDeHorario();

        return $horario !== null
            ? $this->minutosTrabajados($horario, $tipo)
            : (int) round((float) $this->regla('jornada_horas') * 60);
    }

    /**
     * El trabajo real de un turno: la permanencia menos el descanso. Es lo
     * que se compara con la jornada (novedades) y lo que va al reporte: un
     * solo cálculo, para que los dos digan siempre lo mismo.
     */
    public function minutosTrabajados(int $permanencia, ?TipoTurno $tipo = null): int
    {
        return max(0, $permanencia - $this->descansoPara($permanencia, $tipo));
    }

    /**
     * Cuánto descanso no remunerado le toca (ver REGLAS).
     *
     * Edwar, 2026-11-10: el descanso va por el TURNO, no por lo que se quedó.
     * Con un turno con horario, el de su horario (T4 de 4 h → nada; N1 de
     * 7 h 30 min → 30 min), sin pasar del que daría el tiempo en el sitio: un
     * T4 que se queda 4 h 05 min no pierde 30 min, y un N1 que se va a las
     * 3 h no descuenta un descanso que no tomó. Sin turno, por el tiempo en
     * el sitio, como antes.
     */
    public function descansoPara(int $permanencia, ?TipoTurno $tipo = null): int
    {
        $horario = $tipo?->minutosDeHorario();

        return $horario === null
            ? $this->descansoPorTramo($permanencia)
            : min($this->descansoPorTramo($horario), $this->descansoPorTramo($permanencia));
    }

    /** El descanso de los tramos del componente para ese tiempo. */
    protected function descansoPorTramo(int $permanencia): int
    {
        return match (true) {
            $permanencia <= (int) $this->regla('sin_descanso_hasta_h') * 60 => 0,
            $permanencia <= (int) $this->regla('descanso_completo_desde_h') * 60 => (int) $this->regla('descanso_parcial_min'),
            default => (int) $this->regla('descanso_min'),
        };
    }

    /** @return list<string> los cargos de este componente, en el orden en que se escribieron */
    public function cargos(): array
    {
        return array_values((array) $this->regla('cargos', []));
    }

    /** ¿Ese cargo registra supervisiones? Sin importar mayúsculas ni espacios. */
    public function supervisaConCargo(?string $cargo): bool
    {
        $normal = fn ($c) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $c)));

        return $cargo !== null && in_array($normal($cargo), array_map($normal, (array) $this->regla('cargos_supervision', [])), true);
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function nodos(): HasMany
    {
        return $this->hasMany(Nodo::class)->orderBy('orden');
    }

    public function tiposTurno(): HasMany
    {
        return $this->hasMany(TipoTurno::class)->orderBy('orden');
    }

    public function puntosCriticos(): HasMany
    {
        return $this->hasMany(PuntoCritico::class)->orderBy('nombre');
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class);
    }

    public function enlacesTurno(): HasMany
    {
        return $this->hasMany(EnlaceTurno::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
