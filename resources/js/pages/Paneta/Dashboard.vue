<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardHeader from '@/components/DashboardHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, DashboardData, User } from '@/types';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { Wallet, ArrowUpRight, Clock, TrendingUp, QrCode, Plus, Landmark, Send, ArrowDownLeft, Smartphone, ArrowRightLeft, PieChart, ShoppingCart, Receipt, Users, FileText, TrendingUpIcon } from 'lucide-vue-next';

const props = defineProps<{
    dashboardData: DashboardData;
    user: User;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Dashboard' },
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
    <Head title="PANÉTA Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <DashboardHeader />
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Welcome back, {{ user.name }}</h1>
                    <p class="text-muted-foreground">
                        Your zero-custody orchestration dashboard
                    </p>
                </div>
                <Badge :class="user.kyc_status === 'verified' ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground'">
                    KYC: {{ user.kyc_status }}
                </Badge>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Balance</CardTitle>
                        <Wallet class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(dashboardData.total_balance) }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Aggregated from {{ dashboardData.accounts.length }} accounts
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Linked Accounts</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ dashboardData.accounts.length }}</div>
                        <p class="text-xs text-muted-foreground">Active external accounts</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Recent Transactions</CardTitle>
                        <ArrowUpRight class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ dashboardData.recent_transactions.length }}
                        </div>
                        <p class="text-xs text-muted-foreground">In the last 30 days</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Last Refresh</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-sm font-medium">
                            {{ formatDate(dashboardData.last_refresh) }}
                        </div>
                        <p class="text-xs text-muted-foreground">Data is simulated</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions - Personal Account -->
            <Card v-if="user.account_type === 'personal'" class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950 dark:to-indigo-950">
                <CardHeader>
                    <CardTitle class="text-lg">Quick Actions</CardTitle>
                    <CardDescription>Access your most used features</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                        <!-- Accounts -->
                        <Button variant="outline" @click="router.visit('/paneta/accounts')" class="h-24 flex flex-col gap-2 bg-white hover:bg-blue-50 border-blue-200">
                            <Landmark class="h-6 w-6 text-blue-600" />
                            <span class="text-xs font-medium">Accounts</span>
                        </Button>
                        
                        <!-- Pay -->
                        <Button variant="outline" @click="router.visit('/paneta/send-money')" class="h-24 flex flex-col gap-2 bg-white hover:bg-green-50 border-green-200">
                            <QrCode class="h-6 w-6 text-green-600" />
                            <span class="text-xs font-medium">Pay</span>
                        </Button>
                        
                        <!-- Send Money -->
                        <Button variant="outline" @click="router.visit('/paneta/send-money')" class="h-24 flex flex-col gap-2 bg-white hover:bg-purple-50 border-purple-200">
                            <Send class="h-6 w-6 text-purple-600" />
                            <span class="text-xs font-medium">Send Money</span>
                        </Button>
                        
                        <!-- Receive Money -->
                        <Button variant="outline" @click="router.visit('/paneta/payment-requests')" class="h-24 flex flex-col gap-2 bg-white hover:bg-teal-50 border-teal-200">
                            <ArrowDownLeft class="h-6 w-6 text-teal-600" />
                            <span class="text-xs font-medium">Receive Money</span>
                        </Button>
                        
                        <!-- Bills & Airtime -->
                        <Button variant="outline" @click="router.visit('/paneta/bills')" class="h-24 flex flex-col gap-2 bg-white hover:bg-orange-50 border-orange-200">
                            <Receipt class="h-6 w-6 text-orange-600" />
                            <span class="text-xs font-medium">Bills & Airtime</span>
                        </Button>
                        
                        <!-- Currency Exchange -->
                        <Button variant="outline" @click="router.visit('/paneta/currency-exchange')" class="h-24 flex flex-col gap-2 bg-white hover:bg-yellow-50 border-yellow-200">
                            <ArrowRightLeft class="h-6 w-6 text-yellow-600" />
                            <span class="text-xs font-medium">Currency Exchange</span>
                        </Button>
                        
                        <!-- Wealth & Investments -->
                        <Button variant="outline" @click="router.visit('/paneta/wealth')" class="h-24 flex flex-col gap-2 bg-white hover:bg-indigo-50 border-indigo-200">
                            <PieChart class="h-6 w-6 text-indigo-600" />
                            <span class="text-xs font-medium">Wealth & Investments</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Actions - Business Account -->
            <Card v-else-if="user.account_type === 'business'" class="bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-950 dark:to-pink-950">
                <CardHeader>
                    <CardTitle class="text-lg">Quick Actions</CardTitle>
                    <CardDescription>Access your business features</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-10 gap-3">
                        <!-- Accounts -->
                        <Button variant="outline" @click="router.visit('/paneta/accounts')" class="h-24 flex flex-col gap-2 bg-white hover:bg-blue-50 border-blue-200">
                            <Landmark class="h-6 w-6 text-blue-600" />
                            <span class="text-xs font-medium">Accounts</span>
                        </Button>
                        
                        <!-- Pay -->
                        <Button variant="outline" @click="router.visit('/paneta/send-money')" class="h-24 flex flex-col gap-2 bg-white hover:bg-green-50 border-green-200">
                            <QrCode class="h-6 w-6 text-green-600" />
                            <span class="text-xs font-medium">Pay</span>
                        </Button>
                        
                        <!-- Send Money -->
                        <Button variant="outline" @click="router.visit('/paneta/send-money')" class="h-24 flex flex-col gap-2 bg-white hover:bg-purple-50 border-purple-200">
                            <Send class="h-6 w-6 text-purple-600" />
                            <span class="text-xs font-medium">Send Money</span>
                        </Button>
                        
                        <!-- Merchant SoftPOS -->
                        <Button variant="outline" @click="router.visit('/paneta/merchant')" class="h-24 flex flex-col gap-2 bg-white hover:bg-pink-50 border-pink-200">
                            <Smartphone class="h-6 w-6 text-pink-600" />
                            <span class="text-xs font-medium">Merchant SoftPOS</span>
                        </Button>
                        
                        <!-- Request Payment -->
                        <Button variant="outline" @click="router.visit('/paneta/payment-requests')" class="h-24 flex flex-col gap-2 bg-white hover:bg-teal-50 border-teal-200">
                            <ArrowDownLeft class="h-6 w-6 text-teal-600" />
                            <span class="text-xs font-medium">Request Payment</span>
                        </Button>
                        
                        <!-- Batch Payment -->
                        <Button variant="outline" @click="router.visit('/paneta/batch-payments')" class="h-24 flex flex-col gap-2 bg-white hover:bg-cyan-50 border-cyan-200">
                            <Users class="h-6 w-6 text-cyan-600" />
                            <span class="text-xs font-medium">Batch Payment</span>
                        </Button>
                        
                        <!-- Bills & Airtime -->
                        <Button variant="outline" @click="router.visit('/paneta/bills')" class="h-24 flex flex-col gap-2 bg-white hover:bg-orange-50 border-orange-200">
                            <Receipt class="h-6 w-6 text-orange-600" />
                            <span class="text-xs font-medium">Bills & Airtime</span>
                        </Button>
                        
                        <!-- Currency Exchange -->
                        <Button variant="outline" @click="router.visit('/paneta/currency-exchange')" class="h-24 flex flex-col gap-2 bg-white hover:bg-yellow-50 border-yellow-200">
                            <ArrowRightLeft class="h-6 w-6 text-yellow-600" />
                            <span class="text-xs font-medium">Currency Exchange</span>
                        </Button>
                        
                        <!-- FX Dealership -->
                        <Button variant="outline" @click="router.visit('/paneta/currency-exchange')" class="h-24 flex flex-col gap-2 bg-white hover:bg-emerald-50 border-emerald-200">
                            <TrendingUpIcon class="h-6 w-6 text-emerald-600" />
                            <span class="text-xs font-medium">FX Dealership</span>
                        </Button>
                        
                        <!-- Wealth & Investments -->
                        <Button variant="outline" @click="router.visit('/paneta/wealth')" class="h-24 flex flex-col gap-2 bg-white hover:bg-indigo-50 border-indigo-200">
                            <PieChart class="h-6 w-6 text-indigo-600" />
                            <span class="text-xs font-medium">Wealth & Investments</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Accounts & Transactions -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Linked Accounts -->
                <Card>
                    <CardHeader>
                        <CardTitle>Linked Accounts</CardTitle>
                        <CardDescription>Your connected external accounts</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                v-for="account in dashboardData.accounts"
                                :key="account.id"
                                class="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10"
                                    >
                                        <Wallet class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p class="font-medium">
                                            {{ account.institution?.name }}
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ account.account_identifier }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">
                                        {{ formatCurrency(account.mock_balance, account.currency) }}
                                    </p>
                                    <Badge variant="outline" class="text-xs">
                                        {{ account.currency }}
                                    </Badge>
                                </div>
                            </div>
                            <div
                                v-if="dashboardData.accounts.length === 0"
                                class="py-8 text-center text-muted-foreground"
                            >
                                No accounts linked yet
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Transactions -->
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Transactions</CardTitle>
                        <CardDescription>Your latest payment instructions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                v-for="transaction in dashboardData.recent_transactions"
                                :key="transaction.id"
                                class="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div>
                                    <p class="font-medium">{{ transaction.reference }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        To: {{ transaction.acquirer_identifier }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">
                                        {{ formatCurrency(transaction.amount, transaction.currency) }}
                                    </p>
                                    <Badge :class="getStatusColor(transaction.status)">
                                        {{ transaction.status }}
                                    </Badge>
                                </div>
                            </div>
                            <div
                                v-if="dashboardData.recent_transactions.length === 0"
                                class="py-8 text-center text-muted-foreground"
                            >
                                No transactions yet
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Zero-Custody Notice - PANÉTA Branded -->
            <Card class="border-primary/20 bg-primary/5 dark:border-primary/30 dark:bg-primary/10">
                <CardContent class="pt-6">
                    <div class="flex items-start gap-4">
                        <div class="rounded-full bg-primary/10 p-2 dark:bg-primary/20">
                            <svg
                                class="h-5 w-5 text-primary"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-primary dark:text-primary-foreground">
                                Zero-Custody Architecture
                            </h3>
                            <p class="text-sm text-primary/80 dark:text-primary-foreground/80">
                                PANÉTA does not hold your funds. All balances shown are aggregated
                                from your linked external accounts. We only orchestrate payment
                                instructions - your money stays with your institutions.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
