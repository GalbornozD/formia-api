<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_puede_listar_usuarios_de_cualquier_empresa_sin_membresia(): void
    {
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();
        $miembro = User::factory()->create();
        $miembro->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);

        $response = $this->actingAs($master, 'web')
            ->getJson("/api/empresas/{$empresa->id}/usuarios");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_master_puede_crear_usuario_en_cualquier_empresa(): void
    {
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();

        $response = $this->actingAs($master, 'web')->postJson("/api/empresas/{$empresa->id}/usuarios", [
            'email' => 'nuevo@formia.test',
            'nombre' => 'Nuevo',
            'apellido' => 'Usuario',
            'password' => 'password-larga-segura',
            'password_confirmation' => 'password-larga-segura',
            'role_id' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('code', 201)
            ->assertJsonPath('data.role_name', 'master');
        $this->assertDatabaseHas('users', ['email' => 'nuevo@formia.test', 'role_id' => 1]);
        $this->assertDatabaseHas('company_user', ['company_id' => $empresa->id]);
    }

    public function test_administrador_no_puede_crear_un_usuario_master(): void
    {
        $administrador = User::factory()->create();
        $empresa = Company::factory()->create();
        $administrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);

        $response = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->postJson("/api/empresas/{$empresa->id}/usuarios", [
                'email' => 'nuevo@formia.test',
                'nombre' => 'Nuevo',
                'apellido' => 'Usuario',
                'password' => 'password-larga-segura',
                'password_confirmation' => 'password-larga-segura',
                'role_id' => 1,
            ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@formia.test']);
    }

    public function test_administrador_no_puede_gestionar_usuarios_de_otra_empresa(): void
    {
        $administrador = User::factory()->create();
        $empresaPropia = Company::factory()->create();
        $empresaAjena = Company::factory()->create();
        $administrador->empresas()->attach($empresaPropia->id, ['permission' => 15, 'status' => true]);

        // Sin membresía en la empresa ajena: el middleware ya lo frena antes
        // de llegar a la policy.
        $response = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresaAjena->id)
            ->getJson("/api/empresas/{$empresaAjena->id}/usuarios");

        $response->assertStatus(403)->assertJsonPath('error_code', 'empresa_sin_acceso');
    }

    public function test_administrador_no_puede_editar_a_un_usuario_master(): void
    {
        $administrador = User::factory()->create();
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();
        $administrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);
        $master->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);

        $response = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->putJson("/api/empresas/{$empresa->id}/usuarios/{$master->id}", [
                'role_id' => 2,
            ]);

        $response->assertForbidden();
    }

    public function test_administrador_puede_degradar_a_un_usuario_a_un_rol_de_menor_poder(): void
    {
        $administrador = User::factory()->create();
        $empresa = Company::factory()->create();
        $otroAdministrador = User::factory()->create();
        $administrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);
        $otroAdministrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);

        $response = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->putJson("/api/empresas/{$empresa->id}/usuarios/{$otroAdministrador->id}", [
                'role_id' => 4,
            ]);

        $response->assertOk()->assertJsonPath('data.role_name', 'viewer');
        $this->assertDatabaseHas('users', ['id' => $otroAdministrador->id, 'role_id' => 4]);
    }

    public function test_administrador_no_puede_asignar_el_rol_master_al_actualizar(): void
    {
        $administrador = User::factory()->create();
        $empresa = Company::factory()->create();
        $miembro = User::factory()->create();
        $administrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);
        $miembro->empresas()->attach($empresa->id, ['permission' => 0, 'status' => true]);

        $response = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->putJson("/api/empresas/{$empresa->id}/usuarios/{$miembro->id}", [
                'role_id' => 1,
            ]);

        $response->assertUnprocessable();
    }

    public function test_administrador_puede_editar_y_eliminar_un_usuario_de_su_empresa(): void
    {
        $administrador = User::factory()->create();
        $empresa = Company::factory()->create();
        $miembro = User::factory()->create();
        $administrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);
        $miembro->empresas()->attach($empresa->id, ['permission' => 0, 'status' => true]);

        $update = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->putJson("/api/empresas/{$empresa->id}/usuarios/{$miembro->id}", [
                'nombre' => 'Editado',
                'permisos' => ['leer', 'crear'],
            ]);

        $update->assertOk()
            ->assertJsonPath('data.first_name', 'Editado')
            ->assertJsonPath('data.permissions', ['leer', 'crear']);

        // El UPDATE de la membresía de $miembro no debe afectar la fila de
        // $administrador en el mismo company_user (regresión: el pivot no
        // tiene columna `id`, así que un save() mal scopeado pega en todas
        // las filas de la tabla en vez de solo la del usuario editado).
        $this->assertDatabaseHas('company_user', [
            'user_id' => $administrador->id,
            'company_id' => $empresa->id,
            'permission' => 15,
        ]);

        $delete = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->deleteJson("/api/empresas/{$empresa->id}/usuarios/{$miembro->id}");

        $delete->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseMissing('company_user', ['user_id' => $miembro->id, 'company_id' => $empresa->id]);
        // El usuario global sigue existiendo: eliminar solo saca la membresía.
        $this->assertDatabaseHas('users', ['id' => $miembro->id]);
    }
}
