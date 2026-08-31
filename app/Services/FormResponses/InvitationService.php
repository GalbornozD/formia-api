<?php

namespace App\Services\FormResponses;

use App\Enums\AuditAction;
use App\Enums\InvitationChannel;
use App\Enums\InvitationStatus;
use App\Models\FormAssignment;
use App\Models\FormInvitation;
use App\Models\FormPublication;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Reutiliza el prefijo público ya existente en el frontend (`/f/{uuid}`,
     * ver form-response-page.component.ts) en vez de introducir un segundo
     * esquema de URL pública basado en slug.
     */
    public function buildPublicUrl(FormPublication $publication, string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/f/{$publication->uuid}/invite/{$token}";
    }

    /**
     * Crea la invitación de canal 'link' para una asignación de invitado
     * recién materializada. Solo aquí se conoce el token en texto plano
     * (nunca se persiste): se devuelve para que el caller lo muestre una vez.
     *
     * @return array{invitation: FormInvitation, url: string}
     */
    public function createLinkInvitation(FormAssignment $assignment, User $actor): array
    {
        $publication = $assignment->relationLoaded('publication')
            ? $assignment->publication
            : FormPublication::query()->findOrFail($assignment->form_publication_id);

        $token = Str::random(64);

        $invitation = FormInvitation::query()->create([
            'company_id' => $assignment->company_id,
            'form_publication_id' => $assignment->form_publication_id,
            'form_assignment_id' => $assignment->id,
            'guest_respondent_id' => $assignment->guest_respondent_id,
            'channel' => InvitationChannel::Link,
            'recipient' => null,
            'token_hash' => hash('sha256', $token),
            'status' => InvitationStatus::Pending,
            'created_by' => $actor->id,
        ]);

        return ['invitation' => $invitation, 'url' => $this->buildPublicUrl($publication, $token)];
    }

    /**
     * @return array{invitation: FormInvitation, url: string}
     */
    public function regenerate(FormInvitation $old, User $actor): array
    {
        return DB::transaction(function () use ($old, $actor): array {
            $old->forceFill(['status' => InvitationStatus::Cancelled])->save();

            $assignment = $old->relationLoaded('assignment')
                ? $old->assignment
                : FormAssignment::query()->findOrFail($old->form_assignment_id);

            $result = $this->createLinkInvitation($assignment, $actor);

            $this->auditLogger->registrar(
                AuditAction::InvitacionRegenerada,
                $actor,
                $old->company_id,
                'form_invitation',
                (string) $old->id,
            );

            return $result;
        });
    }

    public function cancel(FormInvitation $invitation, User $actor): void
    {
        $invitation->forceFill(['status' => InvitationStatus::Cancelled])->save();

        $this->auditLogger->registrar(
            AuditAction::InvitacionCancelada,
            $actor,
            $invitation->company_id,
            'form_invitation',
            (string) $invitation->id,
        );
    }

    public function markOpened(FormInvitation $invitation): void
    {
        if ($invitation->opened_at !== null) {
            return;
        }

        $invitation->forceFill([
            'opened_at' => now(),
            'status' => in_array($invitation->status, [InvitationStatus::Pending, InvitationStatus::Sent], true)
                ? InvitationStatus::Opened
                : $invitation->status,
        ])->save();
    }

    public function markCompleted(FormInvitation $invitation): void
    {
        $invitation->forceFill([
            'used_at' => now(),
            'status' => InvitationStatus::Completed,
        ])->save();
    }
}
