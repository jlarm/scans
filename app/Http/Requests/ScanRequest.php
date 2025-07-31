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
            'urls' => ['nullable', 'array'],
            'urls.*' => ['required_with:urls', 'url', 'max:2048'],
            'ip_addresses' => ['nullable', 'array'],
            'ip_addresses.*' => ['required_with:ip_addresses', 'ip', 'max:45'],
            'send_notification' => ['boolean'],
            'notification_email' => [
                'nullable',
                'email',
                'max:255',
                'required_if:send_notification,true'
            ],
            'schedule_type' => [
                'required',
                'string',
                Rule::in(['immediate', 'once', 'recurring'])
            ],
            'scheduled_at' => [
                'nullable',
                'date',
                'after:now',
                'required_if:schedule_type,once'
            ],
            'cron_expression' => ['nullable', 'string', 'max:255'],
            'frequency' => [
                'nullable',
                'string',
                Rule::in(['daily', 'weekly', 'monthly']),
                'required_if:schedule_type,recurring'
            ],
            'day_of_week' => [
                'nullable',
                'integer',
                'min:0',
                'max:6',
                'required_if:frequency,weekly'
            ],
            'schedule_time' => [
                'nullable',
                'date_format:H:i',
                'required_if:schedule_type,once,recurring'
            ],
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
