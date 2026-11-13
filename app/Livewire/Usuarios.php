<?php

namespace App\Livewire;

use App\Enums\AccionAuditoria;
use App\Enums\RolDependencia;
use App\Models\User;
use App\Services\Auditor;
use App\Services\AuthentikProvisioner;
use App\Services\ContextoDependencia;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
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
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Los usuarios con acceso a la dependencia activa: tabla de Filament dentro
 * de Documenta. Dar acceso y editar siguen en sus páginas (UsuarioController),
 * con la lógica de Authentik y de rangos; aquí se busca, se filtra y se
 * revoca. De Coordinación en adelante.
 */
class Usuarios extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        abort_unless(app(ContextoDependencia::class)->puedeGestionar(), 403, 'No tienes permiso para gestionar usuarios.');
    }

    public function table(Table $table): Table
    {
        $dependencia = app(ContextoDependencia::class)->requerida()->id;

        return $table
            // El rol sale de la pivote de esta dependencia, no de otra.
            ->query(User::query()
                ->join('dependencia_usuario as du', 'du.user_id', '=', 'users.id')
                ->where('du.dependencia_id', $dependencia)
                ->select('users.*', 'du.rol as rol_aqui', 'du.aprueba_novedades as aprueba_aqui'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Persona')
                    ->weight('semibold')
                    ->sortable()
                    ->description(fn (User $u) => collect([$u->documento ?? 'Sin usuario', $u->cargo])->filter()->join(' · '))
                    ->searchable(query: function (Builder $query, string $search) {
                        // El documento se busca normalizado: «1.234.567» desde una planilla también encuentra.
                        $query->where(fn ($s) => $s->where('users.name', 'like', "%{$search}%")
                            ->orWhere('users.email', 'like', "%{$search}%")
                            ->orWhere('users.documento', 'like', '%'.User::normalizarDocumento($search).'%'));
                    }),
                TextColumn::make('rol_aqui')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, User $u) => RolDependencia::tryFrom((string) $state)?->etiqueta().($u->es_superadmin ? ' · plataforma' : ''))
                    ->color(fn (?string $state) => match ($state) {
                        'administracion' => 'danger',
                        'coordinacion' => 'warning',
                        'edicion' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                IconColumn::make('aprueba_aqui')
                    ->label('Aprueba novedades')
                    ->boolean()
                    ->state(fn (User $u) => (bool) $u->aprueba_aqui || RolDependencia::tryFrom((string) $u->rol_aqui)?->puedeGestionar())
                    ->tooltip(fn (User $u) => RolDependencia::tryFrom((string) $u->rol_aqui)?->puedeGestionar() ? 'Por su rol' : null)
                    ->toggleable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    // Sin identidad en Authentik no hay ingreso, aunque la cuenta esté activa aquí.
                    ->state(fn (User $u) => ! $u->activo ? 'Desactivado' : (blank($u->authentik_id) ? 'Falta en Authentik' : 'Activo'))
                    ->color(fn (string $state) => match ($state) {
                        'Activo' => 'success',
                        'Falta en Authentik' => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (string $state) => $state === 'Falta en Authentik'
                        ? 'Documenta no pudo darle acceso en Authentik. Usa «Sincronizar con Authentik» para reintentarlo.' : null),
                TextColumn::make('ultimo_acceso_at')
                    ->label('Último acceso')
                    ->formatStateUsing(fn ($state) => $state?->fechaHora())
                    ->placeholder('Nunca')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('rol')
                    ->label('Rol')
                    ->options(collect(RolDependencia::cases())->mapWithKeys(fn ($r) => [$r->value => $r->etiqueta()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $rol) => $q->where('du.rol', $rol))),
                // La consulta lleva un join: la columna va con su tabla.
                TernaryFilter::make('activo')
                    ->label('Cuenta activa')
                    ->queries(
                        true: fn (Builder $query) => $query->where('users.activo', true),
                        false: fn (Builder $query) => $query->where('users.activo', false),
                    ),
                TernaryFilter::make('aprueba')
                    ->label('Aprueba novedades')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn ($s) => $s->where('du.aprueba_novedades', true)->orWhereIn('du.rol', ['coordinacion', 'administracion'])),
                        false: fn (Builder $query) => $query->where('du.aprueba_novedades', false)->whereNotIn('du.rol', ['coordinacion', 'administracion']),
                    ),
            ])
            ->headerActions([
                Action::make('dar-acceso')
                    ->label('Dar acceso')
                    ->icon('heroicon-m-user-plus')
                    ->url(route('admin.usuarios.create')),
            ])
            ->recordActions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square')
                    ->link()
                    ->url(fn (User $u) => route('admin.usuarios.edit', $u))
                    ->visible(fn (User $u) => $this->alcanza($u)),
                Action::make('revocar')
                    ->label('Revocar')
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->link()
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $u) => "¿Quitar el acceso de {$u->name}?")
                    ->modalDescription('Deja de entrar a esta dependencia. Su cuenta y lo que hizo se conservan; se le puede volver a dar acceso.')
                    ->modalSubmitActionLabel('Sí, revocar')
                    ->visible(fn (User $u) => $u->id !== auth()->id() && $this->alcanza($u))
                    ->action(fn (User $u) => $this->revocar($u)),
                Action::make('sincronizar')
                    ->label('Sincronizar con Authentik')
                    ->icon('heroicon-m-arrow-path')
                    ->link()
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $u) => "¿Sincronizar a {$u->name} con Authentik?")
                    ->modalDescription(fn (User $u) => $u->activo
                        ? 'Está activa: queda en el grupo de Documenta en Authentik. Si no tiene cuenta allá, se le crea sin contraseña.'
                        : 'Está inactiva: se retira del grupo de Documenta en Authentik. Su cuenta sigue activa para las demás aplicaciones.')
                    ->modalSubmitActionLabel('Sincronizar')
                    ->authorize(fn (User $u) => app(ContextoDependencia::class)->puedeGestionar() && $this->alcanza($u))
                    ->visible(fn (User $u) => $this->alcanza($u))
                    ->action(fn (User $u) => $this->sincronizar($u)),
            ])
            ->emptyStateHeading('Aún no hay usuarios habilitados')
            ->emptyStateDescription('Con «Dar acceso» se crea la cuenta o se suma una que ya existe.');
    }

    /**
     * Nadie gestiona a quien está por encima suyo, y la cuenta de plataforma
     * solo la toca otro superadmin (la misma regla de UsuarioController).
     */
    protected function alcanza(User $usuario): bool
    {
        $actor = auth()->user();
        $dependencia = app(ContextoDependencia::class)->id();

        if ($usuario->es_superadmin && ! $actor->es_superadmin) {
            return false;
        }

        return (RolDependencia::tryFrom((string) $usuario->rol_aqui)?->nivel() ?? 0) <= ($actor->rolEn($dependencia)?->nivel() ?? 0);
    }

    /** Quita el acceso a esta dependencia sin borrar la cuenta. */
    protected function revocar(User $usuario): void
    {
        abort_unless(app(ContextoDependencia::class)->puedeGestionar(), 403);
        abort_if($usuario->id === auth()->id() || ! $this->alcanza($usuario), 403);

        $usuario->dependencias()->detach(app(ContextoDependencia::class)->id());

        app(Auditor::class)->registrar(
            AccionAuditoria::UsuarioDesactivado,
            $usuario,
            "Revocó el acceso de {$usuario->name} a la dependencia",
        );

        // Sin ninguna dependencia ya no entra a Documenta: fuera del grupo.
        if (! $usuario->activo || $usuario->dependencias()->doesntExist()) {
            app(AuthentikProvisioner::class)->quitarAcceso($usuario);
        }

        Notification::make()->title("Se revocó el acceso de {$usuario->name}.")->success()->send();
    }

    /**
     * Lo mismo que guardar la ficha, para una persona: activa, al grupo;
     * inactiva, fuera. Nunca cambia ni desactiva la cuenta de Authentik.
     */
    protected function sincronizar(User $usuario): void
    {
        abort_unless(app(ContextoDependencia::class)->puedeGestionar() && $this->alcanza($usuario), 403);

        // Fuera de la tabla: el registro trae columnas del join que no son de users.
        $usuario = User::findOrFail($usuario->id);
        $authentik = app(AuthentikProvisioner::class);

        if (! $authentik->configurado()) {
            Notification::make()->title('Authentik no está configurado en este servidor.')->warning()->send();

            return;
        }

        $ok = $usuario->activo ? $authentik->darAcceso($usuario) : $authentik->quitarAcceso($usuario);

        if (! $ok) {
            Notification::make()
                ->title('Authentik: no se pudo sincronizar.')
                ->body($authentik->causaDelFallo())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($usuario->activo
                ? "Authentik: {$usuario->name} quedó en el grupo de Documenta."
                : "Authentik: {$usuario->name} quedó fuera del grupo de Documenta. Su cuenta sigue activa para las demás aplicaciones.")
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.tabla-filament');
    }
}
