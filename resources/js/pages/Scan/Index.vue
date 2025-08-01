<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import Badge from '@/components/ui/badge.vue';
import Progress from '@/components/ui/progress.vue';
import Table from '@/components/ui/table.vue';
import TableBody from '@/components/ui/table-body.vue';
import TableCell from '@/components/ui/table-cell.vue';
import TableHead from '@/components/ui/table-head.vue';
import TableHeader from '@/components/ui/table-header.vue';
import TableRow from '@/components/ui/table-row.vue';
import Card from '@/components/ui/card.vue';
import CardContent from '@/components/ui/card-content.vue';
import CardDescription from '@/components/ui/card-description.vue';
import CardHeader from '@/components/ui/card-header.vue';
import CardTitle from '@/components/ui/card-title.vue';
import { Plus, AlertTriangle, CheckCircle, Clock, Play, X } from 'lucide-vue-next';

interface ScanResult {
    id: number;
    uuid: string;
    name: string;
    description: string;
    status: 'pending' | 'running' | 'completed' | 'failed';
    risk_grade: string | null;
    schedule_type: string;
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    company: {
        id: number;
        name: string;
    };
    urls: string[] | null;
    ip_addresses: string[] | null;
    results_count: number;
    passed_checks_count: number;
    failed_checks_count: number;
    high_risk_count: number;
}

interface Stats {
    total_scans: number;
    completed_scans: number;
    running_scans: number;
    failed_scans: number;
    pending_scans: number;
}

interface PaginatedScans {
    data: ScanResult[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    scans: PaginatedScans;
    stats: Stats;
}

const props = defineProps<Props>();

const progressData = ref<Record<number, { progress: number; current_target: string }>>({});
const pollingInterval = ref<NodeJS.Timeout | null>(null);

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'completed':
            return 'default';
        case 'running':
            return 'secondary';
        case 'failed':
            return 'destructive';
        case 'pending':
            return 'outline';
        default:
            return 'outline';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed':
            return CheckCircle;
        case 'running':
            return Play;
        case 'failed':
            return X;
        case 'pending':
            return Clock;
        default:
            return Clock;
    }
};

const getRiskGradeVariant = (grade: string | null) => {
    if (!grade) return 'outline';
    switch (grade) {
        case 'A':
            return 'default';
        case 'B':
            return 'secondary';
        case 'C':
            return 'outline';
        case 'D':
            return 'destructive';
        case 'F':
            return 'destructive';
        default:
            return 'outline';
    }
};

const formatDateTime = (dateString: string | null) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
};

const formatTargets = (urls: string[] | null, ips: string[] | null) => {
    const targets = [];
    if (urls && urls.length > 0) {
        targets.push(`${urls.length} URL${urls.length > 1 ? 's' : ''}`);
    }
    if (ips && ips.length > 0) {
        targets.push(`${ips.length} IP${ips.length > 1 ? 's' : ''}`);
    }
    return targets.join(', ') || 'No targets';
};

const pollProgress = async () => {
    const runningScans = props.scans.data.filter(scan => scan.status === 'running');
    
    if (runningScans.length === 0) {
        return;
    }

    try {
        const promises = runningScans.map(async (scan) => {
            const response = await fetch(route('scans.progress', scan.id));
            const data = await response.json();
            
            progressData.value[scan.id] = {
                progress: data.progress || 0,
                current_target: data.current_target || '',
            };

            // If scan is no longer running, refresh the page
            if (data.status !== 'running') {
                router.reload({ only: ['scans'] });
            }
        });

        await Promise.all(promises);
    } catch (error) {
        console.error('Error polling progress:', error);
    }
};

onMounted(() => {
    // Initial progress check
    pollProgress();
    
    // Set up polling for running scans
    pollingInterval.value = setInterval(pollProgress, 2000); // Poll every 2 seconds
});

onUnmounted(() => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
    }
});
</script>

<template>
    <Head title="Scans" />
    <AppLayout>
        <div class="px-6 mt-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Security Scans</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Monitor and manage your security scans
                    </p>
                </div>
                <Link :href="route('scans.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        New Scan
                    </Button>
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_scans }}</div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-green-600">Completed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ stats.completed_scans }}</div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-blue-600">Running</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ stats.running_scans }}</div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-orange-600">Pending</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">{{ stats.pending_scans }}</div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-red-600">Failed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">{{ stats.failed_scans }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Scans Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Recent Scans</CardTitle>
                    <CardDescription>
                        Showing {{ scans.data.length }} of {{ scans.total }} scans
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Company</TableHead>
                                <TableHead>Targets</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Risk Grade</TableHead>
                                <TableHead>Results</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="scan in scans.data" :key="scan.id">
                                <!-- Name -->
                                <TableCell>
                                    <div>
                                        <div class="font-medium">{{ scan.name }}</div>
                                        <div v-if="scan.description" class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                            {{ scan.description }}
                                        </div>
                                    </div>
                                </TableCell>

                                <!-- Company -->
                                <TableCell>
                                    {{ scan.company.name }}
                                </TableCell>

                                <!-- Targets -->
                                <TableCell>
                                    <div class="text-sm">
                                        {{ formatTargets(scan.urls, scan.ip_addresses) }}
                                    </div>
                                </TableCell>

                                <!-- Status with Progress -->
                                <TableCell>
                                    <div class="space-y-2">
                                        <Badge :variant="getStatusBadgeVariant(scan.status)">
                                            <component :is="getStatusIcon(scan.status)" class="h-3 w-3 mr-1" />
                                            {{ scan.status.charAt(0).toUpperCase() + scan.status.slice(1) }}
                                        </Badge>
                                        
                                        <!-- Progress Bar for Running Scans -->
                                        <div v-if="scan.status === 'running' && progressData[scan.id]" class="w-full">
                                            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                                <span>{{ progressData[scan.id].progress }}% complete</span>
                                                <span v-if="progressData[scan.id].current_target">
                                                    {{ progressData[scan.id].current_target }}
                                                </span>
                                            </div>
                                            <Progress :value="progressData[scan.id].progress" class="h-2" />
                                        </div>
                                    </div>
                                </TableCell>

                                <!-- Risk Grade -->
                                <TableCell>
                                    <Badge v-if="scan.risk_grade" :variant="getRiskGradeVariant(scan.risk_grade)">
                                        {{ scan.risk_grade }}
                                    </Badge>
                                    <span v-else class="text-gray-400">-</span>
                                </TableCell>

                                <!-- Results Summary -->
                                <TableCell>
                                    <div v-if="scan.results_count > 0" class="text-sm space-y-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-green-600">✓ {{ scan.passed_checks_count }}</span>
                                            <span class="text-red-600">✗ {{ scan.failed_checks_count }}</span>
                                            <span v-if="scan.high_risk_count > 0" class="text-orange-600">
                                                <AlertTriangle class="h-3 w-3 inline mr-1" />
                                                {{ scan.high_risk_count }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ scan.results_count }} total checks
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400">No results</span>
                                </TableCell>

                                <!-- Created Date -->
                                <TableCell>
                                    <div class="text-sm">
                                        {{ formatDateTime(scan.created_at) }}
                                    </div>
                                    <div v-if="scan.completed_at" class="text-xs text-gray-500">
                                        Completed: {{ formatDateTime(scan.completed_at) }}
                                    </div>
                                </TableCell>

                                <!-- Actions -->
                                <TableCell>
                                    <div class="flex space-x-2">
                                        <Link :href="route('scans.show', scan.id)">
                                            <Button variant="outline" size="sm">View</Button>
                                        </Link>
                                        <a v-if="scan.status === 'completed'" :href="route('scans.report.pdf', scan.id)" target="_blank">
                                            <Button variant="outline" size="sm">Report</Button>
                                        </a>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Empty State -->
                    <div v-if="scans.data.length === 0" class="text-center py-12">
                        <div class="text-gray-400 mb-4">
                            <Clock class="h-12 w-12 mx-auto mb-2" />
                            <h3 class="text-lg font-medium">No scans yet</h3>
                            <p class="text-sm">Get started by creating your first security scan.</p>
                        </div>
                        <Link :href="route('scans.create')">
                            <Button>
                                <Plus class="h-4 w-4 mr-2" />
                                Create Your First Scan
                            </Button>
                        </Link>
                    </div>

                    <!-- Pagination -->
                    <div v-if="scans.last_page > 1" class="mt-6 flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Showing {{ ((scans.current_page - 1) * scans.per_page) + 1 }} to 
                            {{ Math.min(scans.current_page * scans.per_page, scans.total) }} of 
                            {{ scans.total }} results
                        </div>
                        
                        <div class="flex space-x-2">
                            <template v-for="link in scans.links" :key="link.label">
                                <Button
                                    v-if="link.url"
                                    :variant="link.active ? 'default' : 'outline'"
                                    size="sm"
                                    @click="router.get(link.url)"
                                    v-html="link.label"
                                />
                                <Button
                                    v-else
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>