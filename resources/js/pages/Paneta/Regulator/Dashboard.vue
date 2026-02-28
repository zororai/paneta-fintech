<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import { 
    Users, ArrowUpRight, CheckCircle, XCircle, DollarSign, TrendingUp, 
    AlertTriangle, Calendar, Globe, BarChart3, FileText, Building2, 
    Activity, Shield, Download, Eye
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps<{
    stats: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Regulator', href: '/paneta/regulator' },
    { title: 'Dashboard' },
];

const selectedPeriod = ref(props.stats.current_period || 'all');
const showPartiesModal = ref(false);
const showReportModal = ref(false);
const selectedReport = ref<any>(null);
const loadingReport = ref(false);

const periodOptions = [
    { value: 'all', label: 'All Time' },
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Bi-Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
];

const changePeriod = () => {
    router.get('/paneta/regulator', { period: selectedPeriod.value }, { preserveState: true });
};

const generateReport = async (reportType: string) => {
    loadingReport.value = true;
    try {
        const response = await axios.get('/paneta/regulator/reports/generate', {
            params: { type: reportType }
        });
        selectedReport.value = response.data;
        showReportModal.value = true;
    } catch (error) {
        console.error('Failed to generate report:', error);
    } finally {
        loadingReport.value = false;
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'executed':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};
</script>

<template>
    <Head title="Regulator Panel - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Regulator Panel</h1>
                    <p class="text-muted-foreground">
                        Comprehensive platform oversight and regulatory compliance monitoring
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Badge variant="outline" class="flex items-center gap-1">
                        <Shield class="h-3 w-3" />
                        Read-Only Access
                    </Badge>
                    <div class="flex items-center gap-2">
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                        <select
                            v-model="selectedPeriod"
                            @change="changePeriod"
                            class="rounded-md border px-3 py-1.5 text-sm"
                        >
                            <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 1. Total Transactions Initiated -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
                        <Activity class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.transactions.total }}</div>
                        <p class="text-xs text-muted-foreground">
                            Platform-wide transactions
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Executed</CardTitle>
                        <CheckCircle class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ stats.transactions.executed }}</div>
                        <p class="text-xs text-muted-foreground">
                            Successfully completed
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Pending</CardTitle>
                        <TrendingUp class="h-4 w-4 text-yellow-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-yellow-600">{{ stats.transactions.pending }}</div>
                        <p class="text-xs text-muted-foreground">
                            In progress
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Failed</CardTitle>
                        <XCircle class="h-4 w-4 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-red-600">{{ stats.transactions.failed }}</div>
                        <p class="text-xs text-muted-foreground">
                            Unsuccessful attempts
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- 2. Queries and Success/Failure Rates -->
            <Card>
                <CardHeader>
                    <CardTitle>Queries & Resolution Tracking</CardTitle>
                    <CardDescription>User queries and platform issue resolution metrics</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Queries</p>
                            <p class="text-2xl font-bold">{{ stats.queries.total }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Resolved</p>
                            <p class="text-2xl font-bold text-green-600">{{ stats.queries.resolved }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Success Rate</p>
                            <p class="text-2xl font-bold text-green-600">{{ stats.queries.success_rate }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Failure Rate</p>
                            <p class="text-2xl font-bold text-red-600">{{ stats.queries.failure_rate }}%</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Flagged Transactions and Users -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-orange-500" />
                        Flagged Transactions & Users
                    </CardTitle>
                    <CardDescription>Items requiring regulatory attention</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Flagged Transactions ({{ stats.flagged_data.transactions.length }})</h4>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <div v-for="item in stats.flagged_data.transactions.slice(0, 10)" :key="item.id" class="rounded-lg border p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-mono text-xs">{{ item.reference }}</span>
                                        <Badge variant="destructive" class="text-xs">{{ item.action }}</Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ item.user_name }} ({{ item.user_email }})</p>
                                    <p class="text-xs mt-1">{{ item.reason }}</p>
                                    <p class="text-xs text-muted-foreground mt-1">{{ formatDate(item.created_at) }}</p>
                                </div>
                                <div v-if="stats.flagged_data.transactions.length === 0" class="text-center text-sm text-muted-foreground py-4">
                                    No flagged transactions
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Flagged Users ({{ stats.flagged_data.users.length }})</h4>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <div v-for="item in stats.flagged_data.users.slice(0, 10)" :key="item.id" class="rounded-lg border p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-medium text-sm">{{ item.name }}</span>
                                        <Badge :variant="item.is_suspended ? 'destructive' : 'outline'" class="text-xs">
                                            {{ item.risk_tier.toUpperCase() }}
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ item.email }}</p>
                                    <p class="text-xs mt-1">KYC: {{ item.kyc_status }} | Transactions: {{ item.total_transactions }}</p>
                                    <p class="text-xs mt-1" v-if="item.is_suspended">
                                        <Badge variant="destructive" class="text-xs">SUSPENDED</Badge>
                                    </p>
                                </div>
                                <div v-if="stats.flagged_data.users.length === 0" class="text-center text-sm text-muted-foreground py-4">
                                    No flagged users
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Transaction Parties -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Transaction Parties Overview
                    </CardTitle>
                    <CardDescription>Users and institutions involved in platform transactions</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3 mb-6">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Users</p>
                            <p class="text-2xl font-bold">{{ stats.transaction_parties.total_users }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Total Institutions</p>
                            <p class="text-2xl font-bold">{{ stats.transaction_parties.total_institutions }}</p>
                        </div>
                        <div>
                            <Button @click="showPartiesModal = true" variant="outline" class="mt-4">
                                <Eye class="h-4 w-4 mr-2" />
                                View Details
                            </Button>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-3 font-semibold text-sm">Institutions Summary</h4>
                        <div class="space-y-2">
                            <div v-for="inst in stats.transaction_parties.institutions_summary.slice(0, 5)" :key="inst.name" class="flex justify-between text-sm border-b pb-2">
                                <div>
                                    <span class="font-medium">{{ inst.name }}</span>
                                    <span class="text-muted-foreground ml-2">({{ inst.type }})</span>
                                </div>
                                <span class="font-medium">{{ inst.linked_accounts_count }} accounts</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 5. Volume and Currency Exchange Metrics -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction Volume Metrics</CardTitle>
                        <CardDescription>Platform transaction volumes and distribution</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="mb-4">
                            <p class="text-sm text-muted-foreground">Total Volume</p>
                            <p class="text-3xl font-bold">{{ formatCurrency(stats.volume_metrics.total_volume) }}</p>
                        </div>
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">By Currency</h4>
                            <div class="space-y-2">
                                <div v-for="curr in stats.volume_metrics.by_currency" :key="curr.currency" class="flex justify-between text-sm">
                                    <span>{{ curr.currency }}</span>
                                    <div class="text-right">
                                        <p class="font-medium">{{ formatCurrency(curr.total_volume) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ curr.transaction_count }} txns</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Currency Exchange Activity</CardTitle>
                        <CardDescription>Cross-currency transaction metrics</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="exchange in stats.volume_metrics.currency_exchange" :key="`${exchange.from_currency}-${exchange.to_currency}`" class="rounded-lg border p-3">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-sm">{{ exchange.from_currency }} → {{ exchange.to_currency }}</p>
                                        <p class="text-xs text-muted-foreground">{{ exchange.exchange_count }} exchanges</p>
                                    </div>
                                    <p class="font-medium">{{ formatCurrency(exchange.total_amount) }}</p>
                                </div>
                            </div>
                            <div v-if="stats.volume_metrics.currency_exchange.length === 0" class="text-center text-sm text-muted-foreground py-4">
                                No currency exchange activity
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 6. Automated Reports -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Automated Performance & Financial Reports
                    </CardTitle>
                    <CardDescription>Generate comprehensive regulatory reports</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div v-for="report in stats.performance_reports.available_reports" :key="report.type" class="rounded-lg border p-4">
                            <h4 class="font-semibold text-sm mb-2">{{ report.label }}</h4>
                            <p class="text-xs text-muted-foreground mb-3">
                                Last generated: {{ formatDate(report.last_generated) }}
                            </p>
                            <Button @click="generateReport(report.type)" size="sm" variant="outline" class="w-full">
                                <Download class="h-3 w-3 mr-2" />
                                Generate Report
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 7. Audit Trail Summary -->
            <Card>
                <CardHeader>
                    <CardTitle>Audit Trail Summary</CardTitle>
                    <CardDescription>Comprehensive activity logging and tracking</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3 mb-6">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Audit Logs</p>
                            <p class="text-2xl font-bold">{{ stats.audit_trail_summary.total_logs }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Institutions Tracked</p>
                            <p class="text-2xl font-bold">{{ stats.audit_trail_summary.by_institution_count }}</p>
                        </div>
                        <div>
                            <Button @click="router.visit('/paneta/regulator/audit-trail')" variant="outline">
                                View Full Audit Trail
                            </Button>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-3 font-semibold text-sm">Top Actions</h4>
                        <div class="space-y-2">
                            <div v-for="log in stats.audit_trail_summary.by_action" :key="log.action" class="flex justify-between text-sm border-b pb-2">
                                <span class="font-mono text-xs">{{ log.action }}</span>
                                <span class="font-medium">{{ log.count }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 8. Capital Flows Reports -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Globe class="h-5 w-5" />
                        Capital Flows & Cross-Border Transactions
                    </CardTitle>
                    <CardDescription>International money movement tracking</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Capital Inflows by Country</h4>
                            <div class="space-y-2">
                                <div v-for="flow in stats.capital_flows.inflows.slice(0, 5)" :key="flow.destination_country" class="flex justify-between text-sm">
                                    <span>{{ flow.destination_country }}</span>
                                    <div class="text-right">
                                        <p class="font-medium text-green-600">{{ formatCurrency(flow.total_inflow) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ flow.transaction_count }} txns</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Capital Outflows by Country</h4>
                            <div class="space-y-2">
                                <div v-for="flow in stats.capital_flows.outflows.slice(0, 5)" :key="flow.country" class="flex justify-between text-sm">
                                    <span>{{ flow.country }}</span>
                                    <div class="text-right">
                                        <p class="font-medium text-red-600">{{ formatCurrency(flow.total_outflow) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ flow.transaction_count }} txns</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Transaction Parties Modal -->
            <Dialog v-model:open="showPartiesModal">
                <DialogContent class="max-w-4xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Transaction Parties Details</DialogTitle>
                        <DialogDescription>Complete list of users and institutions involved in transactions</DialogDescription>
                    </DialogHeader>
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-semibold mb-3">Recent Transactions</h4>
                            <div class="space-y-3">
                                <div v-for="txn in stats.transaction_parties.recent_transactions.slice(0, 20)" :key="txn.reference" class="rounded-lg border p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-mono text-xs">{{ txn.reference }}</span>
                                        <Badge :class="getStatusColor(txn.status)">{{ txn.status }}</Badge>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 text-xs">
                                        <div>
                                            <p class="font-semibold">User</p>
                                            <p>{{ txn.user.name }}</p>
                                            <p class="text-muted-foreground">{{ txn.user.email }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">Amount</p>
                                            <p>{{ formatCurrency(txn.amount) }} {{ txn.currency }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">Issuer Institution</p>
                                            <p>{{ txn.issuer_institution.name }}</p>
                                            <p class="text-muted-foreground">{{ txn.issuer_institution.country }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">Destination Institution</p>
                                            <p>{{ txn.destination_institution.name }}</p>
                                            <p class="text-muted-foreground">{{ txn.destination_institution.country }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Report Modal -->
            <Dialog v-model:open="showReportModal">
                <DialogContent class="max-w-4xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Performance Report</DialogTitle>
                        <DialogDescription v-if="selectedReport">
                            {{ selectedReport.type.charAt(0).toUpperCase() + selectedReport.type.slice(1) }} Report - Generated {{ formatDate(selectedReport.generated_at) }}
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="selectedReport" class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-4">
                            <div class="rounded-lg border p-3">
                                <p class="text-sm text-muted-foreground">Total Transactions</p>
                                <p class="text-2xl font-bold">{{ selectedReport.transactions.total }}</p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-sm text-muted-foreground">Executed</p>
                                <p class="text-2xl font-bold text-green-600">{{ selectedReport.transactions.executed }}</p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-sm text-muted-foreground">Pending</p>
                                <p class="text-2xl font-bold text-yellow-600">{{ selectedReport.transactions.pending }}</p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-sm text-muted-foreground">Failed</p>
                                <p class="text-2xl font-bold text-red-600">{{ selectedReport.transactions.failed }}</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold mb-3">Volume Metrics</h4>
                            <p class="text-3xl font-bold">{{ formatCurrency(selectedReport.volume_metrics.total_volume) }}</p>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
