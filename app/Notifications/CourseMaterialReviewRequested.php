<?php

namespace App\Notifications;

use App\Models\CourseMaterial;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CourseMaterialReviewRequested extends Notification
{
    use Queueable;

    public function __construct(public CourseMaterial $material) {}

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
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'material_review_requested',
            'course_material_id' => $this->material->id,
            'course_id' => $this->material->course_id,
            'message' => 'Có yêu cầu xác nhận học liệu: '.$this->material->title,
        ];
    }
}
