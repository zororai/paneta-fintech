<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { BreadcrumbItem, AdminStats, TransactionIntent } from '@/types';
import { Users, ArrowUpRight, CheckCircle, XCircle, DollarSign, TrendingUp, Coins, Wallet, Eye, AlertTriangle, Link2, UserCheck, Calendar, Globe, BarChart3 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps<{
    stats: AdminStats;
    recentTransactions: TransactionIntent[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Admin', href: '/paneta/admin' },
    { title: 'Dashboard' },
];

const selectedPeriod = ref(props.stats.current_period || 'all');
const showTransactionFeeModal = ref(false);
const showSubscriptionModal = ref(false);
const showAdsModal = ref(false);
const revenueDetails = ref<any>(null);
const loadingDetails = ref(false);

const periodOptions = [
    { value: 'all', label: 'All Time' },
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Bi-Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
    { value: '5year', label: '5-Year Tenure' },
    { value: '10year', label: '10-Year Tenure' },
];

const changePeriod = () => {
    router.get('/paneta/admin', { period: selectedPeriod.value }, { preserveState: true });
};

const viewTransactionFeeDetails = async () => {
    loadingDetails.value = true;
    try {
        const response = await axios.get('/paneta/admin/revenue/transaction-fees', {
            params: { period: selectedPeriod.value }
        });
        revenueDetails.value = response.data;
        showTransactionFeeModal.value = true;
    } catch (error) {
        console.error('Failed to load transaction fee details:', error);
    } finally {
        loadingDetails.value = false;
    }
};

const viewSubscriptionDetails = async () => {
    loadingDetails.value = true;
    try {
        const response = await axios.get('/paneta/admin/revenue/subscriptions', {
            params: { period: selectedPeriod.value }
        });
        revenueDetails.value = response.data;
        showSubscriptionModal.value = true;
    } catch (error) {
        console.error('Failed to load subscription details:', error);
    } finally {
        loadingDetails.value = false;
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

const formatTime = (seconds: number) => {
    if (seconds < 60) {
        return `${Math.round(seconds)}s`;
    } else if (seconds < 3600) {
        return `${Math.round(seconds / 60)}m`;
    } else {
        return `${Math.round(seconds / 3600)}h`;
    }
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
    <Head title="Admin Dashboard - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Main Dashboard</h1>
                    <p class="text-muted-foreground">
                        Comprehensive platform analytics and management
                    </p>
                </div>
                <div class="flex items-center gap-3">
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

            <!-- Revenue Breakdown Section -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card class="border-accent/30 bg-gradient-to-br from-accent/5 to-accent/10">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Transaction Fees (0.99%)</CardTitle>
                        <div class="flex items-center gap-2">
                            <Coins class="h-4 w-4 text-accent" />
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0"
                                @click="viewTransactionFeeDetails"
                            >
                                <Eye class="h-3 w-3" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-accent-foreground">
                            {{ formatCurrency(stats.transaction_fees || 0) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            From {{ formatCurrency(stats.total_volume) }} volume
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-primary/30 bg-gradient-to-br from-primary/5 to-primary/10">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Subscription Revenue</CardTitle>
                        <div class="flex items-center gap-2">
                            <UserCheck class="h-4 w-4 text-primary" />
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0"
                                @click="viewSubscriptionDetails"
                            >
                                <Eye class="h-3 w-3" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-primary">
                            {{ formatCurrency(stats.subscription_revenue || 0) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Active subscriptions
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-blue-500/30 bg-gradient-to-br from-blue-500/5 to-blue-500/10">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Ads Revenue</CardTitle>
                        <div class="flex items-center gap-2">
                            <BarChart3 class="h-4 w-4 text-blue-500" />
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-6 w-6 p-0"
                                @click="showAdsModal = true"
                            >
                                <Eye class="h-3 w-3" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">
                            {{ formatCurrency(stats.ads_revenue || 0) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Coming soon
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-green-500/30 bg-gradient-to-br from-green-500/5 to-green-500/10">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Combined Revenue</CardTitle>
                        <DollarSign class="h-4 w-4 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">
                            {{ formatCurrency(stats.total_revenue || 0) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            All revenue sources
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_users }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.verified_users }} verified
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Transactions</CardTitle>
                        <ArrowUpRight class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_transactions }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.pending_transactions }} pending
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Success Rate</CardTitle>
                        <CheckCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                stats.total_transactions > 0
                                    ? (
                                          (stats.executed_transactions / stats.total_transactions) *
                                          100
                                      ).toFixed(1)
                                    : 0
                            }}%
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.failed_transactions }} failed
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Volume</CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(stats.total_volume) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ formatCurrency(stats.today_volume) }} today
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Users by Role -->
            <Card>
                <CardHeader>
                    <CardTitle>Users by Role</CardTitle>
                    <CardDescription>Platform user distribution</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div v-for="(count, role) in stats.users_by_role" :key="role" class="flex flex-col">
                            <span class="text-sm text-muted-foreground capitalize">{{ role }}</span>
                            <span class="text-2xl font-bold">{{ count }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Demographics -->
            <Card>
                <CardHeader>
                    <CardTitle>User Demographics</CardTitle>
                    <CardDescription>Geographical and demographic distribution</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <h4 class="mb-3 font-semibold flex items-center gap-2">
                                <Globe class="h-4 w-4" />
                                Top Countries
                            </h4>
                            <div class="space-y-2">
                                <div v-if="stats.demographics.by_country.length > 0" v-for="item in stats.demographics.by_country.slice(0, 5)" :key="item.country" class="flex justify-between text-sm">
                                    <span>{{ item.country || 'Unknown' }}</span>
                                    <span class="font-medium">{{ item.count }}</span>
                                </div>
                                <div v-else class="text-sm text-muted-foreground py-4 text-center">
                                    No country data available
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-3 font-semibold">By Gender</h4>
                            <div class="space-y-2">
                                <div v-if="stats.demographics.by_gender.length > 0" v-for="item in stats.demographics.by_gender" :key="item.gender" class="flex justify-between text-sm">
                                    <span class="capitalize">{{ item.gender || 'Not specified' }}</span>
                                    <span class="font-medium">{{ item.count }}</span>
                                </div>
                                <div v-else class="text-sm text-muted-foreground py-4 text-center">
                                    No gender data available
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-3 font-semibold">By Age Group</h4>
                            <div class="space-y-2">
                                <div v-if="stats.demographics.by_age_group.length > 0" v-for="item in stats.demographics.by_age_group" :key="item.age_group" class="flex justify-between text-sm">
                                    <span>{{ item.age_group }}</span>
                                    <span class="font-medium">{{ item.count }}</span>
                                </div>
                                <div v-else class="text-sm text-muted-foreground py-4 text-center">
                                    No age data available
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Linked Accounts Statistics -->
            <Card>
                <CardHeader>
                    <CardTitle>Linked Accounts Statistics</CardTitle>
                    <CardDescription>Account linking success and failure tracking</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4 mb-6">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Linked</p>
                            <p class="text-2xl font-bold">{{ stats.linked_accounts_stats.total }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Active</p>
                            <p class="text-2xl font-bold text-green-600">{{ stats.linked_accounts_stats.active }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Failed</p>
                            <p class="text-2xl font-bold text-red-600">{{ stats.linked_accounts_stats.failed }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Success Rate</p>
                            <p class="text-2xl font-bold">{{ stats.linked_accounts_stats.success_rate }}%</p>
                        </div>
                    </div>
                    <div v-if="stats.linked_accounts_stats.failure_reasons.length > 0">
                        <h4 class="mb-3 font-semibold text-sm">Top Failure Reasons</h4>
                        <div class="space-y-2">
                            <div v-for="reason in stats.linked_accounts_stats.failure_reasons" :key="reason.reason" class="flex justify-between text-sm">
                                <span>{{ reason.reason || 'Unknown' }}</span>
                                <span class="font-medium">{{ reason.count }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Enhanced Transaction Breakdown -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Enhanced Transaction Breakdown -->
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction Breakdown</CardTitle>
                        <CardDescription>Detailed status and performance metrics</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-green-500" />
                                        <span class="font-medium">Executed</span>
                                    </div>
                                    <span class="font-bold">{{ stats.transaction_stats.executed.count }}</span>
                                </div>
                                <div class="ml-5 text-xs text-muted-foreground">
                                    Avg completion: {{ formatTime(stats.transaction_stats.executed.avg_completion_time) }}
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-yellow-500" />
                                        <span class="font-medium">Pending</span>
                                    </div>
                                    <span class="font-bold">{{ stats.transaction_stats.pending.count }}</span>
                                </div>
                                <div class="ml-5 text-xs text-muted-foreground">
                                    Avg pending time: {{ formatTime(stats.transaction_stats.pending.avg_pending_time) }}
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-red-500" />
                                        <span class="font-medium">Failed</span>
                                    </div>
                                    <span class="font-bold">{{ stats.transaction_stats.failed.count }}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Activity -->
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Transactions</CardTitle>
                        <CardDescription>Latest platform activity</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                v-for="transaction in recentTransactions"
                                :key="transaction.id"
                                class="flex items-center justify-between"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            'flex h-8 w-8 items-center justify-center rounded-full',
                                            transaction.status === 'executed'
                                                ? 'bg-green-100'
                                                : transaction.status === 'failed'
                                                  ? 'bg-red-100'
                                                  : 'bg-yellow-100',
                                        ]"
                                    >
                                        <CheckCircle
                                            v-if="transaction.status === 'executed'"
                                            class="h-4 w-4 text-green-600"
                                        />
                                        <XCircle
                                            v-else-if="transaction.status === 'failed'"
                                            class="h-4 w-4 text-red-600"
                                        />
                                        <TrendingUp v-else class="h-4 w-4 text-yellow-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ transaction.reference }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ transaction.destination_institution?.name || 'Unknown' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">
                                        {{ formatCurrency(transaction.amount) }}
                                    </p>
                                    <Badge :class="getStatusColor(transaction.status)" class="text-xs">
                                        {{ transaction.status }}
                                    </Badge>
                                </div>
                            </div>

                            <div
                                v-if="recentTransactions.length === 0"
                                class="py-4 text-center text-sm text-muted-foreground"
                            >
                                No transactions yet
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Flagged Transactions and Users -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-orange-500" />
                        Flagged Transactions & Users
                    </CardTitle>
                    <CardDescription>Items requiring attention with recommended actions</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Flagged Transactions</h4>
                            <div class="space-y-3">
                                <div v-for="item in stats.flagged_data.transactions.slice(0, 5)" :key="item.id" class="rounded-lg border p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-mono text-xs">{{ item.reference }}</span>
                                        <Badge variant="destructive" class="text-xs">{{ item.recommended_action }}</Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ item.user_name }}</p>
                                    <p class="text-xs mt-1">{{ item.reason }}</p>
                                </div>
                                <div v-if="stats.flagged_data.transactions.length === 0" class="text-center text-sm text-muted-foreground py-4">
                                    No flagged transactions
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Flagged Users</h4>
                            <div class="space-y-3">
                                <div v-for="item in stats.flagged_data.users.slice(0, 5)" :key="item.id" class="rounded-lg border p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-medium text-sm">{{ item.name }}</span>
                                        <Badge :variant="item.is_suspended ? 'destructive' : 'outline'" class="text-xs">{{ item.recommended_action }}</Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ item.email }}</p>
                                    <p class="text-xs mt-1">{{ item.reason }}</p>
                                </div>
                                <div v-if="stats.flagged_data.users.length === 0" class="text-center text-sm text-muted-foreground py-4">
                                    No flagged users
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Queries and Sanctioning Section -->
            <Card>
                <CardHeader>
                    <CardTitle>Queries & Sanctioning</CardTitle>
                    <CardDescription>User queries and compliance actions tracking</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="rounded-lg border p-4">
                            <h4 class="mb-2 font-semibold text-sm">User Queries</h4>
                            <p class="text-3xl font-bold mb-2">{{ stats.flagged_data.transactions.length + stats.flagged_data.users.length }}</p>
                            <p class="text-xs text-muted-foreground">Total queries raised</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <h4 class="mb-2 font-semibold text-sm">Suspended Accounts</h4>
                            <p class="text-3xl font-bold mb-2 text-red-600">{{ stats.flagged_data.users.filter(u => u.is_suspended).length }}</p>
                            <p class="text-xs text-muted-foreground">Accounts under sanction</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <h4 class="mb-2 font-semibold text-sm">High Risk Users</h4>
                            <p class="text-3xl font-bold mb-2 text-orange-600">{{ stats.flagged_data.users.filter(u => u.risk_tier === 'high').length }}</p>
                            <p class="text-xs text-muted-foreground">Requiring monitoring</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Modals -->
            <Dialog v-model:open="showTransactionFeeModal">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Transaction Fee Details</DialogTitle>
                        <DialogDescription>Breakdown of transaction facilitation fees (0.99%)</DialogDescription>
                    </DialogHeader>
                    <div v-if="revenueDetails" class="space-y-4">
                        <div class="rounded-lg bg-muted p-4">
                            <p class="text-sm text-muted-foreground">Total Fees Collected</p>
                            <p class="text-2xl font-bold">{{ formatCurrency(revenueDetails.total_fees) }}</p>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b">
                                    <tr>
                                        <th class="pb-2 text-left">Date</th>
                                        <th class="pb-2 text-right">Transactions</th>
                                        <th class="pb-2 text-right">Volume</th>
                                        <th class="pb-2 text-right">Fees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detail in revenueDetails.details" :key="detail.date" class="border-b">
                                        <td class="py-2">{{ detail.date }}</td>
                                        <td class="py-2 text-right">{{ detail.transaction_count }}</td>
                                        <td class="py-2 text-right">{{ formatCurrency(detail.total_volume) }}</td>
                                        <td class="py-2 text-right font-medium">{{ formatCurrency(detail.fees_collected) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showSubscriptionModal">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Subscription Revenue Details</DialogTitle>
                        <DialogDescription>Breakdown by subscription tier and user count</DialogDescription>
                    </DialogHeader>
                    <div v-if="revenueDetails" class="space-y-4">
                        <div class="rounded-lg bg-muted p-4">
                            <p class="text-sm text-muted-foreground">Total Subscription Revenue</p>
                            <p class="text-2xl font-bold">{{ formatCurrency(revenueDetails.total_revenue) }}</p>
                        </div>
                        <div class="grid gap-4">
                            <div v-for="detail in revenueDetails.details" :key="detail.code" class="rounded-lg border p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-semibold capitalize">{{ detail.tier }}</h4>
                                        <p class="text-sm text-muted-foreground">{{ detail.user_count }} users</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-bold">{{ formatCurrency(detail.revenue) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ formatCurrency(detail.avg_revenue_per_user) }}/user</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showAdsModal">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ads Revenue Details</DialogTitle>
                        <DialogDescription>Advertisement revenue breakdown</DialogDescription>
                    </DialogHeader>
                    <div class="py-8 text-center text-muted-foreground">
                        <p>Ads revenue feature coming soon</p>
                    </div>
                </DialogContent>
            </Dialog>

        </div>
    </AppLayout>
</template>
