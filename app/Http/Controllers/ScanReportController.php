<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Scan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class ScanReportController extends Controller
{
    public function generatePdf(Scan $scan): Response
    {
        $scan->load([
            'company',
            'results' => function ($query): void {
                $query->orderBy('target')->orderBy('check_type')->orderBy('check_name');
            },
        ]);

        // Get analytics data for the report
        $analytics = $this->getAnalytics($scan);

        // Get detailed results grouped by target and type
        $resultsByTarget = $this->getResultsByTarget($scan);

        // Get vulnerability summary
        $vulnerabilities = $this->getVulnerabilities($scan);

        $data = [
            'scan' => $scan,
            'analytics' => $analytics,
            'resultsByTarget' => $resultsByTarget,
            'vulnerabilities' => $vulnerabilities,
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('pdf.scan-report', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = "scan-report-{$scan->name}-".now()->format('Y-m-d').'.pdf';

        return $pdf->stream($filename);
    }

    private function getAnalytics(Scan $scan): array
    {
        $results = $scan->results;

        return [
            'overview' => [
                'total_checks' => $results->count(),
                'passed_checks' => $results->where('passed', true)->count(),
                'failed_checks' => $results->where('passed', false)->count(),
                'targets_scanned' => $results->unique('target')->count(),
                'scan_duration' => $scan->started_at && $scan->completed_at ?
                    $scan->started_at->diffInSeconds($scan->completed_at) : null,
            ],
            'severity_breakdown' => [
                'critical' => $results->where('severity', 'critical')->count(),
                'high' => $results->filter(fn ($result): bool => $result->severity === 'high' || $result->risk_level === 'high')->count(),
                'medium' => $results->filter(fn ($result): bool => $result->severity === 'medium' || $result->risk_level === 'medium')->count(),
                'low' => $results->filter(fn ($result): bool => $result->severity === 'low' || $result->risk_level === 'low')->count(),
                'info' => $results->filter(fn ($result): bool => is_null($result->severity) && is_null($result->risk_level) && $result->passed)->count(),
            ],
            'check_types' => $results->groupBy('check_type')->map(fn ($group, $type): array => [
                'type' => $type,
                'total' => $group->count(),
                'passed' => $group->where('passed', true)->count(),
                'failed' => $group->where('passed', false)->count(),
                'high_risk' => $group->filter(fn ($result): bool => in_array($result->severity, ['high', 'critical']) ||
                       $result->risk_level === 'high' ||
                       ! empty($result->vulnerabilities))->count(),
            ])->values(),
            'targets' => $results->groupBy('target')->map(fn ($group, $target): array => [
                'target' => $target,
                'target_type' => $group->first()->target_type,
                'total_checks' => $group->count(),
                'passed' => $group->where('passed', true)->count(),
                'failed' => $group->where('passed', false)->count(),
                'risk_score' => $this->calculateRiskScore($group),
            ])->values(),
        ];
    }

    private function getResultsByTarget(Scan $scan): array
    {
        return $scan->results
            ->groupBy('target')
            ->map(fn ($results, $target): array => [
                'target' => $target,
                'target_type' => $results->first()->target_type,
                'results' => $results->groupBy('check_type')->map(fn ($typeResults, $checkType): array => [
                    'check_type' => $checkType,
                    'checks' => $typeResults->map(fn ($result): array => [
                        'id' => $result->id,
                        'check_name' => $result->check_name,
                        'passed' => $result->passed,
                        'severity' => $result->severity ?? $result->risk_level,
                        'message' => $result->message,
                        'description' => $result->description,
                        'recommendations' => $result->recommendations,
                        'vulnerabilities' => $result->vulnerabilities,
                        'check_data' => $result->check_data,
                    ])->values(),
                ])->values(),
            ])
            ->values()
            ->toArray();
    }

    private function getVulnerabilities(Scan $scan): array
    {
        $vulnerabilities = [];

        foreach ($scan->results as $result) {
            if (! empty($result->vulnerabilities)) {
                foreach ($result->vulnerabilities as $vuln) {
                    $vulnerabilities[] = [
                        'target' => $result->target,
                        'check_type' => $result->check_type,
                        'cve' => $vuln['cve'] ?? 'N/A',
                        'severity' => $vuln['severity'] ?? 'unknown',
                        'score' => $vuln['score'] ?? $vuln['cvss_score'] ?? null,
                        'description' => $vuln['description'] ?? '',
                        'published' => $vuln['published'] ?? null,
                        'references' => $vuln['references'] ?? [],
                        'patch_available' => $vuln['patch_available'] ?? false,
                        'recommended_action' => $vuln['recommended_action'] ?? '',
                    ];
                }
            }
        }

        // Sort by severity (critical first)
        usort($vulnerabilities, function (array $a, array $b): int {
            $severityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'unknown' => 0];

            return ($severityOrder[$b['severity']] ?? 0) - ($severityOrder[$a['severity']] ?? 0);
        });

        return $vulnerabilities;
    }

    private function calculateRiskScore(mixed $results): int
    {
        $score = 0;

        foreach ($results as $result) {
            if (! $result->passed) {
                match ($result->severity ?? $result->risk_level) {
                    'critical' => $score += 10,
                    'high' => $score += 7,
                    'medium' => $score += 4,
                    'low' => $score += 1,
                    default => $score += 2,
                };
            }

            // Add extra points for vulnerabilities
            if (! empty($result->vulnerabilities)) {
                $score += count($result->vulnerabilities) * 5;
            }
        }

        return min(100, $score); // Cap at 100
    }
}
