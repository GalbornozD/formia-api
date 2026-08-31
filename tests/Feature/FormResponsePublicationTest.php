<?php

namespace Tests\Feature;

use App\Enums\DistributionMemberType;
use App\Enums\RespondentType;
use App\Models\Company;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormPublication;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Models\GuestRespondent;
use App\Models\User;
use App\Services\DistributionLists\DistributionListService;
use App\Services\FormResponses\FormPublicationService;
use App\Services\FormResponses\InvitationService;
use App\Services\FormResponses\PublicationAudienceService;
use App\Services\FormTypeService as LegacyFormTypeService;
use App\Services\FormVersionService;
use Database\Seeders\FieldTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormResponsePublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Company $company;

    private FormType $formType;

    private FormTypeVersion $version;

    private FormField $nameField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FieldTypeSeeder::class);
        $this->actor = User::factory()->master()->create();
        $this->company = Company::factory()->create();
        $this->formType = app(LegacyFormTypeService::class)->create(
            $this->company,
            ['name' => 'Inspeccion mensual'],
            $this->actor,
        );
        $this->version = $this->formType->versions()->sole();
        $this->nameField = $this->createField('text', 'employee_name', [
            'label' => 'Nombre del colaborador',
            'is_required' => true,
            'validation_rules' => ['min_length' => 2, 'max_length' => 120],
        ]);

        app(FormVersionService::class)->publish($this->formType, $this->version, $this->actor);
        $this->version = $this->version->fresh();
    }

    public function test_admin_creates_publication_and_public_definition_hides_internal_ids(): void
    {
        $response = $this->actingAs($this->actor, 'web')
            ->postJson($this->publicationUrl(), [
                'name' => 'Inspeccion abierta',
                'form_type_version_id' => $this->version->id,
                'respondent_type' => RespondentType::Anonymous->value,
                'thank_you_description' => '<p>Gracias <script>alert(1)</script><strong>equipo</strong></p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Inspeccion abierta')
            ->assertJsonPath('data.respondent_type', 'anonymous');

        $uuid = $response->json('data.uuid');

        $public = $this->getJson("/api/public/forms/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.publication.uuid', $uuid)
            ->assertJsonPath('data.fields.0.field_key', 'employee_name')
            ->assertJsonPath('data.fields.0.is_required', true)
            ->assertJsonPath('data.form_type.version', 1);

        $this->assertArrayNotHasKey('id', $public->json('data.fields.0'));
        $this->assertStringNotContainsString('script', (string) $public->json('data.publication.thank_you.description'));
    }

    public function test_invited_guest_can_autosave_and_submit_via_personalized_link(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::Guest->value,
            'max_responses_per_respondent' => 1,
        ]);

        $guest = GuestRespondent::factory()->create(['company_id' => $this->company->id]);
        $audience = app(PublicationAudienceService::class)->publish(
            $publication,
            ['guest_respondent_ids' => [$guest->id]],
            $this->actor,
        );

        $invitationUrl = $audience->getAttribute('new_invitations')[0]['url'];
        $token = str($invitationUrl)->afterLast('/invite/')->toString();

        $this->getJson("/api/public/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.publication.uuid', $publication->uuid);

        $start = $this->postJson("/api/public/invitations/{$token}/responses")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.respondent_type', 'guest');

        $responseUuid = $start->json('data.uuid');
        $responseToken = $start->json('data.access_token');

        $this->withHeader('X-Response-Token', $responseToken)
            ->postJson("/api/public/responses/{$responseUuid}/submit", ['answers' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields.employee_name');

        $this->withHeader('X-Response-Token', 'invalid')
            ->patchJson("/api/public/responses/{$responseUuid}", [
                'answers' => [['field_key' => 'employee_name', 'value' => 'Ana Perez']],
            ])
            ->assertForbidden();

        $this->withHeader('X-Response-Token', $responseToken)
            ->patchJson("/api/public/responses/{$responseUuid}", [
                'answers' => [['field_key' => 'employee_name', 'value' => 'Ana Perez']],
            ])
            ->assertOk()
            ->assertJsonPath('data.answers.0.field_key', 'employee_name')
            ->assertJsonPath('data.answers.0.value', 'Ana Perez');

        $this->withHeader('X-Response-Token', $responseToken)
            ->postJson("/api/public/responses/{$responseUuid}/submit", ['answers' => []])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('form_responses', [
            'id' => $responseUuid,
            'status' => 'submitted',
            'guest_respondent_id' => $guest->id,
        ]);
        $this->assertDatabaseHas('form_assignments', [
            'form_publication_id' => $publication->id,
            'guest_respondent_id' => $guest->id,
            'status' => 'submitted',
        ]);
    }

    public function test_invitation_token_invalid_is_rejected(): void
    {
        $this->getJson('/api/public/invitations/token-inexistente')
            ->assertNotFound();
    }

    public function test_guest_publication_is_not_reachable_via_generic_public_link(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::Guest->value,
        ]);

        $this->getJson("/api/public/forms/{$publication->uuid}")
            ->assertNotFound();
    }

    public function test_anonymous_guest_starts_as_anonymous_without_guest_identity(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::Anonymous->value,
            'max_responses_per_respondent' => null,
        ]);

        $start = $this->postJson("/api/public/forms/{$publication->uuid}/responses")
            ->assertCreated()
            ->assertJsonPath('data.respondent_type', RespondentType::Anonymous->value)
            ->assertJsonPath('data.respondent', null);

        $this->assertDatabaseHas('form_responses', [
            'id' => $start->json('data.uuid'),
            'respondent_type' => RespondentType::Anonymous->value,
            'guest_respondent_id' => null,
        ]);
        $this->assertDatabaseCount('form_assignments', 0);
    }

    public function test_authenticated_user_sees_only_assigned_publications_and_can_submit(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::User->value,
        ]);
        $user = User::factory()->create();
        $user->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);
        app(FormPublicationService::class)->assign($publication, [$user->id], $this->actor);

        $this->actingAs($user, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->getJson('/api/me/forms')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $publication->uuid)
            ->assertJsonPath('data.0.availability_status', 'pending');

        $start = $this->actingAs($user, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->postJson("/api/me/form-publications/{$publication->uuid}/responses", [
                'answers' => [['field_key' => 'employee_name', 'value' => 'Luis Soto']],
            ])
            ->assertCreated();

        $responseUuid = $start->json('data.uuid');

        $this->actingAs($user, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->postJson("/api/me/form-responses/{$responseUuid}/submit", ['answers' => []])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('form_assignments', [
            'form_publication_id' => $publication->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);
    }

    public function test_authenticated_user_without_assignment_cannot_open_restricted_publication(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::User->value,
        ]);
        $assignedUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $assignedUser->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);
        $otherUser->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);
        app(FormPublicationService::class)->assign($publication, [$assignedUser->id], $this->actor);

        $this->actingAs($otherUser, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->getJson("/api/me/form-publications/{$publication->uuid}")
            ->assertNotFound();
    }

    public function test_public_guest_cannot_open_authenticated_only_publication(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::User->value,
        ]);

        $this->getJson("/api/public/forms/{$publication->uuid}")
            ->assertNotFound();

        $this->postJson("/api/public/forms/{$publication->uuid}/responses")
            ->assertNotFound();
    }

    public function test_authenticated_user_cannot_open_guest_only_publication(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::Anonymous->value,
        ]);
        $user = User::factory()->create();
        $user->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);

        $this->actingAs($user, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->getJson("/api/me/form-publications/{$publication->uuid}")
            ->assertNotFound();
    }

    public function test_inactive_future_and_expired_publications_are_blocked_for_start(): void
    {
        $inactive = $this->createPublication([
            'respondent_type' => RespondentType::Anonymous->value,
            'is_active' => false,
        ]);
        $future = $this->createPublication([
            'respondent_type' => RespondentType::Anonymous->value,
            'starts_at' => now()->addDay()->toIso8601String(),
        ]);
        $expired = $this->createPublication([
            'respondent_type' => RespondentType::Anonymous->value,
            'ends_at' => now()->subMinute()->toIso8601String(),
        ]);

        $this->postJson("/api/public/forms/{$inactive->uuid}/responses")
            ->assertNotFound();

        $this->postJson("/api/public/forms/{$future->uuid}/responses")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publication');

        $this->getJson("/api/public/forms/{$expired->uuid}")
            ->assertOk()
            ->assertJsonPath('data.publication.availability_status', 'expired');

        $this->postJson("/api/public/forms/{$expired->uuid}/responses")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publication');
    }

    public function test_authenticated_user_cannot_access_another_users_response(): void
    {
        $publication = $this->createPublication([
            'respondent_type' => RespondentType::User->value,
            'max_responses_per_respondent' => null,
        ]);
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $owner->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);
        $intruder->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);

        $start = $this->actingAs($owner, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->postJson("/api/me/form-publications/{$publication->uuid}/responses", [
                'answers' => [['field_key' => 'employee_name', 'value' => 'Owner']],
            ])
            ->assertCreated();

        $this->actingAs($intruder, 'web')
            ->withHeader('X-Empresa-Id', (string) $this->company->id)
            ->getJson("/api/me/form-responses/{$start->json('data.uuid')}")
            ->assertForbidden();
    }

    public function test_distribution_list_deduplicates_recipients_across_lists(): void
    {
        $publication = $this->createPublication(['respondent_type' => RespondentType::User->value]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        foreach ([$userA, $userB, $userC] as $user) {
            $user->empresas()->attach($this->company->id, ['permission' => 1, 'status' => true]);
        }

        $listService = app(DistributionListService::class);
        $listA = $listService->create($this->company, ['name' => 'Lista A'], $this->actor);
        $listB = $listService->create($this->company, ['name' => 'Lista B'], $this->actor);
        $listService->addMembers($listA, DistributionMemberType::User, [$userA->id, $userB->id], $this->actor);
        $listService->addMembers($listB, DistributionMemberType::User, [$userB->id, $userC->id], $this->actor);

        $audience = app(PublicationAudienceService::class)->publish(
            $publication,
            ['distribution_list_ids' => [$listA->id, $listB->id]],
            $this->actor,
        );

        $this->assertSame(3, $audience->recipients_count);
        $this->assertDatabaseCount('form_assignments', 3);

        // Publicar de nuevo con la misma config no duplica asignaciones
        // (idempotente ante doble ejecución accidental).
        app(PublicationAudienceService::class)->publish(
            $publication,
            ['distribution_list_ids' => [$listA->id, $listB->id]],
            $this->actor,
        );
        $this->assertDatabaseCount('form_assignments', 3);
    }

    public function test_invitation_regenerate_creates_new_link_and_cancels_old_one(): void
    {
        $publication = $this->createPublication(['respondent_type' => RespondentType::Guest->value]);
        $guest = GuestRespondent::factory()->create(['company_id' => $this->company->id]);

        $audience = app(PublicationAudienceService::class)->publish(
            $publication,
            ['guest_respondent_ids' => [$guest->id]],
            $this->actor,
        );
        $invitation = $publication->invitations()->sole();

        $result = app(InvitationService::class)->regenerate($invitation, $this->actor);

        $this->assertNotSame($invitation->token_hash, $result['invitation']->token_hash);
        $this->assertDatabaseHas('form_invitations', ['id' => $invitation->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('form_invitations', ['id' => $result['invitation']->id, 'status' => 'pending']);
    }

    private function createPublication(array $overrides = []): FormPublication
    {
        return app(FormPublicationService::class)->create($this->company, $this->formType, [
            'name' => 'Publicacion de prueba',
            'form_type_version_id' => $this->version->id,
            'respondent_type' => RespondentType::Anonymous->value,
            'max_responses_per_respondent' => 1,
            ...$overrides,
        ], $this->actor);
    }

    private function createField(string $typeCode, string $fieldKey, array $overrides = []): FormField
    {
        return FormField::factory()->for($this->version)->create([
            'field_type_id' => FieldType::query()->where('code', $typeCode)->sole()->id,
            'field_key' => $fieldKey,
            'label' => str($fieldKey)->replace('_', ' ')->title()->toString(),
            'sort_order' => 0,
            'width' => 12,
            'is_required' => false,
            'validation_rules' => [],
            'settings' => [],
            ...$overrides,
        ]);
    }

    private function publicationUrl(): string
    {
        return "/api/empresas/{$this->company->id}/tipos-formulario/{$this->formType->id}/publicaciones";
    }
}
