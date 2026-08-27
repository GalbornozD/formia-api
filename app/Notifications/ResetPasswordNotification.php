<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * El token en texto plano solo viaja por este canal (email); nunca se
     * persiste — en base de datos solo vive su hash SHA-256.
     */
    public function __construct(private readonly string $tokenPlano) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = sprintf(
            '%s/restablecer-password?token=%s&email=%s',
            rtrim(config('app.frontend_url'), '/'),
            $this->tokenPlano,
            urlencode($notifiable->email),
        );

        return (new MailMessage)
            ->subject('Restablece tu contraseña')
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace expira en 30 minutos.')
            ->line('Si no solicitaste este cambio, puedes ignorar este correo.');
    }
}
