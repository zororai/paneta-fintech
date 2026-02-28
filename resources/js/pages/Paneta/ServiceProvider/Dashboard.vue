<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import {
    TrendingUp, DollarSign, Activity, CheckCircle, Clock, Users,
    BarChart3, AlertCircle, ArrowUpRight, RefreshCw
} from 'lucide-vue-next';

const props = defineProps<{
    provider: any;
    stats: {
        total_offers: number;
        active_offers: number;
        total_trades: number;
        total_volume: number;
        pending_requests: number;
        revenue_this_month: number;
    };
    recentOffers: any[];
    recentTrades: any[];
    performanceMetrics: {
        success_rate: number;
        avg_execution_time: number;
        customer_satisfaction: number;
        volume_trend: any[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Service Provider', href: '/paneta/service-provider' },
    { label: 'Dashboard', href: '/paneta/service-provider' },
];

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const formatTime = (seconds: number) => {
    if (seconds < 60) return `${Math.round(seconds)}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)}m`;
    return `${Math.round(seconds / 3600)}h`;
};

const getStatusBadge = (status: string) => {
    const badges: Record<string, { class: string; label: string }> = {
        active: { class: 'bg-green-100 text-green-700', label: 'Active' },
        pending: { class: 'bg-yellow-100 text-yellow-700', label: 'Pending' },
        executed: { class: 'bg-blue-100 text-blue-700', label: 'Executed' },
        expired: { class: 'bg-gray-100 text-gray-700', label: 'Expired' },
        cancelled: { class: 'bg-red-100 text-red-700', label: 'Cancelled' },
    };
    return badges[status] || { class: 'bg-gray-100 text-gray-700', label: status };
};
</script>

<template>
    <Head title="Service Provider Dashboard - PANÉTA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">{{ provider.name }}</h1>
                    <p class="text-muted-foreground mt-1">FX Provider Dashboard</p>
                </div>
                <div class="flex items-center gap-3">
                    <Badge class="bg-green-100 text-green-700">Verified</Badge>
                    <Badge class="bg-blue-100 text-blue-700">Active</Badge>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Total Offers -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Offers</CardTitle>
                        <Activity class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_offers }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.active_offers }} active
                        </p>
                    </CardContent>
                </Card>

                <!-- Total Trades -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Trades</CardTitle>
                        <CheckCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_trades }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.pending_requests }} pending
                        </p>
                    </CardContent>
                </Card>

                <!-- Total Volume -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Volume</CardTitle>
                        <DollarSign class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(stats.total_volume) }}</div>
                        <p class="text-xs text-muted-foreground">
                            All-time trading volume
                        </p>
                    </CardContent>
                </Card>

                <!-- Revenue This Month -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Revenue (This Month)</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatCurrency(stats.revenue_this_month) }}</div>
                        <p class="text-xs text-green-600 flex items-center gap-1">
                            <ArrowUpRight class="h-3 w-3" />
                            Current month earnings
                        </p>
                    </CardContent>
                </Card>

                <!-- Success Rate -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Success Rate</CardTitle>
                        <BarChart3 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ performanceMetrics.success_rate }}%</div>
                        <p class="text-xs text-muted-foreground">
                            Trade execution success
                        </p>
                    </CardContent>
                </Card>

                <!-- Avg Execution Time -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Avg Execution Time</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatTime(performanceMetrics.avg_execution_time) }}</div>
                        <p class="text-xs text-muted-foreground">
                            Average processing time
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Activity -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Recent Offers -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Activity class="h-5 w-5" />
                            Recent Offers
                        </CardTitle>
                        <CardDescription>Your latest currency exchange offers</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentOffers.length === 0" class="text-center py-8 text-muted-foreground">
                            No offers yet
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="offer in recentOffers"
                                :key="offer.id"
                                class="flex items-center justify-between p-3 border rounded-lg"
                            >
                                <div>
                                    <p class="font-medium">{{ offer.currency_pair }}</p>
                                    <p class="text-sm text-muted-foreground">Rate: {{ offer.rate }}</p>
                                </div>
                                <Badge :class="getStatusBadge(offer.status).class">
                                    {{ getStatusBadge(offer.status).label }}
                                </Badge>
                            </div>
                        </div>
                        <Button variant="outline" class="w-full mt-4" as-child>
                            <a href="/paneta/service-provider/offers">View All Offers</a>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Recent Trades -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5" />
                            Recent Trades
                        </CardTitle>
                        <CardDescription>Latest executed transactions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentTrades.length === 0" class="text-center py-8 text-muted-foreground">
                            No trades yet
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="trade in recentTrades"
                                :key="trade.id"
                                class="flex items-center justify-between p-3 border rounded-lg"
                            >
                                <div>
                                    <p class="font-medium">{{ formatCurrency(trade.amount) }}</p>
                                    <p class="text-sm text-muted-foreground">{{ trade.currency }}</p>
                                </div>
                                <Badge :class="getStatusBadge(trade.status).class">
                                    {{ getStatusBadge(trade.status).label }}
                                </Badge>
                            </div>
                        </div>
                        <Button variant="outline" class="w-full mt-4" as-child>
                            <a href="/paneta/service-provider/trades">View All Trades</a>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card>
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription>Manage your FX provider services</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <Button class="h-24 flex flex-col gap-2" as-child>
                            <a href="/paneta/service-provider/offers">
                                <Activity class="h-6 w-6" />
                                <span>Create Offer</span>
                            </a>
                        </Button>
                        <Button variant="outline" class="h-24 flex flex-col gap-2" as-child>
                            <a href="/paneta/service-provider/trades">
                                <Users class="h-6 w-6" />
                                <span>View Requests</span>
                            </a>
                        </Button>
                        <Button variant="outline" class="h-24 flex flex-col gap-2" as-child>
                            <a href="/paneta/service-provider/reports">
                                <BarChart3 class="h-6 w-6" />
                                <span>Reports</span>
                            </a>
                        </Button>
                        <Button variant="outline" class="h-24 flex flex-col gap-2">
                            <RefreshCw class="h-6 w-6" />
                            <span>Sync Data</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Provider Info -->
            <Card class="bg-gradient-to-br from-blue-50 to-purple-50 border-2 border-blue-200">
                <CardHeader>
                    <CardTitle class="text-blue-700">Provider Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Provider Code</p>
                            <p class="font-semibold">{{ provider.code }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Country</p>
                            <p class="font-semibold">{{ provider.country }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Risk Score</p>
                            <p class="font-semibold">{{ provider.risk_score }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Default Spread</p>
                            <p class="font-semibold">{{ provider.default_spread_percentage }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Customer Rating</p>
                            <p class="font-semibold">{{ performanceMetrics.customer_satisfaction }}/5 ⭐</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Status</p>
                            <Badge class="bg-green-100 text-green-700">{{ provider.is_active ? 'Active' : 'Inactive' }}</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
