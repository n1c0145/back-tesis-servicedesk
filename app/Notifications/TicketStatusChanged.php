<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketStatusChanged extends Notification
{
    use Queueable;

    public $ticketNumber;
    public $projectName;
    public $newStatus;

    /**
     * Create a new notification instance.
     */
    public function __construct($ticketNumber, $projectName, $newStatus)
    {
        $this->ticketNumber = $ticketNumber;
        $this->projectName = $projectName;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Aquí definimos base de datos
    }

    /**
     * Get the array representation of the notification for the database.
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Actualización de ticket',
            'message' => "El ticket #{$this->ticketNumber} del proyecto '{$this->projectName}' fue actualizado a estado '{$this->newStatus}'.",
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            'ticketNumber' => $this->ticketNumber,
            'projectName' => $this->projectName,
            'newStatus' => $this->newStatus,
        ];
    }
}
