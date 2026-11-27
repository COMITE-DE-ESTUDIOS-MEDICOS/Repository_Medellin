<?php

namespace App\Http\Controllers\Admin\SeguridadCctv;

use App\Models\CasoCctv;
use App\Models\Documento;
use App\Services\AlmacenamientoDocumentos;
use App\Support\ResumenCctv;
use App\Support\Xlsx;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reporte ejecutivo y Excel de Seguridad CCTV (DOC-33). Los anulados no cuentan. */
class ReporteController
{
    private const ENCABEZADOS = [
        'Caso', 'Estado', 'Entidad', 'Requerimiento', 'Comuna', 'Nodo', 'Se le pasó a', 'Descripción', 'Ubicación',
        'Cédula de quien atendió', 'Quién atendió', 'Cargo', 'Atendido', 'Tiempo de respuesta', 'Justificación', 'Cerrado',
        'Soporte', 'Evidencia 1', 'Evidencia 2', 'Evidencia 3',
    ];

    public function __construct(private AlmacenamientoDocumentos $almacenamiento) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CasoCctv::class);
        [$desde, $hasta] = $this->rango($request);
        $resumen = ResumenCctv::de($this->casos($desde, $hasta));

        return view('admin.seguridad-cctv.reporte', compact('desde', 'hasta', 'resumen'));
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        Gate::authorize('viewAny', CasoCctv::class);
        [$desde, $hasta] = $this->rango($request);
        $casos = $this->casos($desde, $hasta, ['nodo', 'asignado', 'soporte.versionActual', 'evidencia1.versionActual', 'evidencia2.versionActual', 'evidencia3.versionActual']);
        $resumen = ResumenCctv::de($casos);

        $cifras = [
            ['Casos del rango', $resumen['total']],
            ...$this->pares($resumen['estados']),
            ...$this->pares($resumen['tiempos'] ?? []),
            ['Por entidad', ''],
            ...$this->pares($resumen['entidades']),
            ['Por comuna', ''],
            ...$this->pares($resumen['comunas']),
        ];

        $ruta = Xlsx::libro(tempnam(sys_get_temp_dir(), 'cctv'), [
            'Casos' => [self::ENCABEZADOS, $casos->map(fn (CasoCctv $c): array => $this->fila($c, $request->user()->can('verImagenes', $c)))],
            'Resumen' => [["Del {$desde} al {$hasta}", 'Cantidad o tiempo'], $cifras],
        ]);

        return response()->download($ruta, "seguridad-cctv_{$desde}_{$hasta}.xlsx")->deleteFileAfterSend();
    }

    /** @return array{0: string, 1: string} «Y-m-d»; por defecto, el mes en curso. */
    private function rango(Request $request): array
    {
        $datos = $request->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date', 'after_or_equal:desde']]);

        return [$datos['desde'] ?? now()->startOfMonth()->toDateString(), $datos['hasta'] ?? now()->endOfMonth()->toDateString()];
    }

    /** Una sola consulta plana: los scopes de dependencia y componente actúan solos. */
    private function casos(string $desde, string $hasta, array $con = []): Collection
    {
        return CasoCctv::query()->with($con)
            ->where('estado', '!=', CasoCctv::ANULADO)
            ->requeridoEntre($desde, $hasta)
            ->orderBy('requerido_at')
            ->get();
    }

    /** @return list<array{0: string, 1: int|string}> */
    private function pares(array $cifras): array
    {
        return collect($cifras)->map(fn (int|string $n, string $k): array => [$k, $n])->values()->all();
    }

    /** Las fotos solo para quien puede abrirlas (CA-19). */
    private function fila(CasoCctv $c, bool $conFotos): array
    {
        $foto = fn (?Documento $d): ?array => $conFotos ? $this->miniatura($d) : null;

        return [
            $c->codigo(), ucfirst($c->estado), $c->entidadLegible(), $c->requerido_at->format('d/m/Y H:i'), $c->nombreComuna(),
            $c->nodo?->nombre, $c->asignado?->nombre, $c->descripcion, $c->ubicacion, $c->cedula, $c->nombre, $c->cargo,
            $c->atendido_at?->format('d/m/Y H:i'), $c->tiempoLegible(), $c->justificacion, $c->cerrado_at?->format('d/m/Y H:i'),
            $foto($c->soporte), $foto($c->evidencia1), $foto($c->evidencia2), $foto($c->evidencia3),
        ];
    }

    private function miniatura(?Documento $documento): ?array
    {
        $version = $documento?->versionActual;

        return $version && $this->almacenamiento->existe($version) ? Xlsx::miniatura($this->almacenamiento->contenido($version)) : null;
    }
}
