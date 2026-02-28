<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem } from '@/types';
import { 
    PieChart, 
    TrendingUp, 
    TrendingDown, 
    Shield, 
    Globe, 
    BarChart3,
    AlertTriangle,
    Eye,
    RefreshCw,
    Unlink,
    Key,
    ChevronDown,
    Building2,
    CheckCircle,
    Wallet,
    DollarSign,
    Plus,
    ExternalLink,
    Sparkles,
    Target,
    Activity
} from 'lucide-vue-next';
import { ref } from 'vue';

interface LinkedInstitution {
    id: number;
    name: string;
    type: string;
    status: 'connected' | 'expired' | 'error';
    last_synced: string;
    account_count: number;
    institution: {
        name: string;
        logo_url: string | null;
    };
}

interface Holding {
    symbol: string;
    name: string;
    quantity: number;
    current_price: number;
    market_value: number;
    allocation_pct: number;
    sector: string;
    financial_market: string;
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
    linkedInstitutions: LinkedInstitution[];
    holdings: HoldingGroup[];
    analytics: Analytics;
    isReadOnly: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Wealth & Investments' },
];

const selectedPeriod = ref('YTD');
const showBreakdownDialog = ref(false);
const selectedInstitution = ref<LinkedInstitution | null>(null);
const refreshing = ref<number | null>(null);
const showTiersDialog = ref(false);
const showScenarioDialog = ref(false);

const currentTier = {
    name: 'Silver Tier',
    subtitle: 'Retail Investors Mode',
    price: 'Free'
};

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

const getRiskLabel = (score: number) => {
    if (score <= 3) return 'Low Risk';
    if (score <= 6) return 'Moderate Risk';
    return 'High Risk';
};

const getReturnColor = (value: number) => {
    return value >= 0 ? 'text-green-600' : 'text-red-600';
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'connected':
            return { class: 'bg-green-100 text-green-700', label: 'Connected' };
        case 'expired':
            return { class: 'bg-yellow-100 text-yellow-700', label: 'Expired' };
        case 'error':
            return { class: 'bg-red-100 text-red-700', label: 'Error' };
        default:
            return { class: 'bg-gray-100 text-gray-700', label: status };
    }
};

const refreshInstitution = async (id: number) => {
    refreshing.value = id;
    router.post(`/paneta/accounts/${id}/refresh`, {}, {
        onFinish: () => {
            refreshing.value = null;
        }
    });
};

const disconnectInstitution = (id: number) => {
    if (confirm('Are you sure you want to disconnect this institution? This action cannot be undone.')) {
        router.delete(`/paneta/accounts/${id}`);
    }
};

const reauthenticate = (id: number) => {
    router.get(`/paneta/accounts/${id}/reauth`);
};

const viewBreakdown = (institution: LinkedInstitution) => {
    selectedInstitution.value = institution;
    showBreakdownDialog.value = true;
};
</script>

<template>
    <Head title="Wealth & Investments - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Wealth & Investments</h1>
                    <p class="text-muted-foreground">
                        Manage your portfolio, access global markets, and integrate with financial institutions
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

            <!-- Horizontal Tabs -->
            <Tabs default-value="portfolio" class="space-y-6">
                <TabsList class="grid w-full grid-cols-3 lg:w-auto lg:inline-grid">
                    <TabsTrigger value="portfolio" class="gap-2">
                        <PieChart class="h-4 w-4" />
                        Portfolio Management
                    </TabsTrigger>
                    <TabsTrigger value="markets" class="gap-2">
                        <Globe class="h-4 w-4" />
                        Global Financial Markets
                    </TabsTrigger>
                    <TabsTrigger value="integration" class="gap-2">
                        <BarChart3 class="h-4 w-4" />
                        Market Integration
                    </TabsTrigger>
                </TabsList>

                <!-- Portfolio Management Tab -->
                <TabsContent value="portfolio" class="space-y-6">
                    <!-- Subscription Tier & Connect Asset Bar -->
                    <div class="flex items-center justify-between">
                        <Button 
                            variant="outline" 
                            class="gap-2 border-purple-200 bg-purple-50 hover:bg-purple-100"
                            @click="showTiersDialog = true"
                        >
                            <svg class="h-4 w-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            <div class="text-left">
                                <div class="font-semibold text-purple-900">{{ currentTier.name }}: {{ currentTier.subtitle }}</div>
                                <div class="text-xs text-purple-600">({{ currentTier.price }})</div>
                            </div>
                        </Button>
                        <Button class="gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700">
                            <Plus class="h-4 w-4" />
                            Connect New Asset
                        </Button>
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

                    <!-- Linked Accounts Section -->
                    <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Building2 class="h-5 w-5" />
                                Linked Accounts ({{ linkedInstitutions.length }})
                            </CardTitle>
                            <CardDescription>Connected financial institutions</CardDescription>
                        </div>
                        <Button variant="outline" as-child>
                            <a href="/paneta/accounts">
                                Manage Accounts
                            </a>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="linkedInstitutions.length === 0" class="text-center py-8">
                        <Building2 class="h-12 w-12 mx-auto text-muted-foreground mb-3" />
                        <p class="text-muted-foreground">No accounts linked yet</p>
                        <Button class="mt-4" as-child>
                            <a href="/paneta/accounts">Link Your First Account</a>
                        </Button>
                    </div>
                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="institution in linkedInstitutions"
                            :key="institution.id"
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div class="flex items-center gap-3">
                                <div class="rounded-full bg-primary/10 p-2">
                                    <Building2 class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ institution.institution.name }}</span>
                                        <Badge :class="getStatusBadge(institution.status).class" class="text-xs">
                                            {{ getStatusBadge(institution.status).label }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ institution.account_count }} account(s) • Last synced: {{ institution.last_synced }}
                                    </p>
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm">
                                        <ChevronDown class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="refreshInstitution(institution.id)">
                                        <RefreshCw class="mr-2 h-4 w-4" />
                                        Refresh
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="viewBreakdown(institution)">
                                        <Eye class="mr-2 h-4 w-4" />
                                        View Breakdown
                                    </DropdownMenuItem>
                                    <DropdownMenuItem 
                                        v-if="institution.status === 'expired'"
                                        @click="reauthenticate(institution.id)"
                                    >
                                        <Key class="mr-2 h-4 w-4" />
                                        Re-authenticate
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem 
                                        class="text-red-600"
                                        @click="disconnectInstitution(institution.id)"
                                    >
                                        <Unlink class="mr-2 h-4 w-4" />
                                        Disconnect
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </CardContent>
                    </Card>

                    <!-- Portfolio Summary - Key Metrics -->
                    <div class="grid gap-4 md:grid-cols-4">
                <!-- Total Net Worth -->
                <Card class="md:col-span-2">
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-1">
                            <Wallet class="h-4 w-4" />
                            Total Net Worth
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">
                            {{ formatCurrency(analytics.total_portfolio_value, analytics.base_currency) }}
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            Aggregated across {{ linkedInstitutions.length }} institution(s)
                        </p>
                    </CardContent>
                </Card>

                <!-- Risk Score -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-1">
                            <Shield class="h-4 w-4" />
                            Risk Score
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <span :class="['text-2xl font-bold', getRiskColor(analytics.risk_score)]">
                                {{ analytics.risk_score }}/10
                            </span>
                        </div>
                        <p :class="['text-sm mt-1', getRiskColor(analytics.risk_score)]">
                            {{ getRiskLabel(analytics.risk_score) }}
                        </p>
                    </CardContent>
                </Card>

                <!-- FX Exposure Summary -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription class="flex items-center gap-1">
                            <DollarSign class="h-4 w-4" />
                            FX Exposure
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ analytics.currency_exposure.length }} currencies
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            {{ analytics.currency_exposure[0]?.currency }} dominant ({{ analytics.currency_exposure[0]?.percentage.toFixed(0) }}%)
                        </p>
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
                                        <TableHead>Financial Market</TableHead>
                                        <TableHead>Region</TableHead>
                                        <TableHead class="text-right">Action</TableHead>
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
                                            <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">{{ holding.financial_market }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{{ holding.region }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button size="sm" variant="outline" class="gap-1">
                                                <ExternalLink class="h-3 w-3" />
                                                Trade
                                            </Button>
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

                    <!-- What Users CAN and CANNOT Do -->
                    <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-5 w-5" />
                        Platform Capabilities
                    </CardTitle>
                    <CardDescription>PANÉTA is a Read-Only Financial Data & Analytics Platform</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h4 class="font-medium text-green-600 mb-3 flex items-center gap-2">
                                <CheckCircle class="h-4 w-4" />
                                You CAN
                            </h4>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2">
                                    <RefreshCw class="h-4 w-4 text-muted-foreground" />
                                    Refresh account data
                                </li>
                                <li class="flex items-center gap-2">
                                    <Unlink class="h-4 w-4 text-muted-foreground" />
                                    Disconnect institutions
                                </li>
                                <li class="flex items-center gap-2">
                                    <Key class="h-4 w-4 text-muted-foreground" />
                                    Re-authenticate expired connections
                                </li>
                                <li class="flex items-center gap-2">
                                    <Eye class="h-4 w-4 text-muted-foreground" />
                                    View detailed breakdowns
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-medium text-red-600 mb-3 flex items-center gap-2">
                                <AlertTriangle class="h-4 w-4" />
                                You CANNOT
                            </h4>
                            <ul class="space-y-2 text-sm text-muted-foreground">
                                <li>Execute trades</li>
                                <li>Move funds between accounts</li>
                                <li>Change asset allocations</li>
                                <li>Rebalance portfolios</li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>

                    <!-- Breakdown Dialog -->
                    <Dialog v-model:open="showBreakdownDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Account Breakdown</DialogTitle>
                    <DialogDescription v-if="selectedInstitution">
                        {{ selectedInstitution.institution.name }} - Detailed View
                    </DialogDescription>
                </DialogHeader>
                <div v-if="selectedInstitution" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground">Status</p>
                            <Badge :class="getStatusBadge(selectedInstitution.status).class" class="mt-1">
                                {{ getStatusBadge(selectedInstitution.status).label }}
                            </Badge>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground">Last Synced</p>
                            <p class="font-medium mt-1">{{ selectedInstitution.last_synced }}</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground">Accounts</p>
                            <p class="font-medium mt-1">{{ selectedInstitution.account_count }} linked</p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-sm text-muted-foreground">Type</p>
                            <p class="font-medium mt-1 capitalize">{{ selectedInstitution.type }}</p>
                        </div>
                    </div>
                    <div class="rounded-lg bg-muted p-4">
                        <p class="text-sm text-muted-foreground mb-2">Data Access</p>
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="outline">Balances</Badge>
                            <Badge variant="outline">Transactions</Badge>
                            <Badge variant="outline">Holdings</Badge>
                            <Badge variant="outline" class="text-blue-600">Read-Only</Badge>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showBreakdownDialog = false">Close</Button>
                    <Button 
                        v-if="selectedInstitution" 
                        @click="refreshInstitution(selectedInstitution.id); showBreakdownDialog = false"
                    >
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh Now
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
                </TabsContent>

                <!-- Global Financial Markets Tab -->
                <TabsContent value="markets" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                Global Financial Markets
                            </CardTitle>
                            <CardDescription>
                                Access real-time market data and global investment opportunities
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <Globe class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-lg font-medium">Global Financial Markets</p>
                                <p class="text-muted-foreground mt-2">
                                    Market data and investment opportunities coming soon
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Market Integration Tab -->
                <TabsContent value="integration" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <BarChart3 class="h-5 w-5" />
                                Market Integration
                            </CardTitle>
                            <CardDescription>
                                Connect and integrate with financial markets and institutions
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <BarChart3 class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-lg font-medium">Market Integration</p>
                                <p class="text-muted-foreground mt-2">
                                    Integration features coming soon
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
