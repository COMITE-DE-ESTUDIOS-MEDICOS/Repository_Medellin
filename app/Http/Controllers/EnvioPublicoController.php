<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Enums\EstadoEscaneo;
use App\Enums\EstadoRecepcion;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Notifications\RecepcionRecibida;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use App\Services\CalendarioHabil;
use App\Services\CargaPorTandas;
use App\Services\ContextoDependencia;
use App\Services\Turnos\IdentificadorColaborador;
use App\Services\Turnos\Marcador;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recepción de archivos de gente externa, sin cuenta en el repositorio.
 *
 * Es la única parte del sistema accesible sin autenticar, así que aquí no se
 * dan pistas de nada: quien llega con un token que no sirve ve siempre la
 * misma pantalla, sin saber si no existe, si venció o si fue revocado.
 */
class EnvioPublicoController extends Controller
{
    /**
     * Tope por envío. Va a la par de max_file_uploads en php.ini: si PHP
     * permite menos, descarta los sobrantes en silencio antes de llegar aquí.
     */
    public const MAXIMO_ARCHIVOS = 50;

    public const AVISO_SIN_TURNO = 'Para enviar evidencias tienes que estar en turno: marca tu entrada primero.';

    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
        protected ContextoDependencia $contexto,
        protected CalendarioHabil $calendario,
        protected IdentificadorColaborador $identificador,
        protected Marcador $marcador,
    ) {
    }

    public function formulario(string $token): Response
    {
        $enlace = EnlaceCarga::porToken($token);

        if ($enlace === null || ! $enlace->estaVigente()) {
            return $this->enlaceNoDisponible($token, $enlace);
        }

        // Solo el propósito. Ni el destinatario, ni la dependencia, ni nada
        // de la estructura interna: si el enlace se filtra, no se filtra con
        // él un mapa del sistema.
        return response()->view('publico.enviar', [
            'token' => $token,
            'proposito' => $enlace->proposito,
            // Lo que se puede elegir de una vez; el navegador lo manda en
            // tandas de MAXIMO_ARCHIVOS (carga-tandas.js).
            'maximoArchivos' => config('repositorio.carga.maximo_total'),
            // Con componente, quien envía es una persona de campo: basta su cédula.
            'componente' => $this->componenteDe($enlace)?->load(['nodos' => fn ($q) => $q->activos()]),
        ]);
    }

    public function recibir(Request $peticion, string $token): Response|RedirectResponse|JsonResponse
    {
        $enlace = EnlaceCarga::porToken($token);

        // Una carga grande llega en tandas (carga-tandas.js) y cuenta como un
        // solo envío: si la primera tanda gastó el último uso, las siguientes
        // de esa misma carga entran igual (mientras el enlace siga activo y
        // sin vencer).
        $tanda = $enlace ? CargaPorTandas::desde($peticion, 'enlace:'.$enlace->id) : null;
        $continuaUnaCarga = $tanda?->yaEmpezo() && $enlace->activo && ! $enlace->haExpirado();

        if ($enlace === null || (! $enlace->estaVigente() && ! $continuaUnaCarga)) {
            return $this->enlaceNoDisponible($token, $enlace);
        }

        // Un archivo suelto llega como tal, no como lista de uno. Se envuelve
        // tocando el FileBag crudo a propósito: leerlo antes con file()
        // dejaría el valor viejo en caché y la normalización no surtiría
        // efecto.
        $crudo = $peticion->files->get('archivo');

        if ($crudo !== null && ! is_array($crudo)) {
            $peticion->files->set('archivo', [$crudo]);
        }

        // La validación va después de comprobar el token, no antes: con un
        // FormRequest se ejecutaría primero y un token muerto respondería con
        // errores de campo en vez de con la pantalla genérica.
        $datos = $peticion->validate([
            // La identidad la declara quien sube, no el enlace. Es lo que se
            // guarda como cadena de custodia junto a cada documento, así que
            // se pide entera: sin correo ni entidad no hay a quién volver.
            ...$this->reglasDeIdentidad($peticion, $enlace),

            'archivo' => ['required', 'array', 'max:'.self::MAXIMO_ARCHIVOS, \App\Services\CargaPorTandas::reglaCompleta()],
            'archivo.*' => [
                'bail',
                'file',
                // Esta puerta da a internet, y por eso no acepta lo mismo que
                // la de dentro: aquí solo entran PDF y fotografía. Las hojas
                // de cálculo son cosa de quien ya tiene cuenta.
                'max:'.config('repositorio.tamano_maximo_kb'),
                'mimes:'.implode(',', config('repositorio.formatos.publico.extensiones')),
                'mimetypes:'.implode(',', config('repositorio.formatos.publico.mimetypes')),
                $this->ladoMaximoDeImagen(),
            ],

            // Lista paralela a archivo[]: casan por posición.
            'nombres' => ['nullable', 'array', 'max:'.self::MAXIMO_ARCHIVOS],
            'nombres.*' => ['nullable', 'string', 'max:5000'],

            // Igual de paralela: cuándo se tomó cada foto, según el teléfono
            // que la estampó. Va vacía para los PDF y para lo de galería.
            'tomadas' => ['nullable', 'array', 'max:'.self::MAXIMO_ARCHIVOS],
            'tomadas.*' => ['nullable', 'date'],

            'mensaje' => ['nullable', 'string', 'max:1000'],

            ...CargaPorTandas::reglas(),
        ], [
            'remitente_nombre.required' => 'Escribe tu nombre para que sepan de quién viene.',
            'remitente_email.required' => 'Escribe tu correo, por si necesitan responderte.',
            'remitente_entidad.required' => 'Escribe la entidad o empresa desde la que envías.',
            'archivo.required' => 'Selecciona al menos un archivo.',
            'archivo.max' => 'Solo puedes enviar hasta '.self::MAXIMO_ARCHIVOS.' archivos a la vez.',
            'archivo.*.max' => 'El archivo :position supera el tamaño máximo permitido ('
                .round(config('repositorio.tamano_maximo_kb') / 1024).' MB).',
            'archivo.*.mimes' => 'El archivo :position no es un PDF ni una imagen (JPG, PNG, WEBP).',
            'archivo.*.mimetypes' => 'El contenido del archivo :position no corresponde a un PDF ni a una imagen.',
        ]);

        // Sin esto la auditoría quedaría sin dependencia y administración no
        // vería estos registros en su pantalla de auditoría.
        $this->contexto->establecer($enlace->dependencia);

        $componente = $this->componenteDe($enlace);
        $colaborador = $componente ? $this->colaboradorQueEnvia($peticion, $enlace) : null;

        $archivos = array_values($peticion->file('archivo'));

        // El juicio se hace una sola vez y se congela en cada fila. Una vez
        // porque los archivos de un mismo envío llegaron juntos y no pueden
        // discrepar; congelado porque el horario hábil es configurable y
        // esto tiene que seguir diciendo lo que era cierto hoy.
        $fueraDeHorario = ! $this->calendario->esHabil(now());

        // Si la transacción se cae después de escribir algún archivo, las
        // filas se deshacen solas pero los archivos no: hay que borrarlos.
        $rutasGuardadas = [];
        $hashes = [];

        try {
            [$recepciones, $remitente] = DB::transaction(function () use ($peticion, $enlace, $archivos, $datos, $fueraDeHorario, $componente, $colaborador, $tanda, &$rutasGuardadas, &$hashes) {
                $creadas = [];

                // La primera vez de una persona de campo se registra aquí, en la
                // misma transacción: si el envío falla, tampoco queda registrada.
                if ($componente && $colaborador === null) {
                    $colaborador = $this->identificador->registrar($componente, $peticion->input('cedula'), $datos['colaborador']);
                }

                $remitente = $this->remitente($datos, $colaborador?->load('nodo'));

                foreach ($archivos as $indice => $archivo) {
                    // Reintentar una tanda que sí había llegado no la duplica.
                    $hash = hash_file('sha256', $archivo->getRealPath());

                    if ($tanda && ($tanda->yaRecibido($hash) || in_array($hash, $hashes, true))) {
                        continue;
                    }

                    $hashes[] = $hash;
                    $nombreOriginal = Str::limit($archivo->getClientOriginalName(), 250, '');

                    // El reloj de quien envía puede estar en cualquier huso o
                    // sencillamente mal: se acepta como dato declarado, pero
                    // una fecha futura no se guarda, que no es una foto de hoy.
                    // El teléfono manda el instante con su huso (normalmente en
                    // UTC): hay que traerlo al de la aplicación o la ficha diría
                    // las cinco de la tarde para una foto de mediodía.
                    $tomada = rescue(
                        fn () => ($cruda = $datos['tomadas'][$indice] ?? null)
                            ? Carbon::parse($cruda)->setTimezone(config('app.timezone'))
                            : null,
                        null,
                        false,
                    );

                    if ($tomada?->isFuture()) {
                        $tomada = null;
                    }

                    // El documento se crea en el acto, en la carpeta del
                    // enlace: ya no hay bandeja donde esperar a nadie.
                    $documento = Documento::create([
                        'dependencia_id' => $enlace->dependencia_id,
                        'carpeta_id' => $enlace->carpeta_id,
                        'nombre' => $this->nombrePara($datos, $archivo, $indice),
                        'descripcion' => $datos['mensaje'] ?? null,
                        // Una foto sí tiene fecha propia: la del momento en
                        // que se tomó, que es la que lleva estampada.
                        'fecha_documento' => $tomada?->toDateString(),
                        // Nadie de dentro lo subió: queda huérfano de autor a
                        // propósito, y quién lo mandó se lee en la recepción.
                        'creado_por' => null,
                        'actualizado_por' => null,
                    ]);

                    $version = $this->almacenamiento->guardarVersion(
                        $documento,
                        $archivo,
                        'Recibido por enlace de carga',
                    );

                    $rutasGuardadas[] = $version->ruta;

                    $creadas[] = Recepcion::create([
                        'dependencia_id' => $enlace->dependencia_id,
                        'enlace_carga_id' => $enlace->id,
                        'carpeta_sugerida_id' => $enlace->carpeta_id,
                        'documento_id' => $documento->id,

                        // Lo que declara quien sube. Se guarda aquí y no en el
                        // enlace: el enlace ya no tiene identidad.
                        'colaborador_id' => $remitente['colaborador_id'],
                        'remitente_nombre' => $remitente['nombre'],
                        'remitente_email' => $remitente['email'],
                        'remitente_entidad' => $remitente['entidad'],
                        'nodo' => $remitente['nodo'],
                        'tomada_at' => $tomada,

                        // El archivo vive bajo documentos/, con su versión.
                        // Aquí se apunta la misma ruta para no perder la
                        // referencia si el documento se inactiva.
                        'nombre_original' => $nombreOriginal,
                        'ruta' => $version->ruta,
                        'mime' => $version->mime,
                        'extension' => $version->extension,
                        'tamano' => $version->tamano,
                        'hash' => $version->hash,

                        'mensaje' => $datos['mensaje'] ?? null,
                        'estado' => EstadoRecepcion::Archivado,

                        // El escáner llega en el punto 6; hasta entonces nada
                        // se da por limpio, y la carpeta lo advierte.
                        'estado_escaneo' => EstadoEscaneo::Pendiente,

                        'ip_remitente' => $peticion->ip(),
                        'agente' => substr((string) $peticion->userAgent(), 0, 255),
                        'fuera_de_horario' => $fueraDeHorario,
                    ]);
                }

                // Un uso por envío, no por archivo: quien puso «1 uso» está
                // pensando en una entrega, no en un contador de ficheros. Una
                // carga por tandas es un solo envío: el uso se cuenta en la
                // primera tanda que entra.
                if (! $tanda?->yaEmpezo()) {
                    $enlace->registrarUso();
                }

                return [$creadas, $remitente];
            });
        } catch (Throwable $e) {
            foreach ($rutasGuardadas as $ruta) {
                $this->almacenamiento->eliminar($ruta);
            }

            throw $e;
        }

        $nombres = collect($recepciones)->pluck('nombre_original')->all();

        if ($tanda) {
            $tanda->marcarEmpezada();
            $tanda->anotar($hashes, $nombres);
            // La confirmación y el aviso, con todo lo de la carga, no solo
            // con lo de la última tanda.
            $nombres = $tanda->nombres();
        }

        // Una notificación por envío, no por archivo ni por tanda: nadie
        // quiere seis avisos porque alguien mandó 300 fotos.
        if ($enlace->creador && (! $tanda || $tanda->esUltima())) {
            $enlace->creador->notify(new RecepcionRecibida($enlace, count($nombres), $remitente['nombre']));
        }

        // Una entrada por documento: la auditoría sigue documentos, no envíos.
        $carpeta = $enlace->carpeta?->rutaTexto() ?? 'Raíz';

        foreach ($recepciones as $recepcion) {
            $this->auditor->registrar(
                AccionAuditoria::RecepcionRecibida,
                $recepcion->documento,
                "{$recepcion->remitente_nombre} envió «{$recepcion->nombre_original}» por un enlace de carga",
                [
                    'origen' => 'Enlace de carga',
                    'carpeta' => $carpeta,
                    'enlace' => $enlace->id,
                    'recepcion' => $recepcion->id,
                    'tamano' => $recepcion->tamano,
                ],
            );
        }

        if ($tanda) {
            if (! $tanda->esUltima()) {
                return response()->json(['recibidos' => count($recepciones)]);
            }

            $peticion->session()->flash('envio.confirmado', $nombres);

            return response()->json(['recibidos' => count($recepciones), 'redirect' => route('envio.confirmacion')]);
        }

        return redirect()
            ->route('envio.confirmacion')
            ->with('envio.confirmado', $nombres);
    }

    /**
     * El nombre de cada documento: el que se escribió en su tarjeta, o el
     * del archivo sin extensión si se dejó en blanco.
     */
    /**
     * Rechaza las imágenes con un lado por encima del tope. Solo mira
     * imágenes: un PDF no tiene «lado» y pasa de largo.
     *
     * getimagesize lee la cabecera y no decodifica la imagen, así que sirve
     * también contra una que declare 60 000 px por lado para agotar la
     * memoria del servidor al abrirla.
     */
    protected function ladoMaximoDeImagen(): \Closure
    {
        return function (string $atributo, mixed $archivo, \Closure $fallar): void {
            if (! $archivo instanceof UploadedFile || ! str_starts_with((string) $archivo->getMimeType(), 'image/')) {
                return;
            }

            [$ancho, $alto] = @getimagesize($archivo->getRealPath()) ?: [0, 0];
            $tope = config('repositorio.imagen.lado_maximo_servidor');

            if (max($ancho, $alto) > $tope) {
                $posicion = (int) Str::afterLast($atributo, '.') + 1;

                $fallar("La imagen del archivo {$posicion} mide {$ancho} × {$alto} px y el máximo es {$tope} px por lado. "
                    .'Reduce su tamaño o envíala con menos resolución.');
            }
        };
    }

    protected function nombrePara(array $datos, $archivo, int $indice): string
    {
        $deLaTarjeta = trim((string) ($datos['nombres'][$indice] ?? ''));

        if ($deLaTarjeta !== '') {
            return Str::squish($deLaTarjeta);
        }

        $delArchivo = trim(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME));

        return $delArchivo ?: 'Documento recibido';
    }

    public function confirmacion(Request $peticion): Response
    {
        $archivos = $peticion->session()->get('envio.confirmado');

        // Sin el aviso recién puesto no hay nada que confirmar: se responde
        // lo mismo que a un enlace muerto, sin contar por qué.
        if (empty($archivos)) {
            return $this->pantallaNoDisponible();
        }

        return response()->view('publico.recibido', ['archivos' => (array) $archivos]);
    }

    /**
     * ¿Esta cédula ya está registrada? Lo mismo que en el enlace de turno:
     * solo si existe y el saludo recortado. Nada más sale de aquí.
     */
    public function cedula(Request $peticion, string $token): JsonResponse
    {
        $enlace = EnlaceCarga::porToken($token);
        abort_if($enlace === null || ! $enlace->estaVigente() || $this->componenteDe($enlace) === null, 404);

        $datos = $peticion->validate(['cedula' => ['required', 'string', 'max:20']]);
        $colaborador = $this->identificador->buscar($enlace->dependencia_id, $datos['cedula']);
        $existe = $colaborador?->activo ?? false;

        // DOC-13 (RN-43): para enviar hay que estar en turno; una cédula nueva no lo está.
        if (! $this->marcador->estaEnTurno($existe ? $colaborador : null)) {
            return response()->json(['existe' => false, 'saludo' => '', 'aviso' => self::AVISO_SIN_TURNO]);
        }

        return response()->json(['existe' => $existe, 'saludo' => $existe ? $colaborador->saludo() : '']);
    }

    /** El componente del enlace, si tiene uno y está activo. */
    protected function componenteDe(EnlaceCarga $enlace): ?Componente
    {
        return $enlace->componente?->activo ? $enlace->componente : null;
    }

    /**
     * Con componente: la cédula y, solo si es nueva, sus datos una vez. Sin
     * él: nombre, correo, entidad y nodo, como siempre.
     */
    protected function reglasDeIdentidad(Request $peticion, EnlaceCarga $enlace): array
    {
        $componente = $this->componenteDe($enlace);

        if ($componente === null) {
            return [
                'remitente_nombre' => ['required', 'string', 'max:255'],
                'remitente_email' => ['required', 'email', 'max:255'],
                'remitente_entidad' => ['required', 'string', 'max:255'],
                'nodo' => ['required', 'integer', 'between:1,6'],
            ];
        }

        $peticion->merge(['cedula' => User::normalizarDocumento($peticion->input('cedula'))]);
        $existe = $this->identificador->buscar($enlace->dependencia_id, (string) $peticion->input('cedula')) !== null;

        return ['cedula' => ['required', 'string', 'min:5', 'max:20', 'regex:/^[0-9A-Z]+$/']]
            + ($existe ? [] : $this->identificador->reglasDeRegistro($componente));
    }

    /** La persona de campo que envía, ya registrada; null si es nueva y puede registrarse. */
    protected function colaboradorQueEnvia(Request $peticion, EnlaceCarga $enlace): ?Colaborador
    {
        $colaborador = $this->identificador->buscar($enlace->dependencia_id, (string) $peticion->input('cedula'));

        if ($colaborador && ! $colaborador->activo) {
            throw ValidationException::withMessages([
                'cedula' => 'Tu identificación no está habilitada para enviar. Comunícate con Coordinación.',
            ]);
        }

        if ($colaborador === null && ! $this->componenteDe($enlace)->regla('autoregistro')) {
            throw ValidationException::withMessages([
                'cedula' => 'Tu identificación no está registrada. Pide a Coordinación que te registre.',
            ]);
        }

        // DOC-13 (RN-43): para enviar evidencias hay que estar en turno; una cédula nueva no lo está.
        if (! $this->marcador->estaEnTurno($colaborador)) {
            throw ValidationException::withMessages(['cedula' => self::AVISO_SIN_TURNO]);
        }

        return $colaborador;
    }

    /**
     * Lo que queda como cadena de custodia en la recepción. Con persona de
     * campo se copia de su ficha, para que todo lo que ya lee remitente_*
     * (la ficha del documento, la auditoría) siga funcionando igual.
     */
    protected function remitente(array $datos, ?Colaborador $colaborador): array
    {
        if ($colaborador === null) {
            return [
                'colaborador_id' => null,
                'nombre' => $datos['remitente_nombre'],
                'email' => $datos['remitente_email'],
                'entidad' => $datos['remitente_entidad'],
                'nodo' => $datos['nodo'],
            ];
        }

        return [
            'colaborador_id' => $colaborador->id,
            'nombre' => $colaborador->nombre,
            'email' => $colaborador->correo,
            'entidad' => $colaborador->entidad,
            // recepciones.nodo sigue siendo el número de siempre (1 a 6).
            'nodo' => $colaborador->nodo?->orden,
        ];
    }

    /**
     * Misma pantalla y mismo código para inexistente, revocado, vencido y
     * agotado. Distinguirlos permitiría enumerar tokens válidos.
     */
    protected function enlaceNoDisponible(string $token, ?EnlaceCarga $enlace): Response
    {
        if ($enlace !== null) {
            $this->contexto->establecer($enlace->dependencia);
        }

        $this->auditor->registrar(
            AccionAuditoria::RecepcionRecibida,
            $enlace,
            'Intento de envío con un enlace que no está disponible',
            [
                'resultado' => 'rechazado',
                'motivo' => $this->motivoDelRechazo($enlace),
                // El hash y no el token: identifica el intento sin dejar el
                // secreto escrito en la tabla de auditoría.
                'token_hash' => EnlaceCarga::hashDe($token),
            ],
        );

        return $this->pantallaNoDisponible();
    }

    protected function motivoDelRechazo(?EnlaceCarga $enlace): string
    {
        return match (true) {
            $enlace === null => 'inexistente',
            ! $enlace->activo => 'revocado',
            $enlace->haExpirado() => 'vencido',
            $enlace->agotoSusUsos() => 'agotado',
            default => 'desconocido',
        };
    }

    protected function pantallaNoDisponible(): Response
    {
        return response()->view('publico.no-disponible', [], 404);
    }
}
