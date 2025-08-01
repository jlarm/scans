<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import Badge from '@/components/ui/badge.vue';
import Card from '@/components/ui/card.vue';
import CardContent from '@/components/ui/card-content.vue';
import CardDescription from '@/components/ui/card-description.vue';
import CardHeader from '@/components/ui/card-header.vue';
import CardTitle from '@/components/ui/card-title.vue';
import Table from '@/components/ui/table.vue';
import TableBody from '@/components/ui/table-body.vue';
import TableCell from '@/components/ui/table-cell.vue';
import TableHead from '@/components/ui/table-head.vue';
import TableHeader from '@/components/ui/table-header.vue';
import TableRow from '@/components/ui/table-row.vue';
import Tabs from '@/components/ui/tabs.vue';
import TabsContent from '@/components/ui/tabs-content.vue';
import TabsList from '@/components/ui/tabs-list.vue';
import TabsTrigger from '@/components/ui/tabs-trigger.vue';
import Collapsible from '@/components/ui/collapsible.vue';
import CollapsibleContent from '@/components/ui/collapsible-content.vue';
import CollapsibleTrigger from '@/components/ui/collapsible-trigger.vue';
import { 
    ArrowLeft, 
    Shield, 
    CheckCircle, 
    XCircle, 
    Target,
    Bug,
    FileText,
    BarChart3,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    Download
} from 'lucide-vue-next';
import { Doughnut, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    CategoryScale,
    LinearScale,
    BarElement,
} from 'chart.js';

// Register Chart.js components
ChartJS.register(Title, Tooltip, Legend, ArcElement, CategoryScale, LinearScale, BarElement);

interface Scan {
    id: number;
    uuid: string;
    name: string;
    description: string;
    status: string;
    risk_grade: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    company: {
        id: number;
        name: string;
    };
    urls: string[] | null;
    ip_addresses: string[] | null;
}

interface Analytics {
    overview: {
        total_checks: number;
        passed_checks: number;
        failed_checks: number;
        targets_scanned: number;
        scan_duration: number | null;
    };
    severity_breakdown: {
        critical: number;
        high: number;
        medium: number;
        low: number;
        info: number;
    };
    check_types: Array<{
        type: string;
        total: number;
        passed: number;
        failed: number;
        high_risk: number;
    }>;
    targets: Array<{
        target: string;
        target_type: string;
        total_checks: number;
        passed: number;
        failed: number;
        risk_score: number;
    }>;
}

interface CheckResult {
    id: number;
    check_name: string | null;
    passed: boolean;
    severity: string | null;
    message: string | null;
    description: string | null;
    recommendations: string[] | null;
    vulnerabilities: any[] | null;
    check_data: any;
}

interface CheckTypeGroup {
    check_type: string;
    checks: CheckResult[];
}

interface TargetResults {
    target: string;
    target_type: string;
    results: CheckTypeGroup[];
}

interface Vulnerability {
    target: string;
    check_type: string;
    cve: string;
    severity: string;
    score: number | null;
    description: string;
    published: string | null;
    references: string[];
    patch_available: boolean;
    recommended_action: string;
}

interface Props {
    scan: Scan;
    analytics: Analytics;
    resultsByTarget: TargetResults[];
    vulnerabilities: Vulnerability[];
}

const props = defineProps<Props>();

const expandedTargets = ref<Set<string>>(new Set());
const expandedCheckTypes = ref<Set<string>>(new Set());

const toggleTarget = (target: string) => {
    if (expandedTargets.value.has(target)) {
        expandedTargets.value.delete(target);
    } else {
        expandedTargets.value.add(target);
    }
};

const toggleCheckType = (key: string) => {
    if (expandedCheckTypes.value.has(key)) {
        expandedCheckTypes.value.delete(key);
    } else {
        expandedCheckTypes.value.add(key);
    }
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'completed': return 'default';
        case 'running': return 'secondary';
        case 'failed': return 'destructive';
        case 'pending': return 'outline';
        default: return 'outline';
    }
};

const getRiskGradeVariant = (grade: string | null) => {
    if (!grade) return 'outline';
    switch (grade) {
        case 'A': return 'default';
        case 'B': return 'secondary';
        case 'C': return 'outline'; 
        case 'D': case 'F': return 'destructive';
        default: return 'outline';
    }
};

const getSeverityVariant = (severity: string | null) => {
    switch (severity) {
        case 'critical': return 'destructive';
        case 'high': return 'destructive';
        case 'medium': return 'outline';
        case 'low': return 'secondary';
        default: return 'outline';
    }
};

const formatDuration = (seconds: number | null) => {
    if (!seconds) return 'N/A';
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}m ${remainingSeconds}s`;
};

const formatDateTime = (dateString: string | null) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
};

// Chart data
const severityChartData = computed(() => ({
    labels: ['Critical', 'High', 'Medium', 'Low', 'Info'],
    datasets: [{
        data: [
            props.analytics.severity_breakdown.critical,
            props.analytics.severity_breakdown.high,
            props.analytics.severity_breakdown.medium,
            props.analytics.severity_breakdown.low,
            props.analytics.severity_breakdown.info,
        ],
        backgroundColor: [
            '#dc2626', // Critical - red
            '#ea580c', // High - orange
            '#d97706', // Medium - amber
            '#65a30d', // Low - lime
            '#16a34a', // Info - green
        ],
        borderWidth: 0,
    }],
}));

const severityChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom' as const,
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const label = context.label;
                    const value = context.parsed;
                    const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0);
                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                },
            },
        },
    },
};

const checkTypesChartData = computed(() => ({
    labels: props.analytics.check_types.map(ct => ct.type.replace('_', ' ').toUpperCase()),
    datasets: [
        {
            label: 'Passed',
            data: props.analytics.check_types.map(ct => ct.passed),
            backgroundColor: '#16a34a',
        },
        {
            label: 'Failed',
            data: props.analytics.check_types.map(ct => ct.failed),
            backgroundColor: '#dc2626',
        },
        {
            label: 'High Risk',
            data: props.analytics.check_types.map(ct => ct.high_risk),
            backgroundColor: '#ea580c',
        },
    ],
}));

const checkTypesChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
        },
    },
    scales: {
        x: {
            stacked: false,
        },
        y: {
            stacked: false,
            beginAtZero: true,
        },
    },
};

const targetsChartData = computed(() => ({
    labels: props.analytics.targets.map(t => t.target.length > 30 ? t.target.substring(0, 30) + '...' : t.target),
    datasets: [{
        label: 'Risk Score',
        data: props.analytics.targets.map(t => t.risk_score),
        backgroundColor: props.analytics.targets.map(t => {
            if (t.risk_score >= 80) return '#dc2626'; // High risk - red
            if (t.risk_score >= 50) return '#ea580c'; // Medium risk - orange
            if (t.risk_score >= 20) return '#d97706'; // Low risk - amber
            return '#16a34a'; // Minimal risk - green
        }),
        borderWidth: 0,
    }],
}));

const targetsChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                title: (context: any) => props.analytics.targets[context[0].dataIndex].target,
                label: (context: any) => {
                    const target = props.analytics.targets[context.dataIndex];
                    return [
                        `Risk Score: ${target.risk_score}`,
                        `Total Checks: ${target.total_checks}`,
                        `Failed: ${target.failed}`,
                        `Passed: ${target.passed}`,
                    ];
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
            title: {
                display: true,
                text: 'Risk Score',
            },
        },
    },
};
</script>

<template>
    <Head :title="`Scan: ${scan.name}`" />
    <AppLayout>
        <div class="px-6 mt-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-4">
                    <Link :href="route('scans.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Back to Scans
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ scan.name }}</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ scan.company.name }} • {{ formatDateTime(scan.created_at) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <a v-if="scan.status === 'completed'" :href="route('scans.report.pdf', scan.id)" target="_blank">
                        <Button variant="outline" size="sm">
                            <Download class="h-4 w-4 mr-2" />
                            Download PDF
                        </Button>
                    </a>
                    <Badge :variant="getStatusBadgeVariant(scan.status)">
                        {{ scan.status.charAt(0).toUpperCase() + scan.status.slice(1) }}
                    </Badge>
                    <Badge v-if="scan.risk_grade" :variant="getRiskGradeVariant(scan.risk_grade)" class="text-lg px-3 py-1">
                        {{ scan.risk_grade }}
                    </Badge>
                </div>
            </div>

            <!-- Description -->
            <div v-if="scan.description" class="mb-6">
                <Card>
                    <CardContent class="pt-6">
                        <p class="text-gray-700 dark:text-gray-300">{{ scan.description }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Checks</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ analytics.overview.total_checks }}</div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ analytics.overview.targets_scanned }} targets scanned
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-green-600">Passed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ analytics.overview.passed_checks }}</div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ analytics.overview.total_checks > 0 ? Math.round((analytics.overview.passed_checks / analytics.overview.total_checks) * 100) : 0 }}% success rate
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-red-600">Failed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">{{ analytics.overview.failed_checks }}</div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ analytics.overview.total_checks > 0 ? Math.round((analytics.overview.failed_checks / analytics.overview.total_checks) * 100) : 0 }}% failure rate
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-blue-600">Duration</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">
                            {{ formatDuration(analytics.overview.scan_duration) }}
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ scan.completed_at ? 'Completed' : 'In progress' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs for different views -->
            <Tabs default-value="overview" class="space-y-6">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="overview">
                        <BarChart3 class="h-4 w-4 mr-2" />
                        Overview
                    </TabsTrigger>
                    <TabsTrigger value="results">
                        <Shield class="h-4 w-4 mr-2" />
                        Results
                    </TabsTrigger>
                    <TabsTrigger value="vulnerabilities">
                        <Bug class="h-4 w-4 mr-2" />
                        Vulnerabilities ({{ vulnerabilities.length }})
                    </TabsTrigger>
                    <TabsTrigger value="report">
                        <FileText class="h-4 w-4 mr-2" />
                        Report
                    </TabsTrigger>
                </TabsList>

                <!-- Overview Tab -->
                <TabsContent value="overview" class="space-y-6">
                    <!-- Charts -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Severity Breakdown -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Severity Breakdown</CardTitle>
                                <CardDescription>Distribution of issues by severity level</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="h-64">
                                    <Doughnut :data="severityChartData" :options="severityChartOptions" />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Check Types -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Check Types</CardTitle>
                                <CardDescription>Results by security check category</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="h-64">
                                    <Bar :data="checkTypesChartData" :options="checkTypesChartOptions" />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Targets Risk -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Target Risk Scores</CardTitle>
                                <CardDescription>Risk assessment for each target</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="h-64">
                                    <Bar :data="targetsChartData" :options="targetsChartOptions" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Target Summary Table -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Target Summary</CardTitle>
                            <CardDescription>Detailed breakdown by target</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Target</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Total Checks</TableHead>
                                        <TableHead>Passed</TableHead>
                                        <TableHead>Failed</TableHead>
                                        <TableHead>Risk Score</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="target in analytics.targets" :key="target.target">
                                        <TableCell class="font-medium">
                                            <div class="flex items-center space-x-2">
                                                <Target class="h-4 w-4 text-gray-400" />
                                                <span class="truncate max-w-xs">{{ target.target }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{{ target.target_type.toUpperCase() }}</Badge>
                                        </TableCell>
                                        <TableCell>{{ target.total_checks }}</TableCell>
                                        <TableCell>
                                            <span class="text-green-600 font-medium">{{ target.passed }}</span>
                                        </TableCell>
                                        <TableCell>
                                            <span class="text-red-600 font-medium">{{ target.failed }}</span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center space-x-2">
                                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                                    <div 
                                                        class="h-2 rounded-full"
                                                        :class="{
                                                            'bg-green-500': target.risk_score < 20,
                                                            'bg-yellow-500': target.risk_score >= 20 && target.risk_score < 50,
                                                            'bg-orange-500': target.risk_score >= 50 && target.risk_score < 80,
                                                            'bg-red-500': target.risk_score >= 80
                                                        }"
                                                        :style="{ width: `${target.risk_score}%` }"
                                                    ></div>
                                                </div>
                                                <span class="text-sm font-medium">{{ target.risk_score }}</span>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Results Tab -->
                <TabsContent value="results" class="space-y-6">
                    <div v-for="targetResult in resultsByTarget" :key="targetResult.target" class="space-y-4">
                        <Card>
                            <Collapsible>
                                <CollapsibleTrigger @click="toggleTarget(targetResult.target)" class="w-full">
                                    <CardHeader class="hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <component 
                                                    :is="expandedTargets.has(targetResult.target) ? ChevronDown : ChevronRight" 
                                                    class="h-4 w-4" 
                                                />
                                                <Target class="h-5 w-5 text-blue-600" />
                                                <div class="text-left">
                                                    <CardTitle class="text-lg">{{ targetResult.target }}</CardTitle>
                                                    <CardDescription>
                                                        {{ targetResult.target_type.toUpperCase() }} • 
                                                        {{ targetResult.results.reduce((sum, r) => sum + r.checks.length, 0) }} checks
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <Badge variant="outline">{{ targetResult.target_type }}</Badge>
                                        </div>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                
                                <CollapsibleContent v-if="expandedTargets.has(targetResult.target)">
                                    <CardContent class="pt-0">
                                        <div class="space-y-4">
                                            <div v-for="checkTypeGroup in targetResult.results" :key="`${targetResult.target}-${checkTypeGroup.check_type}`">
                                                <Collapsible>
                                                    <CollapsibleTrigger 
                                                        @click="toggleCheckType(`${targetResult.target}-${checkTypeGroup.check_type}`)"
                                                        class="w-full p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    >
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center space-x-2">
                                                                <component 
                                                                    :is="expandedCheckTypes.has(`${targetResult.target}-${checkTypeGroup.check_type}`) ? ChevronDown : ChevronRight" 
                                                                    class="h-4 w-4" 
                                                                />
                                                                <span class="font-medium">{{ checkTypeGroup.check_type.replace('_', ' ').toUpperCase() }}</span>
                                                                <Badge variant="outline">{{ checkTypeGroup.checks.length }} checks</Badge>
                                                            </div>
                                                            <div class="flex space-x-1">
                                                                <span class="text-green-600 text-sm">
                                                                    ✓ {{ checkTypeGroup.checks.filter(c => c.passed).length }}
                                                                </span>
                                                                <span class="text-red-600 text-sm">
                                                                    ✗ {{ checkTypeGroup.checks.filter(c => !c.passed).length }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </CollapsibleTrigger>
                                                    
                                                    <CollapsibleContent v-if="expandedCheckTypes.has(`${targetResult.target}-${checkTypeGroup.check_type}`)">
                                                        <div class="mt-2 space-y-2">
                                                            <div 
                                                                v-for="check in checkTypeGroup.checks" 
                                                                :key="check.id"
                                                                class="p-3 border rounded-lg"
                                                                :class="{
                                                                    'border-green-200 bg-green-50 dark:bg-green-900/20': check.passed,
                                                                    'border-red-200 bg-red-50 dark:bg-red-900/20': !check.passed
                                                                }"
                                                            >
                                                                <div class="flex items-start justify-between">
                                                                    <div class="flex-1">
                                                                        <div class="flex items-center space-x-2 mb-1">
                                                                            <component 
                                                                                :is="check.passed ? CheckCircle : XCircle" 
                                                                                class="h-4 w-4"
                                                                                :class="{
                                                                                    'text-green-600': check.passed,
                                                                                    'text-red-600': !check.passed
                                                                                }"
                                                                            />
                                                                            <span class="font-medium">{{ check.check_name || 'Security Check' }}</span>
                                                                            <Badge v-if="check.severity" :variant="getSeverityVariant(check.severity)">
                                                                                {{ check.severity.toUpperCase() }}
                                                                            </Badge>
                                                                        </div>
                                                                        
                                                                        <p v-if="check.message" class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                                            {{ check.message }}
                                                                        </p>
                                                                        
                                                                        <p v-if="check.description" class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                                                            {{ check.description }}
                                                                        </p>
                                                                        
                                                                        <div v-if="check.recommendations && check.recommendations.length > 0" class="mt-2">
                                                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recommendations:</p>
                                                                            <ul class="text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">
                                                                                <li v-for="rec in check.recommendations" :key="rec">{{ rec }}</li>
                                                                            </ul>
                                                                        </div>
                                                                        
                                                                        <div v-if="check.vulnerabilities && check.vulnerabilities.length > 0" class="mt-2">
                                                                            <div class="flex items-center space-x-1 mb-1">
                                                                                <Bug class="h-4 w-4 text-red-600" />
                                                                                <p class="text-sm font-medium text-red-600">
                                                                                    {{ check.vulnerabilities.length }} Vulnerabilities Found
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </CollapsibleContent>
                                                </Collapsible>
                                            </div>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Vulnerabilities Tab -->
                <TabsContent value="vulnerabilities" class="space-y-6">
                    <Card v-if="vulnerabilities.length === 0">
                        <CardContent class="pt-6">
                            <div class="text-center py-12">
                                <CheckCircle class="h-12 w-12 text-green-500 mx-auto mb-4" />
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No Vulnerabilities Found</h3>
                                <p class="text-gray-600 dark:text-gray-400">This scan did not identify any known vulnerabilities.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <div v-else class="space-y-4">
                        <div v-for="vuln in vulnerabilities" :key="`${vuln.target}-${vuln.cve}`">
                            <Card class="border-l-4 border-l-red-500">
                                <CardHeader>
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <CardTitle class="flex items-center space-x-2">
                                                <Bug class="h-5 w-5 text-red-600" />
                                                <span>{{ vuln.cve }}</span>
                                                <Badge :variant="getSeverityVariant(vuln.severity)">
                                                    {{ vuln.severity.toUpperCase() }}
                                                </Badge>
                                                <Badge v-if="vuln.score" variant="outline">
                                                    CVSS: {{ vuln.score }}
                                                </Badge>
                                            </CardTitle>
                                            <CardDescription class="mt-1">
                                                {{ vuln.target }} • {{ vuln.check_type.replace('_', ' ') }}
                                                <span v-if="vuln.published"> • Published: {{ new Date(vuln.published).toLocaleDateString() }}</span>
                                            </CardDescription>
                                        </div>
                                        <Badge v-if="vuln.patch_available" variant="secondary">
                                            Patch Available
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-gray-700 dark:text-gray-300 mb-4">{{ vuln.description }}</p>
                                    
                                    <div v-if="vuln.recommended_action" class="mb-4">
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Recommended Action:</h4>
                                        <p class="text-gray-700 dark:text-gray-300">{{ vuln.recommended_action }}</p>
                                    </div>
                                    
                                    <div v-if="vuln.references.length > 0" class="space-y-2">
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100">References:</h4>
                                        <div class="space-y-1">
                                            <a 
                                                v-for="ref in vuln.references" 
                                                :key="ref"
                                                :href="ref"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center space-x-1 text-blue-600 hover:text-blue-800 text-sm"
                                            >
                                                <ExternalLink class="h-3 w-3" />
                                                <span>{{ ref }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </TabsContent>

                <!-- Report Tab -->
                <TabsContent value="report" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Executive Summary</CardTitle>
                            <CardDescription>High-level overview of security posture</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="prose max-w-none dark:prose-invert">
                                <p>
                                    This security scan analyzed <strong>{{ analytics.overview.targets_scanned }}</strong> targets 
                                    and performed <strong>{{ analytics.overview.total_checks }}</strong> security checks.
                                </p>
                                
                                <p>
                                    The scan identified <strong>{{ analytics.overview.failed_checks }}</strong> security issues, 
                                    with <strong>{{ vulnerabilities.length }}</strong> known vulnerabilities detected.
                                    The overall risk grade assigned is <strong class="text-2xl">{{ scan.risk_grade || 'N/A' }}</strong>.
                                </p>

                                <h3>Key Findings:</h3>
                                <ul>
                                    <li v-if="analytics.severity_breakdown.critical > 0">
                                        <strong class="text-red-600">{{ analytics.severity_breakdown.critical }}</strong> critical severity issues require immediate attention
                                    </li>
                                    <li v-if="analytics.severity_breakdown.high > 0">
                                        <strong class="text-orange-600">{{ analytics.severity_breakdown.high }}</strong> high severity issues should be prioritized
                                    </li>
                                    <li v-if="analytics.severity_breakdown.medium > 0">
                                        <strong class="text-yellow-600">{{ analytics.severity_breakdown.medium }}</strong> medium severity issues identified
                                    </li>
                                    <li>
                                        <strong class="text-green-600">{{ analytics.overview.passed_checks }}</strong> checks passed successfully
                                    </li>
                                </ul>

                                <h3>Recommendations:</h3>
                                <ul>
                                    <li v-if="vulnerabilities.length > 0">Address the {{ vulnerabilities.length }} identified vulnerabilities by applying available patches</li>
                                    <li v-if="analytics.severity_breakdown.critical > 0">Prioritize fixing critical severity issues immediately</li>
                                    <li v-if="analytics.severity_breakdown.high > 0">Develop a remediation plan for high severity issues</li>
                                    <li>Consider implementing a regular scanning schedule to maintain security posture</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>