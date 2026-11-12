<?php

namespace App\Services;

use App\Models\Aviso;
use App\Models\Dependencia;
use App\Models\User;
use App\Notifications\AvisoDelSistema;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Manda avisos por la campana: a una dependencia (todos o ciertos roles) o
 * a todo el sistema. Los de cada versión salen de CHANGELOG.md.
 */
class Avisos
{
    /**
     * @param  list<string>|null  $roles  valores de RolDependencia; null = todos
     */
    public function enviar(string $titulo, string $mensaje, ?Dependencia $dependencia = null, ?array $roles = null, ?User $autor = null, ?string $version = null): Aviso
    {
        $destinatarios = $this->destinatarios($dependencia, $roles);

        $aviso = Aviso::create([
            'dependencia_id' => $dependencia?->id,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'version' => $version,
            'roles' => $roles ?: null,
            'destinatarios' => $destinatarios->count(),
            'enviado_por' => $autor?->id,
        ]);

        // Por tandas: con todo el sistema de destinatario no se cargan miles
        // de notificaciones de una sola vez en memoria.
        $destinatarios->chunk(200)->each(fn ($tanda) => Notification::send($tanda, new AvisoDelSistema($aviso)));

        return $aviso;
    }

    /**
     * El aviso de la versión desplegada, si CHANGELOG.md tiene su sección y
     * aún no se anunció. Devuelve null si no hay nada que decir.
     */
    public function anunciarVersion(string $version, string $changelog): ?Aviso
    {
        if (Str::contains($version, ['-dev', '-beta']) || Aviso::where('version', $version)->exists()) {
            return null;
        }

        $novedades = $this->seccionDe($version, $changelog);

        if ($novedades === null) {
            return null;
        }

        return $this->enviar("Documenta se actualizó a {$version}", $novedades, version: $version);
    }

    /**
     * El texto bajo «## v2.4» hasta el siguiente «## », en una línea:
     * las viñetas se unen con «·», que es como cabe en la campana.
     */
    public function seccionDe(string $version, string $changelog): ?string
    {
        // Seguido de espacio o fin de línea, no de \b: entre «v2.» y «4» hay
        // límite de palabra, y «v2.» se habría tomado por «v2.4».
        $patron = '/^##\s+'.preg_quote($version, '/').'(?=[ \t]|$)[^\n]*\n(.*?)(?=^##\s|\z)/ms';

        if (! preg_match($patron, $changelog, $coincidencia)) {
            return null;
        }

        $lineas = collect(preg_split('/\R/', $coincidencia[1]))
            ->map(fn ($linea) => trim(ltrim(trim($linea), '-*• ')))
            ->filter();

        return $lineas->isEmpty() ? null : $lineas->join(' · ');
    }

    protected function destinatarios(?Dependencia $dependencia, ?array $roles)
    {
        if ($dependencia === null) {
            return User::where('activo', true)->get();
        }

        $usuarios = $dependencia->usuarios()->where('activo', true);

        // Sobre la relación y no dentro de when(): ahí llega la consulta suelta,
        // que no sabe de la tabla intermedia, y el filtro no hacía nada útil.
        if ($roles) {
            $usuarios->wherePivotIn('rol', $roles);
        }

        return $usuarios->get();
    }
}
