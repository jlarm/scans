<?php

namespace App\Http\Requests;

use App\Enums\ScanStatus;
use App\Enums\ScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'agent' => ['nullable', 'string', 'max:255'],
            'send_notification' => ['boolean'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'schedule_type' => ['nullable', Rule::enum(ScheduleType::class)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'cron_expression' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ScanStatus::class)],
            'risk_grade' => ['nullable', 'string', 'size:1', 'regex:/^[A-F]$/'],
            'summary' => ['nullable', 'array'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
