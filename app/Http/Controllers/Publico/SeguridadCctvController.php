<?php

namespace App\Http\Controllers\Publico;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publico\AtenderCasoCctvRequest;
use App\Models\CasoCctv;
use App\Models\Colaborador;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use App\Services\Turnos\IdentificadorColaborador;
use App\Services\Turnos\Marcador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * SEGURIDAD CCTV (DOC-33): «Atender caso» desde el enlace por cédula /c/{token}.
 * Misma excepción RN-48 que /d/{token}: cédula activa y en turno, número exacto
 * de un caso abierto del componente, consulta por POST con límite y auditoría,
 * soporte por URL firmada de 10 minutos y, de la persona, solo el saludo recortado.
 */
class SeguridadCctvController extends Controller
{
    public const AVISO_SIN_TURNO = 'Para atender un caso tienes que estar en turno: marca tu entrada primero.';

    /** La misma respuesta para todo lo que no es «hay un caso abierto»: no se sondean números. */
    public const SIN_CASO = 'No hay un caso abierto con ese número.';

    public const YA_ATENDIDO = 'Este caso ya fue atendido.';

    /** Solo se dice a quien ya es una persona en turno del componente y consultó un caso abierto. */
    public const ASIGNADO_A_OTRA = 'Este caso está asignado a otra persona.';

    /** Minutos que vale la URL firmada del soporte. */
    public const SOPORTE_MINUTOS = 10;

    public function __construct(
        protected IdentificadorColaborador $identificador,
        protected AlmacenamientoDocumentos $almacenamiento,
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
        protected Marcador $marcador,
    ) {}

    public function mostrar(string $token): Response
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return response()->view('publico.no-disponible', [], 404);
        }

        return response()->view('publico.seguridad-cctv', [
            'token' => $token,
            'enlace' => $enlace,
            'componente' => $enlace->componente,
        ]);
    }

    /** Si existe y el saludo recortado, nada más. */
    public function cedula(Request $peticion, string $token): JsonResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null, 404);

        $datos = $peticion->validate(['cedula' => ['required', 'string', 'max:20']]);
        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $datos['cedula']);
        $existe = $colaborador !== null && $colaborador->activo;

        // RN-43: solo quien está en turno atiende.
        if ($existe && ! $this->marcador->estaEnTurno($colaborador)) {
            return response()->json(['existe' => false, 'saludo' => '', 'aviso' => self::AVISO_SIN_TURNO]);
        }

        return response()->json(['existe' => $existe, 'saludo' => $existe ? $colaborador->saludo() : '']);
    }

    /**
     * Ver el caso por su número exacto. Cualquier «no» responde igual: mismo
     * código, mismo cuerpo y las mismas consultas a la base.
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
        $ajeno = $encontrado && $this->esDeOtra($caso, $colaborador);

        $this->auditor->registrar(
            AccionAuditoria::CasoCctvConsultado,
            $encontrado ? $caso : $enlace,
            'Consulta del caso '.(ctype_digit($numero) ? CasoCctv::codigoDe((int) $numero) : '«'.mb_substr($numero, 0, 20).'»')
                .' desde el enlace: '.($encontrado ? 'encontrado' : 'no encontrado'),
            [
                'cedula' => mb_substr($cedula, 0, 20),
                'numero' => mb_substr($numero, 0, 20),
                'encontrado' => $encontrado,
                'asignado_a_otra' => $ajeno,
                'enlace' => $enlace->id,
                'ip' => $peticion->ip(),
            ],
        );

        if (! $encontrado) {
            return response()->json(['message' => self::SIN_CASO], 422);
        }

        if ($ajeno) {
            return response()->json(['message' => self::ASIGNADO_A_OTRA], 422);
        }

        $soporte = $caso->soporte()->withoutGlobalScopes()->with('versionActual')->first()?->versionActual;

        // Solo lo necesario para atender: ni entidad, ni quién lo registró, ni a quién se le pasó, ni ids.
        return response()->json([
            'codigo' => $caso->codigo(),
            'descripcion' => $caso->descripcion,
            'ubicacion' => $caso->ubicacion,
            'comuna' => $caso->nombreComuna(),
            'soporte' => $soporte === null ? null : [
                'url' => URL::temporarySignedRoute('cctv.soporte', now()->addMinutes(self::SOPORTE_MINUTOS), [
                    'token' => $token, 'caso' => $caso->id,
                ]),
                'tipo' => str_starts_with((string) $soporte->mime, 'image/') ? 'imagen' : 'pdf',
            ],
        ]);
    }

    /** El soporte del caso: URL firmada (middleware signed) y el enlace y el caso, vigentes. */
    public function soporte(string $token, string $caso): StreamedResponse
    {
        $enlace = $this->enlaceVigente($token);
        abort_if($enlace === null || ! ctype_digit($caso), 404);

        $registro = $this->delEnlace($enlace)->whereKey((int) $caso)->first();
        $version = $registro?->soporte()->withoutGlobalScopes()->with('versionActual')->first()?->versionActual;

        abort_if($version === null || ! $this->almacenamiento->existe($version), 404);

        return Storage::disk($this->almacenamiento->disco())->response($version->ruta, $version->nombre_original, [
            'Content-Type' => $version->mime,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Enviar la atención, con la hora del servidor. Todo se revalida aquí. */
    public function atender(AtenderCasoCctvRequest $peticion, string $token): Response|RedirectResponse
    {
        $enlace = $this->enlaceVigente($token);

        if ($enlace === null) {
            return response()->view('publico.no-disponible', [], 404);
        }

        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $peticion->validated('cedula'));

        if ($colaborador === null || ! $colaborador->activo) {
            throw ValidationException::withMessages([
                'cedula' => 'Esta identificación no está registrada o no está habilitada. Comunícate con Coordinación.',
            ]);
        }

        if (! $this->marcador->estaEnTurno($colaborador)) {
            throw ValidationException::withMessages(['cedula' => self::AVISO_SIN_TURNO]);
        }

        $this->contexto->establecer($enlace->dependencia);

        $numero = (string) $peticion->input('numero');
        $caso = $this->casoAbierto($enlace, $numero);

        if ($caso === null) {
            $this->auditor->registrar(AccionAuditoria::CasoCctvConsultado, $enlace, 'Envío de atención a un caso que no está abierto', [
                'cedula' => $colaborador->documento, 'numero' => mb_substr($numero, 0, 20),
                'encontrado' => false, 'enlace' => $enlace->id, 'ip' => $peticion->ip(),
            ]);

            // A quien ya lo atendió (doble toque) se le dice; a cualquier otro, el mensaje único.
            throw ValidationException::withMessages([
                'numero' => $this->loAtendioEl($enlace, $colaborador, $numero) ? self::YA_ATENDIDO : self::SIN_CASO,
            ]);
        }

        if ($this->esDeOtra($caso, $colaborador)) {
            throw ValidationException::withMessages(['numero' => self::ASIGNADO_A_OTRA]);
        }

        $datos = $peticion->validated();
        $rutas = [];

        try {
            $caso = DB::transaction(function () use ($enlace, $colaborador, $caso, $datos, $peticion, &$rutas): CasoCctv {
                // Dos envíos a la vez: el segundo espera aquí y lo encuentra ya atendido.
                $caso = $this->delEnlace($enlace, bloquear: true)->whereKey($caso->id)->first()
                    ?? throw ValidationException::withMessages(['numero' => self::YA_ATENDIDO]);

                if ($this->esDeOtra($caso, $colaborador)) {
                    throw ValidationException::withMessages(['numero' => self::ASIGNADO_A_OTRA]);
                }

                $ahora = now();

                $caso->fill([
                    'colaborador_id' => $colaborador->id,
                    'cedula' => $colaborador->documento,
                    'nombre' => $colaborador->nombre,
                    'cargo' => $colaborador->cargo,
                    'nodo_id' => $caso->nodo_id ?? $colaborador->nodo_id,
                    'enlace_turno_id' => $enlace->id,
                    'justificacion' => $datos['justificacion'] ?? null,
                    'atendido_at' => $ahora,
                    'tiempo_respuesta_min' => CasoCctv::minutosEntre($caso->requerido_at, $ahora),
                    'estado' => CasoCctv::ATENDIDO,
                ]);

                foreach ([1, 2, 3] as $n) {
                    $archivo = $peticion->file("evidencia{$n}");

                    if ($archivo === null) {
                        continue;
                    }

                    $documento = $this->guardarEvidencia($caso, $n, $archivo);
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
            AccionAuditoria::CasoCctvAtendido,
            $caso,
            "{$caso->nombre} atendió el caso {$caso->codigo()} en {$caso->tiempoLegible()}",
            ['origen' => 'Enlace por cédula', 'enlace' => $enlace->id],
        );

        return redirect()
            ->route('cctv.enlace', ['token' => $token])
            ->with('cctv.atendido', [
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
    protected function casoAbierto(EnlaceTurno $enlace, string $numero): ?CasoCctv
    {
        $numero = preg_match('/^\d{1,9}$/', $numero) === 1 ? (int) $numero : 0;

        return $this->delEnlace($enlace)->where('numero', $numero)->first();
    }

    /** Con persona asignada, solo ella lo atiende; sin ella, cualquiera del componente en turno. */
    protected function esDeOtra(CasoCctv $caso, Colaborador $colaborador): bool
    {
        return $caso->colaborador_asignado_id !== null && (int) $caso->colaborador_asignado_id !== (int) $colaborador->id;
    }

    protected function loAtendioEl(EnlaceTurno $enlace, Colaborador $colaborador, string $numero): bool
    {
        return ctype_digit($numero) && strlen($numero) <= 9 && CasoCctv::withoutGlobalScopes()
            ->where('dependencia_id', $enlace->dependencia_id)->where('componente_id', $enlace->componente_id)
            ->where('numero', (int) $numero)->where('colaborador_id', $colaborador->id)
            ->where('estado', '!=', CasoCctv::ABIERTO)->exists();
    }

    /** Los casos abiertos de este enlace. Sin scopes (no hay sesión): se filtra a mano. */
    protected function delEnlace(EnlaceTurno $enlace, bool $bloquear = false): Builder
    {
        return CasoCctv::withoutGlobalScopes()
            ->where('dependencia_id', $enlace->dependencia_id)
            ->where('componente_id', $enlace->componente_id)
            ->where('estado', CasoCctv::ABIERTO)
            ->when($bloquear, fn (Builder $q): Builder => $q->lockForUpdate());
    }

    /** Cada evidencia es un documento en la carpeta con custodia «Seguridad CCTV · <componente>». */
    protected function guardarEvidencia(CasoCctv $caso, int $n, UploadedFile $archivo): Documento
    {
        $documento = Documento::create([
            'dependencia_id' => $caso->dependencia_id,
            'carpeta_id' => CasoCctv::carpetaPara($caso->componente)->id,
            'nombre' => "Caso {$caso->codigo()} · Evidencia {$n} · {$caso->nombre}",
            'fecha_documento' => now()->toDateString(),
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "Evidencia {$n} del caso {$caso->codigo()}");

        return $documento->load('versionActual');
    }

    /** Sin sesión el contexto responde «sí» a todo módulo: se pregunta al componente del enlace. */
    protected function enlaceVigente(string $token): ?EnlaceTurno
    {
        $enlace = EnlaceTurno::porToken($token);

        return $enlace?->estaVigente() && $enlace->esSeguridadCctv()
            && $enlace->componente?->activo && $enlace->componente->tieneModulo('seguridad_cctv') ? $enlace : null;
    }
}
