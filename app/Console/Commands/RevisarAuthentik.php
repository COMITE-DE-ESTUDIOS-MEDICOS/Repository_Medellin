<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthentikProvisioner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Solo lectura (DOC-22, PA-02): quién no podría entrar por Authentik con su
 * documento, y quién entra por el formulario de emergencia.
 */
class RevisarAuthentik extends Command
{
    protected $signature = 'authentik:revisar';

    protected $description = 'Lista los usuarios activos sin documento, sin cuenta de Authentik con ese documento, y los superadministradores (no cambia nada)';

    public function handle(AuthentikProvisioner $authentik): int
    {
        if (! $authentik->configurado()) {
            $this->error('Authentik no está configurado: faltan AUTHENTIK_BASE_URL, AUTHENTIK_API_TOKEN o AUTHENTIK_GRUPO_ID.');

            return self::FAILURE;
        }

        $filas = [];

        foreach (User::where('activo', true)->orderBy('name')->get() as $u) {
            $nota = match (true) {
                $u->es_superadmin => 'Superadministrador: entra por el formulario de emergencia',
                blank($u->documento) => 'Sin documento: no puede entrar por Authentik',
                default => $this->sinCuenta($authentik, $u->documento),
            };

            if ($nota !== null) {
                $filas[] = [$u->documento ?? '—', $u->name, $nota];
            }
        }

        $this->table(['Documento', 'Nombre', 'Observación'], $filas);
        $this->info(count($filas).' usuarios para revisar. No se cambió nada.');

        return self::SUCCESS;
    }

    protected function sinCuenta(AuthentikProvisioner $authentik, string $documento): ?string
    {
        try {
            return $authentik->buscar($documento) === null
                ? 'No hay cuenta en Authentik con ese documento como usuario'
                : null;
        } catch (Throwable $e) {
            return 'No se pudo consultar Authentik: '.$e->getMessage();
        }
    }
}
