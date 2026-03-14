<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import { 
    ArrowLeft,
    ArrowRight,
    Globe,
    TrendingUp,
    DollarSign,
    Building2,
    BarChart3,
    CheckCircle,
    ExternalLink,
    Shield,
    User,
    Key
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    accountType?: string;
}>();

// Step management
const currentStep = ref<'country' | 'asset' | 'market' | 'broker' | 'action' | 'details' | 'consent' | 'success'>('country');

// Form data
const selectedCountry = ref('');
const selectedAssetType = ref('');
const selectedMarket = ref('');
const selectedBroker = ref<any>(null);
const actionType = ref<'create' | 'link' | null>(null);
const accountDetails = ref({
    account_holder_name: '',
    trading_account_number: '',
    consent_agreed: false,
});

// Options data
const countries = [
    { code: 'US', name: 'United States', flag: '🇺🇸' },
    { code: 'UK', name: 'United Kingdom', flag: '🇬🇧' },
    { code: 'CA', name: 'Canada', flag: '🇨🇦' },
    { code: 'DE', name: 'Germany', flag: '🇩🇪' },
    { code: 'FR', name: 'France', flag: '🇫🇷' },
    { code: 'JP', name: 'Japan', flag: '🇯🇵' },
    { code: 'CN', name: 'China', flag: '🇨🇳' },
    { code: 'HK', name: 'Hong Kong', flag: '🇭🇰' },
    { code: 'SG', name: 'Singapore', flag: '🇸🇬' },
    { code: 'AU', name: 'Australia', flag: '🇦🇺' },
    { code: 'ZA', name: 'South Africa', flag: '🇿🇦' },
    { code: 'ZW', name: 'Zimbabwe', flag: '🇿🇼' },
    { code: 'NG', name: 'Nigeria', flag: '🇳🇬' },
    { code: 'KE', name: 'Kenya', flag: '🇰🇪' },
    { code: 'BR', name: 'Brazil', flag: '🇧🇷' },
    { code: 'MX', name: 'Mexico', flag: '🇲🇽' },
    { code: 'IN', name: 'India', flag: '🇮🇳' },
    { code: 'IT', name: 'Italy', flag: '🇮🇹' },
    { code: 'ES', name: 'Spain', flag: '🇪🇸' },
    { code: 'NL', name: 'Netherlands', flag: '🇳🇱' },
];

const assetTypes = [
    { 
        id: 'stocks', 
        name: 'Stocks & Equities', 
        description: 'Public company shares and equities',
        icon: BarChart3,
        color: 'blue'
    },
    { 
        id: 'bonds', 
        name: 'Bonds & Fixed Income', 
        description: 'Government and corporate bonds',
        icon: TrendingUp,
        color: 'green'
    },
    { 
        id: 'commodities', 
        name: 'Commodities', 
        description: 'Gold, oil, agricultural products',
        icon: DollarSign,
        color: 'yellow'
    },
    { 
        id: 'realestate', 
        name: 'Real Estate', 
        description: 'REITs and property investments',
        icon: Building2,
        color: 'orange'
    },
    { 
        id: 'crypto', 
        name: 'Digital Assets', 
        description: 'Cryptocurrencies and digital tokens',
        icon: Globe,
        color: 'purple'
    },
    { 
        id: 'forex', 
        name: 'Foreign Exchange', 
        description: 'Currency pairs and forex trading',
        icon: DollarSign,
        color: 'red'
    },
];

const financialMarkets = [
    { 
        id: 'nyse', 
        name: 'New York Stock Exchange (NYSE)', 
        country: 'US',
        assets: ['stocks'],
        description: 'Largest stock exchange by market cap'
    },
    { 
        id: 'nasdaq', 
        name: 'NASDAQ', 
        country: 'US',
        assets: ['stocks', 'crypto'],
        description: 'Technology-focused stock exchange'
    },
    { 
        id: 'lse', 
        name: 'London Stock Exchange (LSE)', 
        country: 'UK',
        assets: ['stocks', 'bonds'],
        description: 'Primary stock exchange in Europe'
    },
    { 
        id: 'tsx', 
        name: 'Toronto Stock Exchange (TSX)', 
        country: 'CA',
        assets: ['stocks', 'bonds'],
        description: 'Canada\'s largest stock exchange'
    },
    { 
        id: 'tse', 
        name: 'Tokyo Stock Exchange (TSE)', 
        country: 'JP',
        assets: ['stocks', 'bonds'],
        description: 'Asia\'s largest stock exchange'
    },
    { 
        id: 'hkex', 
        name: 'Hong Kong Stock Exchange (HKEX)', 
        country: 'HK',
        assets: ['stocks', 'bonds'],
        description: 'Major Asian financial hub'
    },
    { 
        id: 'jse', 
        name: 'Johannesburg Stock Exchange (JSE)', 
        country: 'ZA',
        assets: ['stocks', 'bonds'],
        description: 'Africa\'s largest stock exchange'
    },
    { 
        id: 'zse', 
        name: 'Zimbabwe Stock Exchange (ZSE)', 
        country: 'ZW',
        assets: ['stocks'],
        description: 'Zimbabwe\'s primary stock exchange'
    },
    { 
        id: 'vfex', 
        name: 'Victoria Falls Stock Exchange (VFEX)', 
        country: 'ZW',
        assets: ['stocks', 'forex'],
        description: 'Zimbabwe\'s international exchange'
    },
];

const brokers = [
    { 
        id: 1, 
        name: 'Interactive Brokers', 
        country: 'US',
        type: 'Global Broker',
        markets: ['nyse', 'nasdaq', 'lse', 'tse', 'hkex', 'jse'],
        assets: ['stocks', 'bonds', 'forex', 'crypto'],
        logo: '🌐',
        description: 'Access global markets from one account'
    },
    { 
        id: 2, 
        name: 'Charles Schwab', 
        country: 'US',
        type: 'Full-Service Broker',
        markets: ['nyse', 'nasdaq'],
        assets: ['stocks', 'bonds'],
        logo: '💼',
        description: 'Full-service brokerage with research'
    },
    { 
        id: 3, 
        name: 'Fidelity Investments', 
        country: 'US',
        type: 'Investment Manager',
        markets: ['nyse', 'nasdaq'],
        assets: ['stocks', 'bonds', 'realestate'],
        logo: '🏦',
        description: 'Comprehensive investment services'
    },
    { 
        id: 4, 
        name: 'Standard Bank Securities', 
        country: 'ZA',
        type: 'Full-Service Broker',
        markets: ['jse'],
        assets: ['stocks', 'bonds'],
        logo: '🇿🇦',
        description: 'Leading South African broker'
    },
    { 
        id: 5, 
        name: 'eToro', 
        country: 'IL',
        type: 'Social Trading',
        markets: ['nasdaq', 'lse'],
        assets: ['stocks', 'crypto'],
        logo: '👥',
        description: 'Social trading and copy trading'
    },
    { 
        id: 6, 
        name: 'Saxo Bank', 
        country: 'DK',
        type: 'Global Broker',
        markets: ['lse', 'tse', 'hkex'],
        assets: ['stocks', 'forex', 'bonds'],
        logo: '🇩🇰',
        description: 'European global market access'
    },
];

// Computed properties
const filteredMarkets = computed(() => {
    return financialMarkets.filter(market => 
        market.country === selectedCountry.value && 
        market.assets.includes(selectedAssetType.value)
    );
});

const filteredBrokers = computed(() => {
    return brokers.filter(broker => 
        broker.country === selectedCountry.value &&
        broker.assets.includes(selectedAssetType.value) &&
        broker.markets.includes(selectedMarket.value)
    );
});

const getAccountTypeTitle = computed(() => {
    switch (props.accountType) {
        case 'stockbrokers': return 'Stock Broker Account';
        case 'cryptoexchanges': return 'Cryptocurrency Exchange Account';
        case 'investmentplatforms': return 'Investment Platform Account';
        default: return 'Trading Account';
    }
});

const getAccountTypeIcon = computed(() => {
    switch (props.accountType) {
        case 'stockbrokers': return BarChart3;
        case 'cryptoexchanges': return DollarSign;
        case 'investmentplatforms': return Building2;
        default: return Globe;
    }
});

// Methods
const selectCountry = (country: string) => {
    selectedCountry.value = country;
    currentStep.value = 'asset';
};

const selectAssetType = (assetType: string) => {
    selectedAssetType.value = assetType;
    currentStep.value = 'market';
};

const selectMarket = (market: string) => {
    selectedMarket.value = market;
    currentStep.value = 'broker';
};

const selectBroker = (broker: any) => {
    selectedBroker.value = broker;
    currentStep.value = 'action';
};

const selectAction = (action: 'create' | 'link') => {
    actionType.value = action;
    if (action === 'create') {
        // Redirect to broker's platform
        window.open(selectedBroker.value.registration_url || '#', '_blank');
        currentStep.value = 'success';
    } else {
        currentStep.value = 'details';
    }
};

const proceedToConsent = () => {
    if (!accountDetails.value.account_holder_name || !accountDetails.value.trading_account_number) {
        alert('Please fill in all required fields');
        return;
    }
    currentStep.value = 'consent';
};

const linkAccount = async () => {
    if (!accountDetails.value.consent_agreed) {
        alert('Please agree to the consent form to proceed');
        return;
    }
    
    try {
        const response = await fetch('/paneta/connect-trading-account/link', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                broker_id: selectedBroker.value.id,
                account_holder_name: accountDetails.value.account_holder_name,
                trading_account_number: accountDetails.value.trading_account_number,
                country: selectedCountry.value,
                asset_type: selectedAssetType.value,
                market: selectedMarket.value,
                consent_agreed: accountDetails.value.consent_agreed,
            }),
        });

        if (response.ok) {
            currentStep.value = 'success';
        } else {
            const error = await response.json().catch(() => ({}));
            alert(error.message || 'Error linking account. Please try again.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error linking account. Please try again.');
    }
};

const goBack = () => {
    const steps = ['country', 'asset', 'market', 'broker', 'action', 'details', 'consent', 'success'];
    const currentIndex = steps.indexOf(currentStep.value);
    if (currentIndex > 0) {
        currentStep.value = steps[currentIndex - 1] as any;
    }
};

const resetFlow = () => {
    currentStep.value = 'country';
    selectedCountry.value = '';
    selectedAssetType.value = '';
    selectedMarket.value = '';
    selectedBroker.value = null;
    actionType.value = null;
    accountDetails.value = {
        account_holder_name: '',
        trading_account_number: '',
        consent_agreed: false,
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Wealth & Investments', href: '/paneta/wealth' },
    { title: 'Connect Trading Account' },
];
</script>

<template>
    <Head :title="`Connect ${getAccountTypeTitle} - PANÉTA`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl mx-auto p-6">
            <!-- Progress Indicator -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                        <div 
                            v-for="(step, index) in ['Country', 'Asset Type', 'Market', 'Broker', 'Action']"
                            :key="step"
                            class="flex items-center"
                        >
                            <div 
                                class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium"
                                :class="{
                                    'bg-blue-600 text-white': ['country', 'asset', 'market', 'broker', 'action', 'details', 'consent', 'success'].indexOf(currentStep) >= index,
                                    'bg-gray-200 text-gray-600': ['country', 'asset', 'market', 'broker', 'action', 'details', 'consent', 'success'].indexOf(currentStep) < index
                                }"
                            >
                                <CheckCircle v-if="['country', 'asset', 'market', 'broker', 'action', 'details', 'consent', 'success'].indexOf(currentStep) > index" class="w-4 h-4" />
                                <span v-else>{{ index + 1 }}</span>
                            </div>
                            <span 
                                class="ml-2 text-sm font-medium hidden sm:block"
                                :class="{
                                    'text-blue-600': ['country', 'asset', 'market', 'broker', 'action', 'details', 'consent', 'success'].indexOf(currentStep) >= index,
                                    'text-gray-500': ['country', 'asset', 'market', 'broker', 'action', 'details', 'consent', 'success'].indexOf(currentStep) < index
                                }"
                            >
                                {{ step }}
                            </span>
                            <div v-if="index < 4" class="w-8 h-0.5 bg-gray-300 mx-2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 1: Country Selection -->
            <Card v-if="currentStep === 'country'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2">
                        <Globe class="h-6 w-6" />
                        Select Your Country
                    </CardTitle>
                    <CardDescription>
                        Choose the country where you want to open or link your trading account
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <button
                            v-for="country in countries"
                            :key="country.code"
                            @click="selectCountry(country.code)"
                            class="p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors text-center"
                        >
                            <div class="text-2xl mb-2">{{ country.flag }}</div>
                            <div class="font-medium text-sm">{{ country.name }}</div>
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 2: Asset Type Selection -->
            <Card v-if="currentStep === 'asset'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2">
                        <component :is="getAccountTypeIcon" class="h-6 w-6" />
                        Select Asset Type
                    </CardTitle>
                    <CardDescription>
                        Choose the type of assets you want to trade or invest in
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Debug Info (remove in production) -->
                    <div class="mb-4 p-3 bg-gray-100 rounded text-xs">
                        <strong>Debug Info:</strong><br>
                        Country: {{ selectedCountry }}<br>
                        Account Type: {{ props.accountType }}
                    </div>
                    
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="asset in assetTypes"
                            :key="asset.id"
                            @click="selectAssetType(asset.id)"
                            class="p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors cursor-pointer"
                        >
                            <div class="flex items-center gap-3 mb-2">
                                <component :is="asset.icon" class="h-6 w-6" :class="`text-${asset.color}-600`" />
                                <h3 class="font-semibold">{{ asset.name }}</h3>
                            </div>
                            <p class="text-sm text-gray-600">{{ asset.description }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 3: Financial Market Selection -->
            <Card v-if="currentStep === 'market'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2">
                        <BarChart3 class="h-6 w-6" />
                        Select Financial Market
                    </CardTitle>
                    <CardDescription>
                        Choose the financial market where you want to trade
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Debug Info (remove in production) -->
                    <div class="mb-4 p-3 bg-gray-100 rounded text-xs">
                        <strong>Debug Info:</strong><br>
                        Country: {{ selectedCountry }}<br>
                        Asset Type: {{ selectedAssetType }}<br>
                        Available Markets: {{ filteredMarkets.length }}
                    </div>
                    
                    <div v-if="filteredMarkets.length === 0" class="text-center py-8">
                        <BarChart3 class="h-12 w-12 mx-auto text-muted-foreground mb-3" />
                        <p class="text-muted-foreground mb-2">No markets available for this combination</p>
                        <p class="text-sm text-muted-foreground mb-4">
                            Try selecting a different asset type or go back to change your country
                        </p>
                        <Button variant="outline" @click="goBack">
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Go Back
                        </Button>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="market in filteredMarkets"
                            :key="market.id"
                            @click="selectMarket(market.id)"
                            class="p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors cursor-pointer"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold">{{ market.name }}</h3>
                                    <p class="text-sm text-gray-600">{{ market.description }}</p>
                                </div>
                                <ArrowRight class="h-5 w-5 text-gray-400" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 4: Broker Selection -->
            <Card v-if="currentStep === 'broker'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2">
                        <Building2 class="h-6 w-6" />
                        Select Broker
                    </CardTitle>
                    <CardDescription>
                        Choose from permitted brokers for this financial market
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Debug Info (remove in production) -->
                    <div class="mb-4 p-3 bg-gray-100 rounded text-xs">
                        <strong>Debug Info:</strong><br>
                        Country: {{ selectedCountry }}<br>
                        Asset Type: {{ selectedAssetType }}<br>
                        Market: {{ selectedMarket }}<br>
                        Available Brokers: {{ filteredBrokers.length }}
                    </div>
                    
                    <div v-if="filteredBrokers.length === 0" class="text-center py-8">
                        <Building2 class="h-12 w-12 mx-auto text-muted-foreground mb-3" />
                        <p class="text-muted-foreground mb-2">No brokers available for this combination</p>
                        <p class="text-sm text-muted-foreground mb-4">
                            Try selecting a different financial market or go back to change your selections
                        </p>
                        <Button variant="outline" @click="goBack">
                            <ArrowLeft class="h-4 w-4 mr-2" />
                            Go Back
                        </Button>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="broker in filteredBrokers"
                            :key="broker.id"
                            @click="selectBroker(broker)"
                            class="p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors cursor-pointer"
                        >
                            <div class="flex items-center gap-4">
                                <div class="text-2xl">{{ broker.logo }}</div>
                                <div class="flex-1">
                                    <h3 class="font-semibold">{{ broker.name }}</h3>
                                    <p class="text-sm text-gray-600">{{ broker.description }}</p>
                                    <div class="flex gap-2 mt-2">
                                        <Badge variant="outline">{{ broker.type }}</Badge>
                                        <Badge variant="outline">{{ broker.assets.length }} asset types</Badge>
                                    </div>
                                </div>
                                <ArrowRight class="h-5 w-5 text-gray-400" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 5: Action Selection -->
            <Card v-if="currentStep === 'action'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2">
                        <Target class="h-6 w-6" />
                        Choose Your Action
                    </CardTitle>
                    <CardDescription>
                        How would you like to proceed with {{ selectedBroker.name }}?
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            @click="selectAction('create')"
                            class="p-6 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors cursor-pointer text-center"
                        >
                            <ExternalLink class="h-8 w-8 mx-auto mb-3 text-blue-600" />
                            <h3 class="font-semibold mb-2">Create New Account with Broker</h3>
                            <p class="text-sm text-gray-600">
                                Register directly with {{ selectedBroker.name }} and start trading
                            </p>
                        </div>
                        <div
                            @click="selectAction('link')"
                            class="p-6 border rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors cursor-pointer text-center"
                        >
                            <Key class="h-8 w-8 mx-auto mb-3 text-green-600" />
                            <h3 class="font-semibold mb-2">Link Existing Account</h3>
                            <p class="text-sm text-gray-600">
                                Connect your existing {{ selectedBroker.name }} account to PANÉTA
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 6: Account Details -->
            <Card v-if="currentStep === 'details'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2">
                        <User class="h-6 w-6" />
                        Account Details
                    </CardTitle>
                    <CardDescription>
                        Enter your {{ selectedBroker.name }} account information
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4 max-w-md mx-auto">
                        <div>
                            <Label for="account_holder_name">Account Holder Name</Label>
                            <Input
                                id="account_holder_name"
                                v-model="accountDetails.account_holder_name"
                                placeholder="Enter your registered trading name"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <Label for="trading_account_number">Trading Account Number</Label>
                            <Input
                                id="trading_account_number"
                                v-model="accountDetails.trading_account_number"
                                placeholder="Enter your account number"
                                class="mt-1"
                            />
                        </div>
                        <div class="flex gap-3">
                            <Button variant="outline" @click="goBack" class="flex-1">
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Back
                            </Button>
                            <Button @click="proceedToConsent" class="flex-1">
                                Continue
                                <ArrowRight class="h-4 w-4 ml-2" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 7: Consent -->
            <Card v-if="currentStep === 'consent'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2">
                        <Shield class="h-6 w-6" />
                        Terms & Consent
                    </CardTitle>
                    <CardDescription>
                        Review and agree to the terms to connect your account
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold mb-2">Account Connection Consent</h4>
                            <p class="text-sm text-gray-600 mb-3">
                                By connecting your {{ selectedBroker.name }} account, you authorize PANÉTA to:
                            </p>
                            <ul class="text-sm text-gray-600 space-y-1 list-disc list-inside">
                                <li>Access your account balance and portfolio data</li>
                                <li>Retrieve transaction history and holdings</li>
                                <li>Sync data every 15 minutes for real-time updates</li>
                                <li>Use read-only access (no trading capabilities)</li>
                            </ul>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="consent"
                                v-model:checked="accountDetails.consent_agreed"
                            />
                            <Label for="consent" class="text-sm">
                                I agree to the terms and consent to connect my account
                            </Label>
                        </div>

                        <div class="flex gap-3">
                            <Button variant="outline" @click="goBack" class="flex-1">
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Back
                            </Button>
                            <Button 
                                @click="linkAccount" 
                                class="flex-1"
                                :disabled="!accountDetails.consent_agreed"
                            >
                                Connect Account
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 8: Success -->
            <Card v-if="currentStep === 'success'">
                <CardHeader class="text-center">
                    <CardTitle class="flex items-center justify-center gap-2 text-green-600">
                        <CheckCircle class="h-6 w-6" />
                        Account Connected Successfully!
                    </CardTitle>
                    <CardDescription>
                        Your {{ selectedBroker.name }} account has been connected to PANÉTA
                    </CardDescription>
                </CardHeader>
                <CardContent class="text-center">
                    <div class="space-y-4">
                        <div class="bg-green-50 p-4 rounded-lg">
                            <p class="text-sm text-green-800">
                                Your portfolio data will now appear in the Portfolio Management dashboard.
                                Data synchronization will begin automatically.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <Button variant="outline" @click="resetFlow" class="flex-1">
                                Connect Another Account
                            </Button>
                            <Button as-child class="flex-1">
                                <a href="/paneta/wealth">View Portfolio</a>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Navigation -->
            <div class="mt-6 flex justify-between">
                <Button
                    v-if="currentStep !== 'country' && currentStep !== 'success'"
                    variant="outline"
                    @click="goBack"
                >
                    <ArrowLeft class="h-4 w-4 mr-2" />
                    Back
                </Button>
                <div v-else></div>
            </div>
        </div>
    </AppLayout>
</template>
