<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_ve_el_catalogo_completo_de_roles(): void
    {
        $master = User::factory()->master()->create();

        $response = $this->actingAs($master, 'web')->getJson('/api/roles');

        $response->assertOk();
        $nombres = collect($response->json('data'))->pluck('name');
        $this->assertTrue($nombres->contains('master'));
        $this->assertTrue($nombres->contains('administrador'));
    }

    public function test_administrador_no_ve_el_rol_master_en_el_catalogo(): void
    {
        $administrador = User::factory()->create();

        $response = $this->actingAs($administrador, 'web')->getJson('/api/roles');

        $response->assertOk();
        $nombres = collect($response->json('data'))->pluck('name');
        $this->assertFalse($nombres->contains('master'));
        $this->assertTrue($nombres->contains('administrador'));
    }
}
