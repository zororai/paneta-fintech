<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, AdminStats, TransactionIntent } from '@/types';
import { Users, ArrowUpRight, CheckCircle, XCircle, DollarSign, TrendingUp } from 'lucide-vue-next';

const props = defineProps<{
    stats: AdminStats;
    recentTransactions: TransactionIntent[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Admin', href: '/paneta/admin' },
    { title: 'Dashboard' },
];

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
    <Head title="Admin Dashboard - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Regulator Dashboard</h1>
                    <p class="text-muted-foreground">
                        Read-only view of platform activity and compliance
                    </p>
                </div>
                <Badge variant="outline" class="text-sm">
                    Read-Only Access
                </Badge>
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

            <!-- Quick Stats -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Transaction Breakdown -->
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction Breakdown</CardTitle>
                        <CardDescription>Status distribution</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="h-3 w-3 rounded-full bg-green-500" />
                                    <span>Executed</span>
                                </div>
                                <span class="font-medium">{{ stats.executed_transactions }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="h-3 w-3 rounded-full bg-yellow-500" />
                                    <span>Pending</span>
                                </div>
                                <span class="font-medium">{{ stats.pending_transactions }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="h-3 w-3 rounded-full bg-red-500" />
                                    <span>Failed</span>
                                </div>
                                <span class="font-medium">{{ stats.failed_transactions }}</span>
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
                                            {{ formatDate(transaction.created_at) }}
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

            <!-- Admin Notice - PANÉTA Gold Accent (Premium/Regulatory) -->
            <Card class="border-accent/30 bg-accent/5 dark:border-accent/40 dark:bg-accent/10">
                <CardContent class="pt-6">
                    <div class="flex items-start gap-4">
                        <div class="rounded-full bg-accent/20 p-2 dark:bg-accent/30">
                            <svg
                                class="h-5 w-5 text-accent"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-accent-foreground dark:text-foreground">
                                Regulator Access - Read Only
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                This dashboard provides read-only access to platform data for
                                regulatory oversight. No modifications can be made from this
                                interface. All data shown is from the immutable audit log.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
