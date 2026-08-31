<?php

namespace App\Services\FormResponses;

use App\Enums\AssignmentStatus;
use App\Enums\FormResponseStatus;
use App\Enums\RespondentType;
use App\Models\FormAssignment;
use App\Models\FormInvitation;
use App\Models\FormPublication;
use App\Models\FormResponse;
use App\Models\FormResponseAnswer;
use App\Models\GuestRespondent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormResponseService
{
    public function __construct(
        private readonly FormPublicationAccessService $accessService,
        private readonly FieldAnswerValidationService $validationService,
        private readonly InvitationService $invitationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function startForUser(FormPublication $publication, User $user, array $payload): FormResponse
    {
        $response = DB::transaction(function () use ($publication, $user, $payload): FormResponse {
            $publication = FormPublication::query()
                ->whereKey($publication->id)
                ->with(['version', 'formType'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->accessService->ensureCanStartForUser($publication, $user);
            $assignment = $this->accessService->assignmentForUser($publication, $user);
            $draft = $this->draftForUser($publication, $user);

            if ($draft !== null && $publication->allow_draft) {
                $this->markAssignmentStarted($assignment);

                return $draft;
            }

            $this->assertMaxResponsesForUser($publication, $user);

            $response = FormResponse::query()->create([
                'company_id' => $publication->company_id,
                'form_publication_id' => $publication->id,
                'form_type_version_id' => $publication->form_type_version_id,
                'form_assignment_id' => $assignment?->id,
                'respondent_type' => RespondentType::User,
                'user_id' => $user->id,
                'status' => FormResponseStatus::Draft,
                'started_at' => now(),
                'last_saved_at' => now(),
                'locale' => $payload['locale'] ?? null,
            ]);

            $this->markAssignmentStarted($assignment);

            return $response;
        });

        if (($payload['answers'] ?? []) !== []) {
            return $this->save($response, $payload['answers'], false);
        }

        return $this->loadForResource($response);
    }

    /**
     * Flujo público anónimo (`/forms/{slug}`, sin invitación). Solo alcanzable
     * cuando la publicación es respondent_type=anonymous (validado por
     * FormPublicationAccessService::ensureCanStartForGuest): nunca se
     * captura ni resuelve identidad, aunque el payload traiga datos.
     *
     * @param  array<string, mixed>  $payload
     * @return array{response: FormResponse, token: string}
     */
    public function startForGuest(FormPublication $publication, array $payload): array
    {
        $token = Str::random(64);
        $response = DB::transaction(function () use ($publication, $token): FormResponse {
            $publication = FormPublication::query()
                ->whereKey($publication->id)
                ->with(['version', 'formType'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->accessService->ensureCanStartForGuest($publication);

            return FormResponse::query()->create([
                'company_id' => $publication->company_id,
                'form_publication_id' => $publication->id,
                'form_type_version_id' => $publication->form_type_version_id,
                'respondent_type' => RespondentType::Anonymous,
                'status' => FormResponseStatus::Draft,
                'started_at' => now(),
                'last_saved_at' => now(),
                'access_token_hash' => hash('sha256', $token),
                'locale' => $payload['locale'] ?? null,
            ]);
        });

        if (($payload['answers'] ?? []) !== []) {
            $response = $this->save($response, $payload['answers'], false);
        }

        return ['response' => $this->loadForResource($response), 'token' => $token];
    }

    /**
     * Flujo de invitado con link personalizado (`/forms/{slug}/invite/{token}`).
     * El invitado ya viene identificado por la invitación validada — no se
     * resuelve identidad por email en el payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{response: FormResponse, token: string}
     */
    public function startForInvitedGuest(FormInvitation $invitation, FormAssignment $assignment, GuestRespondent $guest, array $payload): array
    {
        $token = Str::random(64);
        $response = DB::transaction(function () use ($invitation, $assignment, $guest, $payload, $token): FormResponse {
            $publication = FormPublication::query()
                ->whereKey($assignment->form_publication_id)
                ->with(['version', 'formType'])
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->draftForGuest($publication, $guest);

            if ($draft !== null && $publication->allow_draft) {
                $draft->forceFill(['access_token_hash' => hash('sha256', $token)])->save();
                $this->invitationService->markOpened($invitation);
                $this->markAssignmentStarted($assignment);

                return $draft;
            }

            $this->assertMaxResponsesForGuest($publication, $guest);

            $response = FormResponse::query()->create([
                'company_id' => $publication->company_id,
                'form_publication_id' => $publication->id,
                'form_type_version_id' => $publication->form_type_version_id,
                'form_assignment_id' => $assignment->id,
                'respondent_type' => RespondentType::Guest,
                'guest_respondent_id' => $guest->id,
                'status' => FormResponseStatus::Draft,
                'started_at' => now(),
                'last_saved_at' => now(),
                'access_token_hash' => hash('sha256', $token),
                'locale' => $payload['locale'] ?? null,
            ]);

            $this->invitationService->markOpened($invitation);
            $this->markAssignmentStarted($assignment);

            return $response;
        });

        if (($payload['answers'] ?? []) !== []) {
            $response = $this->save($response, $payload['answers'], false);
        }

        return ['response' => $this->loadForResource($response), 'token' => $token];
    }

    /**
     * @param  list<array{field_key: string, value: mixed}>  $answers
     */
    public function save(FormResponse $response, array $answers, bool $submit): FormResponse
    {
        return DB::transaction(function () use ($response, $answers, $submit): FormResponse {
            $response = FormResponse::query()
                ->whereKey($response->id)
                ->with(['publication.version', 'publication.formType', 'version', 'assignment.invitation', 'answers.field'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->accessService->ensureEditable($response);

            $shouldValidateAsSubmitted = $submit || $response->isSubmitted();
            $answersForValidation = $shouldValidateAsSubmitted
                ? $this->mergeWithExistingAnswers($response, $answers)
                : $answers;
            $normalized = $this->validationService->normalize($response->version, $answersForValidation, $shouldValidateAsSubmitted);

            foreach ($normalized as $fieldId => $value) {
                FormResponseAnswer::query()->updateOrCreate(
                    ['form_response_id' => $response->id, 'form_field_id' => $fieldId],
                    ['value_json' => $value],
                );
            }

            $response->last_saved_at = now();

            if ($submit) {
                $response->status = FormResponseStatus::Submitted;
                $response->submitted_at ??= now();

                if ($response->assignment !== null) {
                    $response->assignment->forceFill([
                        'submitted_at' => $response->submitted_at,
                        'status' => AssignmentStatus::Submitted,
                    ])->save();

                    if ($response->assignment->invitation !== null) {
                        $this->invitationService->markCompleted($response->assignment->invitation);
                    }
                }
            }

            $response->save();

            return $this->loadForResource($response);
        });
    }

    private function markAssignmentStarted(?FormAssignment $assignment): void
    {
        if ($assignment === null) {
            return;
        }

        $assignment->forceFill([
            'started_at' => $assignment->started_at ?? now(),
            'status' => $assignment->status === AssignmentStatus::Pending ? AssignmentStatus::Started : $assignment->status,
        ])->save();
    }

    private function loadForResource(FormResponse $response): FormResponse
    {
        return $response->refresh()->load([
            'publication.formType',
            'publication.version',
            'publication.empresa.branding',
            'publication.empresa.settings',
            'version',
            'answers.field.fieldType',
            'answers.field.options',
            'assignment',
            'guestRespondent',
            'user',
        ]);
    }

    private function draftForUser(FormPublication $publication, User $user): ?FormResponse
    {
        return FormResponse::query()
            ->where('form_publication_id', $publication->id)
            ->where('user_id', $user->id)
            ->where('status', FormResponseStatus::Draft->value)
            ->latest('updated_at')
            ->first();
    }

    private function draftForGuest(FormPublication $publication, GuestRespondent $guest): ?FormResponse
    {
        return FormResponse::query()
            ->where('form_publication_id', $publication->id)
            ->where('guest_respondent_id', $guest->id)
            ->where('status', FormResponseStatus::Draft->value)
            ->latest('updated_at')
            ->first();
    }

    private function assertMaxResponsesForUser(FormPublication $publication, User $user): void
    {
        if ($publication->max_responses_per_respondent === null) {
            return;
        }

        $count = $publication->responses()->where('user_id', $user->id)->count();
        if ($count >= $publication->max_responses_per_respondent) {
            throw ValidationException::withMessages([
                'publication' => 'Ya alcanzaste el maximo de respuestas permitido para este formulario.',
            ]);
        }
    }

    private function assertMaxResponsesForGuest(FormPublication $publication, GuestRespondent $guest): void
    {
        if ($publication->max_responses_per_respondent === null) {
            return;
        }

        $count = $publication->responses()->where('guest_respondent_id', $guest->id)->count();
        if ($count >= $publication->max_responses_per_respondent) {
            throw ValidationException::withMessages([
                'publication' => 'Ya existe una respuesta de este invitado para este formulario.',
            ]);
        }
    }

    /**
     * @param  list<array{field_key: string, value: mixed}>  $answers
     * @return list<array{field_key: string, value: mixed}>
     */
    private function mergeWithExistingAnswers(FormResponse $response, array $answers): array
    {
        $merged = [];

        foreach ($response->answers as $answer) {
            if ($answer->field !== null) {
                $merged[$answer->field->field_key] = [
                    'field_key' => $answer->field->field_key,
                    'value' => $answer->value_json,
                ];
            }
        }

        foreach ($answers as $answer) {
            $fieldKey = (string) ($answer['field_key'] ?? '');
            $merged[$fieldKey] = [
                'field_key' => $fieldKey,
                'value' => $answer['value'] ?? null,
            ];
        }

        return array_values($merged);
    }
}
