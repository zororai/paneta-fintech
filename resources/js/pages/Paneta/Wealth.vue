<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { BreadcrumbItem } from '@/types';
import { 
    PieChart, 
    TrendingUp, 
    TrendingDown, 
    Shield, 
    Globe, 
    BarChart3,
    AlertTriangle,
    Eye
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Holding {
    symbol: string;
    name: string;
    quantity: number;
    current_price: number;
    market_value: number;
    allocation_pct: number;
    sector: string;
    region: string;
}

interface HoldingGroup {
    asset_class: string;
    holdings: Holding[];
}

interface Analytics {
    total_portfolio_value: number;
    base_currency: string;
    risk_score: number;
    twr: number;
    irr: number;
    volatility: number;
    asset_allocation: { name: string; value: number; color: string }[];
    currency_exposure: { currency: string; percentage: number }[];
    sector_exposure: { sector: string; percentage: number }[];
    geographic_exposure: { region: string; percentage: number }[];
    performance: { period: string; return: number }[];
}

const props = defineProps<{
    brokerAccounts: any[];
    holdings: HoldingGroup[];
    analytics: Analytics;
    isReadOnly: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Wealth Management' },
];

const selectedPeriod = ref('YTD');

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

const formatPercent = (value: number) => {
    return `${value >= 0 ? '+' : ''}${value.toFixed(2)}%`;
};

const getRiskColor = (score: number) => {
    if (score <= 3) return 'text-green-600';
    if (score <= 6) return 'text-yellow-600';
    return 'text-red-600';
};

const getReturnColor = (value: number) => {
    return value >= 0 ? 'text-green-600' : 'text-red-600';
};
</script>

<template>
    <Head title="Wealth Management - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Wealth Management</h1>
                    <p class="text-muted-foreground">
                        Portfolio overview and analytics (Read-Only)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="text-blue-600 border-blue-600">
                        <Eye class="mr-1 h-3 w-3" />
                        Read-Only Mode
                    </Badge>
                    <Badge variant="outline" class="text-orange-600 border-orange-600">
                        Zero-Custody
                    </Badge>
                </div>
            </div>

            <!-- Warning Banner -->
            <div class="flex items-center gap-3 rounded-lg border border-yellow-500/50 bg-yellow-500/10 p-4">
                <AlertTriangle class="h-5 w-5 text-yellow-600" />
                <div>
                    <p class="font-medium text-yellow-600">Decision Support Only</p>
                    <p class="text-sm text-muted-foreground">
                        This module is read-only. PANÉTA does not execute trades, rebalance portfolios, or hold any assets.
                    </p>
                </div>
            </div>

            <!-- Portfolio Summary -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Total Portfolio Value</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(analytics.total_portfolio_value, analytics.base_currency) }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Risk Score</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <span :class="['text-2xl font-bold', getRiskColor(analytics.risk_score)]">
                                {{ analytics.risk_score }}/10
                            </span>
                            <Shield :class="['h-5 w-5', getRiskColor(analytics.risk_score)]" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>TWR (Time-Weighted Return)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <span :class="['text-2xl font-bold', getReturnColor(analytics.twr)]">
                                {{ formatPercent(analytics.twr) }}
                            </span>
                            <TrendingUp v-if="analytics.twr >= 0" class="h-5 w-5 text-green-600" />
                            <TrendingDown v-else class="h-5 w-5 text-red-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Volatility</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ analytics.volatility.toFixed(1) }}%
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Performance & Allocation -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Performance Metrics -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <BarChart3 class="h-5 w-5" />
                            Performance
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex gap-2 mb-4">
                            <Button
                                v-for="period in analytics.performance"
                                :key="period.period"
                                :variant="selectedPeriod === period.period ? 'default' : 'outline'"
                                size="sm"
                                @click="selectedPeriod = period.period"
                            >
                                {{ period.period }}
                            </Button>
                        </div>
                        <div class="space-y-3">
                            <div
                                v-for="period in analytics.performance"
                                :key="period.period"
                                class="flex items-center justify-between rounded-lg border p-3"
                            >
                                <span class="font-medium">{{ period.period }}</span>
                                <span :class="['font-bold', getReturnColor(period.return)]">
                                    {{ formatPercent(period.return) }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Asset Allocation -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <PieChart class="h-5 w-5" />
                            Asset Allocation
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="allocation in analytics.asset_allocation"
                                :key="allocation.name"
                                class="space-y-1"
                            >
                                <div class="flex justify-between text-sm">
                                    <span>{{ allocation.name }}</span>
                                    <span class="font-medium">{{ allocation.value.toFixed(1) }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full"
                                        :style="{ width: `${allocation.value}%`, backgroundColor: allocation.color }"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Holdings Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle>Holdings Breakdown</CardTitle>
                    <CardDescription>
                        Aggregated view from connected broker accounts
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <div v-for="group in holdings" :key="group.asset_class">
                            <h3 class="mb-3 font-semibold">{{ group.asset_class }}</h3>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Symbol</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead class="text-right">Quantity</TableHead>
                                        <TableHead class="text-right">Price</TableHead>
                                        <TableHead class="text-right">Market Value</TableHead>
                                        <TableHead class="text-right">Allocation</TableHead>
                                        <TableHead>Sector</TableHead>
                                        <TableHead>Region</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="holding in group.holdings" :key="holding.symbol">
                                        <TableCell class="font-medium">{{ holding.symbol }}</TableCell>
                                        <TableCell>{{ holding.name }}</TableCell>
                                        <TableCell class="text-right">{{ holding.quantity }}</TableCell>
                                        <TableCell class="text-right">{{ formatCurrency(holding.current_price) }}</TableCell>
                                        <TableCell class="text-right font-medium">{{ formatCurrency(holding.market_value) }}</TableCell>
                                        <TableCell class="text-right">{{ holding.allocation_pct.toFixed(1) }}%</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{{ holding.sector }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{{ holding.region }}</Badge>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Exposure Analysis -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Currency Exposure -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Currency Exposure</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="exposure in analytics.currency_exposure"
                                :key="exposure.currency"
                                class="flex items-center justify-between"
                            >
                                <span>{{ exposure.currency }}</span>
                                <span class="font-medium">{{ exposure.percentage.toFixed(1) }}%</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Sector Exposure -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Sector Exposure</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="exposure in analytics.sector_exposure"
                                :key="exposure.sector"
                                class="flex items-center justify-between"
                            >
                                <span>{{ exposure.sector }}</span>
                                <span class="font-medium">{{ exposure.percentage.toFixed(1) }}%</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Geographic Exposure -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Globe class="h-4 w-4" />
                            Geographic Exposure
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="exposure in analytics.geographic_exposure"
                                :key="exposure.region"
                                class="flex items-center justify-between"
                            >
                                <span>{{ exposure.region }}</span>
                                <span class="font-medium">{{ exposure.percentage.toFixed(1) }}%</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
