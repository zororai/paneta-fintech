<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem, TransactionIntent } from '@/types';
import { 
    ArrowUpRight, CheckCircle, XCircle, Building2, DollarSign, 
    TrendingUp, Users, Globe, Filter
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    transactions: {
        data: TransactionIntent[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters?: {
        status?: string;
        institution_id?: string;
    };
    transaction_parties: any;
    volume_metrics: any;
    currency_exchange: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Regulator', href: '/paneta/regulator' },
    { title: 'All Transactions' },
];

const selectedStatus = ref(props.filters?.status || '');
const selectedInstitution = ref(props.filters?.institution_id || '');

const applyFilters = () => {
    router.get('/paneta/regulator/transactions', {
        status: selectedStatus.value || undefined,
        institution_id: selectedInstitution.value || undefined,
    }, { preserveState: true });
};

const clearFilters = () => {
    selectedStatus.value = '';
    selectedInstitution.value = '';
    router.get('/paneta/regulator/transactions');
};

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
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

const goToPage = (page: number) => {
    router.get('/paneta/regulator/transactions', {
        page,
        status: selectedStatus.value || undefined,
        institution_id: selectedInstitution.value || undefined,
    }, { preserveState: true });
};
</script>

<template>
    <Head title="All Transactions - Regulator Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">All Transactions</h1>
                    <p class="text-muted-foreground">
                        Complete transaction history with parties, volume metrics, and currency exchange data
                    </p>
                </div>
                <Badge variant="outline" class="text-sm">
                    {{ transactions.total }} Total Transactions
                </Badge>
            </div>

            <!-- Transaction Parties Overview -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Transaction Parties Overview
                    </CardTitle>
                    <CardDescription>Users and institutions involved in platform transactions</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 mb-6">
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground mb-1">Total Users</p>
                            <p class="text-3xl font-bold">{{ transaction_parties.total_users }}</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground mb-1">Total Institutions</p>
                            <p class="text-3xl font-bold">{{ transaction_parties.total_institutions }}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-3 font-semibold text-sm">Active Institutions</h4>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div v-for="inst in transaction_parties.institutions_summary.slice(0, 6)" :key="inst.name" class="flex justify-between text-sm border rounded-lg p-3">
                                <div>
                                    <p class="font-medium">{{ inst.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ inst.type }} - {{ inst.country }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">{{ inst.linked_accounts_count }}</p>
                                    <p class="text-xs text-muted-foreground">accounts</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Transaction Volume Metrics -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <DollarSign class="h-5 w-5" />
                        Transaction Volume Metrics
                    </CardTitle>
                    <CardDescription>Platform transaction volumes and distribution</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="mb-6">
                        <p class="text-sm text-muted-foreground mb-1">Total Volume</p>
                        <p class="text-4xl font-bold">{{ formatCurrency(volume_metrics.total_volume) }}</p>
                    </div>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Volume by Currency</h4>
                            <div class="space-y-2">
                                <div v-for="curr in volume_metrics.by_currency" :key="curr.currency" class="flex justify-between text-sm border-b pb-2">
                                    <span class="font-medium">{{ curr.currency }}</span>
                                    <div class="text-right">
                                        <p class="font-medium">{{ formatCurrency(curr.total_volume, curr.currency) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ curr.transaction_count }} transactions</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-3 font-semibold text-sm">Volume by Institution (Top 5)</h4>
                            <div class="space-y-2">
                                <div v-for="inst in volume_metrics.by_institution.slice(0, 5)" :key="inst.name" class="flex justify-between text-sm border-b pb-2">
                                    <span class="font-medium">{{ inst.name }}</span>
                                    <div class="text-right">
                                        <p class="font-medium">{{ formatCurrency(inst.total_volume) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ inst.transaction_count }} txns</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Currency Exchange Activity -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="h-5 w-5" />
                        Currency Exchange Activity
                    </CardTitle>
                    <CardDescription>Cross-currency transaction metrics</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div v-for="exchange in currency_exchange" :key="`${exchange.from_currency}-${exchange.to_currency}`" class="rounded-lg border p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium text-sm">{{ exchange.from_currency }} → {{ exchange.to_currency }}</p>
                                    <p class="text-xs text-muted-foreground">{{ exchange.exchange_count }} exchanges</p>
                                </div>
                                <p class="font-medium">{{ formatCurrency(exchange.total_amount) }}</p>
                            </div>
                        </div>
                        <div v-if="currency_exchange.length === 0" class="col-span-2 text-center text-sm text-muted-foreground py-8">
                            No currency exchange activity
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Filter class="h-5 w-5" />
                        Filter Transactions
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label class="text-sm font-medium mb-2 block">Status</label>
                            <select v-model="selectedStatus" class="w-full rounded-md border px-3 py-2">
                                <option value="">All Statuses</option>
                                <option value="executed">Executed</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <Button @click="applyFilters" variant="default">
                                Apply Filters
                            </Button>
                            <Button @click="clearFilters" variant="outline">
                                Clear
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Transactions Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction History</CardTitle>
                    <CardDescription>Complete list of all platform transactions</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-2 font-medium">Reference</th>
                                    <th class="text-left py-3 px-2 font-medium">User</th>
                                    <th class="text-left py-3 px-2 font-medium">Issuer Institution</th>
                                    <th class="text-left py-3 px-2 font-medium">Destination Institution</th>
                                    <th class="text-left py-3 px-2 font-medium">Amount</th>
                                    <th class="text-left py-3 px-2 font-medium">Status</th>
                                    <th class="text-left py-3 px-2 font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="txn in transactions.data" :key="txn.id" class="border-b hover:bg-muted/50">
                                    <td class="py-4 px-2">
                                        <span class="font-mono text-xs">{{ txn.reference }}</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <div v-if="txn.user">
                                            <p class="text-sm font-medium">{{ txn.user.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ txn.user.email }}</p>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">N/A</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <div v-if="txn.issuer_account?.institution">
                                            <p class="text-sm">{{ txn.issuer_account.institution.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ txn.issuer_account.institution.country }}</p>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">N/A</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <div v-if="txn.destination_institution">
                                            <p class="text-sm">{{ txn.destination_institution.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ txn.destination_institution.country }}</p>
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">{{ txn.acquirer_identifier }}</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <span class="font-medium">{{ formatCurrency(txn.amount, txn.currency) }}</span>
                                    </td>
                                    <td class="py-4 px-2">
                                        <Badge :class="getStatusColor(txn.status)">
                                            {{ txn.status }}
                                        </Badge>
                                    </td>
                                    <td class="py-4 px-2">
                                        <span class="text-sm">{{ formatDate(txn.created_at) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6">
                        <p class="text-sm text-muted-foreground">
                            Showing {{ ((transactions.current_page - 1) * transactions.per_page) + 1 }} 
                            to {{ Math.min(transactions.current_page * transactions.per_page, transactions.total) }} 
                            of {{ transactions.total }} transactions
                        </p>
                        <div class="flex gap-2">
                            <Button 
                                @click="goToPage(transactions.current_page - 1)" 
                                :disabled="transactions.current_page === 1"
                                variant="outline"
                                size="sm"
                            >
                                Previous
                            </Button>
                            <Button 
                                @click="goToPage(transactions.current_page + 1)" 
                                :disabled="transactions.current_page === transactions.last_page"
                                variant="outline"
                                size="sm"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
