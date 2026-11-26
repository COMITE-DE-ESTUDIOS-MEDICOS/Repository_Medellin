<?php

namespace App\Livewire\UsuariosHc;

use App\Models\Componente;
use App\Models\IdentidadUsuarioHc;
use App\Models\Scopes\ComponenteScope;
use App\Models\UsuarioHc;
use App\Services\ContextoDependencia;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * DOC-26 (UHC-06, plan D11): la lista de Usuarios HC. Muestra todas las fichas
 * de la dependencia, de cualquier componente con el módulo (spec §6.1, PA-01),
 * y solo su identificación: sin fotos (PA-17), sin edad ni nada de la
 * caracterización (CA-21). La consulta nunca carga relaciones sensibles.
 */
class Listado extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable {
        updatedTableSearch as protected guardarBusquedaDeFilament;
        updatedTableFilters as protected guardarFiltrosDeFilament;
        bootedInteractsWithTable as protected arrancarTablaDeFilament;
        getTableSessionKey as protected llaveDeSesionDeFilament;
    }

    /** Lo que se ofrece por página; cualquier otro valor que llegue se vuelve 25 (QA UHC-06). */
    public const POR_PAGINA = [25, 50, 100];

    /** Lo que dice la fila cuando la búsqueda casó con un dato que ya no es el vigente (CA-04). */
    public const MARCAS = [
        IdentidadUsuarioHc::NOMBRE => 'Coincide con un nombre anterior',
        IdentidadUsuarioHc::DOCUMENTO => 'Coincide con un documento anterior',
        IdentidadUsuarioHc::FICHA => 'Coincide con un número de ficha anterior',
    ];

    /**
     * La ruta ya lo exige; esto cubre también cada clic de la tabla, que es una
     * petición nueva y no repite el «can:» de la ruta (sí la dependencia y el
     * componente, que son middlewares persistentes).
     */
    public function boot(): void
    {
        abort_unless(Gate::allows('viewAny', UsuarioHc::class), 403, 'No tienes permiso para ver Usuarios HC.');
    }

    public function table(Table $table): Table
    {
        return $table
            // UsuarioHc lleva DependenciaScope y no ComponenteScope: toda la dependencia, nunca otra.
            // componenteRegistro ya salta el de componente (plan D1); sin eso, las
            // fichas de otro componente saldrían sin componente (CA-02).
            ->query(UsuarioHc::query()->with('componenteRegistro'))
            // Las identidades anteriores solo hacen falta para la marca, y solo con búsqueda.
            ->modifyQueryUsing(fn (Builder $query) => $query->when($this->hasTableSearch(), fn (Builder $q) => $q->with('identidades')))
            ->searchable()
            ->searchUsing(fn (Builder $query, string $search) => $query->buscar($search))
            ->searchPlaceholder('Buscar persona') // El campo es angosto: qué se busca lo dice la descripción de la página.
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->paginated(self::POR_PAGINA)
            ->defaultPaginationPageOption(self::POR_PAGINA[0])
            ->defaultSort('nombre_completo')
            ->columns([
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->weight('medium')
                    ->sortable()
                    ->wrap()
                    ->description(fn (UsuarioHc $record) => $this->debajoDelNombre($record)),
                TextColumn::make('numero_documento')
                    ->label('Documento')
                    ->placeholder('Sin documento')
                    ->description(fn (UsuarioHc $record) => $record->numero_documento === null ? null : $record->tipo_documento_texto),
                TextColumn::make('numero_ficha')
                    ->label('Ficha')
                    ->placeholder('Sin ficha'),
                // Con DOC-26 sola, el componente de registro (spec §1). UHC-20 lo cambia por «componente o estado».
                TextColumn::make('componenteRegistro.nombre')
                    ->label('Componente'),
                TextColumn::make('estado_vital')
                    ->label('Estado vital')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === UsuarioHc::FALLECIDA ? 'Fallecida' : 'Viva')
                    ->color(fn (?string $state) => $state === UsuarioHc::FALLECIDA ? 'gray' : 'success'),
            ])
            ->filters([
                SelectFilter::make('componente_registro_id')
                    ->label('Componente')
                    ->options(fn () => self::componentesConElModulo()),
            ])
            ->recordActions([
                // Las rutas llegan con UHC-11 (ver) y UHC-12 (editar): mientras no
                // existan, la acción no se pinta. El permiso es el de la Policy.
                Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->link()
                    ->authorize('view')
                    ->visible(fn () => Route::has('usuarios-hc.show'))
                    ->url(fn (UsuarioHc $record) => route('usuarios-hc.show', $record)),
                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square')
                    ->link()
                    ->authorize('update')
                    ->visible(fn () => Route::has('usuarios-hc.edit'))
                    ->url(fn (UsuarioHc $record) => route('usuarios-hc.edit', $record)),
            ])
            ->emptyStateHeading(fn () => $this->hasTableSearch() ? 'Nadie coincide con la búsqueda' : 'Todavía no hay personas registradas')
            ->emptyStateDescription(fn () => $this->hasTableSearch() ? 'Prueba con otra parte del nombre o del alias, el documento sin puntos o el número de ficha.' : null);
    }

    /** El alias y, si la búsqueda casó con un dato anterior, la marca. Nunca el dato anterior. */
    private function debajoDelNombre(UsuarioHc $record): ?HtmlString
    {
        $lineas = [];

        if (filled($record->alias)) {
            $lineas[] = '<span class="block">Alias: '.e($record->alias).'</span>';
        }

        $anterior = $this->hasTableSearch() ? $record->coincidenciaAnterior($this->getTableSearch()) : null;
        if ($anterior !== null && isset(self::MARCAS[$anterior])) {
            $lineas[] = '<span class="block font-medium text-[var(--warning)]">'.e(self::MARCAS[$anterior]).'</span>';
        }

        return $lineas === [] ? null : new HtmlString(implode('', $lineas));
    }

    /**
     * Las opciones del filtro: los componentes de la dependencia que tienen el
     * módulo. Salto de ComponenteScope a propósito y por escrito (plan D1,
     * docs/FILAMENT.md regla 2); el de dependencia sigue puesto. El módulo se
     * mira en PHP, no con una consulta JSON (lección de SQLite y MySQL).
     *
     * @return array<int, string>
     */
    public static function componentesConElModulo(): array
    {
        return Componente::withoutGlobalScope(ComponenteScope::class)
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Componente $componente) => $componente->tieneModulo('usuarios_hc'))
            ->pluck('nombre', 'id')
            ->all();
    }

    /** Tope de la búsqueda (UsuarioHc::BUSQUEDA_MAX_CARACTERES): Filament no deja cambiar el maxlength de su campo. */
    public function updatedTableSearch(): void
    {
        $this->recortarBusqueda();

        $this->guardarBusquedaDeFilament();
    }

    /** La búsqueda que vuelve de la sesión no pasa por updatedTableSearch: también se recorta aquí. */
    public function bootedInteractsWithTable(): void
    {
        $this->arrancarTablaDeFilament();

        $this->recortarBusqueda();
    }

    private function recortarBusqueda(): void
    {
        $this->tableSearch = mb_substr((string) $this->tableSearch, 0, UsuarioHc::BUSQUEDA_MAX_CARACTERES);
    }

    /**
     * Livewire acepta cualquier valor para el tamaño de página: lo que no esté
     * en POR_PAGINA (500, «all», un texto) se sirve como 25.
     */
    public function getTableRecordsPerPage(): int
    {
        $pedido = is_numeric($this->tableRecordsPerPage) ? (int) $this->tableRecordsPerPage : null;

        return in_array($pedido, self::POR_PAGINA, true) ? $pedido : self::POR_PAGINA[0];
    }

    /**
     * El filtro de componente solo acepta un id de los que ofrece; otro valor
     * (un arreglo, un id de otra dependencia o de Calle) se quita antes de
     * guardarlo en la sesión o pintar la tabla. Sin esto, un arreglo daba 500.
     */
    public function updatedTableFilters(): void
    {
        $valor = data_get($this->tableFilters, 'componente_registro_id.value');

        if (filled($valor) && ! (is_scalar($valor) && array_key_exists((int) $valor, self::componentesConElModulo()))) {
            data_set($this->tableFilters, 'componente_registro_id.value', null);
        }

        $this->guardarFiltrosDeFilament();
    }

    /** Búsqueda y filtros guardados por dependencia: un componente de otra no queda filtrando aquí. */
    public function getTableSessionKey(string $key, bool $isTenantScoped = false): string
    {
        return $this->llaveDeSesionDeFilament('dependencia-'.app(ContextoDependencia::class)->id().'.'.$key, $isTenantScoped);
    }

    public function render(): View
    {
        return view('livewire.tabla-filament');
    }
}
