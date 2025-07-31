<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ScanRequest;
use App\Models\Company;
use App\Models\Scan;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ScanController extends Controller
{
    public function create(): Response
    {
        $companies = Company::select('id', 'name')->orderBy('name')->get();
        
        return Inertia::render('Scan/Create', [
            'companies' => $companies,
        ]);
    }

    public function store(ScanRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        
        // Handle company creation or selection
        if (!empty($validated['company_name'])) {
            // Create new company
            $company = Company::create(['name' => $validated['company_name']]);
            $validated['company_id'] = $company->id;
        }
        
        // Remove fields that are not stored in the database
        unset($validated['company_name']);
        unset($validated['scheduled_date']); // This is only used for processing, not storage
        
        // Generate UUID
        $validated['uuid'] = Str::uuid();
        
        // Filter empty URLs and IP addresses
        if (isset($validated['urls'])) {
            $validated['urls'] = array_values(array_filter($validated['urls'], fn($url) => !empty(trim($url ?? ''))));
            if (empty($validated['urls'])) {
                $validated['urls'] = null;
            }
        }
        
        if (isset($validated['ip_addresses'])) {
            $validated['ip_addresses'] = array_values(array_filter($validated['ip_addresses'], fn($ip) => !empty(trim($ip ?? ''))));
            if (empty($validated['ip_addresses'])) {
                $validated['ip_addresses'] = null;
            }
        }
        
        // Handle scheduling
        if ($validated['schedule_type'] === 'immediate') {
            $validated['scheduled_at'] = now();
        } elseif ($validated['schedule_type'] === 'once') {
            // For "once" scheduling, we need both date and time
            // The date comes from the calendar picker (value) and time from schedule_time
            $date = $request->input('scheduled_date');
            $time = $validated['schedule_time'] ?? '09:00';
            
            if ($date && $time) {
                $validated['scheduled_at'] = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
            } else {
                // If no date is provided for "once" scheduling, default to now + 1 hour
                $validated['scheduled_at'] = now()->addHour();
            }
        } elseif ($validated['schedule_type'] === 'recurring') {
            // Generate cron expression from frequency, day_of_week, and schedule_time
            $validated['cron_expression'] = $this->generateCronExpression(
                $validated['frequency'],
                $validated['day_of_week'] ?? null,
                $validated['schedule_time']
            );
        }
        
        $scan = Scan::create($validated);
        
        return redirect()->route('dashboard')->with('success', 'Scan created successfully!');
    }
    
    private function generateCronExpression(string $frequency, ?int $dayOfWeek, string $time): string
    {
        [$hour, $minute] = explode(':', $time);
        
        return match ($frequency) {
            'daily' => "{$minute} {$hour} * * *",
            'weekly' => "{$minute} {$hour} * * {$dayOfWeek}",
            'monthly' => "{$minute} {$hour} 1 * *",
            default => "{$minute} {$hour} * * *",
        };
    }
}
