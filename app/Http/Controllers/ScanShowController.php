<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ScanShowController extends Controller
{
    public function show(Scan $scan): Response
    {
        $scan->load([
            'company',
            'results' => function ($query) {
                $query->orderBy('target')->orderBy('check_type')->orderBy('check_name');
            }
        ]);

        // Get analytics data for charts
        $analytics = $this->getAnalytics($scan);
        
        // Get detailed results grouped by target and type
        $resultsByTarget = $this->getResultsByTarget($scan);
        
        // Get vulnerability summary
        $vulnerabilities = $this->getVulnerabilities($scan);

        return Inertia::render('Scan/Show', [
            'scan' => $scan,
            'analytics' => $analytics,
            'resultsByTarget' => $resultsByTarget,
            'vulnerabilities' => $vulnerabilities,
        ]);
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
                'high' => $results->filter(fn($result) => $result->severity === 'high' || $result->risk_level === 'high')->count(),
                'medium' => $results->filter(fn($result) => $result->severity === 'medium' || $result->risk_level === 'medium')->count(),
                'low' => $results->filter(fn($result) => $result->severity === 'low' || $result->risk_level === 'low')->count(),
                'info' => $results->filter(fn($result) => is_null($result->severity) && is_null($result->risk_level) && $result->passed)->count(),
            ],
            'check_types' => $results->groupBy('check_type')->map(function ($group, $type) {
                return [
                    'type' => $type,
                    'total' => $group->count(),
                    'passed' => $group->where('passed', true)->count(),
                    'failed' => $group->where('passed', false)->count(),
                    'high_risk' => $group->where(function ($result) {
                        return in_array($result->severity, ['high', 'critical']) || 
                               $result->risk_level === 'high' ||
                               !empty($result->vulnerabilities);
                    })->count(),
                ];
            })->values(),
            'targets' => $results->groupBy('target')->map(function ($group, $target) {
                return [
                    'target' => $target,
                    'target_type' => $group->first()->target_type,
                    'total_checks' => $group->count(),
                    'passed' => $group->where('passed', true)->count(),
                    'failed' => $group->where('passed', false)->count(),
                    'risk_score' => $this->calculateRiskScore($group),
                ];
            })->values(),
        ];
    }

    private function getResultsByTarget(Scan $scan): array
    {
        return $scan->results
            ->groupBy('target')
            ->map(function ($results, $target) {
                return [
                    'target' => $target,
                    'target_type' => $results->first()->target_type,
                    'results' => $results->groupBy('check_type')->map(function ($typeResults, $checkType) {
                        return [
                            'check_type' => $checkType,
                            'checks' => $typeResults->map(function ($result) {
                                return [
                                    'id' => $result->id,
                                    'check_name' => $result->check_name,
                                    'passed' => $result->passed,
                                    'severity' => $result->severity ?? $result->risk_level,
                                    'message' => $result->message,
                                    'description' => $result->description,
                                    'recommendations' => $result->recommendations,
                                    'vulnerabilities' => $result->vulnerabilities,
                                    'check_data' => $result->check_data,
                                ];
                            })->values(),
                        ];
                    })->values(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getVulnerabilities(Scan $scan): array
    {
        $vulnerabilities = [];
        
        foreach ($scan->results as $result) {
            if (!empty($result->vulnerabilities)) {
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
        usort($vulnerabilities, function ($a, $b) {
            $severityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'unknown' => 0];
            return ($severityOrder[$b['severity']] ?? 0) - ($severityOrder[$a['severity']] ?? 0);
        });
        
        return $vulnerabilities;
    }

    private function calculateRiskScore(mixed $results): int
    {
        $score = 0;
        
        foreach ($results as $result) {
            if (!$result->passed) {
                switch ($result->severity ?? $result->risk_level) {
                    case 'critical':
                        $score += 10;
                        break;
                    case 'high':
                        $score += 7;
                        break;
                    case 'medium':
                        $score += 4;
                        break;
                    case 'low':
                        $score += 1;
                        break;
                    default:
                        $score += 2; // Default for failed checks
                }
            }
            
            // Add extra points for vulnerabilities
            if (!empty($result->vulnerabilities)) {
                $score += count($result->vulnerabilities) * 5;
            }
        }
        
        return min(100, $score); // Cap at 100
    }
}
