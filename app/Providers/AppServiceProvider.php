<?php

namespace App\Providers;

use App\Http\Middleware\EstablecerDependencia;
use App\Http\Middleware\VerificarUsuarioActivo;
use App\Services\ContextoDependencia;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SocialiteProviders\Authentik\Provider as AuthentikProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una sola instancia por petición: el middleware la llena y
        // controladores, policies y global scopes la leen.
        $this->app->singleton(ContextoDependencia::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Horas en pantalla en 12 h, como se dicen aquí: «5:09 p. m.». Los
        // Excel para Nómina siguen en 24 h (se ordenan y se calculan).
        Carbon::macro('hora', fn (bool $segundos = false): string => $this->format($segundos ? 'g:i:s' : 'g:i').($this->format('A') === 'AM' ? ' a. m.' : ' p. m.'));
        Carbon::macro('fechaHora', fn (bool $segundos = false): string => $this->format('d/m/Y').' '.$this->hora($segundos));

        // Cada clic de Livewire (filtrar, paginar, guardar en una tabla de
        // Filament) vuelve a pasar por estos: sin el de dependencia,
        // DependenciaScope no filtraría y se verían datos de todas.
        Livewire::addPersistentMiddleware([
            VerificarUsuarioActivo::class,
            EstablecerDependencia::class,
        ]);

        // Las piezas de Filament (tablas, ventanas) con el azul de Documenta.
        FilamentColor::register([
            'primary' => Color::hex('#1f4e79'),
            'danger' => Color::Red,
            'gray' => Color::Slate,
            'info' => Color::Sky,
            'success' => Color::Green,
            'warning' => Color::Amber,
        ]);

        $this->limitarRecepcionExterna();

        // SocialiteProviders no se autodescubre: el driver 'authentik' solo
        // existe si se engancha aquí.
        Event::listen(function (SocialiteWasCalled $evento) {
            $evento->extendSocialite('authentik', AuthentikProvider::class);
        });
    }

    /**
     * Límites de la vía pública. Un enlace legítimo sube unos pocos archivos
     * al día, no cientos.
     */
    protected function limitarRecepcionExterna(): void
    {
        // Abrir el formulario es barato: el límite existe para frenar el
        // barrido de tokens desde una misma IP.
        RateLimiter::for('envio-formulario', fn (Request $peticion) => Limit::perMinute(30)->by($peticion->ip()));

        // Al cargar se limita por IP y, además, por token: una IP compartida
        // —una alcaldía entera detrás de un NAT— no debe agotarle el cupo a
        // los demás remitentes, y un token filtrado no debe poder inundar
        // una bandeja desde muchas IP.
        // Una carga de 300 archivos llega en 6 tandas seguidas (y alguna puede
        // reintentarse): el tope deja pasar eso y sigue frenando una avalancha.
        RateLimiter::for('envio-carga', fn (Request $peticion) => [
            Limit::perMinute(20)->by('ip:'.$peticion->ip()),
            Limit::perMinute(20)->by('token:'.$peticion->route('token')),
        ]);

        // Preguntar por una cédula en un enlace compartido es lo que se usaría
        // para recorrer cédulas al azar: tope bajo por IP. Por token es más
        // alto, porque un QR de turno lo usa todo un equipo a la misma hora.
        RateLimiter::for('cedula-consulta', fn (Request $peticion) => [
            Limit::perMinute(20)->by('ip:'.$peticion->ip()),
            Limit::perMinute(120)->by('token:'.$peticion->route('token')),
        ]);

        // DOC-20: consultar un caso por su código desde el enlace. Estricto
        // (10 cada 10 min) por IP y por cédula, para que no se sondeen números.
        RateLimiter::for('caso-consulta', fn (Request $peticion) => [
            Limit::perMinutes(10, 10)->by('ip:'.$peticion->ip()),
            Limit::perMinutes(10, 10)->by('cedula:'.\App\Models\User::normalizarDocumento((string) $peticion->input('cedula'))),
        ]);

        RateLimiter::for('turno-marca', fn (Request $peticion) => [
            Limit::perMinute(10)->by('ip:'.$peticion->ip()),
            Limit::perMinute(120)->by('token:'.$peticion->route('token')),
        ]);
    }
}
