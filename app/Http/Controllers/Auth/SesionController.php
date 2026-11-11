<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Autenticación mínima y a la medida: no hay registro público ni
 * recuperación por correo. Los usuarios los crea un administrador.
 * Desde DOC-22 todos entran por Authentik; este formulario es solo la
 * entrada de emergencia del superadministrador.
 */
class SesionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, Auditor $auditor): RedirectResponse
    {
        $request->validate([
            'identificador' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], attributes: ['identificador' => 'usuario', 'password' => 'contraseña']);

        // Se acepta el documento, el nombre de usuario o el correo: quien
        // entra cada mañana no debería tener que recordar cuál de los tres
        // eligió el administrador que lo dio de alta.
        $usuario = User::porIdentificador($request->input('identificador'));

        // Se entra en Auth::attempt siempre, también cuando el identificador no
        // existe: es él quien envuelve el intento en el «timebox» de Laravel,
        // que iguala la duración de todos los fallos. Un `$usuario !== null &&`
        // delante cortocircuitaría y se saltaría esa igualación —el intento con
        // un documento inexistente volvía en 2 ms y el de uno real en 200—, con
        // lo que el propio reloj diría qué cuentas están dadas de alta.
        // El id 0 no lo tiene nadie: la secuencia empieza en 1.
        $entro = Auth::attempt(
            ['id' => $usuario?->id ?? 0, 'password' => $request->input('password')],
            $request->boolean('recordarme'),
        );

        // DOC-22 (PA-01): el formulario propio queda solo para el
        // superadministrador, como entrada de emergencia si Authentik se cae.
        // Los demás entran por Authentik. El rechazo va después del attempt
        // (conserva el timebox) y con el mismo mensaje, para no revelar
        // quién es superadministrador.
        if ($entro && ! $request->user()->es_superadmin) {
            Auth::logout();
            $entro = false;
        }

        if (! $entro) {
            throw ValidationException::withMessages([
                'identificador' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        if (! $request->user()->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'identificador' => 'Tu cuenta está desactivada. Comunícate con el administrador.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['ultimo_acceso_at' => now()])->save();

        $auditor->registrar(AccionAuditoria::Ingreso, $request->user());

        return redirect()->intended(route('documentos.index'));
    }

    public function destroy(Request $request, Auditor $auditor): RedirectResponse
    {
        $auditor->registrar(AccionAuditoria::Salida, $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
