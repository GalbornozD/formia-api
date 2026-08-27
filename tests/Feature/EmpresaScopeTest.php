<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Concerns\BelongsToEmpresa;
use App\Support\EmpresaContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmpresaScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('recurso_de_prueba', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('nombre');
            $table->timestamps();
        });
    }

    public function test_sin_contexto_resuelto_la_scope_bloquea_todo(): void
    {
        RecursoDePrueba::withoutGlobalScopes()->create(['company_id' => 1, 'nombre' => 'x']);

        $this->assertSame(0, RecursoDePrueba::count());
    }

    public function test_filtra_solo_por_la_empresa_activa(): void
    {
        $empresaUno = Company::factory()->create();
        $empresaDos = Company::factory()->create();

        RecursoDePrueba::withoutGlobalScopes()->create(['company_id' => $empresaUno->id, 'nombre' => 'de-empresa-1']);
        RecursoDePrueba::withoutGlobalScopes()->create(['company_id' => $empresaDos->id, 'nombre' => 'de-empresa-2']);

        $this->activarEmpresa($empresaUno);

        $resultados = RecursoDePrueba::all();

        $this->assertCount(1, $resultados);
        $this->assertSame('de-empresa-1', $resultados->first()->nombre);
    }

    public function test_asigna_empresa_id_automaticamente_al_crear(): void
    {
        $empresa = Company::factory()->create();
        $this->activarEmpresa($empresa);

        $recurso = RecursoDePrueba::create(['nombre' => 'nuevo']);

        $this->assertSame($empresa->id, $recurso->company_id);
    }

    public function test_master_ve_recursos_de_todas_las_empresas(): void
    {
        $empresaUno = Company::factory()->create();
        $empresaDos = Company::factory()->create();

        RecursoDePrueba::withoutGlobalScopes()->create(['company_id' => $empresaUno->id, 'nombre' => 'de-empresa-1']);
        RecursoDePrueba::withoutGlobalScopes()->create(['company_id' => $empresaDos->id, 'nombre' => 'de-empresa-2']);

        app(EmpresaContext::class)->setMaster();

        $this->assertCount(2, RecursoDePrueba::all());
    }

    private function activarEmpresa(Company $empresa): void
    {
        app(EmpresaContext::class)->set($empresa);
    }
}

class RecursoDePrueba extends Model
{
    use BelongsToEmpresa;

    protected $table = 'recurso_de_prueba';

    protected $fillable = ['company_id', 'nombre'];
}
