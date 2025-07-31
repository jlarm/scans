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
            'agent' => $this->agent,
            'send_notification' => $this->send_notification,
            'notification_email' => $this->notification_email,
            'schedule_type' => $this->schedule_type?->value,
            'scheduled_at' => $this->scheduled_at,
            'cron_expression' => $this->cron_expression,
            'status' => $this->status?->value,
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
