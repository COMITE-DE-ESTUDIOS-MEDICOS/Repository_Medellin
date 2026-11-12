<?php

namespace App\Http\Controllers\Publico;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\Supervision;
use App\Models\User;
use App\Notifications\SupervisionImportante;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use App\Services\Turnos\Marcador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * DOC-11 · Supervisión territorial: el formulario del enlace por cédula de
 * tipo «Supervisión» (/s/{token}). Lo llena quien tiene un cargo que
 * supervisa (RN-39) y califica a dinamizadores registrados (RN-40). Como en
 * turnos, el navegador nunca recibe más que un saludo recortado (RN-15).
 */
class SupervisionController extends Controller
{
    public const AVISO_CARGO = 'Este enlace es solo para el Apoyo al control territorial.';

    public const AVISO_DINAMIZADOR = 'Esta cédula no está registrada como persona de campo.';

    public const AVISO_SIN_TURNO = 'Para registrar la supervisión tienes que estar en turno: marca tu entrada primero.';

    public const AVISO_DINAMIZADOR_SIN_TURNO = 'Esta persona no está en turno: no ha marcado entrada.';

    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
        protected Marcador $marcador,
    ) {
    }

    public function mostrar(string $token): Response
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return response()->view('publico.no-disponible', [], 404);
        }

        $componente = $enlace->componente->load([
            'nodos' => fn ($q) => $q->activos()->orderBy('orden'),
            'puntosCriticos' => fn ($q) => $q->where('activo', true)->orderBy('nombre'),
            'tiposTurno' => fn ($q) => $q->where('activo', true)->where('es_turno', true)->orderBy('orden'),
        ]);

        return response()->view('publico.supervision', [
            'token' => $token,
            'enlace' => $enlace,
            'componente' => $componente,
        ]);
    }

    /** Quien supervisa: existe, está activo y su cargo supervisa. Solo el saludo. */
    public function cedula(Request $peticion, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null, 404);

        $datos = $peticion->validate(['cedula' => ['required', 'string', 'max:20']]);
        $persona = $this->persona($enlace, $datos['cedula']);

        if ($persona === null) {
            return response()->json(['existe' => false, 'saludo' => '']);
        }

        if (! $enlace->componente->supervisaConCargo($persona->cargo)) {
            return response()->json(['existe' => false, 'saludo' => '', 'aviso' => self::AVISO_CARGO]);
        }

        if (! $this->marcador->estaEnTurno($persona)) {
            return response()->json(['existe' => false, 'saludo' => '', 'aviso' => self::AVISO_SIN_TURNO]);
        }

        return response()->json(['existe' => true, 'saludo' => $persona->saludo()]);
    }

    /** Un dinamizador: si está registrado y su saludo recortado, nada más. */
    public function dinamizador(Request $peticion, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null, 404);

        $datos = $peticion->validate(['cedula' => ['required', 'string', 'max:20']]);
        $persona = $this->persona($enlace, $datos['cedula']);

        if ($persona !== null && ! $this->marcador->estaEnTurno($persona)) {
            return response()->json(['existe' => false, 'saludo' => '', 'aviso' => self::AVISO_DINAMIZADOR_SIN_TURNO]);
        }

        return response()->json(['existe' => $persona !== null, 'saludo' => $persona?->saludo() ?? '']);
    }

    public function registrar(Request $peticion, string $token): Response|RedirectResponse
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return response()->view('publico.no-disponible', [], 404);
        }

        $componente = $enlace->componente;
        $peticion->merge(collect(['cedula', 'dinamizador1', 'dinamizador2'])
            ->mapWithKeys(fn ($campo) => [$campo => $peticion->filled($campo) ? User::normalizarDocumento($peticion->input($campo)) : null])
            ->all());

        // Orden de validación de la spec: quién supervisa primero, después el formulario.
        $peticion->validate(['cedula' => ['required', 'string', 'max:20']], [], ['cedula' => 'identificación']);
        $supervisor = $this->persona($enlace, $peticion->input('cedula'));

        if ($supervisor === null) {
            throw ValidationException::withMessages(['cedula' => 'Esta identificación no está registrada. Pide a Coordinación que te registre.']);
        }

        if (! $componente->supervisaConCargo($supervisor->cargo)) {
            throw ValidationException::withMessages(['cedula' => self::AVISO_CARGO]);
        }

        if (! $this->marcador->estaEnTurno($supervisor)) {
            throw ValidationException::withMessages(['cedula' => self::AVISO_SIN_TURNO]);
        }

        $maximo = 'max:'.config('repositorio.tamano_maximo_kb');
        $calificacion = ['required', 'integer', 'between:1,5'];

        $datos = $peticion->validate([
            'punto_critico_id' => ['nullable', 'required_without:punto_critico_otro', Rule::exists('puntos_criticos', 'id')->where('componente_id', $componente->id)],
            'punto_critico_otro' => ['nullable', 'string', 'max:255'],
            'nodo_id' => ['required', Rule::exists('nodos', 'id')->where('componente_id', $componente->id)],
            'fecha_visita' => ['required', 'date', 'before_or_equal:today'],
            'hora_visita' => ['required', 'date_format:H:i'],
            'dinamizador1' => ['required', 'string', 'max:20', 'different:cedula'],
            'dinamizador2' => ['nullable', 'string', 'max:20', 'different:dinamizador1', 'different:cedula'],
            'tipo_turno_id' => ['required', Rule::exists('tipos_turno', 'id')->where('componente_id', $componente->id)->where('es_turno', 1)],
            'descripcion_punto' => ['required', 'string', 'max:5000'],
            ...array_fill_keys(array_keys(Supervision::CRITERIOS), $calificacion),
            'calificacion_global' => $calificacion,
            'incidencia' => ['required', Rule::in(array_keys(Supervision::INCIDENCIAS))],
            'casos_escalados' => ['required', 'in:si,no'],
            'casos_escalados_cual' => ['nullable', 'required_if:casos_escalados,si', 'string', 'max:2000'],
            'casos_prioritarios' => ['nullable', 'string', 'max:2000'],
            'recomendaciones' => ['required', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'evidencia1' => ['required', 'image', $maximo],
            'evidencia2' => ['nullable', 'image', $maximo],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'precision_m' => ['nullable', 'numeric', 'min:0'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'inicio' => ['nullable', 'date'],
        ], [
            'punto_critico_id.required_without' => 'Elige el punto crítico o escribe cuál es.',
            'casos_escalados_cual.required_if' => 'Escribe cuál caso se escaló.',
            'dinamizador1.different' => 'El dinamizador no puede ser quien supervisa.',
            'dinamizador2.different' => 'El dinamizador 2 tiene que ser otra persona.',
            'evidencia1.required' => 'Falta la evidencia #1.',
            ...collect(Supervision::CRITERIOS)->mapWithKeys(fn ($texto, $c) => ["{$c}.required" => "Califica: {$texto}."])->all(),
            'calificacion_global.required' => 'Califica: Calificación global de la dupla.',
        ], [
            'punto_critico_id' => 'punto crítico',
            'nodo_id' => 'nodo',
            'fecha_visita' => 'fecha de la visita',
            'hora_visita' => 'hora de la visita',
            'dinamizador1' => 'cédula del dinamizador 1',
            'dinamizador2' => 'cédula del dinamizador 2',
            'tipo_turno_id' => 'turno del dinamizador',
            'descripcion_punto' => 'descripción del punto crítico',
            'recomendaciones' => 'recomendaciones',
            'evidencia1' => 'evidencia #1',
            'evidencia2' => 'evidencia #2',
        ]);

        $visita = Carbon::parse($datos['fecha_visita'].' '.$datos['hora_visita']);

        if ($visita->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['hora_visita' => 'La hora de la visita no puede ser posterior a la hora actual.']);
        }

        // RN-40: los dinamizadores tienen que ser personas de campo registradas y activas.
        $dinamizadores = [];
        foreach (['dinamizador1', 'dinamizador2'] as $campo) {
            if (blank($datos[$campo] ?? null)) {
                continue;
            }

            $dinamizadores[$campo] = $this->persona($enlace, $datos[$campo])
                ?? throw ValidationException::withMessages([$campo => self::AVISO_DINAMIZADOR]);

            // RN-44: se supervisa a quien está en turno.
            if (! $this->marcador->estaEnTurno($dinamizadores[$campo])) {
                throw ValidationException::withMessages([$campo => self::AVISO_DINAMIZADOR_SIN_TURNO]);
            }
        }

        // Doble toque: el mismo envío dentro del minuto devuelve el primero.
        $repetida = Supervision::withoutGlobalScopes()
            ->where('supervisor_id', $supervisor->id)
            ->where('dinamizador1_id', $dinamizadores['dinamizador1']->id)
            ->where('visita_at', $visita)
            ->where('created_at', '>=', now()->subMinute())
            ->first();

        if ($repetida) {
            return $this->listo($token, $repetida, $supervisor);
        }

        $this->contexto->establecer($enlace->dependencia);
        $rutas = [];

        try {
            $supervision = DB::transaction(function () use ($enlace, $componente, $supervisor, $dinamizadores, $datos, $visita, $peticion, &$rutas) {
                $supervision = Supervision::create([
                    'dependencia_id' => $enlace->dependencia_id,
                    'componente_id' => $componente->id,
                    'enlace_turno_id' => $enlace->id,
                    'supervisor_id' => $supervisor->id,
                    'supervisor_nombre' => $supervisor->nombre,
                    'supervisor_cedula' => $supervisor->documento,
                    'dinamizador1_id' => $dinamizadores['dinamizador1']->id,
                    'dinamizador2_id' => ($dinamizadores['dinamizador2'] ?? null)?->id,
                    'punto_critico_id' => $datos['punto_critico_id'] ?? null,
                    'punto_critico_otro' => blank($datos['punto_critico_id'] ?? null) ? ($datos['punto_critico_otro'] ?? null) : null,
                    'nodo_id' => $datos['nodo_id'],
                    'tipo_turno_id' => $datos['tipo_turno_id'],
                    'visita_at' => $visita,
                    'descripcion_punto' => $datos['descripcion_punto'],
                    ...collect($datos)->only([...array_keys(Supervision::CRITERIOS), 'calificacion_global'])->map(fn ($v) => (int) $v)->all(),
                    'incidencia' => $datos['incidencia'],
                    'casos_escalados' => $datos['casos_escalados'] === 'si',
                    'casos_escalados_cual' => $datos['casos_escalados'] === 'si' ? $datos['casos_escalados_cual'] : null,
                    'casos_prioritarios' => $datos['casos_prioritarios'] ?? null,
                    'recomendaciones' => $datos['recomendaciones'],
                    'observaciones' => $datos['observaciones'] ?? null,
                    'lat' => isset($datos['lat'], $datos['lng']) ? (float) $datos['lat'] : null,
                    'lng' => isset($datos['lat'], $datos['lng']) ? (float) $datos['lng'] : null,
                    'precision_m' => isset($datos['lat'], $datos['precision_m']) ? (int) round($datos['precision_m']) : null,
                    'municipio' => isset($datos['lat']) ? ($datos['municipio'] ?? null) : null,
                    'inicio_at' => $this->inicio($datos['inicio'] ?? null),
                    'ip' => $peticion->ip(),
                    'agente' => substr((string) $peticion->userAgent(), 0, 255),
                ]);

                foreach (['evidencia1', 'evidencia2'] as $campo) {
                    if ($peticion->file($campo) instanceof UploadedFile) {
                        $documento = $this->guardar($supervision, $campo, $peticion->file($campo));
                        $rutas[] = $documento->versionActual->ruta;
                        $supervision->{$campo.'_documento_id'} = $documento->id;
                    }
                }

                $supervision->save();

                return $supervision;
            });
        } catch (Throwable $e) {
            foreach ($rutas as $ruta) {
                $this->almacenamiento->eliminar($ruta);
            }

            throw $e;
        }

        $this->auditor->registrar(
            AccionAuditoria::SupervisionRegistrada,
            $supervision,
            "{$supervisor->nombre} registró la supervisión #{$supervision->id}",
            ['origen' => 'Enlace por cédula', 'enlace' => $enlace->id],
        );

        SupervisionImportante::enviarSiHace($supervision->load('puntoCritico'));

        return $this->listo($token, $supervision, $supervisor);
    }

    protected function listo(string $token, Supervision $supervision, Colaborador $supervisor): RedirectResponse
    {
        return redirect()
            ->route('supervision.enlace', ['token' => $token])
            ->with('supervision.registrada', ['numero' => $supervision->id, 'saludo' => $supervisor->saludo()]);
    }

    /** La hora en que se abrió el formulario: si es creíble (hoy, no futura), si no, nada. */
    protected function inicio(?string $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        $inicio = Carbon::parse($valor);

        return $inicio->isFuture() || $inicio->lt(now()->subHours(12)) ? null : $inicio;
    }

    /** Persona de campo activa de la dependencia del enlace, o null. */
    protected function persona(EnlaceTurno $enlace, ?string $cedula): ?Colaborador
    {
        $persona = filled($cedula) ? Colaborador::porDocumento($enlace->dependencia_id, $cedula) : null;

        return $persona?->activo ? $persona : null;
    }

    /** Cada foto es un documento en «Supervisiones · <componente>», como las de derivaciones. */
    protected function guardar(Supervision $supervision, string $campo, UploadedFile $archivo): Documento
    {
        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $supervision->dependencia_id,
            'carpeta_id' => null,
            'nombre' => 'Supervisiones · '.$supervision->componente->nombre,
        ]);

        $etiqueta = 'Evidencia '.substr($campo, -1);

        $documento = Documento::create([
            'dependencia_id' => $supervision->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => "Supervisión #{$supervision->id} · {$etiqueta} · {$supervision->supervisor_nombre}",
            'fecha_documento' => $supervision->visita_at->toDateString(),
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "{$etiqueta} de la supervisión #{$supervision->id}");

        return $documento->load('versionActual');
    }

    /** Vigente, de supervisión y de un componente activo. */
    protected function enlaceVigente(string $token): ?EnlaceTurno
    {
        $enlace = EnlaceTurno::porToken($token);

        return $enlace?->estaVigente() && $enlace->esSupervision() && $enlace->componente?->activo ? $enlace : null;
    }
}
