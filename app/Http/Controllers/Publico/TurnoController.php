<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\EnlaceTurno;
use App\Models\User;
use App\Services\ContextoDependencia;
use App\Services\Turnos\IdentificadorColaborador;
use App\Services\Turnos\Marcador;
use App\Services\Turnos\TurnoCerrado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Marcar turno desde el QR o el link: cédula, la primera vez sus datos, y
 * un botón. Con el mismo enlace se marca entrada y salida; lo decide el
 * servidor según si la persona tiene una entrada abierta.
 */
class TurnoController extends Controller
{
    public function __construct(
        protected IdentificadorColaborador $identificador,
        protected Marcador $marcador,
        protected ContextoDependencia $contexto,
    ) {
    }

    public function mostrar(string $token): Response
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return $this->noDisponible();
        }

        return response()->view('publico.turno', [
            'token' => $token,
            'enlace' => $enlace,
            'componente' => $enlace->componente->load(['nodos' => fn ($q) => $q->activos()]),
        ]);
    }

    /**
     * ¿Esta cédula ya está registrada? Responde solo si existe, el saludo
     * recortado y si lo siguiente es entrada o salida. Nada más sale de
     * aquí: el enlace es compartido y cualquiera puede probar cédulas.
     */
    public function cedula(Request $peticion, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null, 404);

        $datos = $peticion->validate(['cedula' => ['required', 'string', 'max:20']]);
        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $datos['cedula']);

        if ($colaborador === null || ! $colaborador->activo) {
            return response()->json(['existe' => false, 'saludo' => '', 'accion' => 'entrada']);
        }

        return response()->json([
            'existe' => true,
            'saludo' => $colaborador->saludo(),
            // 'entrada', 'salida', 'reciente' si acaba de entrar, o 'cerrado' si ya hizo su turno del día.
            'accion' => $this->marcador->estadoParaMarcar($colaborador, $enlace->componente),
        ]);
    }

    public function marcar(Request $peticion, string $token): Response|RedirectResponse
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return $this->noDisponible();
        }

        $componente = $enlace->componente;
        $peticion->merge(['cedula' => User::normalizarDocumento($peticion->input('cedula'))]);

        $peticion->validate([
            'cedula' => ['required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/'],
        ], [
            'cedula.regex' => 'La identificación solo lleva números y letras, sin puntos ni espacios.',
        ], ['cedula' => 'identificación']);

        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $peticion->input('cedula'));

        if ($colaborador && ! $colaborador->activo) {
            throw ValidationException::withMessages([
                'cedula' => 'Tu identificación no está habilitada para marcar. Comunícate con Coordinación.',
            ]);
        }

        if ($colaborador === null && ! $componente->regla('autoregistro')) {
            throw ValidationException::withMessages([
                'cedula' => 'Tu identificación no está registrada. Pide a Coordinación que te registre.',
            ]);
        }

        $reglas = [
            'foto' => [
                $componente->regla('foto_obligatoria') ? 'required' : 'nullable',
                'image',
                'max:'.config('repositorio.tamano_maximo_kb'),
            ],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'precision_m' => ['nullable', 'numeric', 'min:0'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'tomadas' => ['nullable', 'array'],
            'tomadas.*' => ['nullable', 'string', 'max:40'],
            // DOC-12: los EPP se preguntan en la entrada (quien es nueva siempre entra).
            'epp' => [
                $colaborador === null || $this->marcador->estadoParaMarcar($colaborador, $componente) === 'entrada' ? 'required' : 'nullable',
                'in:si,no',
            ],
        ];

        if ($colaborador === null) {
            $reglas += $this->identificador->reglasDeRegistro($componente);
        }

        $datos = $peticion->validate($reglas, [
            'foto.required' => 'Toma la foto antes de registrar.',
            'epp.required' => 'Responde si recibiste los EPP.',
        ], [
            'foto' => 'foto',
            'colaborador.nombre' => 'nombre completo',
            'colaborador.nodo_id' => 'nodo',
            'colaborador.cargo' => 'cargo',
        ]);

        if ($componente->regla('ubicacion_obligatoria') && blank($datos['lat'] ?? null)) {
            throw ValidationException::withMessages([
                'lat' => 'Este turno exige tu ubicación: activa el GPS y permite el acceso a la ubicación.',
            ]);
        }

        // La auditoría escribe en la dependencia del contexto, y en la vía
        // pública no hay sesión que la ponga: la da el enlace.
        $this->contexto->establecer($enlace->dependencia);

        try {
            $marcacion = DB::transaction(function () use ($componente, $colaborador, $peticion, $datos, $enlace) {
            $colaborador ??= $this->identificador->registrar($componente, $peticion->input('cedula'), $datos['colaborador']);

            return $this->marcador->marcar($colaborador, $enlace, [
                // El del QR si es de un nodo; si no, el habitual (Marcador::nodoDe).
                'nodo_id' => $datos['colaborador']['nodo_id'] ?? null,
                'lat' => $datos['lat'] ?? null,
                'lng' => $datos['lng'] ?? null,
                'precision_m' => $datos['precision_m'] ?? null,
                'municipio' => $datos['municipio'] ?? null,
                'declarada_at' => $datos['tomadas'][0] ?? null,
                'foto' => $peticion->file('foto'),
                'epp' => isset($datos['epp']) ? $datos['epp'] === 'si' : null,
                'ip' => $peticion->ip(),
                'agente' => $peticion->userAgent(),
            ]);
            });
        } catch (TurnoCerrado $e) {
            throw ValidationException::withMessages(['cedula' => $e->getMessage()]);
        }

        return redirect()
            ->route('turno.enlace', ['token' => $token])
            ->with('turno.marcado', [
                'tipo' => $marcacion->tipo->etiqueta(),
                'hora' => $marcacion->marcada_at->hora(),
                'saludo' => $marcacion->colaborador->saludo(),
                'repetida' => ! $marcacion->wasRecentlyCreated,
                'sin_ubicacion' => $marcacion->sin_ubicacion,
            ]);
    }

    /** Vigente y de un componente activo. Si no, la misma pantalla para todo: no se dice por qué. */
    protected function enlaceVigente(string $token): ?EnlaceTurno
    {
        $enlace = EnlaceTurno::porToken($token);

        return $enlace?->estaVigente() && $enlace->tipo === 'turno' && $enlace->componente?->activo ? $enlace : null;
    }

    protected function noDisponible(): Response
    {
        return response()->view('publico.no-disponible', [], 404);
    }
}
