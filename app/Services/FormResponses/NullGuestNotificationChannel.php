<?php

namespace App\Services\FormResponses;

use App\Contracts\GuestNotificationChannel;
use App\Models\FormInvitation;

/**
 * Implementación por defecto para los canales email/sms/whatsapp, que
 * todavía no tienen un proveedor real conectado. No envía nada: existe
 * para que el resto del código pueda depender de GuestNotificationChannel
 * sin condicionales, y se reemplace por un binding real más adelante.
 */
class NullGuestNotificationChannel implements GuestNotificationChannel
{
    public function send(FormInvitation $invitation, string $plainUrl): void
    {
        // Intencionalmente no-op: ver PublicationAudienceService/InvitationService,
        // el canal 'link' no necesita envío (se copia desde la UI) y
        // email/sms/whatsapp aun no tienen proveedor implementado.
    }
}
