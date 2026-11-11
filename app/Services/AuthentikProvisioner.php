<?php

namespace App\Services;

use App\Enums\AccionAuditoria;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Da y quita el acceso a Documenta en Authentik (DOC-22).
 *
 * Una persona tiene una sola cuenta de Authentik para todas las apps
 * (MedTech, Documenta…). Documenta no es dueña de esa cuenta: solo la crea si
 * no existe y la mete o la saca del grupo de Documenta de este entorno
 * (`services.authentik.grupo_id`). Estar en el grupo es poder entrar.
 *
 * Reglas:
 *
 * 1. Nunca se cambia una cuenta que ya existe: ni nombre, ni correo, ni
 *    username, ni estado, ni contraseña. Nunca se desactiva ni se borra: la
 *    persona perdería las otras apps.
 * 2. La contraseña la gestiona Authentik. Documenta no la envía nunca, ni al
 *    crear la cuenta.
 * 3. Nada de esto puede tumbar la gestión de usuarios. Si Authentik no
 *    contesta, la operación local se completa y el fallo queda en el log y en
 *    la auditoría. Por eso ningún método público de escritura lanza.
 */
class AuthentikProvisioner
{
    public function __construct(protected Auditor $auditor)
    {
    }

    /** Sin URL, sin token o sin grupo no se intenta nada contra Authentik. */
    public function configurado(): bool
    {
        return filled(config('services.authentik.base_url'))
            && filled(config('services.authentik.api_token'))
            && filled(config('services.authentik.grupo_id'));
    }

    /**
     * Deja a la persona en el grupo de Documenta, creando su cuenta solo si
     * no existe. Idempotente: guardar la ficha de nuevo es el reintento.
     *
     * @return bool true si quedó en el grupo
     */
    public function darAcceso(User $usuario): bool
    {
        if (! $this->configurado()) {
            return false;
        }

        $mensaje = null;

        if (blank($usuario->authentik_id)) {
            if (blank($usuario->documento)) {
                $this->anotarFallo("dar acceso a {$usuario->name} en Authentik: no tiene documento, que es su usuario allá", $usuario);

                return false;
            }

            try {
                $pk = $this->buscar($usuario->documento);

                if ($pk === null) {
                    $pk = $this->crearCuenta($usuario);
                    $mensaje = "Creó la cuenta de {$usuario->name} en Authentik y la agregó al grupo de Documenta";
                } else {
                    $mensaje = "Agregó a Documenta la cuenta que ya existía en Authentik de {$usuario->name}";
                }
            } catch (Throwable $e) {
                $this->anotarFallo("crear o encontrar la cuenta de {$usuario->name} en Authentik", $usuario, $e);

                return false;
            }

            $usuario->forceFill(['authentik_id' => $pk])->save();
        }

        try {
            $this->api()->post($this->grupo('add_user'), ['pk' => (int) $usuario->authentik_id])->throw();
        } catch (Throwable $e) {
            $this->anotarFallo("agregar a {$usuario->name} al grupo de Documenta en Authentik", $usuario, $e);

            return false;
        }

        if ($mensaje !== null) {
            $this->auditor->registrar(AccionAuditoria::AuthentikAprovisionado, $usuario, $mensaje);
        }

        return true;
    }

    /**
     * Saca a la persona del grupo de Documenta. Su cuenta de Authentik queda
     * intacta: nunca se desactiva ni se borra.
     */
    /** @return bool false solo si Authentik falló; sin configurar o sin cuenta no hay nada que quitar */
    public function quitarAcceso(User $usuario): bool
    {
        if (! $this->configurado()) {
            return true;
        }

        try {
            // Sin pk guardado pudo entrar al grupo a mano (la migración): se busca.
            $pk = $usuario->authentik_id ?: (filled($usuario->documento) ? $this->buscar($usuario->documento) : null);

            if ($pk === null) {
                return true;
            }

            $this->api()->post($this->grupo('remove_user'), ['pk' => (int) $pk])->throw();
        } catch (Throwable $e) {
            $this->anotarFallo("sacar a {$usuario->name} del grupo de Documenta en Authentik", $usuario, $e);

            return false;
        }

        $this->auditor->registrar(
            AccionAuditoria::AuthentikAprovisionado,
            $usuario,
            "Sacó a {$usuario->name} del grupo de Documenta en Authentik (su cuenta sigue activa para las otras apps)",
        );

        return true;
    }

    /**
     * El pk de la cuenta cuyo username es exactamente ese documento, o null.
     * Lanza si Authentik no contesta: quien llama decide.
     *
     * La API filtra por username, pero no se confía en que la coincidencia
     * sea exacta: se vuelve a comparar aquí.
     */
    public function buscar(string $documento): ?string
    {
        $resultados = $this->api()
            ->get('/api/v3/core/users/', ['username' => $documento])
            ->throw()
            ->json('results', []);

        $cuenta = collect($resultados)->first(fn ($c) => ($c['username'] ?? null) === $documento);

        return $cuenta ? (string) $cuenta['pk'] : null;
    }

    /** Crea la cuenta sin contraseña: la define la persona en Authentik. */
    protected function crearCuenta(User $usuario): string
    {
        $datos = [
            'username' => (string) $usuario->documento,
            'name' => (string) $usuario->name,
            'is_active' => true,
        ];

        if (filled($usuario->email)) {
            $datos['email'] = $usuario->email;
        }

        $pk = $this->api()->post('/api/v3/core/users/', $datos)->throw()->json('pk');

        return filled($pk) ? (string) $pk : throw new \RuntimeException('Authentik no devolvió el pk de la cuenta creada');
    }

    protected function grupo(string $accion): string
    {
        return '/api/v3/core/groups/'.config('services.authentik.grupo_id')."/{$accion}/";
    }

    protected function api(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.authentik.base_url'), '/'))
            ->withToken(config('services.authentik.api_token'))
            ->acceptJson()
            ->asJson()
            // Cortos a propósito: esto corre dentro de la petición del
            // administrador, y un Authentik caído no puede dejarlo colgado.
            ->connectTimeout(5)
            ->timeout(10);
    }

    /**
     * Deja el rastro del fallo en el log y en la auditoría, sin propagarlo.
     * En la auditoría porque quien administra usuarios no lee logs.
     */
    protected function anotarFallo(string $operacion, User $usuario, ?Throwable $e = null): void
    {
        Log::error("Authentik: no se pudo {$operacion}", [
            'usuario_id' => $usuario->id,
            'authentik_id' => $usuario->authentik_id,
            // Solo el estado HTTP: el cuerpo de la respuesta puede traer datos de la cuenta.
            'error' => $e instanceof \Illuminate\Http\Client\RequestException ? 'HTTP '.$e->response->status() : ($e ? class_basename($e) : null),
        ]);

        // Si la auditoría también falla, el log ya guardó lo importante.
        try {
            $this->auditor->registrar(
                AccionAuditoria::AuthentikFallo,
                $usuario,
                "No se pudo {$operacion}. Hay que reintentarlo: guarda de nuevo su ficha.",
            );
        } catch (Throwable $otro) {
            Log::error('Authentik: tampoco se pudo auditar el fallo', ['error' => $otro->getMessage()]);
        }
    }
}
