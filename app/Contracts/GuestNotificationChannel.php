<?php

namespace App\Contracts;

use App\Models\FormInvitation;

/**
 * Punto de extensión para enviar invitaciones por email/SMS/WhatsApp.
 * Hoy solo existe NullGuestNotificationChannel (no-op): agregar un proveedor
 * real implica escribir esta interfaz y bindearla en AppServiceProvider,
 * sin tocar el resto de la arquitectura de invitaciones.
 */
interface GuestNotificationChannel
{
    public function send(FormInvitation $invitation, string $plainUrl): void;
}
