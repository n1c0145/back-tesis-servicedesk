<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgotPasswordCode extends Notification
{
    use Queueable;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Código de recuperación de contraseña')
            ->line("Tu código de recuperación de contraseña es: {$this->code}");
    }
}
