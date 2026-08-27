<?php

namespace Tests\Feature;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Password::defaults()->uncompromised() llama a la API de Have I Been
        // Pwned; se fakea para que los tests corran offline y determinísticos.
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }

    public function test_solicitar_reset_genera_token_y_notifica_sin_revelar_si_el_email_existe(): void
    {
        Notification::fake();

        $usuario = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', ['email' => $usuario->email]);
        $response->assertOk();

        $this->assertDatabaseCount('password_reset_tokens', 1);
        Notification::assertSentTo($usuario, ResetPasswordNotification::class);

        $respuestaInexistente = $this->postJson('/api/forgot-password', ['email' => 'no-existe@formia.test']);
        $respuestaInexistente->assertOk();
        $this->assertSame($response->json('data.message'), $respuestaInexistente->json('data.message'));
    }

    public function test_reset_con_token_valido_actualiza_la_password(): void
    {
        Notification::fake();

        $usuario = User::factory()->create(['password_hash' => 'password-vieja']);

        $tokenCapturado = null;
        Notification::fake();
        $this->postJson('/api/forgot-password', ['email' => $usuario->email]);

        Notification::assertSentTo($usuario, ResetPasswordNotification::class, function ($notification) use (&$tokenCapturado) {
            $reflexion = new \ReflectionProperty($notification, 'tokenPlano');
            $tokenCapturado = $reflexion->getValue($notification);

            return true;
        });

        $response = $this->postJson('/api/reset-password', [
            'email' => $usuario->email,
            'token' => $tokenCapturado,
            'password' => 'password-nueva-larga',
            'password_confirmation' => 'password-nueva-larga',
        ]);

        $response->assertOk();

        $loginConNueva = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password-nueva-larga',
        ]);
        $loginConNueva->assertOk();

        $this->assertSame(1, PasswordResetToken::whereNotNull('used_at')->count());
    }

    public function test_reset_con_token_invalido_es_rechazado(): void
    {
        $usuario = User::factory()->create();

        $response = $this->postJson('/api/reset-password', [
            'email' => $usuario->email,
            'token' => 'token-que-no-existe',
            'password' => 'password-nueva-larga',
            'password_confirmation' => 'password-nueva-larga',
        ]);

        $response->assertUnprocessable();
    }

    public function test_reset_con_token_expirado_es_rechazado(): void
    {
        $usuario = User::factory()->create();

        PasswordResetToken::create([
            'user_id' => $usuario->id,
            'token_hash' => hash('sha256', 'token-expirado'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => $usuario->email,
            'token' => 'token-expirado',
            'password' => 'password-nueva-larga',
            'password_confirmation' => 'password-nueva-larga',
        ]);

        $response->assertUnprocessable();
    }
}
