<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\ScannerServiceInterface;
use App\Enums\ScanStatus;
use App\Models\Scan;
use App\Models\ScanResult;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class ProcessScheduledScans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(ScannerServiceInterface $scannerService): void
    {
        Log::info('Starting ProcessScheduledScans job');

        $scansToRun = $this->getScansToRun();

        Log::info('Found {count} scans to process', ['count' => $scansToRun->count()]);

        foreach ($scansToRun as $scan) {
            $this->processScan($scan, $scannerService);
        }

        Log::info('Completed ProcessScheduledScans job');
    }

    private function getScansToRun()
    {
        $now = now();

        // Get immediate and one-time scans
        $scansToRun = Scan::where(function ($query) use ($now): void {
            // Immediate scans that are still pending
            $query->where('schedule_type', 'immediate')
                ->where('status', ScanStatus::PENDING->value)
                ->where('scheduled_at', '<=', $now);
        })->orWhere(function ($query) use ($now): void {
            // One-time scans scheduled for now or past
            $query->where('schedule_type', 'once')
                ->where('status', ScanStatus::PENDING->value)
                ->where('scheduled_at', '<=', $now);
        })->with('company')->get();

        // Get recurring scans and filter by cron schedule
        $recurringScans = Scan::where('schedule_type', 'recurring')
            ->whereNotNull('cron_expression')
            ->with('company')
            ->get()
            ->filter(fn ($scan): bool => $this->shouldRunRecurringScan($scan, $now));

        return $scansToRun->merge($recurringScans);
    }

    private function shouldRunRecurringScan(Scan $scan, CarbonImmutable $now): bool
    {
        if (! $scan->cron_expression) {
            return false;
        }

        try {
            $cron = new CronExpression($scan->cron_expression);

            // Check if the scan should run now
            $lastRun = $scan->completed_at ?? $scan->created_at;
            $nextRun = $cron->getNextRunDate($lastRun);

            return $nextRun <= $now;
        } catch (Exception $e) {
            Log::error('Invalid cron expression for scan {id}: {expression}', [
                'id' => $scan->id,
                'expression' => $scan->cron_expression,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function processScan(Scan $scan, ScannerServiceInterface $scannerService): void
    {
        Log::info('Processing scan {id}: {name}', [
            'id' => $scan->id,
            'name' => $scan->name,
        ]);

        try {
            // Mark scan as running
            $scan->update([
                'status' => ScanStatus::RUNNING->value,
                'started_at' => now(),
            ]);

            $results = [];

            // Process URLs
            if ($scan->urls) {
                foreach ($scan->urls as $url) {
                    if (! in_array(trim($url), ['', '0'], true)) {
                        Log::info('Scanning URL: {url}', ['url' => $url]);
                        $scanResult = $scannerService->scan($url, 'url');
                        $results[] = $scanResult;
                        $this->storeScanResults($scan, $scanResult);
                    }
                }
            }

            // Process IP addresses
            if ($scan->ip_addresses) {
                foreach ($scan->ip_addresses as $ip) {
                    if (! in_array(trim($ip), ['', '0'], true)) {
                        Log::info('Scanning IP: {ip}', ['ip' => $ip]);
                        $scanResult = $scannerService->scan($ip, 'ip');
                        $results[] = $scanResult;
                        $this->storeScanResults($scan, $scanResult);
                    }
                }
            }

            // Calculate risk grade and summary
            $summary = $this->generateSummary($results);
            $riskGrade = $this->calculateRiskGrade($results);

            // Mark scan as completed
            $scan->update([
                'status' => ScanStatus::COMPLETED->value,
                'completed_at' => now(),
                'summary' => $summary,
                'risk_grade' => $riskGrade,
            ]);

            // Send notification if requested
            if ($scan->send_notification && $scan->notification_email) {
                $this->sendNotification($scan, $summary, $riskGrade);
            }

            Log::info('Completed scan {id} with risk grade {grade}', [
                'id' => $scan->id,
                'grade' => $riskGrade,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to process scan {id}: {error}', [
                'id' => $scan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $scan->update([
                'status' => ScanStatus::FAILED->value,
                'completed_at' => now(),
                'summary' => [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                ],
            ]);

            // Send error notification if requested
            if ($scan->send_notification && $scan->notification_email) {
                $this->sendErrorNotification($scan, $e->getMessage());
            }
        }
    }

    private function generateSummary(array $results): array
    {
        $summary = [
            'total_targets' => count($results),
            'total_checks' => 0,
            'passed_checks' => 0,
            'failed_checks' => 0,
            'vulnerabilities_found' => 0,
            'high_risk_issues' => 0,
            'medium_risk_issues' => 0,
            'low_risk_issues' => 0,
            'scanned_at' => now()->toIso8601String(),
        ];

        foreach ($results as $result) {
            $checks = $result['checks'] ?? [];
            $summary['total_checks'] += count($checks);

            foreach ($checks as $check) {
                if (isset($check['passed'])) {
                    if ($check['passed']) {
                        $summary['passed_checks']++;
                    } else {
                        $summary['failed_checks']++;
                    }
                }

                // Count vulnerabilities
                if (isset($check['vulnerabilities']) && is_array($check['vulnerabilities'])) {
                    $summary['vulnerabilities_found'] += count($check['vulnerabilities']);
                }

                // Count risk levels
                $severity = $check['severity'] ?? $check['risk_level'] ?? 'medium';
                switch ($severity) {
                    case 'high':
                    case 'critical':
                        $summary['high_risk_issues']++;
                        break;
                    case 'medium':
                        $summary['medium_risk_issues']++;
                        break;
                    case 'low':
                        $summary['low_risk_issues']++;
                        break;
                }
            }
        }

        return $summary;
    }

    private function calculateRiskGrade(array $results): string
    {
        $highRiskCount = 0;
        $mediumRiskCount = 0;
        $totalChecks = 0;
        $failedChecks = 0;

        foreach ($results as $result) {
            $checks = $result['checks'] ?? [];
            $totalChecks += count($checks);

            foreach ($checks as $check) {
                if (isset($check['passed']) && ! $check['passed']) {
                    $failedChecks++;
                }

                $severity = $check['severity'] ?? $check['risk_level'] ?? 'medium';
                if (in_array($severity, ['high', 'critical'])) {
                    $highRiskCount++;
                } elseif ($severity === 'medium') {
                    $mediumRiskCount++;
                }

                // Count vulnerabilities as high risk
                if (isset($check['vulnerabilities']) && is_array($check['vulnerabilities'])) {
                    $highRiskCount += count($check['vulnerabilities']);
                }
            }
        }

        if ($totalChecks === 0) {
            return 'F';
        }

        $failureRate = $failedChecks / $totalChecks;

        // Grade based on failure rate and risk levels
        if ($highRiskCount > 0 || $failureRate > 0.5) {
            return 'F';
        }
        if ($failureRate > 0.3 || $mediumRiskCount > 5) {
            return 'D';
        }
        if ($failureRate > 0.2 || $mediumRiskCount > 3) {
            return 'C';
        }
        if ($failureRate > 0.1 || $mediumRiskCount > 1) {
            return 'B';
        }

        return 'A';

    }

    private function sendNotification(Scan $scan, array $summary, string $riskGrade): void
    {
        try {
            // For now, we'll log the notification details
            // In a real application, you would use Laravel's Mail facade
            Log::info('Sending scan completion notification', [
                'scan_id' => $scan->id,
                'scan_name' => $scan->name,
                'company' => $scan->company->name,
                'email' => $scan->notification_email,
                'risk_grade' => $riskGrade,
                'summary' => $summary,
            ]);

            // TODO: Implement actual email sending
            // Mail::to($scan->notification_email)->send(new ScanCompleted($scan, $summary, $riskGrade));
        } catch (Exception $e) {
            Log::error('Failed to send notification for scan {id}: {error}', [
                'id' => $scan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendErrorNotification(Scan $scan, string $error): void
    {
        try {
            Log::info('Sending scan error notification', [
                'scan_id' => $scan->id,
                'scan_name' => $scan->name,
                'company' => $scan->company->name,
                'email' => $scan->notification_email,
                'error' => $error,
            ]);

            // TODO: Implement actual email sending
            // Mail::to($scan->notification_email)->send(new ScanFailed($scan, $error));
        } catch (Exception $e) {
            Log::error('Failed to send error notification for scan {id}: {error}', [
                'id' => $scan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function storeScanResults(Scan $scan, array $scanResult): void
    {
        $target = $scanResult['target'] ?? '';
        $targetType = $scanResult['type'] ?? 'unknown';
        $scannedAt = isset($scanResult['timestamp']) ?
            CarbonImmutable::parse($scanResult['timestamp']) :
            now();

        $checks = $scanResult['checks'] ?? [];

        foreach ($checks as $check) {
            $this->createScanResultRecord($scan, $target, $targetType, $check, $scannedAt);
        }
    }

    private function createScanResultRecord(
        Scan $scan,
        string $target,
        string $targetType,
        array $check,
        CarbonImmutable $scannedAt
    ): void {
        // Extract common fields from check data
        $checkType = $check['type'] ?? 'unknown';
        $checkName = $check['name'] ?? null;
        $passed = $check['passed'] ?? false;
        $severity = $check['severity'] ?? null;
        $riskLevel = $check['risk_level'] ?? null;
        $message = $check['message'] ?? $check['error'] ?? null;
        $description = $check['description'] ?? null;
        $recommendations = $check['recommendations'] ?? null;
        $vulnerabilities = $check['vulnerabilities'] ?? null;

        // Create the scan result record
        ScanResult::create([
            'uuid' => Str::uuid(),
            'scan_id' => $scan->id,
            'target' => $target,
            'target_type' => $targetType,
            'check_type' => $checkType,
            'check_name' => $checkName,
            'passed' => $passed,
            'severity' => $severity,
            'risk_level' => $riskLevel,
            'message' => $message,
            'description' => $description,
            'check_data' => $check, // Store full check data as JSON
            'recommendations' => $recommendations,
            'vulnerabilities' => $vulnerabilities,
            'scanned_at' => $scannedAt,
        ]);
    }
}
