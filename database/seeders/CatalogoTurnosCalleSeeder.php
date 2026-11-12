<?php

namespace Database\Seeders;

use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\PuntoCritico;
use App\Models\TipoTurno;
use Illuminate\Database\Seeder;

/**
 * El catálogo de Calle tal como viene en la malla de octubre de 2026 (MG
 * OCTUBRE 2026) y en el formulario del QR: códigos, horarios, horas de
 * nómina y puntos críticos.
 *
 * Idempotente: solo crea lo que falta. Si Coordinación ya cambió algo desde
 * el panel, se respeta. Lo corre la migración 2026_10_21_000001.
 */
class CatalogoTurnosCalleSeeder extends Seeder
{
    /** código => [nombre, es turno, días ISO, festivos, inicio, fin, horas nómina] */
    public const TURNOS = [
        'N1' => ['Mañana', true, [1, 2, 3, 4, 5, 6], false, '05:00', '12:30', 7],
        'N1.' => ['Tarde', true, [1, 2, 3, 4, 5, 6], false, '12:30', '20:00', 7],
        'T3' => ['Intermedio', true, [1, 2, 3, 4, 5, 6], false, '10:00', '17:30', 7],
        'N7' => ['Nocturno', true, [], false, '21:00', '04:30', 7],
        'T4' => ['Viernes mañana', true, [5], false, '06:00', '10:00', 4],
        'T4.' => ['Viernes tarde', true, [5], false, '12:00', '16:00', 4],
        'C13' => ['Domingos y festivos', true, [7], true, '06:00', '18:00', 12],
        'AP' => ['Inducción de nuevos', true, [], false, null, null, 7],
        'D' => ['Descanso', false, [], false, null, null, 0],
        'I' => ['Incapacidad', false, [], false, null, null, 0],
        'VAC' => ['Vacaciones', false, [], false, null, null, 0],
        'P' => ['Permiso', false, [], false, null, null, 0],
        'PNR' => ['Permiso no remunerado', false, [], false, null, null, 0],
        'F' => ['Falta', false, [], false, null, null, 0],
        'EVENT' => ['Evento', false, [], false, null, null, 0],
        'H' => ['H (por confirmar con nómina)', false, [], false, null, null, 7],
        'Z' => ['Z (por confirmar con nómina)', false, [], false, null, null, 0],
    ];

    public const PUNTOS = [
        'Focalizado día – Centro', 'Focalizado día – Laureles', 'Focalizado día – Poblado',
        'Focalizado noche – Centro', 'Focalizado noche – Laureles', 'Focalizado noche – Poblado',
        'Monitor de ruta', 'Monitor de ruta / Móviles', 'Monitor (ATF)', 'Operador terapéutico (RTI)',
        'Apoyo control territorial', 'Zona por necesidad de servicio', 'Enlace CBA', 'Posturno',
        'Parque de Berrío – Plaza Botero (Calles 50 y 53, Carreras 50 y 53)',
        'Parque de las Luces, Glorieta Alpujarra',
        'Glorieta Minorista, Av. de Greiff',
        'Corredor Avenida Oriental (Calle 44 – Calle 58)',
        'Oreja Av. Oriental – San Juan (Calle 44 con Carrera 46)',
        'Av. La Playa (Carreras 40 – 50), Parque Boston, Teatro Pablo Tobón Uribe, Parque del Periodista',
        'Parque Bolívar / Junín', 'Plazuela San Ignacio', 'Perpetuo Socorro', 'San Benito',
        'Jardín Botánico, Moravia, Puente Ahorcado', 'Puente Ahorcado',
        'Sector Caribe, Terminal del Norte', 'Terminal del Norte',
        'Cúcuta x La Paz y zona de transición (Bazar de los Puentes)',
        'Cra 32 con Calle 81, Manrique Jardín',
        'Parque Juanes y Cra 68', 'Cra 70 (Colombia – San Juan) entre calles 44A y 50',
        'Parques 1 y 2 Laureles (Bulerías – Av. Nutibara y Acacias), Cr 74 a Cr 80 (Cl 35)',
        'Conquistadores – Unicentro – La Picacha – Parques del Río (Cl 34 a Cl 42)',
        'Canalización Cr 93 a Cr 70 (estaciones Santa Lucía y Estadio)',
        'La Macarena y alrededores, San Juan (Cr 72 a Cr 63 / Cr 84 a Cr 70)',
        'Cerro Nutibara – Industriales', 'La 10 – Poblado – Vizcaya',
        'Barrio Colombia, Ciudad del Río', 'Poblado Astorga, Patio Bonito, sector de Manila',
    ];

    public function run(): void
    {
        $inclusion = Dependencia::where('slug', 'inclusion-social')->first();
        $calle = $inclusion
            ? Componente::withoutGlobalScopes()->where('dependencia_id', $inclusion->id)->where('slug', 'calle')->first()
            : null;

        if ($calle !== null) {
            self::cargarEn($calle);
        }
    }

    /** El mismo catálogo en otro componente (p. ej. el de demostración). Idempotente. */
    public static function cargarEn(Componente $calle): void
    {
        $orden = 0;
        foreach (self::TURNOS as $codigo => [$nombre, $esTurno, $dias, $festivos, $inicio, $fin, $horas]) {
            TipoTurno::firstOrCreate(['componente_id' => $calle->id, 'codigo' => $codigo], [
                'nombre' => $nombre, 'es_turno' => $esTurno, 'dias' => $dias ?: null, 'festivos' => $festivos,
                'hora_inicio' => $inicio, 'hora_fin' => $fin, 'horas_nomina' => $horas,
                'orden' => $orden += 10,
            ]);
        }

        foreach (self::PUNTOS as $nombre) {
            PuntoCritico::firstOrCreate(['componente_id' => $calle->id, 'nombre' => $nombre]);
        }
    }
}
