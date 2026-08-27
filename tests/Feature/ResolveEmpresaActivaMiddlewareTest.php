<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\EmpresaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResolveEmpresaActivaMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'empresa.activa'])
            ->get('/test/recurso-de-empresa', function (Request $request) {
                return ApiResponse::success([
                    'empresa_id' => app(EmpresaContext::class)->empresaId(),
                ]);
            });
    }

    public function test_rechaza_sin_empresa_indicada(): void
    {
        $usuario = User::factory()->create();

        $response = $this->actingAs($usuario, 'web')->getJson('/test/recurso-de-empresa');

        $response->assertStatus(409)->assertJsonPath('error_code', 'empresa_no_seleccionada');
    }

    public function test_rechaza_empresa_sin_membresia_activa(): void
    {
        $usuario = User::factory()->create();
        $empresaAjena = Company::factory()->create();

        $response = $this->actingAs($usuario, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresaAjena->id)
            ->getJson('/test/recurso-de-empresa');

        $response->assertStatus(403)->assertJsonPath('error_code', 'empresa_sin_acceso');
    }

    public function test_rechaza_membresia_suspendida(): void
    {
        $usuario = User::factory()->create();
        $empresa = Company::factory()->create();
        $usuario->empresas()->attach($empresa->id, ['permission' => 0, 'status' => false]);

        $response = $this->actingAs($usuario, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->getJson('/test/recurso-de-empresa');

        $response->assertStatus(403);
    }

    public function test_resuelve_la_empresa_activa_via_header(): void
    {
        $usuario = User::factory()->create();
        $empresa = Company::factory()->create();
        $usuario->empresas()->attach($empresa->id, ['permission' => 0, 'status' => true]);

        $response = $this->actingAs($usuario, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->getJson('/test/recurso-de-empresa');

        $response->assertOk()->assertJsonPath('data.empresa_id', $empresa->id);
    }

    public function test_un_header_manipulado_hacia_una_empresa_ajena_no_pasa(): void
    {
        $usuario = User::factory()->create();
        $empresaPropia = Company::factory()->create();
        $empresaAjena = Company::factory()->create();
        $usuario->empresas()->attach($empresaPropia->id, ['permission' => 0, 'status' => true]);

        // El cliente puede mandar cualquier X-Empresa-Id; el backend igual
        // valida contra la membresía real — nunca confía en lo que declara.
        $response = $this->actingAs($usuario, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresaAjena->id)
            ->getJson('/test/recurso-de-empresa');

        $response->assertStatus(403);
    }

    public function test_master_no_necesita_membresia_ni_empresa_seleccionada(): void
    {
        $usuario = User::factory()->master()->create();

        $response = $this->actingAs($usuario, 'web')->getJson('/test/recurso-de-empresa');

        $response->assertOk()->assertJsonPath('data.empresa_id', null);
    }

    public function test_master_puede_resolver_cualquier_empresa_sin_membresia(): void
    {
        $usuario = User::factory()->master()->create();
        $empresa = Company::factory()->create();

        $response = $this->actingAs($usuario, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->getJson('/test/recurso-de-empresa');

        $response->assertOk()->assertJsonPath('data.empresa_id', $empresa->id);
    }
}
