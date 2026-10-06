<?php

namespace App\Notifications;

use App\Models\AcademicImport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AcademicImportFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public AcademicImport $import)
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
            'kind' => 'import_failed',
            'import_id' => $this->import->id,
            'file' => $this->import->original_name,
            'message' => $this->import->status === 'completed' ? 'Import hoàn tất nhưng có dòng lỗi cần kiểm tra.' : 'Import tiến độ học tập thất bại: '.($this->import->failure_message ?: 'Không rõ nguyên nhân.'),
        ];
    }
}
