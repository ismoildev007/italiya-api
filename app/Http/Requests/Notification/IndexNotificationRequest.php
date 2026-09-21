<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\BaseRequest;

class IndexNotificationRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'status' => 'nullable|in:pending,sent,failed',
            'type' => 'nullable|in:attendance_marked,grade_created,payment_received,debt_reminder',
            'student_id' => 'nullable|exists:students,id',
            'limit' => 'nullable|integer|min:1|max:200',
        ];
    }
}
