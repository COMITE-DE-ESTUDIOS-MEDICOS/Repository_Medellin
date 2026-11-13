<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Ingreso por Authentik (OIDC). Authentik autentica, pero no da de alta: las
 * cuentas las crea un administrador en Documenta, que es de donde salen hacia
 * allá. Una identidad que Authentik conoce y esta base de datos no, no entra.
 */
class AuthentikController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('authentik')->redirect();
    }

    public function callback(Request $request, Auditor $auditor): RedirectResponse
    {
        try {
            $remoto = Socialite::driver('authentik')->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->rechazar('No se pudo completar el ingreso con Authentik. Inténtalo de nuevo.');
        }

        $local = $this->cuentaLocalDe($remoto);
        $sub = (string) $remoto->getId();

        if ($local === null) {
            // Queda el rastro: sin esto, un rechazo es indistinguible de una
            // falla de configuración cuando alguien llama a soporte.
            Log::warning('Authentik: identidad sin cuenta local', [
                'username' => $remoto->preferred_username,
                'sub' => $sub,
            ]);

            return $this->rechazar('Tu usuario no está registrado en Documenta. Contacta a un administrador.');
        }

        // La cuenta ya está ligada a otro `sub`: es otra cuenta de Authentik
        // con el mismo documento. No se reemplaza; lo resuelve un administrador.
        if (filled($local->authentik_sub) && $local->authentik_sub !== $sub) {
            Log::warning('Authentik: otro sub con el mismo documento', [
                'user_id' => $local->id,
                'username' => $remoto->preferred_username,
                'sub' => $sub,
            ]);

            return $this->rechazar('Tu usuario no está registrado en Documenta. Contacta a un administrador.');
        }

        // Mismo corte que el ingreso por contraseña: una cuenta desactivada
        // sigue desactivada aunque Authentik la dé por buena.
        if (! $local->activo) {
            return $this->rechazar('Tu cuenta está desactivada. Comunícate con el administrador.');
        }

        Auth::login($local);
        $request->session()->regenerate();
        // El primer ingreso deja ligado el `sub`; los siguientes entran por él.
        $local->forceFill(['ultimo_acceso_at' => now(), 'authentik_sub' => $local->authentik_sub ?: ($sub ?: null)])->save();

        $auditor->registrar(AccionAuditoria::Ingreso, $local, 'Ingreso por Authentik');

        return redirect()->intended(route('documentos.index'));
    }

    /**
     * A quién de esta base corresponde la identidad que devolvió Authentik
     * (DOC-22, CA-06 y CA-07).
     *
     * Primero por el `sub` del OIDC, que queda guardado desde el primer
     * ingreso. Si no está, por el documento (`preferred_username`),
     * normalizado igual que al guardarlo. Un documento que queda vacío no
     * busca nada: nunca puede caer en un usuario sin documento. El correo ya
     * no sirve para entrar.
     */
    protected function cuentaLocalDe(object $remoto): ?User
    {
        $sub = (string) $remoto->getId();

        if ($sub !== '' && $local = User::where('authentik_sub', $sub)->first()) {
            return $local;
        }

        // DOC-22: el usuario se compara TAL CUAL (solo sin espacios a los
        // lados): «adminDocumenta» no liga con «ADMINDOCUMENTA». La base puede
        // comparar sin distinguir mayúsculas (MySQL), así que se confirma en PHP.
        $usuario = trim((string) $remoto->preferred_username);

        // Una cédula escrita con puntos o espacios («1.017.000.111») es la
        // misma cédula: solo para usuarios hechos de dígitos.
        if (preg_match('/^[0-9][0-9.\s-]*$/', $usuario)) {
            $usuario = preg_replace('/\D/', '', $usuario);
        }

        return $usuario !== ''
            ? User::where('documento', $usuario)->get()->first(fn (User $u) => $u->documento === $usuario)
            : null;
    }

    /** El mensaje sale por el mismo bloque de errores que ya pinta la vista de ingreso. */
    protected function rechazar(string $mensaje): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['identificador' => $mensaje]);
    }
}
