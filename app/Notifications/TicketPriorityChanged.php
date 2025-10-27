<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketPriorityChanged extends Notification
{
    use Queueable;

    public $ticketNumber;
    public $projectName;
    public $newPriority;

    public function __construct($ticketNumber, $projectName, $newPriority)
    {
        $this->ticketNumber = $ticketNumber;
        $this->projectName = $projectName;
        $this->newPriority = $newPriority;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Actualización de ticket',
            'message' => "El ticket #{$this->ticketNumber} del proyecto '{$this->projectName}' cambió a prioridad '{$this->newPriority}'.",
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            'ticketNumber' => $this->ticketNumber,
            'projectName' => $this->projectName,
            'newPriority' => $this->newPriority,
        ];
    }
}
