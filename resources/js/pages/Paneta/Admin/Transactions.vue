<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, TransactionIntent, PaginatedResponse } from '@/types';
import { ref } from 'vue';

interface TransactionWithUser extends TransactionIntent {
    user?: {
        id: number;
        name: string;
        email: string;
    };
}

const props = defineProps<{
    transactions: PaginatedResponse<TransactionWithUser>;
    filters: {
        status?: string;
        user_id?: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Admin', href: '/paneta/admin' },
    { title: 'Transactions' },
];

const statusFilter = ref(props.filters.status || '');

const statuses = ['', 'pending', 'confirmed', 'executed', 'failed'];

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
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
        case 'confirmed':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const applyFilter = () => {
    router.get('/paneta/admin/transactions', {
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
    });
};

const clearFilters = () => {
    statusFilter.value = '';
    router.get('/paneta/admin/transactions');
};
</script>

<template>
    <Head title="All Transactions - Admin - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">All Transactions</h1>
                    <p class="text-muted-foreground">
                        Complete transaction history across all users
                    </p>
                </div>
                <Badge variant="outline">Read-Only</Badge>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm font-medium">Status:</label>
                            <select
                                v-model="statusFilter"
                                class="rounded-md border px-3 py-1.5 text-sm"
                                @change="applyFilter"
                            >
                                <option value="">All</option>
                                <option v-for="status in statuses.slice(1)" :key="status" :value="status">
                                    {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                                </option>
                            </select>
                        </div>
                        <Button
                            v-if="statusFilter"
                            variant="outline"
                            size="sm"
                            @click="clearFilters"
                        >
                            Clear Filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Transactions Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction Records</CardTitle>
                    <CardDescription>
                        Showing {{ transactions.data.length }} of {{ transactions.total }} transactions
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left text-sm text-muted-foreground">
                                    <th class="pb-3 font-medium">Reference</th>
                                    <th class="pb-3 font-medium">User</th>
                                    <th class="pb-3 font-medium">From</th>
                                    <th class="pb-3 font-medium">To</th>
                                    <th class="pb-3 font-medium">Amount</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="transaction in transactions.data"
                                    :key="transaction.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-4">
                                        <span class="font-mono text-sm">{{
                                            transaction.reference
                                        }}</span>
                                    </td>
                                    <td class="py-4">
                                        <div>
                                            <p class="font-medium">{{ transaction.user?.name }}</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ transaction.user?.email }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <div>
                                            <p class="text-sm">
                                                {{ transaction.issuer_account?.institution?.name }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ transaction.issuer_account?.account_identifier }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <div>
                                            <p class="text-sm">
                                                {{ transaction.destination_institution?.name || 'Unknown' }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ transaction.acquirer_identifier }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <span class="font-medium">
                                            {{ formatCurrency(transaction.amount, transaction.currency) }}
                                        </span>
                                    </td>
                                    <td class="py-4">
                                        <Badge :class="getStatusColor(transaction.status)">
                                            {{ transaction.status }}
                                        </Badge>
                                    </td>
                                    <td class="py-4 text-sm text-muted-foreground">
                                        {{ formatDate(transaction.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div
                            v-if="transactions.data.length === 0"
                            class="py-12 text-center text-muted-foreground"
                        >
                            No transactions found
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
