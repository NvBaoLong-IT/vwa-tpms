<?php

namespace App\Notifications;

use App\Models\WarningLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AcademicWarningNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public WarningLog $warning, public string $kind = 'warning')
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
            'warning_id' => $this->warning->id,
            'student_code' => $this->warning->student?->student_code,
            'student_name' => $this->warning->student?->full_name,
            'warning_type' => $this->warning->warning_type,
            'reason' => $this->warning->reason,
            'message' => $this->kind === 'import_failed' ? 'Import tiến độ học tập thất bại.' : 'Có cảnh báo học vụ mới cần xử lý.',
        ];
    }
}
