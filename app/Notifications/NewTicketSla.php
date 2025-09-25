<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTicketSla extends Notification
{
    use Queueable;

    public $ticketNumber;
    public $projectName;

    public function __construct($ticketNumber, $projectName)
    {
        $this->ticketNumber = $ticketNumber;
        $this->projectName = $projectName;
    }
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Nuevo ticket SLA',
            'message' => "Se abrió un nuevo ticket con SLA #{$this->ticketNumber} en el proyecto '{$this->projectName}'.",
        ];
    }


    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Nuevo Ticket con SLA')
            ->line("Se abrió un nuevo ticket con SLA #{$this->ticketNumber} en el proyecto '{$this->projectName}'");
    }
}
