<?php

namespace App\Http\Controllers\Admin\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\OrigenColaborador;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarColaboradorRequest;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\User;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use App\Support\Xlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Carga masiva de personas de campo desde la plantilla de Excel, para el
 * arranque. Cada fila se valida con las mismas reglas de «Registrar
 * persona»; si una falla no se guarda ninguna y se dice cuál y por qué.
 *
 * ponytail: temporal, para el ingreso inicial. Se quita con sus dos rutas y
 * los dos botones de colaboradores/index.
 */
class CargaMasivaColaboradoresController extends Controller
{
    public const ENCABEZADOS = [
        'Cédula *', 'Nombre completo *', 'Componente *', 'Nodo *', 'Cargo *',
        'Turno habitual (código)', 'Punto crítico habitual', 'Teléfono', 'Correo', 'Entidad',
    ];

    public const MAXIMO_FILAS = 2000;

    public function __construct(
        protected ContextoDependencia $contexto,
        protected Auditor $auditor,
    ) {}

    /** La plantilla vacía y, en otra hoja, los valores válidos de esta dependencia. */
    public function plantilla(): BinaryFileResponse
    {
        $this->authorize('create', Colaborador::class);

        $listas = [];
        foreach (Componente::with(['nodos' => fn ($q) => $q->where('activo', true), 'tiposTurno', 'puntosCriticos'])->where('activo', true)->orderBy('nombre')->get() as $c) {
            foreach ($c->nodos as $nodo) {
                $listas[] = [$c->nombre, 'Nodo', $nodo->nombre];
            }
            foreach ($c->cargos() as $cargo) {
                $listas[] = [$c->nombre, 'Cargo', $cargo];
            }
            foreach ($c->tiposTurno->where('es_turno', true)->where('activo', true) as $t) {
                $listas[] = [$c->nombre, 'Turno habitual', $t->codigo.'  ('.$t->nombre.')'];
            }
            foreach ($c->puntosCriticos->where('activo', true) as $p) {
                $listas[] = [$c->nombre, 'Punto crítico', $p->nombre];
            }
        }

        $ruta = Xlsx::libro(tempnam(sys_get_temp_dir(), 'plantilla'), [
            'Personas' => [self::ENCABEZADOS, []],
            'Valores válidos' => [['Componente', 'Columna', 'Valor (escríbalo igual)'], $listas],
            'Instrucciones' => [['Cómo llenar la hoja «Personas»'], array_map(fn ($t) => [$t], [
                'Una fila por persona, desde la fila 2. No cambie ni mueva los encabezados.',
                'Las columnas con * son obligatorias.',
                'Cédula: solo números (o letras si es pasaporte), sin puntos ni espacios.',
                'Componente, Nodo, Cargo, Turno y Punto crítico: escríbalos como aparecen en «Valores válidos» (no importan mayúsculas ni tildes).',
                'Turno habitual: solo el código, por ejemplo N1 o T3. Puede quedar vacío.',
                'Si una sola fila tiene un error no se carga ninguna: el sistema dice qué fila corregir.',
                'Las personas cargadas quedan verificadas, como si las registrara Coordinación.',
            ])],
        ]);

        return response()->download($ruta, 'plantilla_carga_personas_de_campo.xlsx')->deleteFileAfterSend();
    }

    public function importar(Request $request): RedirectResponse
    {
        $this->authorize('create', Colaborador::class);

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx', 'max:5120']], [], ['archivo' => 'Excel de carga']);

        $filas = $this->leer($request->file('archivo')->getRealPath());

        if ($filas === []) {
            return back()->with('error', 'La hoja «Personas» no tiene filas para cargar.');
        }
        if (count($filas) > self::MAXIMO_FILAS) {
            return back()->with('error', 'Máximo '.self::MAXIMO_FILAS.' personas por archivo.');
        }

        $componentes = Componente::with(['nodos', 'tiposTurno', 'puntosCriticos'])->get();
        $errores = [];
        $validos = [];
        $vistas = [];

        foreach ($filas as $numero => $celdas) {
            $datos = $this->aDatos($celdas, $componentes, $errores, $numero);

            if ($datos === null) {
                continue;
            }

            if (isset($vistas[$datos['documento']])) {
                $errores[] = "Fila {$numero}: la cédula {$datos['documento']} está repetida (también en la fila {$vistas[$datos['documento']]}).";

                continue;
            }
            $vistas[$datos['documento']] = $numero;

            // Las mismas reglas que «Registrar persona».
            $reglas = GuardarColaboradorRequest::create('/', 'POST', $datos);
            $validador = Validator::make($datos, $reglas->rules(), $reglas->messages(), $reglas->attributes());

            if ($validador->fails()) {
                foreach ($validador->errors()->all() as $mensaje) {
                    $errores[] = "Fila {$numero}: {$mensaje}";
                }

                continue;
            }

            $validos[] = $validador->validated();
        }

        if ($errores !== []) {
            return back()->with('carga_errores', array_slice($errores, 0, 50))
                ->with('error', 'No se cargó nadie: corrige '.(count($errores) === 1 ? 'el error' : 'los '.count($errores).' errores').' y vuelve a subir el archivo.');
        }

        DB::transaction(function () use ($validos, $request) {
            foreach ($validos as $datos) {
                Colaborador::create($datos + [
                    'dependencia_id' => $this->contexto->requerida()->id,
                    'origen' => OrigenColaborador::Admin,
                    'verificado_at' => now(),
                    'verificado_por' => $request->user()->id,
                ]);
            }
        });

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorRegistrado,
            null,
            'Cargó '.count($validos).' personas de campo desde Excel',
            ['archivo' => $request->file('archivo')->getClientOriginalName()],
        );

        return redirect()->route('admin.turnos.colaboradores.index')
            ->with('exito', count($validos) === 1 ? 'Se cargó 1 persona.' : 'Se cargaron '.count($validos).' personas.');
    }

    /** @return array<int, list<mixed>> número de fila en Excel => celdas, sin filas vacías */
    protected function leer(string $ruta): array
    {
        $lector = new Reader;
        $lector->open($ruta);
        $filas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $n => $fila) {
                $celdas = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $fila->toArray());
                if ($n > 1 && array_filter($celdas, fn ($v) => $v !== null && $v !== '') !== []) {
                    $filas[$n] = $celdas;
                }
            }
            break; // Solo la primera hoja: «Personas».
        }

        $lector->close();

        return $filas;
    }

    /** Los nombres de la fila a los ids del formulario. Null si algo no se reconoce. */
    protected function aDatos(array $c, $componentes, array &$errores, int $numero): ?array
    {
        $texto = fn (int $i) => $c[$i] ?? null;
        // Excel guarda la cédula como número: 1017234 llega como 1017234.0.
        $cedula = is_float($c[0] ?? null) || is_int($c[0] ?? null) ? (string) (int) $c[0] : (string) ($c[0] ?? '');
        $igual = fn (?string $a, ?string $b) => Str::lower(Str::ascii((string) $a)) === Str::lower(Str::ascii((string) $b));

        $componente = $componentes->first(fn ($x) => $igual($x->nombre, $texto(2)));
        if ($componente === null) {
            $errores[] = "Fila {$numero}: el componente «{$texto(2)}» no existe.";

            return null;
        }

        $nodo = $componente->nodos->first(fn ($x) => $igual($x->nombre, $texto(3)));
        $cargo = collect($componente->cargos())->first(fn ($x) => $igual($x, $texto(4))) ?? $texto(4);
        $turno = filled($texto(5)) ? $componente->tiposTurno->first(fn ($x) => $igual($x->codigo, (string) $texto(5))) : null;
        $punto = filled($texto(6)) ? $componente->puntosCriticos->first(fn ($x) => $igual($x->nombre, $texto(6))) : null;

        $faltan = array_filter([
            $nodo === null ? "el nodo «{$texto(3)}» no es de {$componente->nombre}" : null,
            filled($texto(5)) && $turno === null ? "el turno «{$texto(5)}» no es de {$componente->nombre}" : null,
            filled($texto(6)) && $punto === null ? "el punto crítico «{$texto(6)}» no es de {$componente->nombre}" : null,
        ]);
        if ($faltan !== []) {
            $errores[] = "Fila {$numero}: ".implode('; ', $faltan).'.';

            return null;
        }

        return [
            'documento' => User::normalizarDocumento($cedula),
            'nombre' => (string) $texto(1),
            'componente_id' => $componente->id,
            'nodo_id' => $nodo->id,
            'cargo' => $cargo,
            'tipo_turno_id' => $turno?->id,
            'punto_critico_id' => $punto?->id,
            'telefono' => $texto(7) === null ? null : (string) (is_float($texto(7)) ? (int) $texto(7) : $texto(7)),
            'correo' => $texto(8),
            'entidad' => $texto(9),
            'dias_descanso' => [],
        ];
    }
}
