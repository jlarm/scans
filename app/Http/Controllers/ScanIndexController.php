<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ScanStatus;
use App\Models\Scan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ScanIndexController extends Controller
{
    public function index(): Response
    {
        $scans = Scan::with(['company', 'results'])
            ->withCount([
                'results',
                'results as passed_checks_count' => function ($query): void {
                    $query->where('passed', true);
                },
                'results as failed_checks_count' => function ($query): void {
                    $query->where('passed', false);
                },
                'results as high_risk_count' => function ($query): void {
                    $query->where(function ($q): void {
                        $q->whereIn('severity', ['high', 'critical'])
                            ->orWhere('risk_level', 'high')
                            ->orWhereNotNull('vulnerabilities');
                    });
                },
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Scan/Index', [
            'scans' => $scans,
            'stats' => $this->getOverallStats(),
        ]);
    }

    public function progress(Request $request, Scan $scan)
    {
        // Get current progress for running scan
        if ($scan->status !== ScanStatus::RUNNING->value) {
            return response()->json([
                'status' => $scan->status,
                'progress' => $scan->status === ScanStatus::COMPLETED->value ? 100 : 0,
            ]);
        }

        // Calculate progress based on results created vs expected
        $expectedChecks = $this->calculateExpectedChecks($scan);
        $completedChecks = $scan->results()->count();

        $progress = $expectedChecks > 0 ?
            min(100, round(($completedChecks / $expectedChecks) * 100)) : 0;

        return response()->json([
            'status' => $scan->status,
            'progress' => $progress,
            'completed_checks' => $completedChecks,
            'expected_checks' => $expectedChecks,
            'current_target' => $this->getCurrentTarget($scan),
        ]);
    }

    private function getOverallStats(): array
    {
        return [
            'total_scans' => Scan::count(),
            'completed_scans' => Scan::where('status', ScanStatus::COMPLETED->value)->count(),
            'running_scans' => Scan::where('status', ScanStatus::RUNNING->value)->count(),
            'failed_scans' => Scan::where('status', ScanStatus::FAILED->value)->count(),
            'pending_scans' => Scan::where('status', ScanStatus::PENDING->value)->count(),
        ];
    }

    private function calculateExpectedChecks(Scan $scan): int
    {
        $expectedChecks = 0;

        // Each URL gets approximately 18 checks (based on ScannerService)
        // - 6 security headers
        // - 1 SSL certificate
        // - 6 additional headers
        // - 5 CORS policy checks
        if ($scan->urls) {
            $expectedChecks += count(array_filter($scan->urls)) * 18;
        }

        // Each IP gets approximately 20 checks
        // - 15 port scans (common ports)
        // - 5 service detection checks
        if ($scan->ip_addresses) {
            $expectedChecks += count(array_filter($scan->ip_addresses)) * 20;
        }

        return $expectedChecks;
    }

    private function getCurrentTarget(Scan $scan): ?string
    {
        // Get the most recent result to determine current target
        $latestResult = $scan->results()
            ->orderBy('created_at', 'desc')
            ->first();

        return $latestResult ? $latestResult->target : null;
    }
}
