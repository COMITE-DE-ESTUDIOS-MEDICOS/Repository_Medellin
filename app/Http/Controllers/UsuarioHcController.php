<?php

namespace App\Http\Controllers;

use App\Enums\AccionAuditoria;
use App\Http\Requests\GuardarUsuarioHcRequest;
use App\Models\UsuarioHc;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** DOC-26 (UHC-07, plan D6): crear una ficha y avisar de coincidencias. Las rutas llevan su `can:`. */
class UsuarioHcController extends Controller
{
    public function __construct(private readonly Auditor $auditor, private readonly ContextoDependencia $contexto) {}

    public function create()
    {
        return view('usuarios-hc.crear', [
            'tipos' => UsuarioHc::opciones(config('repositorio.usuarios_hc.tipos_documento')),
        ]);
    }

    public function store(GuardarUsuarioHcRequest $request): RedirectResponse
    {
        $datos = $request->safe()->only(['tipo_documento', 'numero_documento', 'numero_ficha', 'primer_nombre',
            'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'alias']);

        try {
            $ficha = DB::transaction(function () use ($datos, $request) {
                $ficha = new UsuarioHc($datos + [
                    'dependencia_id' => $this->contexto->id(),
                    'tipo_documento_texto' => config("repositorio.usuarios_hc.tipos_documento.opciones.{$datos['tipo_documento']}"),
                    'estado_vital' => UsuarioHc::VIVA,
                    'creado_por' => $request->user()->id,
                ]);
                // Lo pone el servidor y no cambia (CA-20): no es asignable en masa.
                $ficha->componente_registro_id = $this->contexto->componenteId();
                $ficha->save();

                $this->auditor->registrar(AccionAuditoria::UsuarioHcCreado, $ficha, "Creó la ficha de {$ficha->nombre_completo}");

                return $ficha;
            });
        } catch (QueryException $e) {
            // Dos personas guardaron lo mismo a la vez: gana el índice único (23000).
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            // El texto del error trae los nombres de todas las columnas: se pregunta de nuevo cuál choca.
            $documento = ($datos['numero_documento'] ?? null) !== null ? $request->mensajeRepetido('numero_documento') : null;
            $ficha = $documento === null && ($datos['numero_ficha'] ?? null) !== null ? $request->mensajeRepetido('numero_ficha') : null;

            throw ValidationException::withMessages(match (true) {
                $documento !== null => ['numero_documento' => $documento],
                $ficha !== null => ['numero_ficha' => $ficha],
                default => ['numero_documento' => 'Ya existe una ficha con ese documento o ese número de ficha.'],
            });
        }

        // «Ver la ficha» llega con UHC-11: hasta entonces, la lista.
        return redirect()->route('usuarios-hc.index')->with('exito', "Ficha de {$ficha->nombre_completo} creada.");
    }

    /**
     * El aviso mientras se escribe (CA-07, PA-21): hasta 10 fichas de la dependencia, solo con su
     * identificación. Avisa y no bloquea. El filtro de dependencia lo pone el scope del modelo.
     */
    public function coincidencias(Request $request)
    {
        $d = $request->validate([
            'primer_nombre' => ['nullable', 'string', 'max:100'],
            'primer_apellido' => ['nullable', 'string', 'max:100'],
            'alias' => ['nullable', 'string', 'max:100'],
            'tipo_documento' => ['nullable', 'string', 'max:20'],
            'numero_documento' => ['nullable', 'string', 'max:40'],
            'numero_ficha' => ['nullable', 'string', 'max:60'],
        ]);

        $nombre = UsuarioHc::normalizarTexto($d['primer_nombre'] ?? '');
        $apellido = UsuarioHc::normalizarTexto($d['primer_apellido'] ?? '');
        $alias = UsuarioHc::normalizarTexto($d['alias'] ?? '');
        $documento = ($d['tipo_documento'] ?? null) === UsuarioHc::SIN_DOCUMENTO ? '' : UsuarioHc::normalizarCodigo($d['numero_documento'] ?? '');
        $ficha = UsuarioHc::normalizarCodigo($d['numero_ficha'] ?? '');
        $conNombre = $nombre !== '' && $apellido !== '';

        $base = fn () => UsuarioHc::query()->with('componenteRegistro')->orderBy('nombre_completo');

        // Primero lo exacto (documento, ficha, alias), con su propio tope: muchos nombres
        // parecidos no lo pueden dejar por fuera.
        $exactas = collect();
        if ($documento !== '' || $ficha !== '' || $alias !== '') {
            $exactas = $base()->where(fn ($q) => $q
                ->when($documento !== '', fn ($q) => $q->orWhere('documento_normalizado', $documento))
                ->when($ficha !== '', fn ($q) => $q->orWhere('ficha_normalizada', $ficha))
                ->when($alias !== '', fn ($q) => $q->orWhere('alias_busqueda', $alias)))
                ->limit(10)->get();
        }

        // Después, el mismo primer nombre y primer apellido. Los nombres sueltos no se guardan
        // normalizados, pero nombre_busqueda empieza por el primer nombre y lleva el apellido como
        // palabra entera: la consulta trae candidatas cercanas y la comparación exacta va en PHP.
        // Se recorren por tandas hasta juntar las que faltan, sin tope antes del filtro: cien
        // «Juan Carlos Pérez» no pueden dejar por fuera a «Juan Pérez».
        $porNombre = collect();
        if ($conNombre && $exactas->count() < 10) {
            $porNombre = UsuarioHc::query()->with('componenteRegistro')
                ->where('nombre_busqueda', 'like', "{$nombre} %")
                ->where(fn ($q) => $q->where('nombre_busqueda', 'like', "% {$apellido} %")->orWhere('nombre_busqueda', 'like', "% {$apellido}"))
                ->whereKeyNot($exactas->pluck('id')->all())
                ->lazyById(200)
                ->filter(fn (UsuarioHc $f) => UsuarioHc::normalizarTexto($f->primer_nombre) === $nombre
                    && UsuarioHc::normalizarTexto($f->primer_apellido) === $apellido)
                ->take(10 - $exactas->count())
                ->collect()->sortBy('nombre_completo');
        }

        return view('usuarios-hc._coincidencias', ['fichas' => $exactas->concat($porNombre)->take(10)->values()]);
    }
}
