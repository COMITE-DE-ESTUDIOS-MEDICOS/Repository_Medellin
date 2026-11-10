<?php

namespace App\Services\Turnos;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenMarcacion;
use App\Enums\RolDependencia;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Dependencia;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Models\Scopes\DependenciaScope;
use App\Models\TipoTurno;
use Carbon\CarbonInterface;
use App\Notifications\NovedadTurnoDetectada;
use Illuminate\Support\Facades\Notification;

/**
 * Mira cada marca y anota lo que se sale de lo esperado: sin ubicación,
 * fuera de zona y, al cerrar el turno, si duró de menos, de más o si nunca
 * tuvo salida. Cada novedad avisa a Coordinación por la campana.
 */
class Novedades
{
    public function __construct(protected Programacion $programacion)
    {
    }

    /**
     * @param  bool  $soloDuracion  al volver a juzgar un turno corregido: lo de la
     *                              ubicación ya se anotó cuando se marcó.
     */
    public function evaluar(Marcacion $marcacion, bool $soloDuracion = false): void
    {
        $nuevas = [];

        // Lo de la ubicación solo cuenta en lo que marcó la persona: una
        // corrección o un cierre automático no tienen ubicación por diseño.
        if (! $soloDuracion && $marcacion->origen === OrigenMarcacion::Enlace) {
            if ($marcacion->sin_ubicacion) {
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::SinUbicacion, 'Marcó '.mb_strtolower($marcacion->tipo->etiqueta()).' sin ubicación.');
            }

            // DOC-15 (RN-46): el teléfono dio una ubicación aproximada.
            if ($marcacion->ubicacion_imprecisa) {
                $km = round($marcacion->precision_m / 1000, 1);
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::UbicacionImprecisa,
                    'Marcó '.mb_strtolower($marcacion->tipo->etiqueta())." con una ubicación aproximada (±{$km} km): el teléfono no tiene activada la ubicación precisa.");
            }

            // DOC-12 (RN-41): entró diciendo que no recibió los EPP.
            if ($marcacion->tipo === TipoMarcacion::Entrada && $marcacion->epp_recibidos === false) {
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::SinEpp, 'Marcó entrada sin haber recibido los elementos de protección personal.');
            }

            if ($marcacion->fuera_de_zona) {
                $nuevas[] = $this->anotar($marcacion, TipoNovedad::FueraDeZona, 'Marcó '.mb_strtolower($marcacion->tipo->etiqueta()).' lejos de '.($marcacion->nodo?->nombre ?? 'su nodo').'.');
            }
        }

        // Si ya había salido «no registró entrada» y por fin marcó, ese aviso
        // se cierra solo: llegó tarde (la tardanza se anota abajo), no faltó.
        if (! $soloDuracion && $marcacion->tipo === TipoMarcacion::Entrada) {
            NovedadTurno::withoutGlobalScopes()
                ->where('colaborador_id', $marcacion->colaborador_id)
                ->where('tipo', TipoNovedad::SinEntrada->value)
                ->where('estado', EstadoNovedad::Pendiente->value)
                ->whereDate('fecha', '>=', $marcacion->marcada_at->copy()->subDay())
                ->update([
                    'estado' => EstadoNovedad::Corregida->value,
                    'comentario' => "Marcó entrada a las {$marcacion->marcada_at->hora()}.",
                    'revisada_at' => now(),
                ]);
        }

        // Tarde respecto al turno reconocido, pasada la tolerancia del componente.
        if (! $soloDuracion && $marcacion->tipo === TipoMarcacion::Entrada && $marcacion->tipoTurno
            && $marcacion->minutos_tarde > (int) $marcacion->componente->regla('tolerancia_min')) {
            $nuevas[] = $this->anotar($marcacion, TipoNovedad::Tardanza,
                "Llegó {$this->horas($marcacion->minutos_tarde)} tarde a su turno {$marcacion->tipoTurno->codigo} ({$marcacion->tipoTurno->horario()}).",
                $marcacion->minutos_tarde);
        }

        if ($marcacion->tipo === TipoMarcacion::Salida) {
            $nuevas[] = $this->evaluarDuracion($marcacion);
            $nuevas[] = $this->revisarTardanza($marcacion);
        }

        $this->avisar(array_filter($nuevas));
    }

    /**
     * Compara el trabajo real del turno (permanencia menos descanso) con la
     * jornada esperada de su componente, con la tolerancia.
     */
    protected function evaluarDuracion(Marcacion $salida): ?NovedadTurno
    {
        $entrada = $this->entradaDe($salida);

        if ($entrada === null) {
            return null;
        }

        $permanencia = (int) $entrada->marcada_at->diffInMinutes($salida->marcada_at);
        $componente = $salida->componente;
        $minutos = $componente->minutosTrabajados($permanencia);
        $tolerancia = (int) $componente->regla('tolerancia_min');
        $toleranciaExtra = (int) $componente->regla('tolerancia_extra_min');
        $deTurno = '';
        $duro = $this->horas($minutos);
        // «Estuvo 9 h 46 min; con 1 h de descanso, trabajó 8 h 46 min». En un
        // turno corto no hay descuento, y entonces no se menciona.
        $cuenta = $permanencia === $minutos
            ? "Trabajó {$duro}"
            : "Estuvo {$this->horas($permanencia)}; con {$this->horas($permanencia - $minutos)} de descanso, trabajó {$duro}";

        if ($salida->origen === OrigenMarcacion::CierreAutomatico) {
            return $this->anotar($salida, TipoNovedad::SinSalida, "No marcó salida. Entró el {$entrada->marcada_at->format('d/m')} a las {$entrada->marcada_at->hora()} y el turno se cerró a las {$this->horas($permanencia)} en el sitio.", $minutos, $entrada);
        }

        // DOC-8: lo esperado es el trabajo de SU turno (el reconocido con la
        // entrada y la salida): su horario menos el descanso que le toca. Un T4
        // de 4 h no es «turno corto»; un N1 de 7 h 30 min en el sitio, con
        // 30 min de descanso, espera 7 h. Sin turno con horario, la jornada del
        // componente.
        $turno = $this->programacion->reconocer($componente, $entrada->marcada_at, $entrada->colaborador?->tipoTurno, $salida->marcada_at);
        $tipo = $turno['tipo'] ?? null;
        $esperados = $componente->jornadaPara($tipo);
        if ($tipo?->minutosDeHorario() !== null) {
            $deTurno = " en su turno {$tipo->codigo}";
        }

        if ($minutos < $esperados - $tolerancia) {
            return $this->anotar($salida, TipoNovedad::TurnoCorto, "{$cuenta} de {$this->horas($esperados)} esperadas{$deTurno}.", $minutos, $entrada);
        }

        // Una tolerancia propia (40 a 60 min): salir un rato después no es hora extra.
        if ($minutos > $esperados + $toleranciaExtra) {
            return $this->anotar($salida, TipoNovedad::HorasExtra, "{$cuenta}: {$this->horas($minutos - $esperados)} más de las {$this->horas($esperados)} esperadas{$deTurno}.", $minutos, $entrada);
        }

        return null;
    }

    /**
     * DOC-21: con la salida real, el turno se reconoce de nuevo con las dos
     * horas. La entrada no se reescribe (es inmutable); lo que cambia es su
     * novedad de tardanza pendiente: se cierra si ya no hay tardanza, se
     * ajusta si cambió, o se anota si ahora sí la hay. Solo en entradas
     * marcadas por la persona, que son las que tienen tardanza.
     */
    protected function revisarTardanza(Marcacion $salida): ?NovedadTurno
    {
        if ($salida->origen === OrigenMarcacion::CierreAutomatico) {
            return null;
        }

        $entrada = $this->entradaDe($salida);

        if ($entrada === null || $entrada->origen !== OrigenMarcacion::Enlace) {
            return null;
        }

        $componente = $entrada->componente;
        $turno = $this->programacion->reconocer($componente, $entrada->marcada_at, $entrada->colaborador?->tipoTurno, $salida->marcada_at);

        if ($turno === null) {
            return null;
        }

        $tipo = $turno['tipo'];
        $minutos = $turno['minutos_tarde'];
        $pendiente = NovedadTurno::withoutGlobalScopes()
            ->where('marcacion_id', $entrada->id)
            ->where('tipo', TipoNovedad::Tardanza->value)
            ->where('estado', EstadoNovedad::Pendiente->value)
            ->first();

        if ($minutos <= (int) $componente->regla('tolerancia_min')) {
            $pendiente?->update([
                'estado' => EstadoNovedad::Corregida->value,
                'comentario' => "Con la salida, el turno es {$tipo->codigo} ({$tipo->horario()}): llegó a tiempo.",
                'revisada_at' => now(),
            ]);

            return null;
        }

        $detalle = "Llegó {$this->horas($minutos)} tarde a su turno {$tipo->codigo} ({$tipo->horario()}).";

        if ($pendiente) {
            $pendiente->update(['detalle' => $detalle, 'minutos' => $minutos]);

            return null;
        }

        return $this->anotar($entrada, TipoNovedad::Tardanza, $detalle, $minutos);
    }

    /** La entrada vigente que abre el turno de esa salida. */
    protected function entradaDe(Marcacion $salida): ?Marcacion
    {
        return Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $salida->colaborador_id)
            ->where('tipo', TipoMarcacion::Entrada->value)
            ->where('marcada_at', '<', $salida->marcada_at)
            ->latest('marcada_at')
            ->first();
    }

    protected function anotar(Marcacion $marcacion, TipoNovedad $tipo, string $detalle, ?int $minutos = null, ?Marcacion $entrada = null): NovedadTurno
    {
        // La misma marca no anota dos veces lo mismo; una ya corregida no
        // cuenta, porque el turno se está volviendo a juzgar.
        $existente = NovedadTurno::withoutGlobalScopes()
            ->where('marcacion_id', $marcacion->id)
            ->where('tipo', $tipo->value)
            ->where('estado', '!=', EstadoNovedad::Corregida->value)
            ->first();

        return $existente ?? NovedadTurno::withoutGlobalScopes()->create([
            'marcacion_id' => $marcacion->id,
            'tipo' => $tipo->value,
            'dependencia_id' => $marcacion->dependencia_id,
            'colaborador_id' => $marcacion->colaborador_id,
            'componente_id' => $marcacion->componente_id,
            'entrada_id' => $entrada?->id,
            'detalle' => $detalle,
            'minutos' => $minutos,
        ]);
    }

    /**
     * Quien se esperaba ese día (turno habitual) y no marcó entrada a tiempo.
     * Una sola por persona y día. Avisa también a los líderes (Edición).
     */
    public function sinEntrada(Colaborador $colaborador, TipoTurno $tipo, CarbonInterface $inicio): ?NovedadTurno
    {
        $existe = NovedadTurno::withoutGlobalScopes()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('fecha', $inicio)
            ->where('tipo', TipoNovedad::SinEntrada->value)
            ->exists();

        if ($existe) {
            return null;
        }

        $novedad = NovedadTurno::withoutGlobalScopes()->create([
            'tipo' => TipoNovedad::SinEntrada->value,
            'dependencia_id' => $colaborador->dependencia_id,
            'colaborador_id' => $colaborador->id,
            'componente_id' => $colaborador->componente_id,
            'fecha' => $inicio->toDateString(),
            'detalle' => "Tenía turno {$tipo->codigo} a las {$inicio->hora()} ({$tipo->nombre}) y no marcó entrada.",
        ]);

        $this->avisar([$novedad], conLideres: true);

        return $novedad;
    }

    /**
     * A Coordinación, Administración y quien aprueba novedades de la
     * dependencia (y a toda Edición si se pide), por la campana.
     */
    protected function avisar(array $novedades, bool $conLideres = false): void
    {
        $roles = [RolDependencia::Coordinacion->value, RolDependencia::Administracion->value];

        if ($conLideres) {
            $roles[] = RolDependencia::Edicion->value;
        }

        foreach ($novedades as $novedad) {
            if (! $novedad->wasRecentlyCreated) {
                continue;
            }

            $quienes = Dependencia::find($novedad->dependencia_id)
                ?->usuarios()
                ->where(fn ($q) => $q->whereIn('dependencia_usuario.rol', $roles)
                    ->orWhere('dependencia_usuario.aprueba_novedades', true))
                ->where('activo', true)
                ->get();

            if ($quienes?->isNotEmpty()) {
                Notification::send($quienes, new NovedadTurnoDetectada($novedad->load('colaborador')));
            }
        }
    }

    protected function horas(int $minutos): string
    {
        if ($minutos < 60) {
            return "{$minutos} min";
        }

        return intdiv($minutos, 60).' h'.($minutos % 60 ? ' '.($minutos % 60).' min' : '');
    }
}
