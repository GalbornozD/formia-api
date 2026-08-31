<?php

namespace App\Services\FormResponses;

use App\Enums\AudienceSourceType;
use App\Enums\AuditAction;
use App\Enums\DistributionMemberType;
use App\Enums\RespondentType;
use App\Models\CompanyUser;
use App\Models\DistributionList;
use App\Models\DistributionListMember;
use App\Models\FormAssignment;
use App\Models\FormInvitation;
use App\Models\FormPublication;
use App\Models\FormPublicationAudience;
use App\Models\FormPublicationAudienceSource;
use App\Models\GuestRespondent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resuelve y materializa la audiencia de una publicación (user|guest):
 * valida pertenencia a empresa, deduplica destinatarios que vienen de
 * varias listas, crea asignaciones idempotentes (insertOrIgnore + unique
 * constraints) y, para invitados, la invitación de link correspondiente.
 * Todo dentro de una transacción; pensado para miles de destinatarios
 * (queries por chunks, sin insertar de a uno).
 */
class PublicationAudienceService
{
    public function __construct(
        private readonly InvitationService $invitationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{all_users?: bool, distribution_list_ids?: list<int>, user_ids?: list<int>, guest_respondent_ids?: list<string>}  $config
     * @return array{count: int}
     */
    public function preview(FormPublication $publication, array $config): array
    {
        $this->assertAudienceCapable($publication);

        return ['count' => count($this->resolveAudience($publication, $config)['recipients'])];
    }

    /**
     * @param  array{all_users?: bool, distribution_list_ids?: list<int>, user_ids?: list<int>, guest_respondent_ids?: list<string>}  $config
     */
    public function publish(FormPublication $publication, array $config, User $actor): FormPublicationAudience
    {
        return DB::transaction(function () use ($publication, $config, $actor): FormPublicationAudience {
            $publication = FormPublication::query()->whereKey($publication->id)->lockForUpdate()->firstOrFail();
            $this->assertAudienceCapable($publication);

            $resolved = $this->resolveAudience($publication, $config);

            FormPublicationAudience::query()
                ->where('form_publication_id', $publication->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $audience = FormPublicationAudience::query()->create([
                'company_id' => $publication->company_id,
                'form_publication_id' => $publication->id,
                'respondent_type' => $publication->respondent_type,
                'is_current' => true,
                'recipients_count' => count($resolved['recipients']),
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);

            $sourceIds = [];
            foreach ($resolved['sources'] as $index => $spec) {
                $source = FormPublicationAudienceSource::query()->create([
                    'form_publication_audience_id' => $audience->id,
                    'source_type' => $spec['type'],
                    'distribution_list_id' => $spec['distribution_list_id'],
                    'user_id' => $spec['user_id'],
                    'guest_respondent_id' => $spec['guest_respondent_id'],
                ]);
                $sourceIds[$index] = $source->id;
            }

            $newInvitations = $this->materializeAssignments($publication, $audience, $resolved['recipients'], $sourceIds, $actor);

            $this->auditLogger->registrar(
                AuditAction::PublicacionAudienciaAsignada,
                $actor,
                $publication->company_id,
                'form_publication',
                (string) $publication->id,
                ['respondent_type' => $publication->respondent_type->value, 'recipients_count' => count($resolved['recipients'])],
            );

            $audience->setAttribute('new_invitations', $newInvitations);

            return $audience->load('sources');
        });
    }

    /**
     * Re-sincroniza: recalcula la config de la última audiencia vigente
     * contra el estado ACTUAL de sus listas y publica de nuevo. Como
     * publish() es aditivo (insertOrIgnore + unique), nunca borra
     * asignaciones ya materializadas — solo agrega destinatarios nuevos.
     */
    public function syncNow(FormPublication $publication, User $actor): FormPublicationAudience
    {
        $current = FormPublicationAudience::query()
            ->where('form_publication_id', $publication->id)
            ->where('is_current', true)
            ->with('sources')
            ->first();

        if ($current === null) {
            throw ValidationException::withMessages([
                'audience' => 'Esta publicacion todavia no tiene una audiencia configurada para sincronizar.',
            ]);
        }

        $config = [
            'all_users' => $current->sources->contains(fn ($source) => $source->source_type === AudienceSourceType::AllUsers),
            'distribution_list_ids' => $current->sources->where('source_type', AudienceSourceType::DistributionList)->pluck('distribution_list_id')->filter()->values()->all(),
            'user_ids' => $current->sources->where('source_type', AudienceSourceType::SpecificUser)->pluck('user_id')->filter()->values()->all(),
            'guest_respondent_ids' => $current->sources->where('source_type', AudienceSourceType::SpecificGuest)->pluck('guest_respondent_id')->filter()->values()->all(),
        ];

        return $this->publish($publication, $config, $actor);
    }

    private function assertAudienceCapable(FormPublication $publication): void
    {
        if ($publication->respondent_type === RespondentType::Anonymous) {
            throw ValidationException::withMessages([
                'respondent_type' => 'Las publicaciones anonimas no tienen audiencia: cualquier persona con el enlace puede responder.',
            ]);
        }
    }

    /**
     * @param  array{all_users?: bool, distribution_list_ids?: list<int>, user_ids?: list<int>, guest_respondent_ids?: list<string>}  $config
     * @return array{
     *     sources: list<array{type: AudienceSourceType, distribution_list_id: ?int, user_id: ?int, guest_respondent_id: ?string}>,
     *     recipients: list<array{type: string, id: int|string, source_indexes: list<int>}>,
     * }
     */
    private function resolveAudience(FormPublication $publication, array $config): array
    {
        $respondentType = $publication->respondent_type;
        $sources = [];
        $sourceIndexesByRecipient = [];
        $metaByRecipient = [];

        $addRecipient = function (string $type, int|string $id, int $sourceIndex) use (&$sourceIndexesByRecipient, &$metaByRecipient): void {
            $key = "{$type}:{$id}";
            $sourceIndexesByRecipient[$key][] = $sourceIndex;
            $metaByRecipient[$key] = ['type' => $type, 'id' => $id];
        };

        if ($respondentType === RespondentType::User && ! empty($config['all_users'])) {
            $sourceIndex = count($sources);
            $sources[] = ['type' => AudienceSourceType::AllUsers, 'distribution_list_id' => null, 'user_id' => null, 'guest_respondent_id' => null];

            CompanyUser::query()
                ->where('company_id', $publication->company_id)
                ->where('status', true)
                ->whereHas('usuario', fn ($query) => $query->where('status', true))
                ->orderBy('user_id')
                ->select('user_id')
                ->chunk(500, function ($rows) use ($addRecipient, $sourceIndex): void {
                    foreach ($rows as $row) {
                        $addRecipient('user', $row->user_id, $sourceIndex);
                    }
                });
        }

        $listIds = array_values(array_unique(array_map('intval', $config['distribution_list_ids'] ?? [])));
        if ($listIds !== []) {
            $validListIds = DistributionList::query()
                ->where('company_id', $publication->company_id)
                ->whereIn('id', $listIds)
                ->pluck('id')
                ->all();

            if (count($validListIds) !== count($listIds)) {
                throw ValidationException::withMessages([
                    'distribution_list_ids' => 'Todas las listas deben pertenecer a la empresa de la publicacion.',
                ]);
            }

            $memberType = $respondentType === RespondentType::User ? DistributionMemberType::User : DistributionMemberType::Guest;
            $listSourceIndex = [];
            foreach ($listIds as $listId) {
                $listSourceIndex[$listId] = count($sources);
                $sources[] = ['type' => AudienceSourceType::DistributionList, 'distribution_list_id' => $listId, 'user_id' => null, 'guest_respondent_id' => null];
            }

            DistributionListMember::query()
                ->whereIn('distribution_list_id', $listIds)
                ->where('member_type', $memberType->value)
                ->select(['distribution_list_id', 'user_id', 'guest_respondent_id'])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($addRecipient, $listSourceIndex, $memberType): void {
                    foreach ($rows as $row) {
                        $id = $memberType === DistributionMemberType::User ? $row->user_id : $row->guest_respondent_id;
                        $addRecipient($memberType->value, $id, $listSourceIndex[$row->distribution_list_id]);
                    }
                });
        }

        if ($respondentType === RespondentType::User) {
            $userIds = array_values(array_unique(array_map('intval', $config['user_ids'] ?? [])));
            if ($userIds !== []) {
                $validUserIds = CompanyUser::query()
                    ->where('company_id', $publication->company_id)
                    ->where('status', true)
                    ->whereIn('user_id', $userIds)
                    ->pluck('user_id')
                    ->all();

                if (count($validUserIds) !== count($userIds)) {
                    throw ValidationException::withMessages([
                        'user_ids' => 'Todos los usuarios deben pertenecer activos a la empresa.',
                    ]);
                }

                foreach ($validUserIds as $userId) {
                    $sourceIndex = count($sources);
                    $sources[] = ['type' => AudienceSourceType::SpecificUser, 'distribution_list_id' => null, 'user_id' => $userId, 'guest_respondent_id' => null];
                    $addRecipient('user', $userId, $sourceIndex);
                }
            }
        }

        if ($respondentType === RespondentType::Guest) {
            $guestIds = array_values(array_unique($config['guest_respondent_ids'] ?? []));
            if ($guestIds !== []) {
                $validGuestIds = GuestRespondent::query()
                    ->where('company_id', $publication->company_id)
                    ->whereIn('id', $guestIds)
                    ->pluck('id')
                    ->all();

                if (count($validGuestIds) !== count($guestIds)) {
                    throw ValidationException::withMessages([
                        'guest_respondent_ids' => 'Todos los invitados deben pertenecer a la empresa.',
                    ]);
                }

                foreach ($validGuestIds as $guestId) {
                    $sourceIndex = count($sources);
                    $sources[] = ['type' => AudienceSourceType::SpecificGuest, 'distribution_list_id' => null, 'user_id' => null, 'guest_respondent_id' => $guestId];
                    $addRecipient('guest', $guestId, $sourceIndex);
                }
            }
        }

        if ($sources === []) {
            throw ValidationException::withMessages([
                'config' => 'Selecciona al menos un destinatario, una lista o "todos los usuarios".',
            ]);
        }

        $recipients = [];
        foreach ($metaByRecipient as $key => $meta) {
            $recipients[] = [...$meta, 'source_indexes' => $sourceIndexesByRecipient[$key]];
        }

        return ['sources' => $sources, 'recipients' => $recipients];
    }

    /**
     * @param  list<array{type: string, id: int|string, source_indexes: list<int>}>  $recipients
     * @param  array<int, int>  $sourceIds
     * @return list<array{invitation: FormInvitation, url: string}>
     */
    private function materializeAssignments(FormPublication $publication, FormPublicationAudience $audience, array $recipients, array $sourceIds, User $actor): array
    {
        $newInvitations = [];

        foreach (array_chunk($recipients, 500) as $chunk) {
            $userIds = array_values(array_filter(array_map(fn ($r) => $r['type'] === 'user' ? $r['id'] : null, $chunk), fn ($v) => $v !== null));
            $guestIds = array_values(array_filter(array_map(fn ($r) => $r['type'] === 'guest' ? $r['id'] : null, $chunk), fn ($v) => $v !== null));

            $matchQuery = fn () => FormAssignment::query()
                ->where('form_publication_id', $publication->id)
                ->where(function ($query) use ($userIds, $guestIds): void {
                    if ($userIds !== []) {
                        $query->orWhereIn('user_id', $userIds);
                    }
                    if ($guestIds !== []) {
                        $query->orWhereIn('guest_respondent_id', $guestIds);
                    }
                });

            $existingIds = $matchQuery()->pluck('id')->all();

            $now = now();
            $rows = array_map(fn ($r) => [
                'uuid' => (string) Str::uuid(),
                'company_id' => $publication->company_id,
                'form_publication_id' => $publication->id,
                'respondent_type' => $r['type'],
                'user_id' => $r['type'] === 'user' ? $r['id'] : null,
                'guest_respondent_id' => $r['type'] === 'guest' ? $r['id'] : null,
                'status' => 'pending',
                'form_publication_audience_id' => $audience->id,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $actor->id,
            ], $chunk);

            DB::table('form_assignments')->insertOrIgnore($rows);

            $assignments = $matchQuery()->get(['id', 'company_id', 'form_publication_id', 'user_id', 'guest_respondent_id']);

            $recipientByKey = [];
            foreach ($chunk as $r) {
                $recipientByKey["{$r['type']}:{$r['id']}"] = $r;
            }

            $pivotRows = [];
            $newlyCreatedAssignments = [];

            foreach ($assignments as $assignment) {
                $key = $assignment->user_id !== null ? "user:{$assignment->user_id}" : "guest:{$assignment->guest_respondent_id}";
                $recipient = $recipientByKey[$key] ?? null;

                if ($recipient === null) {
                    continue;
                }

                foreach ($recipient['source_indexes'] as $sourceIndex) {
                    $pivotRows[] = [
                        'form_assignment_id' => $assignment->id,
                        'form_publication_audience_source_id' => $sourceIds[$sourceIndex],
                        'created_at' => $now,
                    ];
                }

                if (! in_array($assignment->id, $existingIds, true)) {
                    $newlyCreatedAssignments[] = $assignment;
                }
            }

            if ($pivotRows !== []) {
                DB::table('form_assignment_sources')->insertOrIgnore($pivotRows);
            }

            if ($publication->respondent_type === RespondentType::Guest) {
                foreach ($newlyCreatedAssignments as $assignment) {
                    $assignment->setRelation('publication', $publication);
                    $newInvitations[] = $this->invitationService->createLinkInvitation($assignment, $actor);
                }
            }
        }

        return $newInvitations;
    }
}
