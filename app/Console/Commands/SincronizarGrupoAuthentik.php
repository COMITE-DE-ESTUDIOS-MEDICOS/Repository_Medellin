<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthentikProvisioner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Mete en el grupo de Documenta de este entorno a todos los usuarios activos
 * con documento y con alguna dependencia (DOC-22, migración). Con --simular
 * solo consulta y dice qué haría.
 */
class SincronizarGrupoAuthentik extends Command
{
    protected $signature = 'authentik:sincronizar-grupo {--simular : Solo dice a quién agregaría, sin cambiar nada}';

    protected $description = 'Agrega al grupo de Documenta en Authentik a los usuarios activos con documento y dependencia';

    public function handle(AuthentikProvisioner $authentik): int
    {
        if (! $authentik->configurado()) {
            $this->error('Authentik no está configurado: faltan AUTHENTIK_BASE_URL, AUTHENTIK_API_TOKEN o AUTHENTIK_GRUPO_ID.');

            return self::FAILURE;
        }

        $simular = (bool) $this->option('simular');

        $filas = User::where('activo', true)
            ->whereNotNull('documento')->where('documento', '!=', '')
            ->whereHas('dependencias')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [$u->documento, $u->name, $simular ? $this->queHaria($authentik, $u) : $this->hacer($authentik, $u)]);

        $this->table(['Documento', 'Nombre', $simular ? 'Haría' : 'Resultado'], $filas);
        $this->info(($simular ? 'Simulación: no se cambió nada. ' : '').$filas->count().' usuarios revisados.');

        return self::SUCCESS;
    }

    protected function queHaria(AuthentikProvisioner $authentik, User $u): string
    {
        if (filled($u->authentik_id)) {
            return 'Agregarlo al grupo (ya ligado)';
        }

        try {
            return $authentik->buscar($u->documento) === null
                ? 'Crear la cuenta sin contraseña y agregarla al grupo'
                : 'Agregar al grupo la cuenta que ya existe';
        } catch (Throwable $e) {
            return 'No se pudo consultar Authentik: '.$e->getMessage();
        }
    }

    protected function hacer(AuthentikProvisioner $authentik, User $u): string
    {
        return $authentik->darAcceso($u) ? 'En el grupo' : 'Falló (ver auditoría)';
    }
}
