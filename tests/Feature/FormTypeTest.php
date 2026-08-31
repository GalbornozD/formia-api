<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FormType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_puede_listar_tipos_de_formulario_de_cualquier_empresa(): void
    {
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();
        FormType::factory()->for($empresa, 'empresa')->create();

        $response = $this->actingAs($master, 'web')
            ->getJson("/api/empresas/{$empresa->id}/tipos-formulario");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_master_puede_crear_tipo_de_formulario_en_cualquier_empresa(): void
    {
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();

        $response = $this->actingAs($master, 'web')->postJson("/api/empresas/{$empresa->id}/tipos-formulario", [
            'nombre' => 'Inspeccion de seguridad',
            'descripcion' => 'Checklist de seguridad en terreno',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Inspeccion de seguridad');
        $this->assertDatabaseHas('form_types', [
            'company_id' => $empresa->id,
            'name' => 'Inspeccion de seguridad',
        ]);
        $this->assertDatabaseHas('form_type_versions', [
            'form_type_id' => $response->json('data.id'),
            'version' => 1,
            'is_published' => false,
            'created_by' => $master->id,
            'updated_by' => $master->id,
        ]);
    }

    public function test_descripcion_enriquecida_se_sanitiza_y_puede_superar_255_caracteres(): void
    {
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();
        $contenido = str_repeat('Contenido de evaluación ', 20);

        $response = $this->actingAs($master, 'web')->postJson("/api/empresas/{$empresa->id}/tipos-formulario", [
            'nombre' => 'Evaluación enriquecida',
            'descripcion' => "<h1>Objetivo</h1><p onclick=\"alert(1)\"><strong>{$contenido}</strong></p><script>alert(2)</script>",
        ]);

        $response->assertCreated();
        $description = $response->json('data.description');
        $this->assertIsString($description);
        $this->assertStringContainsString('<strong>', $description);
        $this->assertStringNotContainsString('onclick', $description);
        $this->assertStringNotContainsString('script', $description);
        $this->assertGreaterThan(255, strlen($description));
    }

    public function test_descripcion_enriquecida_respeta_un_limite_tecnico_razonable(): void
    {
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();

        $this->actingAs($master, 'web')->postJson("/api/empresas/{$empresa->id}/tipos-formulario", [
            'nombre' => 'Descripción demasiado extensa',
            'descripcion' => str_repeat('a', 20001),
        ])->assertUnprocessable()->assertJsonValidationErrors('descripcion');
    }

    public function test_administrador_puede_crear_tipo_de_formulario_en_su_empresa(): void
    {
        $administrador = User::factory()->create();
        $empresa = Company::factory()->create();
        $administrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);

        $response = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->postJson("/api/empresas/{$empresa->id}/tipos-formulario", [
                'nombre' => 'Reporte de incidente',
            ]);

        $response->assertCreated();
    }

    public function test_administrador_no_puede_gestionar_tipos_de_formulario_de_otra_empresa(): void
    {
        $administrador = User::factory()->create();
        $empresaPropia = Company::factory()->create();
        $empresaAjena = Company::factory()->create();
        $administrador->empresas()->attach($empresaPropia->id, ['permission' => 15, 'status' => true]);

        $response = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresaAjena->id)
            ->getJson("/api/empresas/{$empresaAjena->id}/tipos-formulario");

        $response->assertStatus(403)->assertJsonPath('error_code', 'empresa_sin_acceso');
    }

    public function test_no_permite_nombres_duplicados_en_la_misma_empresa(): void
    {
        $master = User::factory()->master()->create();
        $empresa = Company::factory()->create();
        FormType::factory()->for($empresa, 'empresa')->create(['name' => 'Inspeccion']);

        $response = $this->actingAs($master, 'web')->postJson("/api/empresas/{$empresa->id}/tipos-formulario", [
            'nombre' => 'Inspeccion',
        ]);

        $response->assertUnprocessable();
    }

    public function test_permite_el_mismo_nombre_en_empresas_distintas(): void
    {
        $master = User::factory()->master()->create();
        $empresaA = Company::factory()->create();
        $empresaB = Company::factory()->create();
        FormType::factory()->for($empresaA, 'empresa')->create(['name' => 'Inspeccion']);

        $response = $this->actingAs($master, 'web')->postJson("/api/empresas/{$empresaB->id}/tipos-formulario", [
            'nombre' => 'Inspeccion',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('form_types', [
            'company_id' => $empresaB->id,
            'name' => 'Inspeccion',
        ]);
    }

    public function test_administrador_puede_editar_y_eliminar_un_tipo_de_su_empresa(): void
    {
        $administrador = User::factory()->create();
        $empresa = Company::factory()->create();
        $administrador->empresas()->attach($empresa->id, ['permission' => 15, 'status' => true]);
        $tipo = FormType::factory()->for($empresa, 'empresa')->create();

        $update = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->putJson("/api/empresas/{$empresa->id}/tipos-formulario/{$tipo->id}", [
                'nombre' => 'Editado',
                'estado' => false,
            ]);

        $update->assertOk()->assertJsonPath('data.name', 'Editado')->assertJsonPath('data.status', false);

        $delete = $this->actingAs($administrador, 'web')
            ->withHeader('X-Empresa-Id', (string) $empresa->id)
            ->deleteJson("/api/empresas/{$empresa->id}/tipos-formulario/{$tipo->id}");

        $delete->assertOk();
        $this->assertDatabaseHas('form_types', [
            'id' => $tipo->id,
            'status' => false,
        ]);
    }
}
