<?php

namespace App\Services\FormResponses;

use App\Enums\FormAvailabilityStatus;
use App\Enums\FormResponseStatus;
use App\Enums\InvitationStatus;
use App\Enums\RespondentType;
use App\Models\FormAssignment;
use App\Models\FormInvitation;
use App\Models\FormPublication;
use App\Models\FormResponse;
use App\Models\User;
use App\Support\EmpresaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

class FormPublicationAccessService
{
    public function __construct(private readonly EmpresaContext $context) {}

    /**
     * @return EloquentCollection<int, FormPublication>
     */
    public function publicationsForUser(User $user): EloquentCollection
    {
        return $this->baseUserQuery($user)
            ->with(['formType', 'version', 'assignments' => fn ($query) => $query->where('user_id', $user->id)])
            ->withCount(['assignments', 'responses'])
            ->orderByRaw('COALESCE(starts_at, created_at) desc')
            ->get();
    }

    public function findForUser(string $uuid, User $user): FormPublication
    {
        $publication = $this->baseUserQuery($user)
            ->where('uuid', $uuid)
            ->with(['formType', 'version', 'empresa.branding', 'empresa.settings'])
            ->firstOrFail();

        $this->ensureCanViewAsUser($publication, $user);

        return $publication;
    }

    /**
     * Resuelve una publicación por el link público general (`/forms/{slug}`).
     * Solo sirve para respondent_type=anonymous: `guest` exige invitación.
     */
    public function findForGuest(string $uuid): FormPublication
    {
        $publication = FormPublication::query()
            ->where('uuid', $uuid)
            ->with(['formType', 'version', 'empresa.branding', 'empresa.settings'])
            ->firstOrFail();

        if (! $publication->is_active || $publication->respondent_type !== RespondentType::Anonymous) {
            abort(404);
        }

        if (! $this->hasPublishedVersion($publication)) {
            abort(404);
        }

        return $publication;
    }

    public function responseForUser(string $responseUuid, User $user): FormResponse
    {
        $response = FormResponse::query()
            ->whereKey($responseUuid)
            ->with(['publication.formType', 'publication.version', 'publication.empresa.branding', 'publication.empresa.settings', 'version', 'answers.field.fieldType', 'assignment'])
            ->firstOrFail();

        abort_unless($response->respondent_type === RespondentType::User && $response->user_id === $user->id, 403);

        if (! $user->esMaster()) {
            abort_unless($response->company_id === $this->context->empresaId(), 404);
        }

        return $response;
    }

    public function responseForGuest(string $responseUuid, ?string $token): FormResponse
    {
        abort_if($token === null || trim($token) === '', 403, 'Token de respuesta requerido.');

        $response = FormResponse::query()
            ->whereKey($responseUuid)
            ->with(['publication.formType', 'publication.version', 'publication.empresa.branding', 'publication.empresa.settings', 'version', 'answers.field.fieldType', 'guestRespondent'])
            ->firstOrFail();

        abort_unless(in_array($response->respondent_type, [RespondentType::Guest, RespondentType::Anonymous], true), 403);
        abort_unless(is_string($response->access_token_hash) && hash_equals($response->access_token_hash, hash('sha256', $token)), 403);

        // La respuesta debe seguir correspondiendo al mismo respondent_type
        // vigente de la publicación (una publicación no cambia de modalidad,
        // pero esto evita depender de datos ya inconsistentes).
        abort_unless($response->publication->is_active && $response->publication->respondent_type === $response->respondent_type, 404);

        return $response;
    }

    /**
     * Valida un token de invitación (link personalizado de un invitado) y
     * devuelve la invitación con publicación/asignación/invitado cargados.
     * Nunca confía en IDs internos: todo se resuelve desde el hash del token.
     */
    public function resolveInvitation(string $token): FormInvitation
    {
        $invitation = FormInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with(['publication.formType', 'publication.version', 'publication.empresa.branding', 'publication.empresa.settings', 'assignment', 'guestRespondent'])
            ->first();

        abort_if($invitation === null, 404);
        abort_if($invitation->status === InvitationStatus::Cancelled, 410, 'Esta invitacion fue cancelada.');
        abort_if($invitation->expires_at !== null && $invitation->expires_at->isPast(), 410, 'Esta invitacion ha expirado.');

        $publication = $invitation->publication;
        abort_if($publication === null || $publication->respondent_type !== RespondentType::Guest, 404);

        $this->ensureOpen($publication);

        return $invitation;
    }

    public function ensureCanStartForUser(FormPublication $publication, User $user): void
    {
        $this->ensureCanViewAsUser($publication, $user);
        $this->ensureOpen($publication);
    }

    public function ensureCanStartForGuest(FormPublication $publication): void
    {
        if ($publication->respondent_type !== RespondentType::Anonymous) {
            abort(404);
        }

        $this->ensureOpen($publication);
    }

    public function ensureEditable(FormResponse $response): void
    {
        $publication = $response->publication;

        if ($response->isSubmitted() && ! $publication->allow_edit_after_submit) {
            throw ValidationException::withMessages([
                'response' => 'Esta respuesta ya fue enviada y no permite edicion posterior.',
            ]);
        }

        if ($publication->ends_at !== null && $publication->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'response' => 'El periodo para responder este formulario ya termino.',
            ]);
        }
    }

    public function assignmentForUser(FormPublication $publication, User $user): ?FormAssignment
    {
        return $publication->assignments()
            ->where('user_id', $user->id)
            ->first();
    }

    public function availabilityForUser(FormPublication $publication, User $user): FormAvailabilityStatus
    {
        if ($publication->ends_at !== null && $publication->ends_at->isPast()) {
            return FormAvailabilityStatus::Expired;
        }

        $latest = $publication->responses()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->first();

        if ($latest?->status === FormResponseStatus::Submitted) {
            return FormAvailabilityStatus::Completed;
        }

        if ($latest?->status === FormResponseStatus::Draft) {
            return FormAvailabilityStatus::InProgress;
        }

        return FormAvailabilityStatus::Pending;
    }

    private function baseUserQuery(User $user): Builder
    {
        return FormPublication::query()
            ->where('is_active', true)
            ->where('respondent_type', RespondentType::User->value)
            ->whereHas('formType', fn (Builder $query) => $query->where('status', true))
            ->whereHas('version', fn (Builder $query) => $query->where('is_published', true)->where('is_active', true))
            ->when(! $user->esMaster(), fn (Builder $query) => $query->where('company_id', $this->context->empresaId()))
            ->where(function (Builder $query) use ($user): void {
                $query->whereDoesntHave('assignments')
                    ->orWhereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery->where('user_id', $user->id));
            });
    }

    private function ensureCanViewAsUser(FormPublication $publication, User $user): void
    {
        abort_unless($publication->respondent_type === RespondentType::User, 404);

        if (! $user->esMaster()) {
            abort_unless($publication->company_id === $this->context->empresaId(), 404);
        }

        if ($publication->assignments()->exists()) {
            abort_unless($publication->assignments()->where('user_id', $user->id)->exists(), 403);
        }
    }

    private function ensureOpen(FormPublication $publication): void
    {
        if (! $publication->is_active || ! $this->hasPublishedVersion($publication)) {
            abort(404);
        }

        if ($publication->starts_at !== null && $publication->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'publication' => 'El formulario aun no esta disponible para responder.',
            ]);
        }

        if ($publication->ends_at !== null && $publication->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'publication' => 'El periodo para responder este formulario ya termino.',
            ]);
        }
    }

    private function hasPublishedVersion(FormPublication $publication): bool
    {
        return $publication->version !== null
            && $publication->version->is_published
            && $publication->version->is_active
            && $publication->formType !== null
            && $publication->formType->status;
    }
}
