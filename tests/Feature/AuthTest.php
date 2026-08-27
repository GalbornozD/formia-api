<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_con_una_sola_empresa_la_autoselecciona(): void
    {
        $empresa = Company::factory()->create();
        $usuario = User::factory()->create(['password_hash' => 'password12345']);
        $usuario->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);

        $response = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.empresa_activa_id', $empresa->id)
            ->assertJsonPath('data.requiere_seleccion_empresa', false)
            ->assertJsonCount(1, 'data.empresas')
            ->assertJsonPath('meta.request_id', fn ($id) => is_string($id) && $id !== '');
    }

    public function test_login_con_multiples_empresas_exige_seleccion(): void
    {
        $empresaUno = Company::factory()->create();
        $empresaDos = Company::factory()->create();
        $usuario = User::factory()->create(['password_hash' => 'password12345']);
        $usuario->empresas()->attach($empresaUno->id, ['permission' => 0, 'status' => true]);
        $usuario->empresas()->attach($empresaDos->id, ['permission' => 0, 'status' => true]);

        $response = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.empresa_activa_id', null)
            ->assertJsonPath('data.requiere_seleccion_empresa', true)
            ->assertJsonCount(2, 'data.empresas');
    }

    public function test_login_con_password_incorrecta_incrementa_intentos_fallidos(): void
    {
        $usuario = User::factory()->create(['password_hash' => 'password12345']);

        $response = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password-incorrecta',
        ]);

        $response->assertUnprocessable();
        $this->assertSame(1, $usuario->fresh()->failed_login_attempts);
    }

    public function test_cuenta_se_bloquea_progresivamente_tras_repetidos_fallos(): void
    {
        $usuario = User::factory()->create(['password_hash' => 'password12345']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $usuario->email,
                'password' => 'password-incorrecta',
            ])->assertUnprocessable();
        }

        $usuario->refresh();
        $this->assertSame(5, $usuario->failed_login_attempts);
        $this->assertNotNull($usuario->locked_until);
        $this->assertTrue($usuario->locked_until->isFuture());

        // Incluso con la password correcta, la cuenta bloqueada rechaza el login
        // (todavía dentro del cupo del rate limiter, que es más alto que 5).
        $response = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password12345',
        ]);

        $response->assertUnprocessable();
    }

    public function test_rate_limiter_de_login_bloquea_tras_el_umbral(): void
    {
        $usuario = User::factory()->create(['password_hash' => 'password12345']);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', [
                'email' => $usuario->email,
                'password' => 'password-incorrecta',
            ]);
        }

        $response = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password12345',
        ]);

        $response->assertStatus(429);
    }

    public function test_seleccionar_empresa_rechaza_membresia_inexistente(): void
    {
        $usuario = User::factory()->create();
        $empresaAjena = Company::factory()->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/seleccionar-empresa', [
            'empresa_id' => $empresaAjena->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_seleccionar_empresa_activa_la_membresia_valida(): void
    {
        $usuario = User::factory()->create();
        $empresa = Company::factory()->create();
        $usuario->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);

        $response = $this->actingAs($usuario, 'web')->postJson('/api/seleccionar-empresa', [
            'empresa_id' => $empresa->id,
        ]);

        $response->assertOk()->assertJsonPath('data.id', $empresa->id);
        $this->assertSame($empresa->id, session('empresa_activa_id'));
    }

    public function test_logout_invalida_la_sesion(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario, 'web')->postJson('/api/logout');

        $response->assertOk();
        $this->assertGuest('web');
    }
}
