<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewTicketNormal extends Notification
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
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Nuevo ticket',
            'message' => "Se abrió un nuevo ticket #{$this->ticketNumber} en el proyecto '{$this->projectName}'.",
        ];
    }
}