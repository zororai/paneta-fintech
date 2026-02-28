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
import { ref, computed } from 'vue';

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
const activeTab = ref('portfolio');
const selectedMarketCategory = ref<string | null>(null);
const selectedRegion = ref<string | null>(null);
const selectedExchange = ref<string | null>(null);
const selectedSecurity = ref<any | null>(null);
const showSecurityDetail = ref(false);
const showLinkTradingAccountDialog = ref(false);
const linkingStep = ref<'search' | 'details' | 'consent' | 'success'>('search');
const brokerSearchQuery = ref('');
const selectedBroker = ref<any | null>(null);
const tradingAccountForm = ref({
    account_holder_name: '',
    trading_account_number: '',
    consent_agreed: false,
});

const currentTier = {
    name: 'Silver Tier',
    subtitle: 'Retail Investors Mode',
    price: 'Free'
};

const marketCategories = [
    { id: 'stocks', name: 'Stock Markets', icon: 'chart', color: 'blue' },
    { id: 'bonds', name: 'Bond & Fixed Income Markets', icon: 'trending', color: 'green' },
    { id: 'commodities', name: 'Commodities Markets', icon: 'box', color: 'yellow' },
    { id: 'digital', name: 'Digital Assets Markets', icon: 'bitcoin', color: 'purple' },
    { id: 'realestate', name: 'Real Estate Markets', icon: 'building', color: 'orange' },
];

const stockExchangesByRegion = {
    'North America': [
        { id: 'nyse', name: 'New York Stock Exchange (NYSE)', country: 'USA', companies: 2800 },
        { id: 'nasdaq', name: 'NASDAQ', country: 'USA', companies: 3300 },
        { id: 'tsx', name: 'Toronto Stock Exchange (TSX)', country: 'Canada', companies: 1500 },
    ],
    'Europe': [
        { id: 'lse', name: 'London Stock Exchange (LSE)', country: 'UK', companies: 2000 },
        { id: 'euronext', name: 'Euronext', country: 'EU', companies: 1300 },
        { id: 'dax', name: 'Frankfurt Stock Exchange (DAX)', country: 'Germany', companies: 800 },
    ],
    'Asia': [
        { id: 'hkex', name: 'Hong Kong Stock Exchange (HKEX)', country: 'Hong Kong', companies: 2500 },
        { id: 'sse', name: 'Shanghai Stock Exchange (SSE)', country: 'China', companies: 1800 },
        { id: 'tse', name: 'Tokyo Stock Exchange (TSE)', country: 'Japan', companies: 3700 },
    ],
    'Africa': [
        { id: 'jse', name: 'Johannesburg Stock Exchange (JSE)', country: 'South Africa', companies: 350 },
        { id: 'zse', name: 'Zimbabwe Stock Exchange (ZSE)', country: 'Zimbabwe', companies: 65 },
        { id: 'vfex', name: 'Victoria Falls Stock Exchange (VFEX)', country: 'Zimbabwe', companies: 15 },
        { id: 'nse', name: 'Nigerian Stock Exchange (NSE)', country: 'Nigeria', companies: 160 },
    ],
};

const globalBrokers = [
    { id: 1, name: 'Interactive Brokers', country: 'USA', type: 'Global Broker', markets: ['Stocks', 'Options', 'Futures', 'Forex'], logo: '🌐' },
    { id: 2, name: 'Charles Schwab', country: 'USA', type: 'Full-Service Broker', markets: ['Stocks', 'ETFs', 'Mutual Funds'], logo: '💼' },
    { id: 3, name: 'TD Ameritrade', country: 'USA', type: 'Online Broker', markets: ['Stocks', 'Options', 'Futures'], logo: '📊' },
    { id: 4, name: 'Fidelity Investments', country: 'USA', type: 'Full-Service Broker', markets: ['Stocks', 'Bonds', 'Mutual Funds'], logo: '🏦' },
    { id: 5, name: 'E*TRADE', country: 'USA', type: 'Online Broker', markets: ['Stocks', 'Options', 'Futures'], logo: '💹' },
    { id: 6, name: 'Saxo Bank', country: 'Denmark', type: 'Global Broker', markets: ['Stocks', 'Forex', 'CFDs'], logo: '🇩🇰' },
    { id: 7, name: 'IG Group', country: 'UK', type: 'CFD Broker', markets: ['CFDs', 'Forex', 'Spread Betting'], logo: '🇬🇧' },
    { id: 8, name: 'Plus500', country: 'Israel', type: 'CFD Broker', markets: ['CFDs', 'Forex', 'Crypto'], logo: '🇮🇱' },
    { id: 9, name: 'eToro', country: 'Israel', type: 'Social Trading', markets: ['Stocks', 'Crypto', 'Commodities'], logo: '👥' },
    { id: 10, name: 'Robinhood', country: 'USA', type: 'Commission-Free Broker', markets: ['Stocks', 'Options', 'Crypto'], logo: '🏹' },
    { id: 11, name: 'Vanguard', country: 'USA', type: 'Investment Manager', markets: ['Mutual Funds', 'ETFs', 'Bonds'], logo: '⛵' },
    { id: 12, name: 'BlackRock', country: 'USA', type: 'Asset Manager', markets: ['ETFs', 'Mutual Funds', 'Alternatives'], logo: '🪨' },
    { id: 13, name: 'CMC Markets', country: 'UK', type: 'CFD Broker', markets: ['CFDs', 'Forex', 'Spread Betting'], logo: '📈' },
    { id: 14, name: 'DEGIRO', country: 'Netherlands', type: 'Online Broker', markets: ['Stocks', 'ETFs', 'Bonds'], logo: '🇳🇱' },
    { id: 15, name: 'Trading 212', country: 'UK', type: 'Commission-Free Broker', markets: ['Stocks', 'ETFs', 'CFDs'], logo: '🎯' },
    { id: 16, name: 'XTB', country: 'Poland', type: 'Forex & CFD Broker', markets: ['Forex', 'CFDs', 'Stocks'], logo: '🇵🇱' },
    { id: 17, name: 'OANDA', country: 'USA', type: 'Forex Broker', markets: ['Forex', 'CFDs'], logo: '💱' },
    { id: 18, name: 'Questrade', country: 'Canada', type: 'Online Broker', markets: ['Stocks', 'Options', 'ETFs'], logo: '🇨🇦' },
    { id: 19, name: 'Wealthsimple', country: 'Canada', type: 'Robo-Advisor', markets: ['Stocks', 'ETFs', 'Crypto'], logo: '🤖' },
    { id: 20, name: 'Standard Bank Securities', country: 'South Africa', type: 'Full-Service Broker', markets: ['JSE Stocks', 'Bonds', 'ETFs'], logo: '🇿🇦' },
];

const filteredBrokers = computed(() => {
    if (!brokerSearchQuery.value) return globalBrokers;
    const query = brokerSearchQuery.value.toLowerCase();
    return globalBrokers.filter(broker => 
        broker.name.toLowerCase().includes(query) ||
        broker.country.toLowerCase().includes(query) ||
        broker.type.toLowerCase().includes(query)
    );
});

const sampleSecurities: Record<string, any[]> = {
    'nyse': [
        { symbol: 'AAPL', name: 'Apple Inc.', price: 178.50, change: 2.3, sector: 'Technology', marketCap: '2.8T' },
        { symbol: 'MSFT', name: 'Microsoft Corporation', price: 378.91, change: 1.8, sector: 'Technology', marketCap: '2.8T' },
        { symbol: 'JPM', name: 'JPMorgan Chase & Co.', price: 158.20, change: -0.5, sector: 'Financials', marketCap: '456B' },
    ],
    'jse': [
        { symbol: 'NPN', name: 'Naspers Limited', price: 3250.00, change: 1.2, sector: 'Technology', marketCap: '1.4T ZAR' },
        { symbol: 'AGL', name: 'Anglo American plc', price: 425.50, change: -0.8, sector: 'Mining', marketCap: '580B ZAR' },
        { symbol: 'SBK', name: 'Standard Bank Group', price: 152.30, change: 0.5, sector: 'Financials', marketCap: '245B ZAR' },
    ],
    'zse': [
        { symbol: 'OK', name: 'OK Zimbabwe Limited', price: 45.50, change: 3.5, sector: 'Retail', marketCap: '12B ZWL' },
        { symbol: 'DELTA', name: 'Delta Corporation', price: 125.00, change: 2.1, sector: 'Beverages', marketCap: '35B ZWL' },
        { symbol: 'ECONET', name: 'Econet Wireless Zimbabwe', price: 85.75, change: 1.8, sector: 'Telecommunications', marketCap: '28B ZWL' },
    ],
    'vfex': [
        { symbol: 'PADENGA', name: 'Padenga Holdings Limited', price: 0.28, change: 0.0, sector: 'Agriculture', marketCap: '42M USD' },
        { symbol: 'SEEDCO', name: 'SeedCo International', price: 0.65, change: 1.5, sector: 'Agriculture', marketCap: '85M USD' },
    ],
};

const resetMarketNavigation = () => {
    selectedRegion.value = null;
    selectedExchange.value = null;
    selectedSecurity.value = null;
};

const selectMarketCategory = (categoryId: string) => {
    selectedMarketCategory.value = categoryId;
    resetMarketNavigation();
};

const selectRegion = (region: string) => {
    selectedRegion.value = region;
    selectedExchange.value = null;
    selectedSecurity.value = null;
};

const selectExchange = (exchangeId: string) => {
    selectedExchange.value = exchangeId;
    selectedSecurity.value = null;
};

const viewSecurityDetail = (security: any) => {
    selectedSecurity.value = security;
    showSecurityDetail.value = true;
};

const openLinkTradingAccountDialog = () => {
    showLinkTradingAccountDialog.value = true;
    linkingStep.value = 'search';
    brokerSearchQuery.value = '';
    selectedBroker.value = null;
    tradingAccountForm.value = {
        account_holder_name: '',
        trading_account_number: '',
        consent_agreed: false,
    };
};

const selectBroker = (broker: any) => {
    selectedBroker.value = broker;
    linkingStep.value = 'details';
};

const backToSearch = () => {
    linkingStep.value = 'search';
    selectedBroker.value = null;
};

const proceedToConsent = () => {
    if (!tradingAccountForm.value.account_holder_name || !tradingAccountForm.value.trading_account_number) {
        alert('Please fill in all required fields');
        return;
    }
    linkingStep.value = 'consent';
};

const backToDetails = () => {
    linkingStep.value = 'details';
};

const linkTradingAccount = () => {
    if (!tradingAccountForm.value.consent_agreed) {
        alert('Please agree to the consent form to proceed');
        return;
    }
    
    // Here you would make an API call to link the account
    // For now, we'll simulate success
    linkingStep.value = 'success';
    
    // In a real implementation:
    // router.post('/paneta/wealth/link-trading-account', {
    //     broker_id: selectedBroker.value.id,
    //     account_holder_name: tradingAccountForm.value.account_holder_name,
    //     trading_account_number: tradingAccountForm.value.trading_account_number,
    // });
};

const closeLinkDialog = () => {
    showLinkTradingAccountDialog.value = false;
    linkingStep.value = 'search';
    selectedBroker.value = null;
    tradingAccountForm.value = {
        account_holder_name: '',
        trading_account_number: '',
        consent_agreed: false,
    };
};

const redirectToMarketIntegration = () => {
    // This will switch to the Market Integration tab
    showSecurityDetail.value = false;
    // In a real implementation, you'd use router or emit event to switch tabs
    alert('Redirecting to Market Integration to complete purchase...');
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
            <Tabs v-model="activeTab" default-value="portfolio" class="space-y-6">
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
                        <Button 
                            class="gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                            @click="activeTab = 'integration'"
                        >
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
                        <div class="mt-4 pt-4 border-t">
                            <Button 
                                variant="outline" 
                                class="w-full gap-2"
                                @click="showScenarioDialog = true"
                            >
                                <Activity class="h-4 w-4" />
                                Run Scenario Analysis
                            </Button>
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

                    <!-- Personalized Investment Recommendations -->
                    <Card class="border-2 border-green-200">
                        <CardHeader class="bg-gradient-to-r from-green-50 to-emerald-50">
                            <CardTitle class="flex items-center gap-2 text-green-900">
                                <Sparkles class="h-5 w-5" />
                                Personalized Investment Recommendations
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="grid gap-6 lg:grid-cols-2">
                                <!-- AI-Powered Insights -->
                                <div class="space-y-4">
                                    <h3 class="font-semibold text-lg mb-4">AI-Powered Insights</h3>
                                    
                                    <!-- Portfolio Optimization -->
                                    <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="rounded-full bg-green-200 p-2">
                                                <Target class="h-4 w-4 text-green-700" />
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-green-900">Portfolio Optimization</h4>
                                                <p class="text-sm text-green-800 mt-1">
                                                    Consider increasing international equity allocation by 3% to improve diversification and reduce concentration risk.
                                                </p>
                                                <Button size="sm" class="mt-3 bg-green-600 hover:bg-green-700">
                                                    Apply Recommendation
                                                </Button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- New Opportunity -->
                                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="rounded-full bg-blue-200 p-2">
                                                <TrendingUp class="h-4 w-4 text-blue-700" />
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-blue-900">New Opportunity</h4>
                                                <p class="text-sm text-blue-800 mt-1">
                                                    Emerging markets real estate REITs showing strong fundamentals. Consider 2-3% allocation.
                                                </p>
                                                <Button size="sm" variant="outline" class="mt-3 border-blue-600 text-blue-600 hover:bg-blue-50">
                                                    Research Opportunity
                                                </Button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Risk Alert -->
                                    <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="rounded-full bg-red-200 p-2">
                                                <AlertTriangle class="h-4 w-4 text-red-700" />
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-red-900">Risk Alert</h4>
                                                <p class="text-sm text-red-800 mt-1">
                                                    High correlation detected between tech stocks and crypto holdings. Consider reducing exposure.
                                                </p>
                                                <Button size="sm" variant="outline" class="mt-3 border-red-600 text-red-600 hover:bg-red-50">
                                                    Review Risk
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Market Intelligence -->
                                <div class="space-y-4">
                                    <h3 class="font-semibold text-lg mb-4">Market Intelligence</h3>
                                    
                                    <!-- Current Market Outlook -->
                                    <div class="rounded-lg border p-4">
                                        <h4 class="font-semibold mb-3">Current Market Outlook</h4>
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm">Global Equities</span>
                                                <Badge class="bg-green-100 text-green-700">Bullish</Badge>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm">Fixed Income</span>
                                                <Badge class="bg-yellow-100 text-yellow-700">Neutral</Badge>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm">Real Estate</span>
                                                <Badge class="bg-green-100 text-green-700">Bullish</Badge>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm">Commodities</span>
                                                <Badge class="bg-red-100 text-red-700">Bearish</Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Key Market Events -->
                                    <div class="rounded-lg border p-4">
                                        <h4 class="font-semibold mb-3">Key Market Events</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li class="flex items-start gap-2">
                                                <span class="text-muted-foreground">•</span>
                                                <span>Fed decision expected July 31st</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <span class="text-muted-foreground">•</span>
                                                <span>Q2 earnings season underway</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <span class="text-muted-foreground">•</span>
                                                <span>European inflation data pending</span>
                                            </li>
                                            <li class="flex items-start gap-2">
                                                <span class="text-muted-foreground">•</span>
                                                <span>China PMI data release July 1st</span>
                                            </li>
                                        </ul>
                                    </div>
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

                    <!-- Subscription Tiers Dialog -->
                    <Dialog v-model:open="showTiersDialog">
                        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle class="text-2xl">Subscription Tiers</DialogTitle>
                                <DialogDescription>
                                    Choose the plan that best fits your investment needs
                                </DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-4 md:grid-cols-3 py-4">
                                <!-- Silver Tier -->
                                <Card class="border-2 border-purple-200">
                                    <CardHeader class="bg-purple-50">
                                        <CardTitle class="flex items-center gap-2">
                                            <svg class="h-5 w-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            Silver Tier
                                        </CardTitle>
                                        <div class="text-2xl font-bold">Free</div>
                                    </CardHeader>
                                    <CardContent class="pt-4">
                                        <p class="text-sm text-muted-foreground mb-4">Retail Investors Mode</p>
                                        <ul class="space-y-2 text-sm">
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Basic portfolio tracking
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Up to 5 linked accounts
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Daily data refresh
                                            </li>
                                        </ul>
                                        <Badge class="mt-4 w-full justify-center bg-purple-100 text-purple-700">Current Plan</Badge>
                                    </CardContent>
                                </Card>

                                <!-- Gold Tier -->
                                <Card class="border-2 border-yellow-400">
                                    <CardHeader class="bg-yellow-50">
                                        <CardTitle class="flex items-center gap-2">
                                            <svg class="h-5 w-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            Gold Tier
                                        </CardTitle>
                                        <div class="text-2xl font-bold">$29/mo</div>
                                    </CardHeader>
                                    <CardContent class="pt-4">
                                        <p class="text-sm text-muted-foreground mb-4">Professional Investors</p>
                                        <ul class="space-y-2 text-sm">
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Advanced analytics
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Unlimited linked accounts
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Real-time data
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                AI recommendations
                                            </li>
                                        </ul>
                                        <Button class="mt-4 w-full bg-yellow-600 hover:bg-yellow-700">Upgrade</Button>
                                    </CardContent>
                                </Card>

                                <!-- Platinum Tier -->
                                <Card class="border-2 border-blue-400">
                                    <CardHeader class="bg-blue-50">
                                        <CardTitle class="flex items-center gap-2">
                                            <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            Platinum Tier
                                        </CardTitle>
                                        <div class="text-2xl font-bold">$99/mo</div>
                                    </CardHeader>
                                    <CardContent class="pt-4">
                                        <p class="text-sm text-muted-foreground mb-4">Institutional Investors</p>
                                        <ul class="space-y-2 text-sm">
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                All Gold features
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Scenario analysis
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Custom reports
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <CheckCircle class="h-4 w-4 text-green-600" />
                                                Priority support
                                            </li>
                                        </ul>
                                        <Button class="mt-4 w-full bg-blue-600 hover:bg-blue-700">Upgrade</Button>
                                    </CardContent>
                                </Card>
                            </div>
                        </DialogContent>
                    </Dialog>

                    <!-- Scenario Analysis Dialog -->
                    <Dialog v-model:open="showScenarioDialog">
                        <DialogContent class="max-w-3xl">
                            <DialogHeader>
                                <DialogTitle class="text-2xl">Scenario Analysis</DialogTitle>
                                <DialogDescription>
                                    Test how your portfolio would perform under different market conditions
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-4 py-4">
                                <div class="rounded-lg bg-blue-50 border border-blue-200 p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <Shield class="h-5 w-5 text-blue-600" />
                                        <h4 class="font-semibold text-blue-900">Premium Feature</h4>
                                    </div>
                                    <p class="text-sm text-blue-800">
                                        Scenario Analysis is available on Gold and Platinum tiers. Upgrade your subscription to access advanced portfolio simulation tools.
                                    </p>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="rounded-lg border p-4">
                                        <h4 class="font-semibold mb-2">Market Crash (-30%)</h4>
                                        <p class="text-sm text-muted-foreground">See how your portfolio would perform in a severe market downturn</p>
                                    </div>
                                    <div class="rounded-lg border p-4">
                                        <h4 class="font-semibold mb-2">Bull Market (+20%)</h4>
                                        <p class="text-sm text-muted-foreground">Analyze potential gains in a strong bull market</p>
                                    </div>
                                    <div class="rounded-lg border p-4">
                                        <h4 class="font-semibold mb-2">Interest Rate Hike</h4>
                                        <p class="text-sm text-muted-foreground">Impact of central bank rate increases</p>
                                    </div>
                                    <div class="rounded-lg border p-4">
                                        <h4 class="font-semibold mb-2">Currency Fluctuation</h4>
                                        <p class="text-sm text-muted-foreground">FX exposure stress testing</p>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" @click="showScenarioDialog = false">Close</Button>
                                <Button class="bg-gradient-to-r from-blue-600 to-indigo-600" @click="showTiersDialog = true; showScenarioDialog = false">
                                    Upgrade to Access
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </TabsContent>

                <!-- Global Financial Markets Tab -->
                <TabsContent value="markets" class="space-y-6">
                    <!-- Breadcrumb Navigation -->
                    <div v-if="selectedMarketCategory" class="flex items-center gap-2 text-sm">
                        <Button variant="ghost" size="sm" @click="selectedMarketCategory = null; resetMarketNavigation()">
                            Global Markets
                        </Button>
                        <span class="text-muted-foreground">/</span>
                        <Button variant="ghost" size="sm" @click="resetMarketNavigation()">
                            {{ marketCategories.find(c => c.id === selectedMarketCategory)?.name }}
                        </Button>
                        <template v-if="selectedRegion">
                            <span class="text-muted-foreground">/</span>
                            <Button variant="ghost" size="sm" @click="selectedExchange = null; selectedSecurity = null">
                                {{ selectedRegion }}
                            </Button>
                        </template>
                        <template v-if="selectedExchange">
                            <span class="text-muted-foreground">/</span>
                            <span class="font-medium">{{ stockExchangesByRegion[selectedRegion as keyof typeof stockExchangesByRegion]?.find(e => e.id === selectedExchange)?.name }}</span>
                        </template>
                    </div>

                    <!-- Market Category Selection -->
                    <div v-if="!selectedMarketCategory">
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Globe class="h-5 w-5" />
                                    Global Financial Markets
                                </CardTitle>
                                <CardDescription>
                                    Access global investment opportunities across multiple asset classes
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <!-- Stock Markets -->
                                    <Card 
                                        class="cursor-pointer border-2 hover:border-blue-400 transition-colors"
                                        @click="selectMarketCategory('stocks')"
                                    >
                                        <CardContent class="p-6">
                                            <div class="flex flex-col items-center text-center space-y-3">
                                                <div class="rounded-full bg-blue-100 p-4">
                                                    <BarChart3 class="h-8 w-8 text-blue-600" />
                                                </div>
                                                <h3 class="font-semibold text-lg">Stock Markets</h3>
                                                <p class="text-sm text-muted-foreground">
                                                    Access global stock exchanges and equities
                                                </p>
                                                <Badge class="bg-blue-100 text-blue-700">10,000+ Companies</Badge>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <!-- Bond & Fixed Income -->
                                    <Card 
                                        class="cursor-pointer border-2 hover:border-green-400 transition-colors"
                                        @click="selectMarketCategory('bonds')"
                                    >
                                        <CardContent class="p-6">
                                            <div class="flex flex-col items-center text-center space-y-3">
                                                <div class="rounded-full bg-green-100 p-4">
                                                    <TrendingUp class="h-8 w-8 text-green-600" />
                                                </div>
                                                <h3 class="font-semibold text-lg">Bond & Fixed Income</h3>
                                                <p class="text-sm text-muted-foreground">
                                                    Government and corporate bonds worldwide
                                                </p>
                                                <Badge class="bg-green-100 text-green-700">Coming Soon</Badge>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <!-- Commodities -->
                                    <Card 
                                        class="cursor-pointer border-2 hover:border-yellow-400 transition-colors"
                                        @click="selectMarketCategory('commodities')"
                                    >
                                        <CardContent class="p-6">
                                            <div class="flex flex-col items-center text-center space-y-3">
                                                <div class="rounded-full bg-yellow-100 p-4">
                                                    <DollarSign class="h-8 w-8 text-yellow-600" />
                                                </div>
                                                <h3 class="font-semibold text-lg">Commodities</h3>
                                                <p class="text-sm text-muted-foreground">
                                                    Gold, oil, agricultural products & more
                                                </p>
                                                <Badge class="bg-yellow-100 text-yellow-700">Coming Soon</Badge>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <!-- Digital Assets -->
                                    <Card 
                                        class="cursor-pointer border-2 hover:border-purple-400 transition-colors"
                                        @click="selectMarketCategory('digital')"
                                    >
                                        <CardContent class="p-6">
                                            <div class="flex flex-col items-center text-center space-y-3">
                                                <div class="rounded-full bg-purple-100 p-4">
                                                    <Activity class="h-8 w-8 text-purple-600" />
                                                </div>
                                                <h3 class="font-semibold text-lg">Digital Assets</h3>
                                                <p class="text-sm text-muted-foreground">
                                                    Cryptocurrencies and blockchain assets
                                                </p>
                                                <Badge class="bg-purple-100 text-purple-700">Coming Soon</Badge>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <!-- Real Estate -->
                                    <Card 
                                        class="cursor-pointer border-2 hover:border-orange-400 transition-colors"
                                        @click="selectMarketCategory('realestate')"
                                    >
                                        <CardContent class="p-6">
                                            <div class="flex flex-col items-center text-center space-y-3">
                                                <div class="rounded-full bg-orange-100 p-4">
                                                    <Building2 class="h-8 w-8 text-orange-600" />
                                                </div>
                                                <h3 class="font-semibold text-lg">Real Estate</h3>
                                                <p class="text-sm text-muted-foreground">
                                                    REITs and property investment opportunities
                                                </p>
                                                <Badge class="bg-orange-100 text-orange-700">Coming Soon</Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Stock Markets - Region Selection -->
                    <div v-if="selectedMarketCategory === 'stocks' && !selectedRegion">
                        <Card>
                            <CardHeader>
                                <CardTitle>Select Region</CardTitle>
                                <CardDescription>Choose a geographical region to view stock exchanges</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <Card 
                                        v-for="(exchanges, region) in stockExchangesByRegion" 
                                        :key="region"
                                        class="cursor-pointer hover:bg-accent transition-colors"
                                        @click="selectRegion(region)"
                                    >
                                        <CardContent class="p-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h3 class="font-semibold">{{ region }}</h3>
                                                    <p class="text-sm text-muted-foreground">{{ exchanges.length }} exchanges</p>
                                                </div>
                                                <Globe class="h-6 w-6 text-muted-foreground" />
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Stock Exchanges List -->
                    <div v-if="selectedMarketCategory === 'stocks' && selectedRegion && !selectedExchange">
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ selectedRegion }} Stock Exchanges</CardTitle>
                                <CardDescription>Select an exchange to view listed companies</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <Card 
                                        v-for="exchange in stockExchangesByRegion[selectedRegion as keyof typeof stockExchangesByRegion]" 
                                        :key="exchange.id"
                                        class="cursor-pointer hover:bg-accent transition-colors"
                                        @click="selectExchange(exchange.id)"
                                    >
                                        <CardContent class="p-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h3 class="font-semibold">{{ exchange.name }}</h3>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <Badge variant="outline">{{ exchange.country }}</Badge>
                                                        <span class="text-sm text-muted-foreground">{{ exchange.companies }} companies</span>
                                                    </div>
                                                </div>
                                                <svg class="h-5 w-5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Securities List -->
                    <div v-if="selectedMarketCategory === 'stocks' && selectedExchange">
                        <Card>
                            <CardHeader>
                                <CardTitle>Listed Companies</CardTitle>
                                <CardDescription>Click on any company to view details and trading information</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Symbol</TableHead>
                                            <TableHead>Company Name</TableHead>
                                            <TableHead class="text-right">Price</TableHead>
                                            <TableHead class="text-right">Change</TableHead>
                                            <TableHead>Sector</TableHead>
                                            <TableHead>Market Cap</TableHead>
                                            <TableHead class="text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow 
                                            v-for="security in sampleSecurities[selectedExchange]" 
                                            :key="security.symbol"
                                            class="cursor-pointer hover:bg-accent"
                                            @click="viewSecurityDetail(security)"
                                        >
                                            <TableCell class="font-bold">{{ security.symbol }}</TableCell>
                                            <TableCell>{{ security.name }}</TableCell>
                                            <TableCell class="text-right font-medium">${{ security.price.toFixed(2) }}</TableCell>
                                            <TableCell class="text-right">
                                                <span :class="security.change >= 0 ? 'text-green-600' : 'text-red-600'">
                                                    {{ security.change >= 0 ? '+' : '' }}{{ security.change.toFixed(2) }}%
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{{ security.sector }}</Badge>
                                            </TableCell>
                                            <TableCell>{{ security.marketCap }}</TableCell>
                                            <TableCell class="text-right">
                                                <Button size="sm" variant="outline">View Details</Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Security Detail Dialog -->
                    <Dialog v-model:open="showSecurityDetail">
                        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
                            <DialogHeader v-if="selectedSecurity">
                                <DialogTitle class="text-2xl">{{ selectedSecurity.symbol }} - {{ selectedSecurity.name }}</DialogTitle>
                                <DialogDescription>
                                    {{ selectedSecurity.sector }} • Market Cap: {{ selectedSecurity.marketCap }}
                                </DialogDescription>
                            </DialogHeader>
                            <div v-if="selectedSecurity" class="space-y-6 py-4">
                                <!-- Price Information -->
                                <div class="grid gap-4 md:grid-cols-3">
                                    <Card>
                                        <CardContent class="p-4">
                                            <p class="text-sm text-muted-foreground">Current Price</p>
                                            <p class="text-3xl font-bold">${{ selectedSecurity.price.toFixed(2) }}</p>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardContent class="p-4">
                                            <p class="text-sm text-muted-foreground">Change (24h)</p>
                                            <p :class="['text-3xl font-bold', selectedSecurity.change >= 0 ? 'text-green-600' : 'text-red-600']">
                                                {{ selectedSecurity.change >= 0 ? '+' : '' }}{{ selectedSecurity.change.toFixed(2) }}%
                                            </p>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardContent class="p-4">
                                            <p class="text-sm text-muted-foreground">Market Cap</p>
                                            <p class="text-3xl font-bold">{{ selectedSecurity.marketCap }}</p>
                                        </CardContent>
                                    </Card>
                                </div>

                                <!-- Company Information -->
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Company Information</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Sector</span>
                                            <Badge>{{ selectedSecurity.sector }}</Badge>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Exchange</span>
                                            <span class="font-medium">{{ stockExchangesByRegion[selectedRegion as keyof typeof stockExchangesByRegion]?.find(e => e.id === selectedExchange)?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Symbol</span>
                                            <span class="font-bold">{{ selectedSecurity.symbol }}</span>
                                        </div>
                                    </CardContent>
                                </Card>

                                <!-- Recent News & Announcements -->
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Recent News & Announcements</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="space-y-3">
                                            <div class="border-l-4 border-blue-500 pl-4 py-2">
                                                <p class="font-medium">Q2 Earnings Report Released</p>
                                                <p class="text-sm text-muted-foreground">2 days ago</p>
                                            </div>
                                            <div class="border-l-4 border-green-500 pl-4 py-2">
                                                <p class="font-medium">New Product Launch Announced</p>
                                                <p class="text-sm text-muted-foreground">1 week ago</p>
                                            </div>
                                            <div class="border-l-4 border-yellow-500 pl-4 py-2">
                                                <p class="font-medium">Board of Directors Meeting</p>
                                                <p class="text-sm text-muted-foreground">2 weeks ago</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" @click="showSecurityDetail = false">Close</Button>
                                <Button class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700" @click="redirectToMarketIntegration">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Buy {{ selectedSecurity?.symbol }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </TabsContent>

                <!-- Market Integration Tab -->
                <TabsContent value="integration" class="space-y-6">
                    <!-- Header Section -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                Multi-Market Account Linkage
                            </h2>
                            <p class="text-sm text-muted-foreground mt-1">
                                Connect and manage trading accounts from various financial markets and exchanges worldwide. Access equities, fixed-income, commodities, and other asset classes from a single unified platform.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button 
                                variant="default" 
                                class="gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700"
                                @click="openLinkTradingAccountDialog"
                            >
                                <Globe class="h-4 w-4" />
                                Link Global Market Trading Account
                            </Button>
                            <Button variant="outline" class="gap-2">
                                <Activity class="h-4 w-4" />
                                Blockchain Integration
                            </Button>
                            <Button variant="outline" class="gap-2">
                                <RefreshCw class="h-4 w-4" />
                                Reset Integration
                            </Button>
                        </div>
                    </div>

                    <!-- Connected Trading Accounts -->
                    <Card class="border-2 border-blue-200">
                        <CardHeader class="bg-blue-50">
                            <CardTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5 text-blue-600" />
                                Connected Trading Accounts ({{ linkedInstitutions.length }})
                            </CardTitle>
                            <CardDescription>
                                Link your trading accounts from global financial markets for consolidated portfolio management and real-time data synchronization.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div v-if="linkedInstitutions.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                                <Building2 class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-lg font-medium">No Trading Accounts Connected</p>
                                <p class="text-muted-foreground mt-2 mb-4">
                                    Connect your first trading account to start managing your global portfolio
                                </p>
                                <Button class="gap-2">
                                    <Plus class="h-4 w-4" />
                                    Connect Trading Account
                                </Button>
                            </div>

                            <div v-else class="grid gap-4 md:grid-cols-2">
                                <Card 
                                    v-for="account in linkedInstitutions" 
                                    :key="account.id"
                                    class="border-2"
                                >
                                    <CardContent class="p-6">
                                        <!-- Account Header -->
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center gap-3">
                                                <div class="rounded-full bg-green-100 p-2">
                                                    <CheckCircle class="h-5 w-5 text-green-600" />
                                                </div>
                                                <div>
                                                    <h3 class="font-semibold text-lg">{{ account.institution.name }}</h3>
                                                    <p class="text-sm text-muted-foreground">{{ account.type }} • Global Markets</p>
                                                </div>
                                            </div>
                                            <Badge :class="getStatusBadge(account.status).class">
                                                {{ getStatusBadge(account.status).label }}
                                            </Badge>
                                        </div>

                                        <!-- Account Details -->
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-muted-foreground">Account Value:</span>
                                                <span class="font-bold text-lg">$850,000</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-muted-foreground">Exchange:</span>
                                                <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                                                    NYSE/NASDAQ
                                                </Badge>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-muted-foreground">Last Sync:</span>
                                                <span class="text-sm font-medium">{{ account.last_synced }}</span>
                                            </div>
                                            <div>
                                                <span class="text-sm text-muted-foreground">Permissions:</span>
                                                <div class="flex gap-2 mt-2">
                                                    <Badge variant="outline" class="text-xs">read</Badge>
                                                    <Badge variant="outline" class="text-xs">trade</Badge>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-2 mt-4 pt-4 border-t">
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                class="flex-1 text-red-600 hover:text-red-700 hover:bg-red-50"
                                                @click="disconnectInstitution(account.id)"
                                            >
                                                Disconnect
                                            </Button>
                                            <Button 
                                                size="sm" 
                                                class="flex-1 gap-1"
                                                @click="refreshInstitution(account.id)"
                                                :disabled="refreshing === account.id"
                                            >
                                                <RefreshCw :class="['h-3 w-3', refreshing === account.id ? 'animate-spin' : '']" />
                                                Sync
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Add New Account Section -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Plus class="h-5 w-5" />
                                Connect New Trading Account
                            </CardTitle>
                            <CardDescription>
                                Link accounts from brokers, exchanges, and investment platforms worldwide
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-3">
                                <Button variant="outline" class="h-24 flex flex-col gap-2" as-child>
                                    <a href="/paneta/accounts">
                                        <BarChart3 class="h-6 w-6" />
                                        <span>Stock Brokers</span>
                                    </a>
                                </Button>
                                <Button variant="outline" class="h-24 flex flex-col gap-2" as-child>
                                    <a href="/paneta/accounts">
                                        <DollarSign class="h-6 w-6" />
                                        <span>Crypto Exchanges</span>
                                    </a>
                                </Button>
                                <Button variant="outline" class="h-24 flex flex-col gap-2" as-child>
                                    <a href="/paneta/accounts">
                                        <Building2 class="h-6 w-6" />
                                        <span>Investment Platforms</span>
                                    </a>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <!-- Link Global Market Trading Account Dialog -->
            <Dialog :open="showLinkTradingAccountDialog" @update:open="closeLinkDialog">
                <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
                    <!-- Step 1: Search for Broker -->
                    <div v-if="linkingStep === 'search'">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                Link Global Market Trading Account
                            </DialogTitle>
                            <DialogDescription>
                                Search for your broker or investment platform to link your trading account
                            </DialogDescription>
                        </DialogHeader>
                        
                        <div class="mt-6 space-y-4">
                            <!-- Search Input -->
                            <div>
                                <label class="text-sm font-medium mb-2 block">Search for Broker or Investment Platform</label>
                                <input 
                                    v-model="brokerSearchQuery"
                                    type="text"
                                    placeholder="e.g., Interactive Brokers, Charles Schwab, Standard Bank..."
                                    class="w-full rounded-md border px-4 py-2"
                                />
                            </div>

                            <!-- Broker List -->
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <div 
                                    v-for="broker in filteredBrokers" 
                                    :key="broker.id"
                                    @click="selectBroker(broker)"
                                    class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 cursor-pointer transition-colors"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="text-3xl">{{ broker.logo }}</div>
                                        <div>
                                            <p class="font-medium">{{ broker.name }}</p>
                                            <p class="text-sm text-muted-foreground">{{ broker.type }} • {{ broker.country }}</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="market in broker.markets.slice(0, 3)" :key="market" variant="outline" class="text-xs">
                                            {{ market }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Account Details -->
                    <div v-if="linkingStep === 'details'">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                Link Account - {{ selectedBroker?.name }}
                            </DialogTitle>
                            <DialogDescription>
                                Enter your trading account details
                            </DialogDescription>
                        </DialogHeader>

                        <div class="mt-6 space-y-4">
                            <!-- Selected Broker Display -->
                            <div class="flex items-center gap-3 p-4 bg-muted rounded-lg">
                                <div class="text-3xl">{{ selectedBroker?.logo }}</div>
                                <div>
                                    <p class="font-medium">{{ selectedBroker?.name }}</p>
                                    <p class="text-sm text-muted-foreground">{{ selectedBroker?.type }} • {{ selectedBroker?.country }}</p>
                                </div>
                            </div>

                            <!-- Account Holder Name -->
                            <div>
                                <label class="text-sm font-medium mb-2 block">Account Holder Name <span class="text-red-500">*</span></label>
                                <input 
                                    v-model="tradingAccountForm.account_holder_name"
                                    type="text"
                                    placeholder="Enter your full name as it appears on your account"
                                    class="w-full rounded-md border px-4 py-2"
                                />
                            </div>

                            <!-- Trading Account Number -->
                            <div>
                                <label class="text-sm font-medium mb-2 block">Trading Account Number <span class="text-red-500">*</span></label>
                                <input 
                                    v-model="tradingAccountForm.trading_account_number"
                                    type="text"
                                    placeholder="Enter your trading account number"
                                    class="w-full rounded-md border px-4 py-2"
                                />
                            </div>

                            <div class="flex items-start gap-2 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <AlertTriangle class="h-5 w-5 text-blue-600 mt-0.5" />
                                <div class="text-sm text-blue-900">
                                    <p class="font-medium">Read-Only Access</p>
                                    <p class="text-blue-800 mt-1">
                                        PANÉTA will only read your portfolio data. We cannot execute trades or move funds from your account.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <DialogFooter class="mt-6">
                            <Button variant="outline" @click="backToSearch">
                                Back
                            </Button>
                            <Button @click="proceedToConsent">
                                Continue
                            </Button>
                        </DialogFooter>
                    </div>

                    <!-- Step 3: Consent Form -->
                    <div v-if="linkingStep === 'consent'">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2">
                                <Shield class="h-5 w-5" />
                                Consent & Authorization
                            </DialogTitle>
                            <DialogDescription>
                                Please review and agree to the terms before linking your account
                            </DialogDescription>
                        </DialogHeader>

                        <div class="mt-6 space-y-4">
                            <!-- Consent Form Content -->
                            <div class="border rounded-lg p-6 max-h-96 overflow-y-auto bg-muted/30">
                                <h3 class="font-semibold text-lg mb-4">Account Linking Consent Form</h3>
                                
                                <div class="space-y-4 text-sm">
                                    <div>
                                        <h4 class="font-semibold mb-2">1. Data Access Authorization</h4>
                                        <p class="text-muted-foreground">
                                            I authorize PANÉTA to access my trading account data from {{ selectedBroker?.name }} for the purpose of portfolio aggregation, analysis, and decision support. This includes but is not limited to: account balances, holdings, transaction history, and performance metrics.
                                        </p>
                                    </div>

                                    <div>
                                        <h4 class="font-semibold mb-2">2. Read-Only Access</h4>
                                        <p class="text-muted-foreground">
                                            I understand that PANÉTA operates on a zero-custody, read-only basis. PANÉTA cannot and will not execute trades, transfer funds, or make any changes to my trading account at {{ selectedBroker?.name }}.
                                        </p>
                                    </div>

                                    <div>
                                        <h4 class="font-semibold mb-2">3. Data Security & Privacy</h4>
                                        <p class="text-muted-foreground">
                                            I acknowledge that PANÉTA will securely store and encrypt my account credentials and data. My information will not be shared with third parties without my explicit consent, except as required by law or regulatory authorities.
                                        </p>
                                    </div>

                                    <div>
                                        <h4 class="font-semibold mb-2">4. Account Holder Verification</h4>
                                        <p class="text-muted-foreground">
                                            I confirm that I am the authorized account holder of trading account number {{ tradingAccountForm.trading_account_number }} at {{ selectedBroker?.name }}, and that the information provided is accurate and complete.
                                        </p>
                                    </div>

                                    <div>
                                        <h4 class="font-semibold mb-2">5. Regulatory Compliance</h4>
                                        <p class="text-muted-foreground">
                                            I understand that PANÉTA may share aggregated and anonymized data with regulatory authorities for compliance and oversight purposes, in accordance with applicable financial regulations.
                                        </p>
                                    </div>

                                    <div>
                                        <h4 class="font-semibold mb-2">6. Revocation Rights</h4>
                                        <p class="text-muted-foreground">
                                            I may revoke this authorization at any time by disconnecting my account through the PANÉTA platform. Upon revocation, PANÉTA will cease accessing my account data and securely delete stored credentials.
                                        </p>
                                    </div>

                                    <div>
                                        <h4 class="font-semibold mb-2">7. Liability Disclaimer</h4>
                                        <p class="text-muted-foreground">
                                            I acknowledge that PANÉTA provides decision support tools only and does not provide investment advice. I am solely responsible for all investment decisions made using PANÉTA's platform.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Consent Checkbox -->
                            <div class="flex items-start gap-3 p-4 border-2 rounded-lg">
                                <input 
                                    v-model="tradingAccountForm.consent_agreed"
                                    type="checkbox"
                                    id="consent-checkbox"
                                    class="mt-1"
                                />
                                <label for="consent-checkbox" class="text-sm cursor-pointer">
                                    <span class="font-medium">I have read and agree to the terms above.</span>
                                    <span class="text-muted-foreground block mt-1">
                                        By checking this box, I authorize PANÉTA to link my trading account at {{ selectedBroker?.name }} and access my portfolio data for decision support purposes.
                                    </span>
                                </label>
                            </div>
                        </div>

                        <DialogFooter class="mt-6">
                            <Button variant="outline" @click="backToDetails">
                                Back
                            </Button>
                            <Button 
                                @click="linkTradingAccount"
                                :disabled="!tradingAccountForm.consent_agreed"
                            >
                                Link Account
                            </Button>
                        </DialogFooter>
                    </div>

                    <!-- Step 4: Success -->
                    <div v-if="linkingStep === 'success'">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2 text-green-600">
                                <CheckCircle class="h-5 w-5" />
                                Account Linked Successfully!
                            </DialogTitle>
                            <DialogDescription>
                                Your trading account has been connected to PANÉTA
                            </DialogDescription>
                        </DialogHeader>

                        <div class="mt-6 space-y-4">
                            <div class="flex flex-col items-center justify-center py-8">
                                <div class="rounded-full bg-green-100 p-4 mb-4">
                                    <CheckCircle class="h-12 w-12 text-green-600" />
                                </div>
                                <h3 class="text-xl font-semibold mb-2">Account Successfully Linked!</h3>
                                <p class="text-muted-foreground text-center max-w-md">
                                    Your {{ selectedBroker?.name }} trading account has been connected. Your portfolio data will be synced and available in the Portfolio Management section.
                                </p>
                            </div>

                            <div class="border rounded-lg p-4 bg-muted/30">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="text-3xl">{{ selectedBroker?.logo }}</div>
                                    <div>
                                        <p class="font-medium">{{ selectedBroker?.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ selectedBroker?.type }}</p>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Account Holder:</span>
                                        <span class="font-medium">{{ tradingAccountForm.account_holder_name }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Account Number:</span>
                                        <span class="font-medium">{{ tradingAccountForm.trading_account_number }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Status:</span>
                                        <Badge class="bg-green-100 text-green-700">Connected</Badge>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-start gap-2 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <Activity class="h-5 w-5 text-blue-600 mt-0.5" />
                                <div class="text-sm text-blue-900">
                                    <p class="font-medium">Next Steps</p>
                                    <p class="text-blue-800 mt-1">
                                        Your portfolio data is being synced. This may take a few minutes. You can view your consolidated portfolio in the Portfolio Management tab.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <DialogFooter class="mt-6">
                            <Button @click="closeLinkDialog" class="w-full">
                                Done
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
