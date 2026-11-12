<?php

namespace App\Http\Controllers\Publico;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\Scopes\DependenciaScope;
use App\Models\User;
use App\Notifications\CasoAtendido;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use App\Services\Turnos\IdentificadorColaborador;
use App\Services\Turnos\Marcador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * DERIVACIONES Y RESPUESTA TERRITORIAL (DOC-20): «Atender caso» desde el
 * enlace por cédula. La oficina crea el caso DC-NNN; la persona de campo,
 * registrada, activa y en turno, lo consulta por su código exacto y envía la
 * atención, que es la marca de cierre.
 *
 * Es la única excepción a «la vía pública no devuelve datos personales», con
 * las 8 condiciones de la spec: solo este tipo de enlace, primero la cédula,
 * el código exacto con un único mensaje para «no hay», consulta por POST con
 * límite y auditoría, soporte por URL firmada de 10 minutos, solo lo necesario,
 * todo revalidado al enviar y nada en el almacenamiento del navegador.
 */
class DerivacionController extends Controller
{
    public const AVISO_SIN_TURNO = 'Para atender un caso tienes que estar en turno: marca tu entrada primero.';

    /** La misma respuesta para todo lo que no es «hay un caso abierto»: no se sondean números. */
    public const SIN_CASO = 'No hay un caso abierto con ese número.';

    public const YA_ATENDIDO = 'Este caso ya fue atendido.';

    /** Minutos que vale la URL firmada del soporte. */
    public const SOPORTE_MINUTOS = 10;

    public function __construct(
        protected IdentificadorColaborador $identificador,
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

        return response()->view('publico.derivacion', [
            'token' => $token,
            'enlace' => $enlace,
            'componente' => $enlace->componente->load(['nodos' => fn ($q) => $q->activos()]),
        ]);
    }

    /** Lo mismo que en turnos: si existe y el saludo recortado, nada más. */
    public function cedula(Request $peticion, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null, 404);

        $datos = $peticion->validate(['cedula' => ['required', 'string', 'max:20']]);
        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $datos['cedula']);
        $existe = $colaborador !== null && $colaborador->activo;

        // DOC-13 (RN-43): solo quien está en turno atiende.
        if ($existe && ! $this->marcador->estaEnTurno($colaborador)) {
            return response()->json(['existe' => false, 'saludo' => '', 'aviso' => self::AVISO_SIN_TURNO]);
        }

        return response()->json(['existe' => $existe, 'saludo' => $existe ? $colaborador->saludo() : '']);
    }

    /**
     * Ver el caso por su código exacto. Cualquier «no» (cédula que no sirve,
     * número que no existe, ya atendido, de otro componente) responde igual:
     * mismo código, mismo cuerpo, y las mismas consultas a la base.
     */
    public function caso(Request $peticion, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null, 404);

        $this->contexto->establecer($enlace->dependencia);

        $cedula = User::normalizarDocumento((string) $peticion->input('cedula'));
        $numero = (string) $peticion->input('numero');

        // Siempre se hacen las dos búsquedas, aunque la primera ya diga que no.
        $colaborador = $this->colaboradorEnTurno($enlace, $cedula);
        $caso = $this->casoAbierto($enlace, $numero);
        $encontrado = $colaborador !== null && $caso !== null;

        $this->auditor->registrar(
            AccionAuditoria::CasoConsultado,
            $encontrado ? $caso : $enlace,
            'Consulta del caso '.(ctype_digit($numero) ? Derivacion::codigoDe((int) $numero) : '«'.mb_substr($numero, 0, 20).'»')
                .' desde el enlace: '.($encontrado ? 'encontrado' : 'no encontrado'),
            [
                'cedula' => mb_substr($cedula, 0, 20),
                'numero' => mb_substr($numero, 0, 20),
                'encontrado' => $encontrado,
                'enlace' => $enlace->id,
                'ip' => $peticion->ip(),
            ],
        );

        if (! $encontrado) {
            return response()->json(['message' => self::SIN_CASO], 422);
        }

        $soporte = $caso->soporte()->withoutGlobalScopes()->with('versionActual')->first()?->versionActual;

        // Solo lo necesario para atender (condición 6): ni quién lo creó, ni ids, ni otros casos.
        return response()->json([
            'codigo' => $caso->codigo(),
            'mensaje' => $caso->descripcion,
            'entidad' => $caso->entidad,
            'ubicacion' => $caso->ubicacion,
            'soporte' => $soporte === null ? null : [
                'url' => URL::temporarySignedRoute('derivacion.soporte', now()->addMinutes(self::SOPORTE_MINUTOS), [
                    'token' => $token, 'derivacion' => $caso->id,
                ]),
                'tipo' => str_starts_with((string) $soporte->mime, 'image/') ? 'imagen' : 'pdf',
            ],
            'nodo_id' => $colaborador->nodo_id,
            'vencido' => $this->minutosDesde($caso) > Derivacion::META_MINUTOS,
        ]);
    }

    /** El soporte del caso: URL firmada (middleware signed) y el enlace y el caso, vigentes. */
    public function soporte(string $token, string $derivacion): StreamedResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null || ! ctype_digit($derivacion), 404);

        $caso = $this->delEnlace($enlace)->whereKey((int) $derivacion)->first();
        $version = $caso?->soporte()->withoutGlobalScopes()->with('versionActual')->first()?->versionActual;

        abort_if($version === null || ! $this->almacenamiento->existe($version), 404);

        // En línea, para verlo en <img> o en otra pestaña; el tipo es el que se detectó al guardarlo.
        return Storage::disk($this->almacenamiento->disco())->response($version->ruta, $version->nombre_original, [
            'Content-Type' => $version->mime,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Enviar la atención: la marca de cierre, con la hora del servidor. Todo se revalida aquí. */
    public function atender(Request $peticion, string $token): Response|RedirectResponse
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return response()->view('publico.no-disponible', [], 404);
        }

        $peticion->merge(['cedula' => User::normalizarDocumento($peticion->input('cedula'))]);
        $peticion->validate(['cedula' => ['required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/']], [
            'cedula.regex' => 'La identificación solo lleva números y letras, sin puntos ni espacios.',
        ], ['cedula' => 'identificación']);

        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $peticion->input('cedula'));

        if ($colaborador === null || ! $colaborador->activo) {
            throw ValidationException::withMessages([
                'cedula' => 'Esta identificación no está registrada o no está habilitada. Comunícate con Coordinación.',
            ]);
        }

        if (! $this->marcador->estaEnTurno($colaborador)) {
            throw ValidationException::withMessages(['cedula' => self::AVISO_SIN_TURNO]);
        }

        $caso = $this->casoAbierto($enlace, (string) $peticion->input('numero'));

        if ($caso === null) {
            // También es una consulta: queda en la auditoría como las de «Ver caso».
            $this->contexto->establecer($enlace->dependencia);
            $this->auditor->registrar(AccionAuditoria::CasoConsultado, $enlace, 'Envío de atención a un caso que no está abierto', [
                'cedula' => $colaborador->documento, 'numero' => mb_substr((string) $peticion->input('numero'), 0, 20),
                'encontrado' => false, 'enlace' => $enlace->id, 'ip' => $peticion->ip(),
            ]);

            // A quien ya lo atendió (doble toque) se le dice; a cualquier otro, el mensaje único.
            $loAtendio = ctype_digit((string) $peticion->input('numero')) && Derivacion::withoutGlobalScope(DependenciaScope::class)
                ->where('dependencia_id', $enlace->dependencia_id)->where('componente_id', $enlace->componente_id)
                ->where('numero', (int) $peticion->input('numero'))->where('colaborador_id', $colaborador->id)
                ->where('estado', Derivacion::SOLUCIONADA)->exists();

            throw ValidationException::withMessages(['numero' => $loAtendio ? self::YA_ATENDIDO : self::SIN_CASO]);
        }

        $imagen = ['required', 'image', 'mimetypes:'.implode(',', config('repositorio.formatos.imagenes.mimetypes')), 'max:'.config('repositorio.tamano_maximo_kb')];

        $datos = $peticion->validate([
            'nodo_id' => ['required', Rule::exists('nodos', 'id')->where('componente_id', $enlace->componente_id)->where('activo', 1)],
            'cumplio_requerimiento' => ['required', 'boolean'],
            'observacion' => ['required_if:cumplio_requerimiento,0', 'nullable', 'string', 'max:5000'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
            'evidencia1' => $imagen,
            'evidencia2' => $imagen,
            'evidencia3' => $imagen,
        ], [
            'cumplio_requerimiento.required' => 'Responde si se cumplió con el requerimiento.',
            'observacion.required_if' => 'Si no se cumplió con el requerimiento, escribe la observación.',
            'evidencia1.required' => 'Falta la evidencia #1.',
            'evidencia2.required' => 'Falta la evidencia #2.',
            'evidencia3.required' => 'Falta la evidencia #3.',
        ], [
            'nodo_id' => 'nodo que atiende', 'cumplio_requerimiento' => '¿se cumplió con el requerimiento?',
            'observacion' => 'observación', 'justificacion' => 'justificación',
            'evidencia1' => 'evidencia #1', 'evidencia2' => 'evidencia #2', 'evidencia3' => 'evidencia #3',
        ]);

        if ($this->minutosDesde($caso) > Derivacion::META_MINUTOS && blank($datos['justificacion'] ?? null)) {
            throw ValidationException::withMessages([
                'justificacion' => 'Pasaron más de '.intdiv(Derivacion::META_MINUTOS, 60).' horas desde que se creó el caso: escribe la justificación.',
            ]);
        }

        // La auditoría y los documentos van a la dependencia del enlace.
        $this->contexto->establecer($enlace->dependencia);

        $rutas = [];

        try {
            $caso = DB::transaction(function () use ($enlace, $colaborador, $caso, $datos, $peticion, &$rutas) {
                // Dos envíos a la vez: el segundo espera aquí y encuentra el caso ya cerrado.
                $caso = $this->delEnlace($enlace, bloquear: true)->whereKey($caso->id)->first();

                if ($caso === null) {
                    throw ValidationException::withMessages(['numero' => self::YA_ATENDIDO]);
                }

                $ahora = now();

                $caso->fill([
                    'colaborador_id' => $colaborador->id,
                    'cedula' => $colaborador->documento,
                    'nombre' => $colaborador->nombre,
                    'cargo' => $colaborador->cargo,
                    'enlace_turno_id' => $enlace->id,
                    'nodo_id' => (int) $datos['nodo_id'],
                    'cumplio_requerimiento' => (bool) $datos['cumplio_requerimiento'],
                    'observacion' => $datos['observacion'] ?? null,
                    'justificacion' => $datos['justificacion'] ?? null,
                    'atendida_at' => $ahora,
                    'hora_respuesta' => $ahora->format('H:i'),
                    'tiempo_respuesta_min' => max(0, (int) $caso->requeridaAt()->diffInMinutes($ahora)),
                    'estado' => Derivacion::SOLUCIONADA,
                ]);

                foreach ([1, 2, 3] as $n) {
                    $documento = $this->guardarEvidencia($caso, $n, $peticion->file("evidencia{$n}"));
                    $rutas[] = $documento->versionActual->ruta;
                    $caso->{"evidencia{$n}_documento_id"} = $documento->id;
                }

                $caso->save();

                return $caso;
            });
        } catch (Throwable $e) {
            // Los documentos se deshacen con la transacción; los archivos físicos, aquí.
            foreach ($rutas as $ruta) {
                $this->almacenamiento->eliminar($ruta);
            }

            throw $e;
        }

        $this->auditor->registrar(
            AccionAuditoria::CasoAtendido,
            $caso,
            "{$caso->nombre} atendió el caso {$caso->codigo()} en {$caso->tiempoLegible()}",
            ['origen' => 'Enlace por cédula', 'enlace' => $enlace->id, 'cumplio_requerimiento' => $caso->cumplio_requerimiento],
        );

        CasoAtendido::enviar($caso);

        return redirect()
            ->route('derivacion.enlace', ['token' => $token])
            ->with('derivacion.atendida', [
                'codigo' => $caso->codigo(),
                'tiempo' => $caso->tiempoLegible(),
                'saludo' => $colaborador->saludo(),
            ]);
    }

    /** Registrada, activa en la dependencia del enlace y en turno; si no, null. */
    protected function colaboradorEnTurno(EnlaceTurno $enlace, string $cedula): ?Colaborador
    {
        $colaborador = preg_match('/^[0-9A-Z]{5,20}$/', $cedula) === 1
            ? $this->identificador->buscar($enlace->dependencia_id, $cedula)
            : null;

        $enTurno = $this->marcador->estaEnTurno($colaborador?->activo ? $colaborador : null);

        return $enTurno ? $colaborador : null;
    }

    /** El caso abierto con ese número exacto, de la dependencia y el componente del enlace. */
    protected function casoAbierto(EnlaceTurno $enlace, string $numero): ?Derivacion
    {
        $numero = preg_match('/^\d{1,9}$/', $numero) === 1 ? (int) $numero : 0;

        return $this->delEnlace($enlace)->where('numero', $numero)->first();
    }

    /**
     * Los casos abiertos que este enlace puede ver. Sin el scope de dependencia
     * (no hay sesión), pero con el borrado lógico: un eliminado no existe.
     */
    protected function delEnlace(EnlaceTurno $enlace, bool $bloquear = false)
    {
        return Derivacion::withoutGlobalScope(DependenciaScope::class)
            ->where('dependencia_id', $enlace->dependencia_id)
            ->where('componente_id', $enlace->componente_id)
            ->whereNotNull('numero')
            ->where('estado', Derivacion::PENDIENTE)
            ->when($bloquear, fn ($q) => $q->lockForUpdate());
    }

    protected function minutosDesde(Derivacion $caso): int
    {
        return max(0, (int) $caso->requeridaAt()->diffInMinutes(now()));
    }

    /** Cada evidencia es un documento en «Derivaciones · <componente>». */
    protected function guardarEvidencia(Derivacion $caso, int $n, UploadedFile $archivo): Documento
    {
        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => null,
            'nombre' => 'Derivaciones · '.$caso->componente->nombre,
        ]);

        $documento = Documento::create([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => "Caso {$caso->codigo()} · Evidencia {$n} · {$caso->nombre}",
            'fecha_documento' => now()->toDateString(),
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "Evidencia {$n} del caso {$caso->codigo()}");

        return $documento->load('versionActual');
    }

    protected function enlaceVigente(string $token): ?EnlaceTurno
    {
        $enlace = EnlaceTurno::porToken($token);

        return $enlace?->estaVigente() && $enlace->esDerivacion() && $enlace->componente?->activo ? $enlace : null;
    }
}
