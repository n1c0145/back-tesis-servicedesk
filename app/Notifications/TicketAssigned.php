<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification
{
    use Queueable;

    public $ticketNumber;
    public $projectName;

    /**
     * Create a new notification instance.
     */
    public function __construct($ticketNumber, $projectName)
    {
        $this->ticketNumber = $ticketNumber;
        $this->projectName = $projectName;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Se guarda en base de datos
    }

    /**
     * Array para la notificación en base de datos.
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Ticket asignado',
            'message' => "Se te ha asignado el ticket #{$this->ticketNumber} del proyecto '{$this->projectName}'.",
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            'ticketNumber' => $this->ticketNumber,
            'projectName' => $this->projectName,
        ];
    }
}
