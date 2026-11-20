<?php

namespace App\Livewire\Turnos;

use App\Models\Componente;
use App\Models\Marcacion;
use App\Models\Nodo;
use App\Services\Turnos\Marcador;
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
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * Quién está trabajando ahora: entradas sin salida, la más antigua primero.
 * Solo consulta (no tiene acciones): tabla de Filament con páginas, búsqueda
 * y filtros dentro de la página de Documenta.
 */
class EnTurno extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            // Marcacion lleva DependenciaScope: solo las de la dependencia actual.
            ->query(Marcacion::entradasAbiertas()->with(['colaborador', 'nodo', 'componente', 'tipoTurno', 'puntoCritico']))
            ->paginated([25, 50, 100])
            ->defaultSort('marcada_at')
            ->columns([
                TextColumn::make('colaborador.nombre')
                    ->label('Persona')
                    ->weight('medium')
                    ->description(fn (Marcacion $m) => 'CC '.$m->colaborador->documento.($m->colaborador->cargo ? ' · '.$m->colaborador->cargo : ''))
                    ->url(fn (Marcacion $m) => auth()->user()->can('view', $m->colaborador) ? route('admin.turnos.colaboradores.show', $m->colaborador) : null)
                    ->searchable(query: fn ($query, $search) => $query->whereHas('colaborador', fn ($q) => $q
                        ->where('nombre', 'like', "%{$search}%")->orWhere('documento', 'like', "%{$search}%"))),
                TextColumn::make('donde')
                    ->label('Dónde')
                    ->state(fn (Marcacion $m) => $m->componente->nombre.($m->nodo ? ' · '.$m->nodo->nombre : ''))
                    ->description(fn (Marcacion $m) => match (true) {
                        $m->puntoCritico === null => null,
                        $m->puntoCritico->urlMapa() === null => $m->puntoCritico->nombre,
                        default => new HtmlString('<a href="'.e($m->puntoCritico->urlMapa()).'" target="_blank" rel="noopener" class="text-[var(--primary)] hover:underline">'.e($m->puntoCritico->nombre).'</a>'),
                    }),
                TextColumn::make('tipoTurno.codigo')
                    ->label('Turno')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sin reconocer')
                    ->description(fn (Marcacion $m) => $m->tipoTurno === null ? null : new HtmlString(e($m->tipoTurno->horario())
                        .($m->minutos_tarde > $m->componente->regla('tolerancia_min')
                            ? '<span class="block font-medium text-[var(--warning)]">'.$m->minutos_tarde.' min tarde</span>' : ''))),
                TextColumn::make('marcada_at')
                    ->label('Entró')
                    ->sortable()
                    ->formatStateUsing(fn (Marcacion $m) => ($m->marcada_at->isToday() ? 'Hoy' : $m->marcada_at->format('d/m')).' '.$m->marcada_at->hora())
                    ->description(fn (Marcacion $m) => $m->sin_ubicacion ? 'Sin ubicación' : null),
                TextColumn::make('lleva')
                    ->label('Lleva')
                    ->state(fn (Marcacion $m) => intdiv($m->minutosDesde(), 60).' h '.($m->minutosDesde() % 60).' min')
                    // DOC-21: pasado su tope (cargo o turno), el cierre automático lo dará por «sin salida».
                    ->color(fn (Marcacion $m) => app(Marcador::class)->vencida($m) ? 'danger' : 'gray')
                    ->description(fn (Marcacion $m) => app(Marcador::class)->vencida($m) ? 'Pasó del máximo del turno' : null),
            ])
            ->filters([
                SelectFilter::make('componente_id')
                    ->label('Componente')
                    ->options(fn () => Componente::activos()->orderBy('nombre')->pluck('nombre', 'id')->all()),
                SelectFilter::make('nodo_id')
                    ->label('Nodo')
                    ->options(fn () => Nodo::whereHas('componente')->orderBy('orden')->pluck('nombre', 'id')->all()),
            ])
            ->emptyStateHeading('Nadie está en turno ahora mismo');
    }

    public function render(): View
    {
        return view('livewire.tabla-filament');
    }
}
