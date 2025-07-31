<?php

namespace App\Http\Resources;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Scan */
class ScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'urls' => $this->urls,
            'ip_addresses' => $this->ip_addresses,
            'send_notification' => $this->send_notification,
            'notification_email' => $this->notification_email,
            'schedule_type' => $this->schedule_type,
            'scheduled_at' => $this->scheduled_at,
            'cron_expression' => $this->cron_expression,
            'frequency' => $this->frequency,
            'day_of_week' => $this->day_of_week,
            'schedule_time' => $this->schedule_time,
            'status' => $this->status,
            'risk_grade' => $this->risk_grade,
            'summary' => $this->summary,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}
