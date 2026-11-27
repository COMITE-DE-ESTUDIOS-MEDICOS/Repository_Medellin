<?php

namespace App\Livewire\SeguridadCctv;

use App\Models\CasoCctv;
use App\Models\Derivacion;
use App\Models\Nodo;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Los casos de Seguridad CCTV (DOC-33): solo consulta. Tabla de Filament con
 * páginas y filtros dentro de la página de Documenta; cada fila abre la ficha.
 */
class Casos extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    private const ESTADOS = [
        CasoCctv::ABIERTO => 'Abierto',
        CasoCctv::ATENDIDO => 'Atendido',
        CasoCctv::CERRADO => 'Cerrado',
        CasoCctv::ANULADO => 'Anulado',
    ];

    public function table(Table $table): Table
    {
        return $table
            // CasoCctv lleva DependenciaScope y ComponenteScope: solo los de aquí.
            ->query(CasoCctv::query()->with(['nodo', 'asignado']))
            ->paginated([25, 50, 100])
            ->defaultSort('requerido_at', 'desc')
            ->recordUrl(fn (CasoCctv $record): string => route('admin.turnos.cctv.show', $record))
            ->columns([
                TextColumn::make('numero')
                    ->label('Caso')
                    ->formatStateUsing(fn (int $state): string => CasoCctv::codigoDe($state))
                    ->weight('medium')
                    ->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        CasoCctv::ABIERTO => 'warning',
                        CasoCctv::ATENDIDO => 'info',
                        CasoCctv::CERRADO => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('entidad')
                    ->label('Entidad')
                    ->state(fn (CasoCctv $record): string => $record->entidadLegible())
                    ->wrap(),
                TextColumn::make('requerido_at')
                    ->label('Requerimiento')
                    ->formatStateUsing(fn (Carbon $state): string => $state->fechaHora())
                    ->sortable(),
                TextColumn::make('comuna')
                    ->label('Comuna')
                    ->state(fn (CasoCctv $record): string => $record->nombreComuna())
                    ->sortable(),
                TextColumn::make('nodo.nombre')->label('Nodo')->placeholder('Sin nodo'),
                TextColumn::make('asignado.nombre')->label('Se le pasó a')->placeholder('Sin asignar'),
                TextColumn::make('tiempo_respuesta_min')
                    ->label('Tiempo de respuesta')
                    ->placeholder('—')
                    ->formatStateUsing(fn (int $state): string => Derivacion::duracion($state)),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(self::ESTADOS),
                SelectFilter::make('entidad')->label('Entidad')->options(CasoCctv::ENTIDADES),
                SelectFilter::make('comuna')
                    ->label('Comuna')
                    ->options(fn (): array => collect(config('repositorio.comunas'))
                        ->map(fn (string $nombre, int $numero): string => "{$numero} · {$nombre}")->all()),
                SelectFilter::make('nodo_id')
                    ->label('Nodo')
                    ->options(fn (): array => Nodo::whereHas('componente')->orderBy('orden')->pluck('nombre', 'id')->all()),
                Filter::make('requerimiento')
                    ->label('Fecha del requerimiento')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->requeridoEntre($data['desde'] ?? null, $data['hasta'] ?? null)),
            ])
            ->emptyStateHeading('Aún no hay casos');
    }

    public function render(): View
    {
        return view('livewire.tabla-filament');
    }
}
