<?php

namespace App\Console\Commands;

use App\Enums\OrigenColaborador;
use App\Enums\TipoMarcacion;
use App\Enums\TipoNovedad;
use App\Models\Ausencia;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Models\NovedadTurno;
use App\Models\PuntoCritico;
use App\Models\TipoTurno;
use App\Services\ContextoDependencia;
use App\Services\Turnos\Marcador;
use App\Services\Turnos\Programacion;
use App\Services\Turnos\RevisorEntradas;
use Database\Seeders\CatalogoTurnosCalleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Prueba de estrés de los turnos automáticos, SOLO en desarrollo.
 *
 * Llena «DEMO · Calle» con personas ficticias (SIM-###) con turno, punto y
 * descanso habituales, algunas de vacaciones o incapacitadas, y simula un día
 * entero: cada una marca a su hora, tarde o no marca; y la revisión de
 * entradas corre cada 15 minutos como en el servidor. Al final compara lo que
 * pasó con lo que el sistema detectó.
 *
 * Las notificaciones son de verdad: les llegan a los editores y a
 * Coordinación de Inclusión Social en este equipo.
 */
class SimularDiaTurnos extends Command
{
    protected $signature = 'turnos:simular-dia
        {fecha? : Día a simular (por defecto, ayer)}
        {--personas=60 : Cuántas personas simuladas}
        {--faltan=10 : Porcentaje que no marca}
        {--tarde=20 : Porcentaje que llega tarde}
        {--semilla=7 : Para repetir exactamente la misma simulación}';

    protected $description = 'Simula un día de marcaciones en DEMO · Calle (solo desarrollo)';

    public function handle(Marcador $marcador, Programacion $programacion, RevisorEntradas $revisor, ContextoDependencia $contexto): int
    {
        if (app()->isProduction()) {
            $this->error('Esto crea personas y marcas inventadas: no se corre en producción.');

            return self::FAILURE;
        }

        mt_srand((int) $this->option('semilla'));
        $dia = Carbon::parse($this->argument('fecha') ?? now()->subDay()->toDateString())->startOfDay();
        $dependencia = Dependencia::where('slug', 'inclusion-social')->firstOrFail();
        $contexto->establecer($dependencia);

        $componente = $this->prepararComponente($dependencia);
        $enlace = EnlaceTurno::withoutGlobalScopes()->firstOrCreate(
            ['componente_id' => $componente->id, 'nombre' => 'SIMULACIÓN (datos inventados)'],
            ['token_hash' => EnlaceTurno::hashDe($t = EnlaceTurno::generarToken()), 'token_cifrado' => $t,
                'dependencia_id' => $dependencia->id, 'tipo' => 'turno'],
        );
        $personas = $this->prepararPersonas($componente, $dia);

        // Lo que la simulación decide de cada esperado: a qué hora marca, o si no marca.
        $esperados = $programacion->esperados($componente, $dia);
        $plan = $esperados->map(function (array $e) {
            $suerte = mt_rand(1, 100);
            $faltan = (int) $this->option('faltan');
            $tarde = (int) $this->option('tarde');

            $llega = match (true) {
                $suerte <= $faltan => null,
                $suerte <= $faltan + $tarde => $e['inicio']->copy()->addMinutes(mt_rand(20, 70)),
                default => $e['inicio']->copy()->addMinutes(mt_rand(-10, 10)),
            };

            return $e + ['llega' => $llega];
        });

        // Línea de tiempo: marcas y, cada 15 minutos, la revisión del servidor.
        $eventos = collect();
        foreach ($plan as $p) {
            if ($p['llega']) {
                $eventos->push(['cuando' => $p['llega'], 'persona' => $p['colaborador']]);
                $eventos->push(['cuando' => $p['llega']->copy()->addMinutes(mt_rand(450, 490)), 'persona' => $p['colaborador']]);
            }
        }
        for ($t = $dia->copy(); $t->lt($dia->copy()->addDay()->addHours(6)); $t->addMinutes(15)) {
            $eventos->push(['cuando' => $t->copy(), 'revisar' => true]);
        }

        $inicio = microtime(true);
        $this->info("Simulando {$dia->translatedFormat('l j \\d\\e F Y')}: {$personas} personas, {$plan->count()} esperadas…");

        $marcas = 0;
        foreach ($eventos->sortBy(fn ($e) => $e['cuando']->getTimestamp())->values() as $e) {
            Carbon::setTestNow($e['cuando']);

            if (isset($e['revisar'])) {
                $revisor->revisar();

                continue;
            }

            // DOC-12: en la entrada, un 3 % dice que no recibió los EPP.
            $marcador->marcar($e['persona'], $enlace, ['lat' => 6.2476, 'lng' => -75.5658, 'precision_m' => 10, 'municipio' => 'Medellín, Antioquia', 'epp' => mt_rand(1, 100) > 3]);
            $marcas++;
        }
        Carbon::setTestNow();

        $this->informe($componente, $dia, $plan, $marcas, microtime(true) - $inicio);

        return self::SUCCESS;
    }

    protected function prepararComponente(Dependencia $dependencia): Componente
    {
        $componente = Componente::withoutGlobalScopes()->firstOrCreate(
            ['dependencia_id' => $dependencia->id, 'slug' => 'demo-calle'],
            ['nombre' => 'DEMO · Calle', 'activo' => true],
        );
        $componente->update(['activo' => true, 'config' => ['foto_obligatoria' => false, 'autoregistro' => false] + ($componente->config ?? [])]);

        foreach (['Nodo 1', 'Nodo 2', 'Nodo 3'] as $i => $nombre) {
            Nodo::firstOrCreate(['componente_id' => $componente->id, 'nombre' => $nombre], ['orden' => $i + 1, 'activo' => true]);
        }

        CatalogoTurnosCalleSeeder::cargarEn($componente);

        return $componente;
    }

    /** Personas SIM-### con su turno, punto y descanso habituales; algunas ausentes ese día. */
    protected function prepararPersonas(Componente $componente, Carbon $dia): int
    {
        $n = max(1, (int) $this->option('personas'));
        $turnos = TipoTurno::where('componente_id', $componente->id)->whereIn('codigo', ['N1', 'N1.', 'T3', 'N7'])->get()->keyBy('codigo');
        $reparto = ['N1' => 35, 'N1.' => 35, 'T3' => 15, 'N7' => 15];
        $puntos = PuntoCritico::where('componente_id', $componente->id)->pluck('id');
        $nodos = $componente->nodos()->pluck('id');
        $ausencias = TipoTurno::where('componente_id', $componente->id)->whereIn('codigo', ['VAC', 'I'])->get();

        // Lo de una simulación anterior se borra: solo de este componente.
        $previas = Colaborador::withoutGlobalScopes()->where('componente_id', $componente->id)->where('documento', 'like', 'SIM%')->pluck('id');
        DB::table('novedades_turno')->whereIn('colaborador_id', $previas)->delete();
        DB::table('marcaciones')->whereIn('colaborador_id', $previas)->delete();
        DB::table('ausencias')->whereIn('colaborador_id', $previas)->delete();

        for ($i = 1; $i <= $n; $i++) {
            $suerte = mt_rand(1, 100);
            $codigo = $suerte <= 35 ? 'N1' : ($suerte <= 70 ? 'N1.' : ($suerte <= 85 ? 'T3' : 'N7'));

            $persona = Colaborador::withoutGlobalScopes()->updateOrCreate(
                ['dependencia_id' => $componente->dependencia_id, 'documento' => sprintf('SIM%03d', $i)],
                [
                    'nombre' => sprintf('Persona simulada %03d', $i), 'cargo' => 'Operador terapéutico',
                    'componente_id' => $componente->id, 'nodo_id' => $nodos[$i % $nodos->count()],
                    'tipo_turno_id' => $turnos[$codigo]->id, 'punto_critico_id' => $puntos->random(),
                    // Un día de descanso a la semana, repartido.
                    'dias_descanso' => [($i % 7) + 1],
                    'origen' => OrigenColaborador::Admin, 'verificado_at' => now(), 'activo' => true,
                ],
            );

            if (mt_rand(1, 100) <= 5) {
                Ausencia::create([
                    'dependencia_id' => $componente->dependencia_id, 'colaborador_id' => $persona->id,
                    'tipo_turno_id' => $ausencias->random()->id,
                    'desde' => $dia->copy()->subDays(2), 'hasta' => $dia->copy()->addDays(3),
                    'motivo' => 'Simulación',
                ]);
            }
        }

        // Las de una simulación anterior más grande dejan de contar.
        Colaborador::withoutGlobalScopes()->where('componente_id', $componente->id)
            ->where('documento', 'like', 'SIM%')->where('documento', '>', sprintf('SIM%03d', $n))
            ->update(['activo' => false]);

        return $n;
    }

    protected function informe(Componente $componente, Carbon $dia, $plan, int $marcas, float $segundos): void
    {
        $novedades = NovedadTurno::withoutGlobalScopes()->where('componente_id', $componente->id)
            ->whereIn('colaborador_id', $plan->pluck('colaborador.id'));
        $avisos = (clone $novedades)->where('tipo', TipoNovedad::SinEntrada->value)->whereDate('fecha', $dia);
        // Las que siguen pendientes al final del día: las que de verdad no marcaron.
        $sinEntrada = (clone $avisos)->where('estado', 'pendiente')->pluck('colaborador_id');
        $faltaron = $plan->whereNull('llega')->pluck('colaborador.id');
        $entradas = Marcacion::withoutGlobalScopes()->where('componente_id', $componente->id)
            ->where('tipo', TipoMarcacion::Entrada->value)->whereDate('marcada_at', '>=', $dia)
            ->whereIn('colaborador_id', $plan->pluck('colaborador.id'))->get();

        $this->newLine();
        $this->table(['Qué', 'Cuántos'], [
            ['Personas esperadas ese día', $plan->count()],
            ['Marcas registradas (entradas + salidas)', $marcas],
            ['Entradas con turno reconocido', $entradas->whereNotNull('tipo_turno_id')->count().' de '.$entradas->count()],
            ['Tardanzas anotadas', (clone $novedades)->where('tipo', TipoNovedad::Tardanza->value)->count()],
            ['Entradas sin EPP (novedad «Sin EPP»)', (clone $novedades)->where('tipo', TipoNovedad::SinEpp->value)->count()],
            ['Simuladas sin marcar', $faltaron->count()],
            ['Avisos «no registró entrada» enviados', (clone $avisos)->count()],
            ['  · se cerraron solos (llegaron tarde)', (clone $avisos)->where('estado', 'corregida')->count()],
            ['  · siguen abiertos al final del día', $sinEntrada->count()],
            ['  · faltas que se escaparon', $faltaron->diff($sinEntrada)->count()],
            ['  · falsas alarmas (sí marcaron)', $sinEntrada->diff($faltaron)->count()],
            ['Ausentes ese día (VAC / I)', Ausencia::withoutGlobalScopes()->en($dia)->whereIn('colaborador_id', Colaborador::withoutGlobalScopes()->where('componente_id', $componente->id)->pluck('id'))->count()],
            ['Tiempo de la simulación', round($segundos, 1).' s'],
        ]);

        $this->line('Míralo en Turnos → Novedades y en la campana (llegan a Edición y Coordinación).');
    }
}
