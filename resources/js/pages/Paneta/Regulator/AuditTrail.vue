<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem, AuditLog } from '@/types';
import { FileText, Filter, Calendar, User, Activity, Database, Shield } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    logs: {
        data: AuditLog[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    actions: string[];
    filters?: {
        action?: string;
        entity_type?: string;
        date_from?: string;
        date_to?: string;
    };
    summary: {
        total_logs: number;
        by_action: Array<{ action: string; count: number }>;
        by_entity_type: Array<{ entity_type: string; count: number }>;
        by_date: Array<{ date: string; count: number }>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Regulator', href: '/paneta/regulator' },
    { title: 'Audit Trail' },
];

const selectedAction = ref(props.filters?.action || '');
const selectedEntityType = ref(props.filters?.entity_type || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const applyFilters = () => {
    router.get('/paneta/regulator/audit-trail', {
        action: selectedAction.value || undefined,
        entity_type: selectedEntityType.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true });
};

const clearFilters = () => {
    selectedAction.value = '';
    selectedEntityType.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    router.get('/paneta/regulator/audit-trail');
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const getActionColor = (action: string) => {
    if (action.includes('create') || action.includes('register')) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
    } else if (action.includes('update') || action.includes('modify')) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
    } else if (action.includes('delete') || action.includes('suspend')) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
    } else if (action.includes('flag') || action.includes('alert')) {
        return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300';
    } else {
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const goToPage = (page: number) => {
    router.get('/paneta/regulator/audit-trail', {
        page,
        action: selectedAction.value || undefined,
        entity_type: selectedEntityType.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true });
};
</script>

<template>
    <Head title="Audit Trail - Regulator Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Audit Trail</h1>
                    <p class="text-muted-foreground">
                        Complete platform activity log for regulatory compliance and oversight
                    </p>
                </div>
                <Badge variant="outline" class="flex items-center gap-2">
                    <Shield class="h-3 w-3" />
                    {{ logs.total }} Total Records
                </Badge>
            </div>

            <!-- Summary Metrics -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Audit Logs</CardTitle>
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ summary.total_logs }}</div>
                        <p class="text-xs text-muted-foreground">
                            All platform activities
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Unique Actions</CardTitle>
                        <Activity class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ summary.by_action.length }}</div>
                        <p class="text-xs text-muted-foreground">
                            Different action types
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Entity Types</CardTitle>
                        <Database class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ summary.by_entity_type.length }}</div>
                        <p class="text-xs text-muted-foreground">
                            Tracked entities
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Days</CardTitle>
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ summary.by_date.length }}</div>
                        <p class="text-xs text-muted-foreground">
                            Days with activity
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Top Actions & Entity Types -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Top Actions</CardTitle>
                        <CardDescription>Most frequent platform actions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div v-for="item in summary.by_action.slice(0, 10)" :key="item.action" class="flex justify-between items-center text-sm border-b pb-2">
                                <Badge :class="getActionColor(item.action)" class="font-mono text-xs">
                                    {{ item.action }}
                                </Badge>
                                <span class="font-medium">{{ item.count }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Entity Types</CardTitle>
                        <CardDescription>Tracked entity distribution</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div v-for="item in summary.by_entity_type" :key="item.entity_type" class="flex justify-between items-center text-sm border-b pb-2">
                                <span class="capitalize">{{ item.entity_type || 'N/A' }}</span>
                                <span class="font-medium">{{ item.count }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-5 w-5" />
                        Filter Audit Logs
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="text-sm font-medium mb-2 block">Action</label>
                            <select v-model="selectedAction" class="w-full rounded-md border px-3 py-2">
                                <option value="">All Actions</option>
                                <option v-for="action in actions" :key="action" :value="action">
                                    {{ action }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-2 block">Entity Type</label>
                            <select v-model="selectedEntityType" class="w-full rounded-md border px-3 py-2">
                                <option value="">All Types</option>
                                <option value="user">User</option>
                                <option value="transaction">Transaction</option>
                                <option value="institution">Institution</option>
                                <option value="linked_account">Linked Account</option>
                                <option value="subscription">Subscription</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-2 block">Date From</label>
                            <input v-model="dateFrom" type="date" class="w-full rounded-md border px-3 py-2" />
                        </div>
                        <div>
                            <label class="text-sm font-medium mb-2 block">Date To</label>
                            <input v-model="dateTo" type="date" class="w-full rounded-md border px-3 py-2" />
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <Button @click="applyFilters" variant="default">
                            Apply Filters
                        </Button>
                        <Button @click="clearFilters" variant="outline">
                            Clear
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Audit Logs Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Audit Log Records</CardTitle>
                    <CardDescription>Complete immutable record of all platform activities</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-2 font-medium">ID</th>
                                    <th class="text-left py-3 px-2 font-medium">Action</th>
                                    <th class="text-left py-3 px-2 font-medium">User</th>
                                    <th class="text-left py-3 px-2 font-medium">Entity Type</th>
                                    <th class="text-left py-3 px-2 font-medium">Entity ID</th>
                                    <th class="text-left py-3 px-2 font-medium">Metadata</th>
                                    <th class="text-left py-3 px-2 font-medium">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in logs.data" :key="log.id" class="border-b hover:bg-muted/50">
                                    <td class="py-4 px-2">
                                        <span class="font-mono text-xs">#{{ log.id }}</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <Badge :class="getActionColor(log.action)" class="font-mono text-xs">
                                            {{ log.action }}
                                        </Badge>
                                    </td>
                                    <td class="py-4 px-2">
                                        <div v-if="log.user">
                                            <p class="text-sm font-medium">{{ log.user.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ log.user.email }}</p>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">System</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <span class="text-sm capitalize">{{ log.entity_type || 'N/A' }}</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <span class="font-mono text-xs">{{ log.entity_id || 'N/A' }}</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <div v-if="log.metadata" class="max-w-xs">
                                            <details class="text-xs">
                                                <summary class="cursor-pointer text-primary hover:underline">
                                                    View metadata
                                                </summary>
                                                <pre class="mt-2 p-2 bg-muted rounded text-xs overflow-x-auto">{{ JSON.stringify(log.metadata, null, 2) }}</pre>
                                            </details>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">No metadata</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <span class="text-sm">{{ formatDate(log.created_at) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6">
                        <p class="text-sm text-muted-foreground">
                            Showing {{ ((logs.current_page - 1) * logs.per_page) + 1 }} 
                            to {{ Math.min(logs.current_page * logs.per_page, logs.total) }} 
                            of {{ logs.total }} records
                        </p>
                        <div class="flex gap-2">
                            <Button 
                                @click="goToPage(logs.current_page - 1)" 
                                :disabled="logs.current_page === 1"
                                variant="outline"
                                size="sm"
                            >
                                Previous
                            </Button>
                            <div class="flex items-center gap-2 px-3">
                                <span class="text-sm">Page {{ logs.current_page }} of {{ logs.last_page }}</span>
                            </div>
                            <Button 
                                @click="goToPage(logs.current_page + 1)" 
                                :disabled="logs.current_page === logs.last_page"
                                variant="outline"
                                size="sm"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Compliance Notice -->
            <Card class="border-primary/50 bg-primary/5">
                <CardContent class="pt-6">
                    <div class="flex items-start gap-4">
                        <Shield class="h-6 w-6 text-primary mt-1" />
                        <div>
                            <h3 class="font-semibold text-lg mb-2">Regulatory Compliance Notice</h3>
                            <p class="text-sm text-muted-foreground">
                                This audit trail is immutable and cannot be modified or deleted. All platform activities 
                                are automatically logged to ensure full transparency and regulatory compliance. Each record 
                                includes the user, action, affected entity, and complete metadata for comprehensive oversight.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
