<?php

namespace App\Livewire\Turnos;

use App\Models\Componente;
use App\Models\Nodo;
use App\Models\PuntoCritico;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;

/**
 * Las zonas a las que se asigna personal, con su ubicación para verlas en el
 * mapa. Tabla y ventanas de Filament dentro de una página de Documenta.
 */
class PuntosCriticos extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** «6.2518, -75.5636», como lo copia Google Maps o OpenStreetMap. */
    public const PATRON_COORDENADAS = '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/';

    public function table(Table $table): Table
    {
        return $table
            // Componente lleva DependenciaScope: solo los de la dependencia actual.
            ->query(PuntoCritico::query()->whereHas('componente')->with(['componente', 'nodo']))
            ->paginated([25, 50, 100])
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label('Punto crítico')->searchable()->sortable()->wrap(),
                TextColumn::make('nodo.nombre')->label('Nodo')->placeholder('—')->sortable(),
                TextColumn::make('ubicacion')
                    ->label('Ubicación')
                    ->state(fn (PuntoCritico $p) => $p->urlMapa() ? 'Ver en el mapa' : 'Sin ubicación')
                    ->color(fn (PuntoCritico $p) => $p->urlMapa() ? 'primary' : 'gray')
                    ->icon(fn (PuntoCritico $p) => $p->urlMapa() ? 'heroicon-o-map-pin' : null)
                    ->url(fn (PuntoCritico $p) => $p->urlMapa(), shouldOpenInNewTab: true),
                TextColumn::make('componente.nombre')->label('Componente')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('componente_id')->label('Componente')->options(fn () => self::componentes()),
                SelectFilter::make('nodo_id')
                    ->label('Nodo')
                    ->options(fn () => Nodo::whereHas('componente')->orderBy('orden')->pluck('nombre', 'id')->all()),
                TernaryFilter::make('ubicado')
                    ->label('Ubicación')
                    ->trueLabel('Con ubicación')
                    ->falseLabel('Sin ubicación')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('lat'),
                        false: fn ($q) => $q->whereNull('lat'),
                    ),
                TernaryFilter::make('activo')->label('Activo')->default(true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo punto crítico')
                    ->model(PuntoCritico::class)
                    ->authorize('create', PuntoCritico::class)
                    ->schema(self::formulario())
                    ->mutateDataUsing(fn (array $data) => self::conCoordenadas($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('update')
                    ->schema(self::formulario())
                    ->mutateRecordDataUsing(fn (array $data) => $data + [
                        'coordenadas' => isset($data['lat'], $data['lng']) ? "{$data['lat']}, {$data['lng']}" : null,
                    ])
                    ->mutateDataUsing(fn (array $data) => self::conCoordenadas($data)),
                DeleteAction::make()->authorize('delete'),
            ])
            ->emptyStateHeading('Aún no hay puntos críticos');
    }

    public static function formulario(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('componente_id')
                    ->label('Componente')
                    ->options(fn () => self::componentes())
                    ->required()
                    ->live()
                    ->native(false),
                Select::make('nodo_id')
                    ->label('Nodo')
                    ->helperText('Opcional: el nodo que suele cubrir este punto.')
                    ->options(fn (Get $get) => Nodo::where('componente_id', $get('componente_id'))->orderBy('orden')->pluck('nombre', 'id')->all())
                    ->native(false),
                TextInput::make('nombre')
                    ->label('Nombre del punto')
                    ->required()
                    ->maxLength(300)
                    ->columnSpanFull()
                    ->unique(PuntoCritico::class, 'nombre', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('componente_id', $get('componente_id'))),
                TextInput::make('coordenadas')
                    ->label('Coordenadas')
                    ->placeholder('6.2518, -75.5636')
                    ->helperText('En Google Maps: clic derecho sobre el punto y clic en las cifras para copiarlas.')
                    ->regex(self::PATRON_COORDENADAS)
                    ->validationMessages(['regex' => 'Escríbelas como «latitud, longitud», por ejemplo 6.2518, -75.5636.'])
                    ->columnSpanFull(),
                Toggle::make('activo')->label('Activo')->default(true),
            ]),
        ];
    }

    /** Pasa «lat, lng» a sus dos columnas (o las deja vacías). */
    public static function conCoordenadas(array $datos): array
    {
        $coincide = preg_match(self::PATRON_COORDENADAS, (string) ($datos['coordenadas'] ?? ''), $partes);
        unset($datos['coordenadas']);

        return [
            ...$datos,
            'lat' => $coincide ? (float) $partes[1] : null,
            'lng' => $coincide ? (float) $partes[2] : null,
        ];
    }

    protected static function componentes(): array
    {
        return Componente::activos()->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public function render(): View
    {
        return view('livewire.tabla-filament');
    }
}
