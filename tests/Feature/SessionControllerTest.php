<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_solo_las_sesiones_vigentes_del_usuario_autenticado(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();

        $vigente = Session::factory()->for($usuario, 'usuario')->create();
        Session::factory()->for($usuario, 'usuario')->revocada()->create();
        Session::factory()->for($usuario, 'usuario')->expirada()->create();
        Session::factory()->for($otroUsuario, 'usuario')->create();

        $response = $this->actingAs($usuario, 'web')->getJson('/api/sesiones');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($vigente->id, $response->json('data.0.id'));
    }

    public function test_marca_cual_es_la_sesion_del_request_actual(): void
    {
        $usuario = User::factory()->create();
        $sesion = Session::factory()->for($usuario, 'usuario')->create();

        $response = $this->withSession([AuthService::CLAVE_SESION_ID => $sesion->id])
            ->actingAs($usuario, 'web')
            ->getJson('/api/sesiones');

        $response->assertOk()->assertJsonPath('data.0.es_actual', true);
    }

    public function test_no_puede_cerrar_la_sesion_de_otro_usuario(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();
        $sesionDeB = Session::factory()->for($usuarioB, 'usuario')->create();

        $this->actingAs($usuarioA, 'web')
            ->deleteJson("/api/sesiones/{$sesionDeB->id}")
            ->assertForbidden();

        $this->assertNull($sesionDeB->fresh()->revoked_at);
    }

    public function test_cerrar_la_sesion_actual_la_invalida_de_inmediato(): void
    {
        $usuario = User::factory()->create();
        $sesion = Session::factory()->for($usuario, 'usuario')->create();

        $response = $this->withSession([AuthService::CLAVE_SESION_ID => $sesion->id])
            ->actingAs($usuario, 'web')
            ->deleteJson("/api/sesiones/{$sesion->id}");

        $response->assertOk();
        $this->assertNotNull($sesion->fresh()->revoked_at);
        $this->assertGuest('web');
    }

    public function test_cerrar_otra_sesion_bloquea_el_siguiente_request_de_ese_dispositivo(): void
    {
        $usuario = User::factory()->create();
        $sesionOtroDispositivo = Session::factory()->for($usuario, 'usuario')->create();

        $this->withSession([AuthService::CLAVE_SESION_ID => $sesionOtroDispositivo->id])
            ->actingAs($usuario, 'web')
            ->getJson('/api/me')
            ->assertOk();

        $this->actingAs($usuario, 'web')
            ->deleteJson("/api/sesiones/{$sesionOtroDispositivo->id}")
            ->assertOk();

        $this->withSession([AuthService::CLAVE_SESION_ID => $sesionOtroDispositivo->id])
            ->actingAs($usuario, 'web')
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_una_sesion_activa_extiende_su_expiracion_en_cada_request(): void
    {
        $usuario = User::factory()->create();
        $sesion = Session::factory()->for($usuario, 'usuario')->create([
            'expires_at' => now()->addMinute(),
        ]);

        $this->withSession([AuthService::CLAVE_SESION_ID => $sesion->id])
            ->actingAs($usuario, 'web')
            ->getJson('/api/me')
            ->assertOk();

        $this->assertTrue($sesion->fresh()->expires_at->greaterThan(now()->addMinutes(100)));
    }

    public function test_solo_master_puede_listar_todas_las_sesiones(): void
    {
        $usuario = User::factory()->create(['role_id' => Role::ADMINISTRADOR]);
        Session::factory()->for($usuario, 'usuario')->create();

        $this->actingAs($usuario, 'web')
            ->getJson('/api/sesiones/todas')
            ->assertForbidden();
    }

    public function test_master_lista_todas_las_sesiones_paginadas(): void
    {
        $master = User::factory()->create(['role_id' => Role::MASTER]);
        $otroUsuario = User::factory()->create();
        Session::factory()->for($master, 'usuario')->count(2)->create();
        Session::factory()->for($otroUsuario, 'usuario')->count(3)->create();

        $response = $this->actingAs($master, 'web')
            ->getJson('/api/sesiones/todas?per_page=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data.data')
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.per_page', 3)
            ->assertJsonPath('data.current_page', 1);
    }

    public function test_master_filtra_todas_las_sesiones_por_usuario(): void
    {
        $master = User::factory()->create(['role_id' => Role::MASTER]);
        $buscado = User::factory()->create(['email' => 'ana.reyes@example.com']);
        $otro = User::factory()->create(['email' => 'otro@example.com']);
        Session::factory()->for($buscado, 'usuario')->create();
        Session::factory()->for($otro, 'usuario')->create();

        $response = $this->actingAs($master, 'web')
            ->getJson('/api/sesiones/todas?q=ana.reyes');

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.usuario.id', $buscado->id);
    }
}
