<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class ProjectAssigned extends Notification
{
    use Queueable;
    public $projectName;
    public $newStatus;
    public $ticketNumber;
    /**
     * Create a new notification instance.
     */
    public function __construct($projectName)
    {
        $this->projectName = $projectName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Proyecto Asigando',
            'message' => "Fuiste asignado al proyecto '{$this->projectName}'.",
        ];
    }
}
