<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\FormTypeService;
use Database\Seeders\FieldTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Company $company;

    private FormType $formType;

    private FormTypeVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FieldTypeSeeder::class);
        $this->actor = User::factory()->master()->create();
        $this->company = Company::factory()->create();
        $this->formType = app(FormTypeService::class)->create(
            $this->company,
            ['name' => 'Evaluación de desempeño'],
            $this->actor,
        );
        $this->version = $this->formType->versions()->sole();
    }

    public function test_field_description_is_stored_as_sanitized_rich_text(): void
    {
        $field = $this->createField('text', 'safety_notes', ['label' => 'Notas de seguridad']);

        $response = $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($field), [
                'description' => '<p>Adjunta el <strong>documento</strong>.</p><a href="javascript:alert(1)">No ejecutar</a>',
            ])
            ->assertOk();

        $response->assertJsonPath(
            'data.description',
            '<p>Adjunta el <strong>documento</strong>.</p><a>No ejecutar</a>',
        );
        $this->assertSame(
            '<p>Adjunta el <strong>documento</strong>.</p><a>No ejecutar</a>',
            $field->fresh()->description,
        );
    }

    public function test_can_add_update_reorder_and_delete_nested_fields(): void
    {
        $section = $this->createField('section', 'personal_data', ['label' => 'Datos personales']);
        $name = $this->createField('text', 'employee_name', [
            'parent_field_id' => $section->id,
            'label' => 'Nombre',
            'default_value' => 'Por definir',
        ]);
        $score = $this->createField('number', 'score', ['label' => 'Puntaje']);

        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($name), [
                'label' => 'Nombre completo',
                'width' => 6,
                'validation_rules' => ['min_length' => 3, 'max_length' => 100],
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Nombre completo')
            ->assertJsonPath('data.width', 6);

        $this->assertDatabaseHas('form_fields', [
            'id' => $name->id,
            'label' => 'Nombre completo',
            'updated_by' => $this->actor->id,
        ]);
        $this->assertSame('Por definir', $name->fresh()->default_value);

        $this->actingAs($this->actor, 'web')
            ->putJson($this->versionUrl().'/campos/orden', [
                'fields' => [
                    ['id' => $score->id, 'parent_field_id' => null, 'sort_order' => 0],
                    ['id' => $section->id, 'parent_field_id' => null, 'sort_order' => 1],
                    ['id' => $name->id, 'parent_field_id' => $section->id, 'sort_order' => 0],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $score->id)
            ->assertJsonPath('data.1.children.0.id', $name->id);

        $this->actingAs($this->actor, 'web')
            ->deleteJson($this->fieldUrl($section))
            ->assertOk();

        $this->assertDatabaseMissing('form_fields', ['id' => $section->id]);
        $this->assertDatabaseMissing('form_fields', ['id' => $name->id]);
        $this->assertDatabaseHas('form_fields', ['id' => $score->id]);
    }

    public function test_manages_normalized_options_and_single_defaults(): void
    {
        $select = $this->createField('select', 'department');
        $text = $this->createField('text', 'notes');

        $yes = $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($select).'/opciones', [
                'option_value' => 'yes',
                'option_label' => 'Sí',
                'is_default' => true,
            ])
            ->assertCreated();

        $no = $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($select).'/opciones', [
                'option_value' => 'no',
                'option_label' => 'No',
                'is_default' => true,
            ])
            ->assertCreated();

        $yesId = (int) $yes->json('data.id');
        $noId = (int) $no->json('data.id');
        $this->assertDatabaseHas('form_field_options', ['id' => $yesId, 'is_default' => false]);
        $this->assertDatabaseHas('form_field_options', ['id' => $noId, 'is_default' => true]);

        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($select)."/opciones/{$noId}", ['option_label' => 'No aplica'])
            ->assertOk()
            ->assertJsonPath('data.option_label', 'No aplica');

        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($select).'/opciones/orden', [
                'options' => [
                    ['id' => $noId, 'sort_order' => 0],
                    ['id' => $yesId, 'sort_order' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $noId);

        $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($select).'/opciones', [
                'option_value' => 'yes',
                'option_label' => 'Duplicada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('option_value');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($text).'/opciones', [
                'option_value' => 'invalid',
                'option_label' => 'Inválida',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('options');

        $this->actingAs($this->actor, 'web')
            ->deleteJson($this->fieldUrl($select)."/opciones/{$yesId}")
            ->assertOk();
        $this->assertDatabaseMissing('form_field_options', ['id' => $yesId]);
    }

    public function test_duplicate_copies_complete_subtree_options_and_audit_fields(): void
    {
        $section = $this->createField('section', 'experience');
        $reason = $this->createField('select', 'leaving_reason', [
            'parent_field_id' => $section->id,
            'settings' => ['searchable' => true, 'allow_clear' => false],
        ]);
        $option = $this->createOption($reason, 'resignation', 'Renuncia');

        $response = $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($section).'/duplicar')
            ->assertCreated()
            ->assertJsonPath('data.field_key', 'experience_copy')
            ->assertJsonPath('data.children.0.field_key', 'leaving_reason_copy');

        $copy = FormField::query()->findOrFail((int) $response->json('data.id'));
        $childCopy = FormField::query()->where('parent_field_id', $copy->id)->sole();

        $this->assertNotSame($section->id, $copy->id);
        $this->assertSame($copy->id, $childCopy->parent_field_id);
        $this->assertSame($reason->settings, $childCopy->settings);
        $this->assertDatabaseHas('form_field_options', [
            'form_field_id' => $childCopy->id,
            'option_value' => $option->option_value,
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);
    }

    public function test_rejects_invalid_keys_types_parents_cycles_and_type_configuration(): void
    {
        $section = $this->createField('section', 'section_a');
        $nestedSection = $this->createField('section', 'section_b', ['parent_field_id' => $section->id]);
        $table = $this->createField('table', 'work_history');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('text', 'section_a'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field_key');

        $otherVersion = FormTypeVersion::factory()->for($this->formType)->create(['version' => 2]);
        $foreignParent = FormField::factory()->for($otherVersion)->create([
            'field_type_id' => $this->fieldTypeId('section'),
            'field_key' => 'foreign_section',
        ]);
        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('text', 'foreign_child', [
                'parent_field_id' => $foreignParent->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_field_id');

        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($section), ['parent_field_id' => $nestedSection->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_field_id');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('section', 'nested_table', [
                'parent_field_id' => $table->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_field_id');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('number', 'invalid_range', [
                'validation_rules' => ['min' => 10, 'max' => 5],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('validation_rules.min');

        $inactiveType = FieldType::query()->where('code', 'phone')->sole();
        $inactiveType->update(['is_active' => false]);
        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('phone', 'phone'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field_type_id');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('text', 'bad_width', ['width' => 5]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('width');
    }

    public function test_reorder_requires_complete_acyclic_hierarchy(): void
    {
        $first = $this->createField('section', 'first');
        $second = $this->createField('section', 'second');

        $this->actingAs($this->actor, 'web')
            ->putJson($this->versionUrl().'/campos/orden', [
                'fields' => [['id' => $first->id, 'parent_field_id' => null, 'sort_order' => 0]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields');

        $this->actingAs($this->actor, 'web')
            ->putJson($this->versionUrl().'/campos/orden', [
                'fields' => [
                    ['id' => $first->id, 'parent_field_id' => $second->id, 'sort_order' => 0],
                    ['id' => $second->id, 'parent_field_id' => $first->id, 'sort_order' => 0],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields');
    }

    public function test_publish_validates_structure_and_makes_version_immutable(): void
    {
        $select = $this->createField('select', 'decision');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/publicar')
            ->assertUnprocessable()
            ->assertJsonValidationErrors("fields.{$select->id}.options");

        $this->createOption($select, 'approved', 'Aprobado');
        $table = $this->createField('table', 'items');
        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/publicar')
            ->assertUnprocessable()
            ->assertJsonValidationErrors("fields.{$table->id}.children");

        $column = $this->createField('text', 'item_name', ['parent_field_id' => $table->id]);
        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/publicar')
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $publishedVersion = $this->version->fresh();
        $this->assertNotNull($publishedVersion->published_at);
        $this->assertSame($this->actor->id, $publishedVersion->updated_by);
        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($column), ['label' => 'No permitido'])
            ->assertStatus(409);
        $this->actingAs($this->actor, 'web')
            ->deleteJson($this->fieldUrl($column))
            ->assertStatus(409);
        $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($table).'/duplicar')
            ->assertStatus(409);
        $this->actingAs($this->actor, 'web')
            ->putJson($this->versionUrl().'/campos/orden', [
                'fields' => [
                    ['id' => $select->id, 'parent_field_id' => null, 'sort_order' => 0],
                    ['id' => $table->id, 'parent_field_id' => null, 'sort_order' => 1],
                    ['id' => $column->id, 'parent_field_id' => $table->id, 'sort_order' => 0],
                ],
            ])
            ->assertStatus(409);
        $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($select).'/opciones', [
                'option_value' => 'rejected',
                'option_label' => 'Rechazado',
            ])
            ->assertStatus(409);

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/publicar')
            ->assertOk()
            ->assertJsonPath('data.is_published', true);
    }

    public function test_clone_rebuilds_parents_and_options_without_mutating_source(): void
    {
        $section = $this->createField('section', 'employment');
        $status = $this->createField('radio', 'employment_status', ['parent_field_id' => $section->id]);
        $this->createOption($status, 'active', 'Activo', ['is_default' => true]);
        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/publicar')
            ->assertOk();

        $response = $this->actingAs($this->actor, 'web')
            ->postJson($this->formTypeUrl().'/versiones', ['source_version_id' => $this->version->id])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.is_published', false);

        $clone = FormTypeVersion::query()->findOrFail((int) $response->json('data.id'));
        $clonedSection = $clone->fields()->where('field_key', 'employment')->sole();
        $clonedStatus = $clone->fields()->where('field_key', 'employment_status')->sole();

        $this->assertNotSame($section->id, $clonedSection->id);
        $this->assertNotSame($status->id, $clonedStatus->id);
        $this->assertSame($clonedSection->id, $clonedStatus->parent_field_id);
        $this->assertDatabaseHas('form_field_options', [
            'form_field_id' => $clonedStatus->id,
            'option_value' => 'active',
            'is_default' => true,
        ]);

        $cloneUrl = $this->versionUrl($clone)."/campos/{$clonedStatus->id}";
        $this->actingAs($this->actor, 'web')
            ->putJson($cloneUrl, ['label' => 'Estado laboral nuevo'])
            ->assertOk();

        $this->assertSame('employment_status', $status->fresh()->field_key);
        $this->assertNotSame('Estado laboral nuevo', $status->fresh()->label);
        $this->assertSame('Estado laboral nuevo', $clonedStatus->fresh()->label);
    }

    public function test_builder_returns_complete_tree_and_active_registry(): void
    {
        $section = $this->createField('section', 'general');
        $select = $this->createField('select', 'area', ['parent_field_id' => $section->id]);
        $this->createOption($select, 'operations', 'Operaciones');

        $this->actingAs($this->actor, 'web')
            ->getJson($this->versionUrl().'/constructor')
            ->assertOk()
            ->assertJsonPath('data.form_type.id', $this->formType->id)
            ->assertJsonPath('data.fields.0.id', $section->id)
            ->assertJsonPath('data.fields.0.children.0.id', $select->id)
            ->assertJsonPath('data.fields.0.children.0.options.0.option_value', 'operations')
            ->assertJsonCount(32, 'data.field_types');
    }

    public function test_field_type_seeder_is_idempotent_and_exposes_only_complete_types(): void
    {
        $this->seed(FieldTypeSeeder::class);
        $this->seed(FieldTypeSeeder::class);

        $this->assertSame(32, FieldType::query()->count());
        $this->assertDatabaseHas('field_types', [
            'code' => 'autocomplete',
            'has_options' => true,
            'is_container' => false,
        ]);
        $this->assertDatabaseHas('field_types', [
            'code' => 'repeatable_group',
            'has_options' => false,
            'is_container' => true,
        ]);
    }

    public function test_priority_types_validate_and_normalize_their_configuration_and_defaults(): void
    {
        $currency = $this->createField('currency', 'salary', [
            'width' => 6,
            'default_value' => 1234.5,
            'settings' => ['currency' => 'USD'],
            'validation_rules' => ['min' => 0, 'max' => 5000, 'decimals' => 2],
        ]);
        $this->assertSame('USD', $currency->settings['currency']);
        $this->assertSame(1234.5, $currency->default_value);

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('percentage', 'invalid_percentage', [
                'default_value' => 101,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_value');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('url', 'unsafe_url', [
                'default_value' => 'javascript:alert(1)',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_value');

        $rut = $this->createField('rut', 'employee_rut', [
            'default_value' => '12.345.678-5',
        ]);
        $this->assertSame('12345678-5', $rut->default_value);

        $richText = $this->createField('rich_text', 'observations', [
            'default_value' => [
                'ops' => [[
                    'insert' => 'Contenido seguro',
                    'attributes' => [
                        'bold' => true,
                        'color' => '#ff0000',
                        'link' => 'javascript:alert(1)',
                    ],
                ]],
            ],
        ]);
        $this->assertSame([
            'ops' => [[
                'insert' => 'Contenido seguro',
                'attributes' => ['bold' => true],
            ]],
        ], $richText->default_value);

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('date_range', 'invalid_dates', [
                'default_value' => ['start' => '2026-09-10', 'end' => '2026-09-01'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_value');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('slider', 'invalid_slider', [
                'settings' => ['min' => 10, 'max' => 5, 'step' => 1, 'show_value' => true],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.min');

        $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload('nps', 'invalid_nps', [
                'default_value' => 11,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('default_value');

        $this->createField('yes_no', 'accepted', ['default_value' => true]);
        $this->createField('autocomplete', 'searchable_area');
        $this->createField('likert', 'agreement');
    }

    public function test_table_is_always_full_width_while_children_keep_their_own_width(): void
    {
        $table = $this->createField('table', 'work_history', ['width' => 6]);
        $company = $this->createField('text', 'company_name', [
            'parent_field_id' => $table->id,
            'width' => 6,
        ]);
        $role = $this->createField('text', 'role_name', [
            'parent_field_id' => $table->id,
            'width' => 6,
        ]);

        $this->assertSame(12, $table->width);
        $this->assertSame(6, $company->width);
        $this->assertSame(6, $role->width);

        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($table), ['width' => 3])
            ->assertOk()
            ->assertJsonPath('data.width', 12);

        $this->actingAs($this->actor, 'web')
            ->getJson($this->versionUrl().'/constructor')
            ->assertOk()
            ->assertJsonPath('data.fields.0.width', 12)
            ->assertJsonPath('data.fields.0.children.0.width', 6)
            ->assertJsonPath('data.fields.0.children.1.width', 6);
    }

    public function test_table_column_allowlist_is_enforced_by_individual_field_endpoint(): void
    {
        $table = $this->createField('table', 'work_history');

        foreach (['signature', 'file'] as $typeCode) {
            $this->actingAs($this->actor, 'web')
                ->postJson($this->versionUrl().'/campos', $this->fieldPayload($typeCode, "{$typeCode}_column", [
                    'parent_field_id' => $table->id,
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('parent_field_id');
        }

        foreach (['text', 'select', 'yes_no'] as $typeCode) {
            $field = $this->createField($typeCode, "{$typeCode}_column", [
                'parent_field_id' => $table->id,
            ]);

            $this->assertSame($table->id, $field->parent_field_id);
        }
    }

    public function test_table_column_allowlist_is_enforced_when_saving_complete_definition(): void
    {
        foreach (['signature', 'file'] as $typeCode) {
            $this->actingAs($this->actor, 'web')
                ->putJson($this->versionUrl().'/constructor', [
                    'fields' => [
                        $this->definitionFieldPayload('table', 'table', 'work_history'),
                        $this->definitionFieldPayload($typeCode, 'column', "{$typeCode}_column", 'table'),
                    ],
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('fields.1.parent_client_id');
        }

        $this->actingAs($this->actor, 'web')
            ->putJson($this->versionUrl().'/constructor', [
                'fields' => [
                    $this->definitionFieldPayload('table', 'table', 'work_history'),
                    $this->definitionFieldPayload('select', 'select', 'status', 'table'),
                    $this->definitionFieldPayload('yes_no', 'yes_no', 'is_current', 'table', 1),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.fields.0.children.0.field_type.code', 'select')
            ->assertJsonPath('data.fields.0.children.1.field_type.code', 'yes_no');
    }

    public function test_repeatable_group_accepts_children_and_validates_item_limits(): void
    {
        $group = $this->createField('repeatable_group', 'workers', [
            'settings' => [
                'min_items' => 1,
                'max_items' => 5,
                'allow_add' => true,
                'allow_remove' => true,
            ],
        ]);
        $child = $this->createField('rut', 'worker_rut', [
            'parent_field_id' => $group->id,
            'width' => 6,
        ]);

        $this->assertSame($group->id, $child->parent_field_id);

        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($group), [
                'settings' => [
                    'min_items' => 10,
                    'max_items' => 2,
                    'allow_add' => true,
                    'allow_remove' => true,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.min_items');
    }

    public function test_rejects_cross_company_access(): void
    {
        $otherCompany = Company::factory()->create();
        $administrator = User::factory()->create();
        $administrator->empresas()->attach($otherCompany->id, ['permission' => 15, 'status' => true]);

        $this->actingAs($administrator, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->getJson($this->versionUrl().'/constructor')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'empresa_sin_acceso');
    }

    public function test_rejects_roles_without_builder_permission(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::VIEWER]);
        $viewer->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);
        $this->actingAs($viewer, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->getJson($this->versionUrl().'/constructor')
            ->assertForbidden();

        $this->actingAs($viewer, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->getJson('/api/field-types')
            ->assertForbidden();
    }

    public function test_rejects_foreign_source_versions_and_mismatched_options(): void
    {
        $otherCompany = Company::factory()->create();
        $otherType = app(FormTypeService::class)->create($otherCompany, ['name' => 'Otro'], $this->actor);
        $otherVersion = $otherType->versions()->sole();

        $this->actingAs($this->actor, 'web')
            ->postJson($this->formTypeUrl().'/versiones', ['source_version_id' => $otherVersion->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_version_id');

        $first = $this->createField('select', 'first_select');
        $second = $this->createField('select', 'second_select');
        $option = $this->createOption($first, 'one', 'Uno');

        $this->actingAs($this->actor, 'web')
            ->putJson($this->fieldUrl($second)."/opciones/{$option->id}", ['option_label' => 'Alterada'])
            ->assertNotFound();
    }

    private function createField(string $typeCode, string $fieldKey, array $overrides = []): FormField
    {
        $response = $this->actingAs($this->actor, 'web')
            ->postJson($this->versionUrl().'/campos', $this->fieldPayload($typeCode, $fieldKey, $overrides))
            ->assertCreated();

        return FormField::query()->findOrFail((int) $response->json('data.id'));
    }

    private function createOption(
        FormField $field,
        string $value,
        string $label,
        array $overrides = [],
    ): FormFieldOption {
        $response = $this->actingAs($this->actor, 'web')
            ->postJson($this->fieldUrl($field).'/opciones', [
                'option_value' => $value,
                'option_label' => $label,
                ...$overrides,
            ])
            ->assertCreated();

        return FormFieldOption::query()->findOrFail((int) $response->json('data.id'));
    }

    private function fieldPayload(string $typeCode, string $fieldKey, array $overrides = []): array
    {
        return [
            'field_type_id' => $this->fieldTypeId($typeCode),
            'field_key' => $fieldKey,
            'label' => str($fieldKey)->replace('_', ' ')->title()->toString(),
            'width' => 12,
            ...$overrides,
        ];
    }

    private function definitionFieldPayload(
        string $typeCode,
        string $clientId,
        string $fieldKey,
        ?string $parentClientId = null,
        int $sortOrder = 0,
    ): array {
        return [
            'client_id' => $clientId,
            'parent_client_id' => $parentClientId,
            'field_type_id' => $this->fieldTypeId($typeCode),
            'field_key' => $fieldKey,
            'label' => str($fieldKey)->replace('_', ' ')->title()->toString(),
            'description' => null,
            'placeholder' => null,
            'default_value' => null,
            'is_required' => false,
            'is_readonly' => false,
            'is_hidden' => false,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'width' => 12,
            'validation_rules' => [],
            'settings' => [],
            'options' => [],
        ];
    }

    private function fieldTypeId(string $code): int
    {
        return FieldType::query()->where('code', $code)->sole()->id;
    }

    private function formTypeUrl(): string
    {
        return "/api/empresas/{$this->company->id}/tipos-formulario/{$this->formType->id}";
    }

    private function versionUrl(?FormTypeVersion $version = null): string
    {
        $version ??= $this->version;

        return $this->formTypeUrl()."/versiones/{$version->id}";
    }

    private function fieldUrl(FormField $field): string
    {
        return $this->versionUrl()."/campos/{$field->id}";
    }
}
