<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoNovedad;
use App\Enums\TipoNovedad;
use App\Http\Controllers\Controller;
use App\Models\Ausencia;
use App\Models\Colaborador;
use App\Models\NovedadTurno;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Descanso, vacaciones, incapacidad, permiso… por fechas. Lo registran los
 * líderes (Edición) desde la ficha de la persona o, para justificar un «no
 * registró entrada», desde Novedades. Esos días no se espera a la persona.
 */
class AusenciaController extends Controller
{
    public function __construct(protected Auditor $auditor)
    {
    }

    public function store(Request $request, Colaborador $colaborador): RedirectResponse
    {
        $this->authorize('update', $colaborador);

        $datos = $request->validate([
            'tipo_turno_id' => ['required', $this->codigoDeAusencia($colaborador)],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [], ['tipo_turno_id' => 'tipo de ausencia', 'hasta' => 'hasta']);

        $ausencia = $this->registrar($request, $colaborador, $datos);

        return back()->with('exito', "{$ausencia->tipo->nombre} registrada: {$ausencia->rango()}.");
    }

    /** Justifica un «no registró entrada»: la ausencia de ese día y la novedad aprobada. */
    public function justificar(Request $request, NovedadTurno $novedad): RedirectResponse
    {
        abort_unless($request->user()->puedeAprobarNovedadesEn($novedad->dependencia_id), 403);
        abort_unless($novedad->tipo === TipoNovedad::SinEntrada && $novedad->estaPendiente(), 422);

        $colaborador = $novedad->colaborador;
        $datos = $request->validate([
            'tipo_turno_id' => ['required', $this->codigoDeAusencia($colaborador)],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [], ['tipo_turno_id' => 'tipo de ausencia']);

        $ausencia = $this->registrar($request, $colaborador, $datos + [
            'desde' => $novedad->fecha->toDateString(),
            'hasta' => $novedad->fecha->toDateString(),
        ]);

        $novedad->update([
            'estado' => EstadoNovedad::Aprobada,
            'comentario' => "Justificada: {$ausencia->tipo->codigo} · {$ausencia->tipo->nombre}".($ausencia->motivo ? ". {$ausencia->motivo}" : ''),
            'revisada_por' => $request->user()->id,
            'revisada_at' => now(),
        ]);

        return back()->with('exito', "Justificada como {$ausencia->tipo->nombre}.");
    }

    public function destroy(Request $request, Ausencia $ausencia): RedirectResponse
    {
        $this->authorize('update', $ausencia->colaborador);

        $ausencia->delete();

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorActualizado,
            $ausencia->colaborador,
            "Quitó la ausencia {$ausencia->tipo->codigo} ({$ausencia->rango()}) de {$ausencia->colaborador->nombre}",
        );

        return back()->with('exito', 'Ausencia quitada.');
    }

    protected function registrar(Request $request, Colaborador $colaborador, array $datos): Ausencia
    {
        $ausencia = Ausencia::create($datos + [
            'dependencia_id' => $colaborador->dependencia_id,
            'colaborador_id' => $colaborador->id,
            'registrada_por' => $request->user()->id,
        ])->load('tipo');

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorActualizado,
            $colaborador,
            "Registró {$ausencia->tipo->codigo} ({$ausencia->tipo->nombre}) para {$colaborador->nombre}: {$ausencia->rango()}",
        );

        return $ausencia;
    }

    /** Solo un código de su componente que no sea turno de trabajo (D, VAC, I…). */
    protected function codigoDeAusencia(Colaborador $colaborador)
    {
        return Rule::exists('tipos_turno', 'id')
            ->where('componente_id', $colaborador->componente_id)
            ->where('es_turno', 0)
            ->where('activo', true);
    }
}
