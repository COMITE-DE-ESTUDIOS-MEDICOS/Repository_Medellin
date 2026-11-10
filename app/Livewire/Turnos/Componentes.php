<?php

namespace App\Livewire\Turnos;

use App\Enums\AccionAuditoria;
use App\Models\Componente;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Calle, Básica, Estabilización… con sus reglas, cargos y nodos. Crear uno es
 * lo único que hace falta para llevar los turnos a otro equipo: sus
 * diferencias van en las reglas. Tabla y panel lateral de Filament dentro
 * de Documenta. De Coordinación en adelante (ComponentePolicy).
 *
 * Los nodos no se borran: se desactivan, y siguen existiendo para lo que ya
 * se marcó en ellos.
 */
class Componentes extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Componente::query()->withCount(['nodos', 'colaboradores']))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label('Componente')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('nodos_count')->label('Nodos')->badge()->color('gray')->sortable(),
                TextColumn::make('colaboradores_count')->label('Personas')->sortable(),
                TextColumn::make('jornada')
                    ->label('Jornada')
                    ->state(fn (Componente $c) => $c->regla('jornada_horas').' h · tolerancia '.$c->regla('tolerancia_min').' min'),
                TextColumn::make('cargos')
                    ->label('Cargos')
                    ->state(fn (Componente $c) => $c->cargos() ?: null)
                    ->badge()
                    ->placeholder('Libres')
                    ->toggleable(),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Activo')->default(true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo componente')
                    ->model(Componente::class)
                    ->authorize('create', Componente::class)
                    ->slideOver()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->schema(self::formulario())
                    ->mutateDataUsing(fn (array $data) => self::preparar($data) + [
                        'dependencia_id' => app(ContextoDependencia::class)->requerida()->id,
                        'activo' => true,
                    ])
                    ->after(fn (Componente $record) => app(Auditor::class)->registrar(
                        AccionAuditoria::ComponenteGuardado, $record, "Creó el componente {$record->nombre}",
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('update')
                    ->slideOver()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->schema(self::formulario())
                    // El tope por cargo se guarda como mapa; el Repeater lo edita como filas.
                    ->mutateRecordDataUsing(function (array $data) {
                        $data['config']['horas_max_por_cargo'] = collect($data['config']['horas_max_por_cargo'] ?? [])
                            ->map(fn ($horas, $cargo) => ['cargo' => $cargo, 'horas' => $horas])->values()->all();

                        return $data;
                    })
                    ->mutateDataUsing(fn (array $data, Componente $record) => self::preparar($data, $record))
                    ->after(fn (Componente $record) => app(Auditor::class)->registrar(
                        AccionAuditoria::ComponenteGuardado, $record, "Actualizó el componente {$record->nombre}",
                        ['cambios' => array_keys($record->getChanges())],
                    )),
            ])
            ->emptyStateHeading('Aún no hay componentes')
            ->emptyStateDescription('Crea Calle, Básica, Estabilización… con sus reglas y sus nodos.');
    }

    /** El mismo formulario para crear y para editar. */
    public static function formulario(): array
    {
        return [
            Section::make('Componente')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->placeholder('Ej: Estabilización')
                        ->required()
                        ->maxLength(100)
                        ->rule(fn (?Componente $record) => self::nombreUnico($record)),
                    TagsInput::make('config.cargos')
                        ->label('Cargos')
                        ->placeholder('Escribe un cargo y presiona Enter')
                        ->helperText('Los que se eligen de una lista al registrar a una persona de este componente. Vacío: cargo libre.')
                        ->default([])
                        ->formatStateUsing(fn ($state) => $state ?? []),
                    TagsInput::make('config.cargos_supervision')
                        ->label('Cargos que supervisan')
                        ->placeholder('Ej: Apoyo al control territorial')
                        ->helperText('Quienes tienen estos cargos pueden registrar supervisiones territoriales con su cédula.')
                        ->default([])
                        ->formatStateUsing(fn ($state) => $state ?? []),
                    Toggle::make('activo')
                        ->label('Componente activo')
                        ->helperText('Inactivo, sus enlaces dejan de funcionar. Su historia se conserva.')
                        ->visibleOn('edit'),
                ]),

            Section::make('Jornada')
                ->description('Lo que se compara con el trabajo real de cada turno.')
                ->columns(2)
                ->schema([
                    self::regla('jornada_horas', 'Jornada esperada (horas)', 1, 24, 'Trabajo neto esperado, sin el descanso. Más o menos que esto, pasada la tolerancia, sale como horas extra o turno corto.'),
                    self::regla('tolerancia_min', 'Tolerancia (minutos)', 0, 240, 'Margen antes de contar un turno como corto o como tardanza.'),
                    self::regla('tolerancia_extra_min', 'Tolerancia de horas extra (minutos)', 40, 60, 'Salir hasta tanto después del fin del turno no cuenta como hora extra. También es el margen con que se cierra solo un turno sin salida.'),
                    self::regla('horas_max_turno', 'Duración máxima del turno (horas)', 1, 24, 'Si el sistema no reconoce el turno, una entrada sin salida se cierra a estas horas y queda como novedad. Si lo reconoce, se cierra al fin del turno más la tolerancia de horas extra.')
                        ->gte('config.jornada_horas'),
                    // DOC-21 (CA-08): se guarda como cargo => horas (preparar()).
                    Repeater::make('config.horas_max_por_cargo')
                        ->label('Duración máxima por cargo')
                        ->helperText('Para estos cargos, el turno sin salida se cierra a estas horas de la entrada en vez de la duración máxima general.')
                        ->defaultItems(0)
                        ->addActionLabel('Agregar cargo')
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema([
                            Select::make('cargo')
                                ->label('Cargo')
                                // Los cargos del componente, como están ahora en el formulario.
                                ->options(fn ($get) => collect((array) $get('../../cargos'))->filter()->mapWithKeys(fn ($c) => [$c => $c])->all())
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            TextInput::make('horas')->label('Horas')->integer()->required()->minValue(1)->maxValue(24),
                        ]),
                    self::regla('turnos_por_dia', 'Turnos por día', 1, 6, 'Cumplidos, el enlace ya no deja marcar ese día.'),
                    self::regla('salida_minima_min', 'Salida mínima (minutos)', 0, 240, 'Volver a marcar antes de esto, después de la entrada, no cuenta como salida: es un reintento. Si alguien se va antes, Coordinación agrega la salida.'),
                ]),

            Section::make('Descanso no remunerado')
                ->description('Por tramos de permanencia (Talento Humano). Lo que se descuenta no cuenta como trabajo.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    self::regla('sin_descanso_hasta_h', 'Turno corto: hasta (horas)', 0, 12, 'Hasta aquí no se descuenta nada: una pausa corta se paga como trabajo.')
                        ->live(onBlur: true),
                    self::regla('descanso_parcial_min', 'Turno medio: descuento (minutos)', 0, 240, 'Entre el turno corto y el largo.')
                        ->lte('config.descanso_min')->live(onBlur: true),
                    self::regla('descanso_completo_desde_h', 'Turno largo: más de (horas)', 1, 24, 'Desde aquí se descuenta el descanso completo.')
                        ->gte('config.sin_descanso_hasta_h')->live(onBlur: true),
                    self::regla('descanso_min', 'Turno largo: descuento (minutos)', 0, 240, 'El descanso completo de un turno largo.')
                        ->live(onBlur: true),
                    // Lo que da con estos valores, turno por turno: así no hay que hacer la cuenta.
                    Text::make(fn ($get, ?Componente $record) => self::ejemploDescanso($get, $record))
                        ->columnSpanFull(),
                ]),

            Section::make('Turno automático')
                ->description('Cómo se reconoce el turno por la hora de entrada y cuándo avisar a quien no marcó.')
                ->columns(3)
                ->collapsible()
                ->schema([
                    self::regla('reconocer_antes_min', 'Reconoce desde (min antes)', 0, 600, 'Una entrada hasta tantos minutos antes del inicio cuenta para ese turno.'),
                    self::regla('reconocer_despues_min', 'Reconoce hasta (min después)', 0, 720, 'Pasado este margen, ya no se reconoce como ese turno.'),
                    self::regla('aviso_sin_entrada_min', 'Aviso «no registró entrada» (min)', 5, 600, 'Tanto tiempo después del inicio, sin entrada, sale el aviso.'),
                ]),

            Section::make('Al marcar')
                ->columns(3)
                ->collapsible()
                ->schema([
                    self::casilla('foto_obligatoria', 'Foto obligatoria', 'Con sello de hora y ubicación.'),
                    self::casilla('ubicacion_obligatoria', 'Ubicación obligatoria', 'Si no, marca igual y queda como novedad.'),
                    self::casilla('autoregistro', 'Se registran solas', 'La primera vez llenan sus datos. Si no, Coordinación las registra antes.'),
                    self::regla('precision_minima_m', 'Precisión mínima (m)', 50, 20000, 'Una ubicación con más error que esto (p. ej. un iPhone con «Ubicación exacta» apagada) se toma como imprecisa: no se juzga fuera de zona y queda como novedad.'),
                ]),

            Section::make('Nodos')
                ->description('Arrástralos para ordenarlos. No se borran: se desactivan.')
                ->schema([
                    Repeater::make('nodos')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn('orden')
                        ->reorderableWithDragAndDrop()
                        ->deletable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state) => ($state['nombre'] ?? 'Nodo nuevo').(($state['activo'] ?? true) ? '' : ' (inactivo)'))
                        ->addActionLabel('Agregar nodo')
                        ->columns(6)
                        ->schema([
                            TextInput::make('nombre')->label('Nombre')->required()->maxLength(100)->columnSpan(2),
                            // La zona va completa o no va: un centro sin radio no sirve para juzgar si una marca quedó lejos.
                            TextInput::make('lat')->label('Latitud')->numeric()->minValue(-90)->maxValue(90)->requiredWith('lng,radio_m'),
                            TextInput::make('lng')->label('Longitud')->numeric()->minValue(-180)->maxValue(180)->requiredWith('lat,radio_m'),
                            TextInput::make('radio_m')->label('Radio (m)')->integer()->minValue(10)->maxValue(100000)->requiredWith('lat,lng'),
                            Toggle::make('activo')->label('Activo')->default(true)->inline(false),
                        ]),
                ]),
        ];
    }

    /** Un número de las reglas del componente (config), con su valor por defecto. */
    /**
     * «Con estos valores: T4 (4 h en el sitio) → 4 h de trabajo · N1 (7 h 30 min) → 7 h…».
     * Con los turnos con horario del componente; al crearlo, con 4 h, 7 h 30 min y 12 h.
     */
    public static function ejemploDescanso($get, ?Componente $record): string
    {
        $prueba = new Componente(['config' => collect(['sin_descanso_hasta_h', 'descanso_parcial_min', 'descanso_completo_desde_h', 'descanso_min'])
            ->mapWithKeys(fn ($clave) => [$clave => (int) ($get("config.{$clave}") ?? Componente::REGLAS[$clave])])->all()]);

        $turnos = $record
            ? \App\Models\TipoTurno::where('componente_id', $record->id)->where('activo', true)->orderBy('orden')->get()
                ->filter(fn ($t) => $t->minutosDeHorario() !== null)
                ->map(fn ($t) => [$t->codigo, $t->minutosDeHorario()])
            : collect([['Turno de 4 h', 240], ['Turno de 7 h 30 min', 450], ['Turno de 12 h', 720]]);

        $horas = fn (int $m) => intdiv($m, 60).' h'.($m % 60 ? ' '.($m % 60).' min' : '');

        // Los que duran lo mismo, juntos: «N1, N1., T3, N7 (7 h 30 min en el sitio) → 7 h de trabajo».
        return 'Con estos valores: '.$turnos->groupBy(fn ($t) => $t[1])->sortKeys()
            ->map(fn ($grupo, $minutos) => $grupo->pluck(0)->join(', ')." ({$horas($minutos)} en el sitio) → {$horas($prueba->minutosTrabajados($minutos))} de trabajo")
            ->join(' · ').'.';
    }

    protected static function regla(string $clave, string $etiqueta, int $min, int $max, string $ayuda): TextInput
    {
        return TextInput::make("config.{$clave}")
            ->label($etiqueta)
            ->helperText($ayuda)
            ->integer()
            ->required()
            ->minValue($min)
            ->maxValue($max)
            ->default(Componente::REGLAS[$clave])
            ->formatStateUsing(fn ($state) => $state ?? Componente::REGLAS[$clave]);
    }

    protected static function casilla(string $clave, string $etiqueta, string $ayuda): Toggle
    {
        return Toggle::make("config.{$clave}")
            ->label($etiqueta)
            ->helperText($ayuda)
            ->default(Componente::REGLAS[$clave])
            ->formatStateUsing(fn ($state) => $state ?? Componente::REGLAS[$clave]);
    }

    /**
     * Antes de guardar: el slug sale del nombre, y las reglas van completas
     * (las que no están en el formulario conservan su valor o su defecto).
     */
    protected static function preparar(array $data, ?Componente $record = null): array
    {
        $config = array_replace(Componente::REGLAS, $record?->config ?? [], $data['config'] ?? []);
        $limpio = fn ($cargo) => trim(preg_replace('/\s+/', ' ', (string) $cargo));
        foreach (['cargos', 'cargos_supervision'] as $lista) {
            $config[$lista] = array_values(array_unique(array_filter(array_map($limpio, (array) ($config[$lista] ?? [])))));
        }
        // Del Repeater llegan filas {cargo, horas}; se guarda cargo => horas.
        $config['horas_max_por_cargo'] = collect((array) $config['horas_max_por_cargo'])
            ->mapWithKeys(fn ($v, $k) => is_array($v) ? [$limpio($v['cargo'] ?? '') => (int) ($v['horas'] ?? 0)] : [$k => (int) $v])
            ->filter(fn ($horas, $cargo) => $cargo !== '' && $horas > 0)
            ->all();

        return [
            ...$data,
            'nombre' => trim($data['nombre']),
            'slug' => Str::slug($data['nombre']),
            'config' => $config,
        ];
    }

    /** Dos componentes no se llaman igual en la misma dependencia (el slug es único). */
    protected static function nombreUnico(?Componente $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record) {
            $existe = Componente::where('slug', Str::slug((string) $value))
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                ->exists();

            if ($existe) {
                $fail('Ya existe un componente con ese nombre.');
            }
        };
    }

    public function render(): View
    {
        return view('livewire.tabla-filament');
    }
}
