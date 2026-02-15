<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, TransactionIntent, PaginatedResponse } from '@/types';
import { ArrowUpRight, Eye, Plus } from 'lucide-vue-next';

const props = defineProps<{
    transactions: PaginatedResponse<TransactionIntent>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Transactions' },
];

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
</script>

<template>
    <Head title="Transactions - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Transactions</h1>
                    <p class="text-muted-foreground">
                        View your transaction history and payment instructions
                    </p>
                </div>
                <Button as="a" href="/paneta/transactions/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Transaction
                </Button>
            </div>

            <!-- Transactions Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction History</CardTitle>
                    <CardDescription>
                        All your payment instructions and their status
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left text-sm text-muted-foreground">
                                    <th class="pb-3 font-medium">Reference</th>
                                    <th class="pb-3 font-medium">From</th>
                                    <th class="pb-3 font-medium">To</th>
                                    <th class="pb-3 font-medium">Amount</th>
                                    <th class="pb-3 font-medium">Status</th>
                                    <th class="pb-3 font-medium">Date</th>
                                    <th class="pb-3 font-medium"></th>
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
                                            <p class="font-medium">
                                                {{
                                                    transaction.issuer_account?.institution?.name
                                                }}
                                            </p>
                                            <p class="text-sm text-muted-foreground">
                                                {{
                                                    transaction.issuer_account?.account_identifier
                                                }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <span class="text-sm">{{
                                            transaction.acquirer_identifier
                                        }}</span>
                                    </td>
                                    <td class="py-4">
                                        <span class="font-medium">
                                            {{
                                                formatCurrency(
                                                    transaction.amount,
                                                    transaction.currency
                                                )
                                            }}
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
                                    <td class="py-4">
                                        <Link
                                            :href="`/paneta/transactions/${transaction.id}`"
                                            class="inline-flex items-center text-sm text-primary hover:underline"
                                        >
                                            <Eye class="mr-1 h-4 w-4" />
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Empty State -->
                        <div
                            v-if="transactions.data.length === 0"
                            class="py-12 text-center"
                        >
                            <ArrowUpRight class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 class="text-lg font-semibold">No transactions yet</h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Send your first payment to see it here
                            </p>
                            <Button as="a" href="/paneta/transactions/create">
                                <Plus class="mr-2 h-4 w-4" />
                                Send Money
                            </Button>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="transactions.last_page > 1"
                        class="mt-6 flex items-center justify-between"
                    >
                        <p class="text-sm text-muted-foreground">
                            Showing {{ transactions.data.length }} of {{ transactions.total }}
                            transactions
                        </p>
                        <div class="flex gap-2">
                            <Button
                                v-for="page in transactions.last_page"
                                :key="page"
                                :variant="page === transactions.current_page ? 'default' : 'outline'"
                                size="sm"
                                as="a"
                                :href="`/paneta/transactions?page=${page}`"
                            >
                                {{ page }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
