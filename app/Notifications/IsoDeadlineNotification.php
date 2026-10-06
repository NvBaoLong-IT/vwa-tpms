<?php

namespace App\Notifications;

use App\Models\IsoStep;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IsoDeadlineNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public IsoStep $step, public string $kind)
    {
        //
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
     * Get the mail representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'iso_step_id' => $this->step->id,
            'workflow_id' => $this->step->iso_workflow_id,
            'message' => $this->kind === 'overdue' ? 'Bước ISO đã quá hạn.' : 'Bước ISO sắp đến hạn.',
            'step_name' => $this->step->step_name,
            'deadline' => $this->step->deadline?->format('Y-m-d'),
        ];
    }
}
