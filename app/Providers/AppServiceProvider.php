<?php

namespace App\Providers;

use App\Contracts\GuestNotificationChannel;
use App\Services\FormResponses\NullGuestNotificationChannel;
use App\Support\EmpresaContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmpresaContext::class);

        // Extensión futura: bindear aquí un canal real de email/sms/whatsapp
        // en lugar del no-op, sin tocar InvitationService/PublicationAudienceService.
        $this->app->bind(GuestNotificationChannel::class, NullGuestNotificationChannel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // NIST SP 800-63B: largo mínimo en vez de reglas de complejidad
        // forzada, más chequeo contra listas de contraseñas filtradas
        // (Have I Been Pwned, vía k-anonimity — Laravel nunca envía la
        // contraseña completa, solo un prefijo del hash SHA-1).
        Password::defaults(fn () => Password::min(12)->uncompromised());

        // Umbral más alto que el bloqueo progresivo de cuenta (5 intentos):
        // este limiter frena fuerza bruta rápida / distribuida entre varias
        // cuentas desde la misma IP; el bloqueo de cuenta cubre el intento
        // lento y dirigido a una sola cuenta.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip().'|'.strtolower((string) $request->input('email')));
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.strtolower((string) $request->input('email')));
        });

        RateLimiter::for('public-forms', function (Request $request) {
            $routeKey = (string) $request->route('publicationUuid', $request->route('responseUuid', $request->route('token', '')));

            return Limit::perMinute(60)->by($request->ip().'|'.$routeKey);
        });
    }
}
