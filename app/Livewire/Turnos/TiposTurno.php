<?php

namespace App\Livewire\Turnos;

use App\Models\Componente;
use App\Models\TipoTurno;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
 * El catálogo de códigos de la malla (N1, T3, N7, C13, D, I…) con su horario y
 * las horas que cuenta nómina. Tabla y ventanas de Filament dentro de una
 * página normal de Documenta.
 *
 * Fuera del panel, Filament no autoriza solo: cada acción lleva su authorize().
 */
class TiposTurno extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            // Componente lleva DependenciaScope: solo los de la dependencia actual.
            ->query(TipoTurno::query()->whereHas('componente')->with('componente'))
            ->paginated([25, 50, 100])
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->badge()
                    ->color(fn (TipoTurno $t) => $t->es_turno ? 'primary' : 'gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('horario')->label('Horario')->state(fn (TipoTurno $t) => $t->horario() ?: '—'),
                TextColumn::make('dias')->label('Días')->state(fn (TipoTurno $t) => $t->es_turno ? $t->diasLegibles() : '—'),
                TextColumn::make('horas_nomina')->label('Horas nómina')->numeric(1)->sortable(),
                TextColumn::make('componente.nombre')->label('Componente')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activo')->label('Activo')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('componente_id')->label('Componente')->options(fn () => self::componentes()),
                TernaryFilter::make('es_turno')
                    ->label('Tipo')
                    ->trueLabel('Turnos de trabajo')
                    ->falseLabel('Descansos y ausencias'),
                TernaryFilter::make('activo')->label('Activo')->default(true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo tipo de turno')
                    ->model(TipoTurno::class)
                    ->authorize('create', TipoTurno::class)
                    ->schema(self::formulario()),
            ])
            ->recordActions([
                EditAction::make()->authorize('update')->schema(self::formulario()),
                DeleteAction::make()->authorize('delete'),
            ])
            ->emptyStateHeading('Aún no hay tipos de turno');
    }

    /** El mismo formulario para crear y para editar. */
    public static function formulario(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('componente_id')
                    ->label('Componente')
                    ->options(fn () => self::componentes())
                    ->required()
                    ->native(false),
                TextInput::make('codigo')
                    ->label('Código')
                    ->helperText('El mismo que usa nómina.')
                    ->required()
                    ->maxLength(10)
                    ->placeholder('N1, T3, C13…')
                    ->unique(TipoTurno::class, 'codigo', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('componente_id', $get('componente_id'))),
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(255)->placeholder('Mañana, Nocturno, Descanso…'),
                Toggle::make('es_turno')
                    ->label('Es un turno de trabajo')
                    ->helperText('Apágalo para descanso, incapacidad, vacaciones, permisos…')
                    ->default(true)
                    ->live()
                    ->inline(false),
                TimePicker::make('hora_inicio')->label('Entra')->seconds(false)->visible(fn (Get $get) => (bool) $get('es_turno')),
                TimePicker::make('hora_fin')
                    ->label('Sale')
                    ->seconds(false)
                    ->helperText('Menor que la entrada = termina al día siguiente.')
                    ->visible(fn (Get $get) => (bool) $get('es_turno')),
                CheckboxList::make('dias')
                    ->label('Días')
                    ->options(TipoTurno::DIAS)
                    ->columns(7)
                    ->helperText('Ninguno marcado = todos los días.')
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => (bool) $get('es_turno')),
                Toggle::make('festivos')->label('También festivos')->inline(false)->visible(fn (Get $get) => (bool) $get('es_turno')),
                TextInput::make('horas_nomina')
                    ->label('Horas que cuenta nómina')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(24)
                    ->step(0.1)
                    ->default(0)
                    ->required(),
                TextInput::make('orden')->label('Orden en las listas')->numeric()->default(0),
                Toggle::make('activo')->label('Activo')->default(true)->inline(false),
            ]),
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
