<?php

namespace App\Services\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\OrigenMarcacion;
use App\Enums\TipoMarcacion;
use App\Models\Carpeta;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Documento;
use App\Models\EnlaceTurno;
use App\Models\Marcacion;
use App\Models\NovedadTurno;
use App\Enums\EstadoNovedad;
use App\Models\Nodo;
use App\Models\Scopes\DependenciaScope;
use App\Models\TipoTurno;
use App\Models\User;
use App\Services\AlmacenamientoDocumentos;
use App\Services\Auditor;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La única puerta por la que se crean marcaciones. Decide si toca entrada o
 * salida —el botón no lo decide—, ignora el doble toque, guarda la foto como
 * documento y congela los juicios (fuera de zona, sin ubicación).
 */
class Marcador
{
    /** Una segunda marca dentro de este margen es un doble toque, no una salida. */
    public const SEGUNDOS_DOBLE_TOQUE = 120;

    public function __construct(
        protected AlmacenamientoDocumentos $almacenamiento,
        protected Auditor $auditor,
        protected Novedades $novedades,
        protected Programacion $programacion,
    ) {
    }

    /** La entrada abierta de la persona, o null si no está en turno. */
    public function estadoDe(Colaborador $colaborador): ?Marcacion
    {
        return Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->entradasAbiertas()
            ->where('colaborador_id', $colaborador->id)
            ->first();
    }

    /** DOC-13 (RN-43): en turno = su última marcación vigente es una entrada. */
    public function estaEnTurno(?Colaborador $colaborador): bool
    {
        return $colaborador !== null && $this->estadoDe($colaborador) !== null;
    }

    /** Lo que haría ahora: entrada si no está en turno, salida si lo está. */
    public function siguienteTipo(Colaborador $colaborador): TipoMarcacion
    {
        return $this->estadoDe($colaborador) ? TipoMarcacion::Salida : TipoMarcacion::Entrada;
    }

    /**
     * Lo que puede hacer ahora en ese componente: 'entrada', 'salida',
     * 'reciente' si acaba de marcar (DOC-17), o
     * 'cerrado' si ya hizo los turnos que permite el día (regla
     * turnos_por_dia). Cerrado, lo que haya que corregir lo corrige
     * Coordinación o Edición, no la persona marcando otra vez.
     *
     * DOC-21 (CA-07): una entrada abierta que ya pasó su tope no pide salida;
     * marcar() la cierra y lo nuevo es una entrada. Aquí no se escribe nada.
     */
    public function estadoParaMarcar(Colaborador $colaborador, Componente $componente): string
    {
        // DOC-17: recién marcó; otro botón aquí solo invita al error.
        if (($ultima = $this->ultimaDe($colaborador)) && $this->esReintento($ultima, $componente)) {
            return 'reciente';
        }

        if (($abierta = $this->estadoDe($colaborador)) && ! $this->vencida($abierta->setRelation('colaborador', $colaborador))) {
            return TipoMarcacion::Salida->value;
        }

        $hoy = now()->setTimezone(config('app.timezone'));
        $turnosHoy = Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $colaborador->id)
            ->where('tipo', TipoMarcacion::Entrada->value)
            ->whereBetween('marcada_at', [$hoy->copy()->startOfDay(), $hoy->copy()->endOfDay()])
            ->count();

        return $turnosHoy >= (int) $componente->regla('turnos_por_dia') ? 'cerrado' : TipoMarcacion::Entrada->value;
    }

    /** La última marca que hizo la persona: un cierre automático no es un toque suyo. */
    protected function ultimaDe(Colaborador $colaborador): ?Marcacion
    {
        return Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $colaborador->id)
            ->where('origen', '!=', OrigenMarcacion::CierreAutomatico->value)
            ->latest('marcada_at')
            ->first();
    }

    /**
     * DOC-17 (RN-47): una marca de hace menos de salida_minima_min. Otra marca
     * entonces es un reintento —la anterior llegó y el teléfono no se enteró—,
     * no el paso de entrada a salida ni al revés (eso invertía el turno de
     * noche). Si de verdad salió o volvió antes, lo agrega Coordinación.
     */
    protected function esReintento(Marcacion $ultima, Componente $componente): bool
    {
        return $ultima->marcada_at->diffInMinutes(now()) < (int) $componente->regla('salida_minima_min');
    }

    /**
     * Marca por el enlace. $datos: nodo_id, lat, lng, precision_m, municipio,
     * declarada_at, foto (UploadedFile), epp (bool|null, solo en la entrada), ip, agente.
     *
     * Devuelve la marcación creada, o la anterior si fue un doble toque
     * (entonces wasRecentlyCreated es false).
     */
    public function marcar(Colaborador $colaborador, EnlaceTurno $enlace, array $datos): Marcacion
    {
        $rutaFoto = null;

        try {
            $marcacion = DB::transaction(function () use ($colaborador, $enlace, $datos, &$rutaFoto) {
                // Bloquear a la persona serializa sus dos toques simultáneos:
                // el segundo espera y ya ve la marca del primero.
                Colaborador::withoutGlobalScopes()->whereKey($colaborador->id)->lockForUpdate()->first();

                $ultima = $this->ultimaDe($colaborador);

                $componente = $enlace->componente;

                if ($ultima && ($ultima->marcada_at->diffInSeconds(now()) < self::SEGUNDOS_DOBLE_TOQUE || $this->esReintento($ultima, $componente))) {
                    return $ultima;
                }

                // DOC-21 (CA-07): sin esperar al cron, la entrada que ya pasó su
                // tope se cierra; lo que marca ahora es una entrada, no esa salida.
                if (($abierta = $this->estadoDe($colaborador)) && $this->vencida($abierta->setRelation('colaborador', $colaborador))) {
                    $this->cerrarSinSalida($abierta);
                }

                // Sin lanzar aquí: el cierre de arriba se guarda aunque hoy ya no pueda marcar.
                if ($this->estadoParaMarcar($colaborador, $componente) === 'cerrado') {
                    return null;
                }

                $nodo = $this->nodoDe($componente, $datos['nodo_id'] ?? null, $enlace, $colaborador);
                $tipo = $this->siguienteTipo($colaborador);
                $ahora = now();
                $conUbicacion = isset($datos['lat'], $datos['lng']);
                // DOC-15 (RN-46): con kilómetros de error no se puede decir si estaba en su zona.
                $imprecisa = $conUbicacion && isset($datos['precision_m'])
                    && (float) $datos['precision_m'] > (float) $componente->regla('precision_minima_m');

                // En la entrada, el turno se reconoce solo por la hora (Programacion)
                // y el punto es el del QR si es de un punto, o el habitual.
                $turno = $tipo === TipoMarcacion::Entrada
                    ? $this->programacion->reconocer($componente, $ahora, $colaborador->tipoTurno)
                    : null;

                $foto = null;

                if (($datos['foto'] ?? null) instanceof UploadedFile) {
                    $foto = $this->guardarFoto($componente, $colaborador, $tipo, $ahora, $datos['foto']);
                    $rutaFoto = $foto->versionActual->ruta;
                }

                return Marcacion::create([
                    'dependencia_id' => $colaborador->dependencia_id,
                    'colaborador_id' => $colaborador->id,
                    'componente_id' => $componente->id,
                    'nodo_id' => $nodo?->id,
                    'enlace_turno_id' => $enlace->id,
                    'tipo_turno_id' => $turno['tipo']->id ?? null,
                    'punto_critico_id' => $tipo === TipoMarcacion::Entrada ? ($enlace->punto_critico_id ?? $colaborador->punto_critico_id) : null,
                    'minutos_tarde' => $turno['minutos_tarde'] ?? null,
                    'tipo' => $tipo,
                    'marcada_at' => $ahora,
                    'declarada_at' => $this->declarada($datos['declarada_at'] ?? null),
                    'lat' => $conUbicacion ? (float) $datos['lat'] : null,
                    'lng' => $conUbicacion ? (float) $datos['lng'] : null,
                    'precision_m' => $conUbicacion && isset($datos['precision_m']) ? (int) round($datos['precision_m']) : null,
                    'municipio' => $conUbicacion ? ($datos['municipio'] ?? null) : null,
                    'documento_id' => $foto?->id,
                    'origen' => OrigenMarcacion::Enlace,
                    'fuera_de_zona' => $conUbicacion && ! $imprecisa && $this->fueraDeZona($nodo, (float) $datos['lat'], (float) $datos['lng']),
                    'ubicacion_imprecisa' => $imprecisa,
                    'fuera_de_turno' => false,
                    'sin_ubicacion' => ! $conUbicacion,
                    // DOC-12: solo la entrada pregunta por los EPP; lo demás queda sin dato.
                    'epp_recibidos' => $tipo === TipoMarcacion::Entrada && isset($datos['epp']) ? (bool) $datos['epp'] : null,
                    'ip' => $datos['ip'] ?? null,
                    'agente' => isset($datos['agente']) ? substr((string) $datos['agente'], 0, 255) : null,
                ]);
            });
        } catch (Throwable $e) {
            // La fila se deshace sola; el archivo ya escrito no.
            if ($rutaFoto) {
                $this->almacenamiento->eliminar($rutaFoto);
            }

            throw $e;
        }

        if ($marcacion === null) {
            throw new TurnoCerrado('Ya registraste tu turno de hoy. Si hay un error en la hora, avísale a Coordinación para que lo corrija.');
        }

        if ($marcacion->wasRecentlyCreated) {
            $this->auditar($marcacion, $colaborador);
            $this->novedades->evaluar($marcacion);
        }

        return $marcacion;
    }

    /**
     * Una corrección: una marca que faltó, agregada por Coordinación con su
     * motivo. Nunca edita una existente.
     */
    public function registrarManual(
        Colaborador $colaborador,
        TipoMarcacion $tipo,
        CarbonInterface $cuando,
        string $motivo,
        User $autor,
        ?Nodo $nodo = null,
    ): Marcacion {
        $marcacion = Marcacion::create([
            'dependencia_id' => $colaborador->dependencia_id,
            'colaborador_id' => $colaborador->id,
            'componente_id' => $nodo?->componente_id ?? $colaborador->componente_id,
            'nodo_id' => $nodo?->id ?? $colaborador->nodo_id,
            'tipo' => $tipo,
            'marcada_at' => $cuando,
            'origen' => OrigenMarcacion::Manual,
            'motivo' => $motivo,
            'registrada_por' => $autor->id,
            'sin_ubicacion' => true,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::TurnoCorreccion,
            $marcacion,
            "Agregó una {$tipo->etiqueta()} de {$colaborador->nombre} del {$cuando->fechaHora()}: {$motivo}",
        );

        if ($tipo === TipoMarcacion::Salida) {
            $this->novedades->evaluar($marcacion);
        }

        return $marcacion;
    }

    /**
     * Corregir la hora de una marca (una novedad, un error de quien marcó).
     * No se pisa nada: la original queda anulada —quién, cuándo, por qué— y
     * apunta a la nueva. Las novedades que dependían de ella pasan a
     * «corregida», y el turno se vuelve a juzgar con la hora buena.
     */
    public function corregir(Marcacion $original, CarbonInterface $hora, string $motivo, User $autor, ?TipoMarcacion $tipo = null): Marcacion
    {
        if ($original->estaAnulada()) {
            throw new TurnoCerrado('Esa marca ya se corrigió. Corrige la que la reemplazó.');
        }

        $tipo ??= $original->tipo;

        // DOC-18: cambiar el tipo es para enderezar una secuencia invertida,
        // donde las vecinas también están mal: ahí solo se exige que no sea futura.
        if ($tipo === $original->tipo) {
            $this->exigirCoherencia($original, $hora);
        } elseif ($hora->isFuture()) {
            throw new TurnoCerrado('La hora corregida no puede ser del futuro.');
        }

        $nueva = DB::transaction(function () use ($original, $hora, $motivo, $autor, $tipo) {
            $nueva = Marcacion::create([
                'dependencia_id' => $original->dependencia_id,
                'colaborador_id' => $original->colaborador_id,
                'componente_id' => $original->componente_id,
                'nodo_id' => $original->nodo_id,
                'enlace_turno_id' => $original->enlace_turno_id,
                'tipo' => $tipo,
                'marcada_at' => $hora,
                'origen' => OrigenMarcacion::Manual,
                'motivo' => "Corrige la {$original->tipo->etiqueta()} del {$original->marcada_at->fechaHora()}: {$motivo}",
                'registrada_por' => $autor->id,
                // La ubicación y la foto eran de la marca original: siguen ahí.
                'sin_ubicacion' => true,
            ]);

            $original->update([
                'anulada_at' => now(),
                'anulada_por' => $autor->id,
                'motivo_anulacion' => $motivo,
                'reemplazada_por_id' => $nueva->id,
            ]);

            $this->cerrarNovedadesDe($original, "Se corrigió la hora: {$motivo}", $autor);

            return $nueva;
        });

        $cambio = $tipo === $original->tipo ? '' : " ({$original->tipo->etiqueta()} → {$tipo->etiqueta()})";

        $this->auditor->registrar(
            AccionAuditoria::TurnoCorreccion,
            $nueva,
            "Corrigió la {$original->tipo->etiqueta()} de {$original->colaborador->nombre}{$cambio}: "
                ."{$original->marcada_at->fechaHora()} → {$hora->fechaHora()} ({$motivo})",
            ['anulada' => $original->id],
        );

        // El turno se vuelve a juzgar con la hora buena: la salida corregida,
        // o la salida que sigue a la entrada corregida.
        $salida = $nueva->tipo === TipoMarcacion::Salida ? $nueva : $this->salidaDe($nueva);

        if ($salida) {
            $this->novedades->evaluar($salida, soloDuracion: true);
        }

        return $nueva;
    }

    /**
     * DOC-18: quitar una marca que no debió existir (un reintento, una marca
     * de más). No se borra: queda anulada, sin reemplazo, con quién y por qué,
     * y deja de contar para el turno y el reporte.
     */
    public function anular(Marcacion $marcacion, string $motivo, User $autor): void
    {
        if ($marcacion->estaAnulada()) {
            throw new TurnoCerrado('Esa marca ya está anulada.');
        }

        DB::transaction(function () use ($marcacion, $motivo, $autor) {
            $marcacion->update([
                'anulada_at' => now(),
                'anulada_por' => $autor->id,
                'motivo_anulacion' => $motivo,
            ]);

            $this->cerrarNovedadesDe($marcacion, "Se anuló la marca: {$motivo}", $autor);
        });

        $this->auditor->registrar(
            AccionAuditoria::TurnoCorreccion,
            $marcacion,
            "Anuló la {$marcacion->tipo->etiqueta()} de {$marcacion->colaborador->nombre} del "
                ."{$marcacion->marcada_at->fechaHora()} ({$motivo})",
        );
    }

    /** Las novedades pendientes que dependían de una marca que ya no vale. */
    protected function cerrarNovedadesDe(Marcacion $marcacion, string $comentario, User $autor): void
    {
        NovedadTurno::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('marcacion_id', $marcacion->id)->orWhere('entrada_id', $marcacion->id))
            ->where('estado', EstadoNovedad::Pendiente->value)
            ->update([
                'estado' => EstadoNovedad::Corregida->value,
                'comentario' => $comentario,
                'revisada_por' => $autor->id,
                'revisada_at' => now(),
            ]);
    }

    /** Una salida no puede quedar antes de su entrada, ni una entrada después de su salida. */
    protected function exigirCoherencia(Marcacion $original, CarbonInterface $hora): void
    {
        if ($hora->isFuture()) {
            throw new TurnoCerrado('La hora corregida no puede ser del futuro.');
        }

        if ($original->tipo === TipoMarcacion::Salida) {
            $entrada = $this->vecina($original, TipoMarcacion::Entrada, antes: true);

            if ($entrada && $hora->lte($entrada->marcada_at)) {
                throw new TurnoCerrado("La salida no puede quedar antes de la entrada ({$entrada->marcada_at->fechaHora()}).");
            }

            return;
        }

        $salida = $this->salidaDe($original);

        if ($salida && $hora->gte($salida->marcada_at)) {
            throw new TurnoCerrado("La entrada no puede quedar después de la salida ({$salida->marcada_at->fechaHora()}).");
        }
    }

    /** La salida vigente que cierra el turno de esa entrada, si ya lo cerró. */
    protected function salidaDe(Marcacion $entrada): ?Marcacion
    {
        $siguiente = $this->vecina($entrada, null, antes: false);

        return $siguiente?->tipo === TipoMarcacion::Salida ? $siguiente : null;
    }

    protected function vecina(Marcacion $marca, ?TipoMarcacion $tipo, bool $antes): ?Marcacion
    {
        return Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->vigentes()
            ->where('colaborador_id', $marca->colaborador_id)
            ->whereKeyNot($marca->id)
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo->value))
            ->where('marcada_at', $antes ? '<' : '>', $marca->marcada_at)
            ->orderBy('marcada_at', $antes ? 'desc' : 'asc')
            ->first();
    }

    /**
     * Cierra las entradas que pasaron su tope (tope()): una salida «sin
     * salida» a la hora del tope, que queda como novedad. La corre la tarea
     * programada cada hora; marcar() también cierra la de quien marca, así que
     * el cron no es la única defensa. Devuelve cuántas cerró.
     */
    public function cerrarAbiertas(): int
    {
        $cerradas = 0;

        $abiertas = Marcacion::withoutGlobalScope(DependenciaScope::class)
            ->entradasAbiertas()
            ->with(['componente', 'colaborador', 'dependencia'])
            ->get();

        foreach ($abiertas as $entrada) {
            if ($this->vencida($entrada)) {
                $this->cerrarSinSalida($entrada);
                $cerradas++;
            }
        }

        return $cerradas;
    }

    /**
     * DOC-21: hasta cuándo puede seguir abierta una entrada:
     *  - si su cargo tiene máximo propio (horas_max_por_cargo), la entrada más esas horas;
     *  - si no, el fin del turno más largo que podría ser más el margen de horas extra (porTurno());
     *  - si ningún turno encaja, la entrada más la duración máxima del componente (horas_max_turno).
     */
    public function tope(Marcacion $entrada): Carbon
    {
        return $this->topeYTurnoCorto($entrada)[0];
    }

    public function vencida(Marcacion $entrada): bool
    {
        return ! $this->tope($entrada)->isFuture();
    }

    /** @return array{0: Carbon, 1: ?TipoTurno} el tope y el turno corto, si fue el que lo puso */
    protected function topeYTurnoCorto(Marcacion $entrada): array
    {
        $componente = $entrada->componente;
        $marcada = Carbon::parse($entrada->marcada_at);
        $delCargo = ((array) $componente->regla('horas_max_por_cargo', []))[trim((string) $entrada->colaborador?->cargo)] ?? null;

        // Un cargo con su propio máximo (conductores) se cierra a esas horas de la entrada.
        if ($delCargo !== null) {
            return [$marcada->copy()->addHours((int) $delCargo), null];
        }

        // Si no, al fin del turno más largo que podría ser, más el margen de horas
        // extra; sin turno reconocible, a la duración máxima del componente.
        return $this->porTurno($componente, $marcada)
            ?? [$marcada->copy()->addHours((int) $componente->regla('horas_max_turno')), null];
    }

    /**
     * DOC-21 v0.4 (Edwar, 2026-11-10): el turno sin salida se cierra al fin
     * del turno MÁS LARGO que esa entrada podría ser —todos los de la ventana
     * de reconocimiento—, más el margen de horas extra (tolerancia_extra_min).
     * Una entrada del viernes a las 6:00 puede ser T4 o un N1 que llegó tarde:
     * se cierra a las 13:30; un C13 del domingo, a las 19:00. Así a nadie que
     * esté trabajando se le corta el turno, y quien olvida la salida queda con
     * horas casi exactas. Null si ningún turno encaja o alguno no tiene hora de
     * fin (con duda, manda la duración máxima del componente).
     *
     * @return array{0: Carbon, 1: TipoTurno}|null el tope y el turno que lo puso
     */
    protected function porTurno(Componente $componente, Carbon $entrada): ?array
    {
        $antes = (int) $componente->regla('reconocer_antes_min');
        $despues = (int) $componente->regla('reconocer_despues_min');
        $mas = null;

        foreach (TipoTurno::where('componente_id', $componente->id)->where('activo', true)->where('es_turno', true)->get() as $t) {
            // Un día antes y uno después, por los turnos que cruzan la medianoche.
            foreach ([-1, 0, 1] as $d) {
                $dia = $entrada->copy()->addDays($d);
                if (! $t->hora_inicio || ! $this->programacion->aplica($t, $dia)) {
                    continue;
                }

                $inicio = $this->programacion->inicio($t, $dia);
                $diferencia = (int) $inicio->diffInMinutes($entrada, false);
                if ($diferencia < -$antes || $diferencia > $despues) {
                    continue;
                }

                if (! $t->hora_fin) {
                    return null;
                }

                $fin = $this->fin($t, $inicio);
                if ($mas === null || $fin->gt($mas[0])) {
                    $mas = [$fin, $t];
                }
            }
        }

        return $mas ? [$mas[0]->addMinutes((int) $componente->regla('tolerancia_extra_min')), $mas[1]] : null;
    }

    /** El fin de un turno que empezó en $inicio (al día siguiente si cruza la medianoche). */
    protected function fin(TipoTurno $tipo, CarbonInterface $inicio): Carbon
    {
        $fin = Carbon::parse($inicio->toDateString().' '.$tipo->hora_fin);

        return $fin->lte($inicio) ? $fin->addDay() : $fin;
    }

    /** Cierra una entrada sin salida a la hora de su tope: auditoría y novedad «no marcó salida». */
    public function cerrarSinSalida(Marcacion $entrada): Marcacion
    {
        [$tope, $corto] = $this->topeYTurnoCorto($entrada);

        // En consola no hay sesión: la auditoría va a la dependencia del turno.
        app(\App\Services\ContextoDependencia::class)->establecer($entrada->dependencia);

        $salida = Marcacion::create([
            'dependencia_id' => $entrada->dependencia_id,
            'colaborador_id' => $entrada->colaborador_id,
            'componente_id' => $entrada->componente_id,
            'nodo_id' => $entrada->nodo_id,
            'tipo' => TipoMarcacion::Salida,
            'marcada_at' => $tope,
            'origen' => OrigenMarcacion::CierreAutomatico,
            'motivo' => $corto
                ? "No marcó salida: se cerró al terminar su turno ({$corto->codigo}) y el margen de horas extra."
                : 'No marcó salida: se cerró al cumplir la duración máxima del turno.',
            'sin_ubicacion' => true,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::TurnoCierreAutomatico,
            $salida,
            "Cerró el turno de {$entrada->colaborador->nombre} sin salida (entró el {$entrada->marcada_at->fechaHora()})",
        );

        $this->novedades->evaluar($salida);

        return $salida;
    }

    /** El nodo: el elegido al marcar, el fijo del QR, o el habitual de la persona. */
    protected function nodoDe(Componente $componente, mixed $nodoId, EnlaceTurno $enlace, Colaborador $colaborador): ?Nodo
    {
        $id = $enlace->nodo_id ?? $nodoId ?? $colaborador->nodo_id;

        return $id ? $componente->nodos()->whereKey($id)->first() : null;
    }

    /**
     * Distancia en metros entre la marca y el centro del nodo (haversine).
     * Sin zona declarada en el nodo no hay nada que juzgar.
     */
    protected function fueraDeZona(?Nodo $nodo, float $lat, float $lng): bool
    {
        if (! $nodo?->tieneZona()) {
            return false;
        }

        $radio = 6371000;
        $dLat = deg2rad($lat - $nodo->lat);
        $dLng = deg2rad($lng - $nodo->lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($nodo->lat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return 2 * $radio * asin(min(1, sqrt($a))) > $nodo->radio_m;
    }

    /** La hora del teléfono, solo como referencia. Una del futuro no se guarda. */
    protected function declarada(mixed $cruda): ?CarbonInterface
    {
        $fecha = rescue(fn () => $cruda ? \Illuminate\Support\Carbon::parse($cruda)->setTimezone(config('app.timezone')) : null, null, false);

        return $fecha?->isFuture() ? null : $fecha;
    }

    /** La foto va al repositorio como documento, en «Turnos · <componente>». */
    protected function guardarFoto(Componente $componente, Colaborador $colaborador, TipoMarcacion $tipo, CarbonInterface $cuando, UploadedFile $archivo): Documento
    {
        $carpeta = Carpeta::withoutGlobalScopes()->firstOrCreate([
            'dependencia_id' => $componente->dependencia_id,
            'carpeta_id' => null,
            'nombre' => 'Turnos · '.$componente->nombre,
        ]);

        $documento = Documento::create([
            'dependencia_id' => $componente->dependencia_id,
            'carpeta_id' => $carpeta->id,
            'nombre' => "{$tipo->etiqueta()} · {$colaborador->nombre} · {$cuando->fechaHora()}",
            'fecha_documento' => $cuando->toDateString(),
        ]);

        $this->almacenamiento->guardarVersion($documento, $archivo, "Foto de {$tipo->etiqueta()} de turno");

        return $documento->load('versionActual');
    }

    protected function auditar(Marcacion $marcacion, Colaborador $colaborador): void
    {
        $this->auditor->registrar(
            $marcacion->esEntrada() ? AccionAuditoria::TurnoEntrada : AccionAuditoria::TurnoSalida,
            $marcacion,
            "{$colaborador->nombre} marcó {$marcacion->tipo->etiqueta()}"
                .($marcacion->nodo ? " en {$marcacion->nodo->nombre}" : ''),
            array_filter([
                'sin_ubicacion' => $marcacion->sin_ubicacion ?: null,
                'fuera_de_zona' => $marcacion->fuera_de_zona ?: null,
            ]),
        );
    }
}
