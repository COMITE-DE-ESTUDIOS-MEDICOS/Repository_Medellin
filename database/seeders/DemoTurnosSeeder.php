<?php

namespace Database\Seeders;

use App\Enums\EstadoNovedad;
use App\Enums\OrigenColaborador;
use App\Enums\TipoNovedad;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\EnlaceTurno;
use App\Models\Nodo;
use App\Models\NovedadTurno;
use App\Services\ContextoDependencia;
use App\Services\Turnos\Marcador;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Un mes de turnos INVENTADOS para ver el reporte (pestaña «Resumen
 * mensual»). Solo para desarrollo o una demostración: nombres y cédulas son
 * ficticios y todo cuelga del componente «DEMO · Calle», fácil de borrar.
 *
 *   php artisan db:seed --class=DemoTurnosSeeder
 *
 * Marca con el Marcador de verdad, moviendo el reloj a cada hora: así el
 * descanso, las novedades y las horas extra salen como saldrían en la calle.
 */
class DemoTurnosSeeder extends Seeder
{
    /** El mes que se llena. */
    protected const MES = '2026-09';

    /**
     * Cada persona y cómo trabaja. 'entra' y 'horas' son la hora de llegada y
     * cuánto se queda en el sitio; 'descansa' es su día libre (0 = domingo).
     */
    protected const PERSONAS = [
        ['900000101', 'Ana Restrepo (DEMO)', 'Operador terapéutico', 'Nodo 1', 'entra' => '07:00', 'horas' => 8, 'descansa' => 0],
        ['900000102', 'Bruno Cárdenas (DEMO)', 'Monitor de ruta', 'Nodo 1', 'entra' => '07:00', 'horas' => 8, 'descansa' => 3, 'extra_cada' => 3],
        ['900000103', 'Carla Mejía (DEMO)', 'Conductor camioneta', 'Nodo 2', 'entra' => '21:00', 'horas' => 8, 'descansa' => 1],
        ['900000104', 'Diego Ospina (DEMO)', 'Operador terapéutico', 'Nodo 2', 'entra' => '14:00', 'horas' => 8, 'descansa' => 5, 'corto_cada' => 4],
        ['900000105', 'Elena Vargas (DEMO)', 'Monitor de ruta', 'Nodo 3', 'entra' => '06:00', 'horas' => 10, 'descansa' => 2],
        ['900000106', 'Felipe Rúa (DEMO)', 'Conductor microbús', 'Nodo 3', 'entra' => '07:00', 'horas' => 8, 'descansa' => 6, 'falta_cada' => 5],
        ['900000107', 'Gloria Patiño (DEMO)', 'Operador terapéutico', 'Nodo 1', 'entra' => null],
    ];

    public function run(Marcador $marcador, ContextoDependencia $contexto): void
    {
        // Nadie recibe en su campana las novedades de esta demostración.
        Notification::fake();

        $dependencia = Dependencia::where('slug', 'inclusion-social')->firstOrFail();
        $contexto->establecer($dependencia);

        $componente = Componente::withoutGlobalScopes()->updateOrCreate(
            ['dependencia_id' => $dependencia->id, 'slug' => 'demo-calle'],
            ['nombre' => 'DEMO · Calle', 'activo' => true, 'config' => [
                'jornada_horas' => 7,
                'foto_obligatoria' => false,
                'autoregistro' => false,
                'cargos' => ['Conductor camioneta', 'Conductor microbús', 'Operador terapéutico', 'Monitor de ruta'],
            ]],
        );

        // Se puede volver a correr: lo de la demostración anterior se borra
        // entero (las marcaciones de verdad nunca se tocan: solo este componente).
        DB::table('novedades_turno')->where('componente_id', $componente->id)->delete();
        DB::table('marcaciones')->where('componente_id', $componente->id)->delete();
        DB::table('enlaces_turno')->where('componente_id', $componente->id)->delete();

        $nodos = collect(['Nodo 1', 'Nodo 2', 'Nodo 3'])->mapWithKeys(fn ($nombre, $i) => [$nombre => Nodo::updateOrCreate(
            ['componente_id' => $componente->id, 'nombre' => $nombre],
            ['orden' => $i + 1, 'activo' => true, 'lat' => 6.2476 + $i / 100, 'lng' => -75.5658, 'radio_m' => 300],
        )]);

        $token = EnlaceTurno::generarToken();
        $enlace = EnlaceTurno::withoutGlobalScopes()->create([
            'token_hash' => EnlaceTurno::hashDe($token), 'token_cifrado' => $token,
            'dependencia_id' => $dependencia->id, 'tipo' => 'turno', 'componente_id' => $componente->id,
            'nombre' => 'DEMO (datos inventados)',
        ]);

        $inicio = Carbon::parse(self::MES.'-01');

        foreach (self::PERSONAS as $p) {
            [$cedula, $nombre, $cargo, $nodo] = $p;

            $persona = Colaborador::withoutGlobalScopes()->updateOrCreate(
                ['dependencia_id' => $dependencia->id, 'documento' => $cedula],
                ['nombre' => $nombre, 'cargo' => $cargo, 'componente_id' => $componente->id, 'nodo_id' => $nodos[$nodo]->id,
                    'entidad' => 'Comité de Estudios Médicos', 'origen' => OrigenColaborador::Admin, 'verificado_at' => now(), 'activo' => true],
            );

            if ($p['entra'] === null) {
                continue; // No marcó en todo el mes: así sale en el resumen.
            }

            for ($dia = $inicio->copy(), $n = 0; $dia->month === $inicio->month; $dia->addDay(), $n++) {
                if ($dia->dayOfWeek === $p['descansa'] || (isset($p['falta_cada']) && $n % $p['falta_cada'] === 4)) {
                    continue;
                }

                $minutos = $p['horas'] * 60;
                $minutos += isset($p['extra_cada']) && $n % $p['extra_cada'] === 0 ? 150 : 0;   // se quedó 2 h 30 más
                $minutos -= isset($p['corto_cada']) && $n % $p['corto_cada'] === 0 ? 180 : 0;   // se fue 3 h antes
                $minutos += ($n * 7) % 11 - 5;                                                  // unos minutos de más o de menos

                $entrada = $dia->copy()->setTimeFromTimeString($p['entra'])->addMinutes(($n * 3) % 9 - 4);
                $datos = ['lat' => $nodos[$nodo]->lat, 'lng' => $nodos[$nodo]->lng, 'precision_m' => 12, 'municipio' => 'Medellín, Antioquia'];

                Carbon::setTestNow($entrada);
                $marcador->marcar($persona, $enlace, $datos);
                Carbon::setTestNow($entrada->copy()->addMinutes($minutos));
                $marcador->marcar($persona, $enlace, $datos);
            }
        }

        Carbon::setTestNow();

        // Coordinación ya revisó la mitad de las horas extra.
        NovedadTurno::withoutGlobalScopes()
            ->where('componente_id', $componente->id)
            ->where('tipo', TipoNovedad::HorasExtra->value)
            ->orderBy('id')
            ->get()
            ->filter(fn ($novedad, $i) => $i % 2 === 0)
            ->each(fn ($novedad) => $novedad->update(['estado' => EstadoNovedad::Aprobada]));

        $this->command?->info('Mes '.self::MES.' de DEMO · Calle listo. Reporte: Turnos → Marcaciones y reporte, del 1 al 30 de septiembre.');
    }
}
