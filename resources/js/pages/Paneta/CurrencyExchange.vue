<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem, LinkedAccount, Institution } from '@/types';
import { ArrowRightLeft, TrendingUp, Clock, DollarSign, RefreshCw, Plus, Users, Globe, Bell } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface FxQuote {
    id: number;
    provider: string;
    rate: number;
    spread: number;
    fee: number;
    destination_amount: number;
    eta: string;
    expires_at: string;
}

interface P2POffer {
    id: number;
    user: {
        name: string;
        location: string;
        trust_score: number;
        total_trades: number;
    };
    sell_currency: string;
    buy_currency: string;
    rate: number;
    amount: number;
    min_amount: number;
    max_amount: number;
    settlement_methods: string[];
    expires_at: string;
    status: string;
}

const props = defineProps<{
    linkedAccounts: LinkedAccount[];
    fxProviders: Institution[];
    recentQuotes: any[];
    currencies: string[];
    panetaFeePercent: number;
    p2pOffers?: P2POffer[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Currency Exchange' },
];

// Debug logging
console.log('=== P2P OFFERS DEBUG ===');
console.log('p2pOffers exists:', !!props.p2pOffers);
console.log('p2pOffers value:', props.p2pOffers);
console.log('p2pOffers type:', typeof props.p2pOffers);
console.log('Is Array:', Array.isArray(props.p2pOffers));
console.log('Length:', props.p2pOffers?.length);
console.log('First offer:', props.p2pOffers?.[0]);
console.log('Condition check (!p2pOffers || length === 0):', !props.p2pOffers || props.p2pOffers.length === 0);
console.log('========================');

const quotes = ref<FxQuote[]>([]);
const isLoading = ref(false);
const selectedQuote = ref<FxQuote | null>(null);

const offerForm = useForm({
    source_account_id: null as number | null,
    destination_account_id: null as number | null,
    sell_currency: 'USD',
    buy_currency: 'ZWL',
    rate: 0,
    amount: 100,
    min_amount: 10,
    settlement_methods: [] as string[],
    expires_in_days: 7,
});

const form = useForm({
    source_account_id: null as number | null,
    source_currency: 'USD',
    destination_currency: 'ZWL',
    amount: 100,
});

const selectedAccount = computed(() => {
    return props.linkedAccounts.find((a) => a.id === form.source_account_id);
});

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

const formatRate = (rate: number) => {
    return rate.toFixed(6);
};

const getQuotes = async () => {
    if (!form.source_account_id || !form.amount) return;
    
    isLoading.value = true;
    quotes.value = [];
    selectedQuote.value = null;

    try {
        const response = await fetch('/paneta/currency-exchange/quote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                source_account_id: form.source_account_id,
                source_currency: form.source_currency,
                destination_currency: form.destination_currency,
                amount: form.amount,
            }),
        });

        const data = await response.json();
        if (data.success) {
            quotes.value = data.quotes;
        }
    } catch (error) {
        console.error('Failed to get quotes:', error);
    } finally {
        isLoading.value = false;
    }
};

const selectQuote = (quote: FxQuote) => {
    selectedQuote.value = quote;
};

const confirmQuoteExchange = () => {
    if (!selectedQuote.value) return;
    alert('FX instruction generated. Note: PANÉTA does not execute trades - this generates a signed instruction for external execution.');
};

const toggleSettlementMethod = (method: string) => {
    const index = offerForm.settlement_methods.indexOf(method);
    if (index > -1) {
        offerForm.settlement_methods.splice(index, 1);
    } else {
        offerForm.settlement_methods.push(method);
    }
};

const createOffer = () => {
    offerForm.post('/paneta/p2p-escrow/offers', {
        onSuccess: () => {
            offerForm.reset();
        },
    });
};

const formatTimeAgo = (date: string) => {
    const now = new Date();
    const then = new Date(date);
    const diff = Math.floor((now.getTime() - then.getTime()) / 60000);
    if (diff < 60) return `${diff} mins ago`;
    if (diff < 1440) return `${Math.floor(diff / 60)} hours ago`;
    return `${Math.floor(diff / 1440)} days ago`;
};

// FX Intent form state
const showIntentDialog = ref(false);
const showExchangeDialog = ref(false);
const selectedProvider = ref<Institution | null>(null);

const exchangeForm = useForm({
    provider_id: null as number | null,
    source_account_id: null as number | null,
    destination_account_id: null as number | null,
    source_currency: 'USD',
    destination_currency: 'EUR',
    amount: 1000,
    settlement_preference: 'instant',
});

const openIntentDialog = (provider: Institution) => {
    selectedProvider.value = provider;
    exchangeForm.provider_id = provider.id;
    showIntentDialog.value = true;
};

const closeIntentDialog = () => {
    showIntentDialog.value = false;
};

const proceedToConfirmation = () => {
    if (!exchangeForm.source_account_id || !exchangeForm.destination_account_id || !exchangeForm.amount) {
        alert('Please fill in all required fields');
        return;
    }
    showIntentDialog.value = false;
    showExchangeDialog.value = true;
};

const closeExchangeDialog = () => {
    showExchangeDialog.value = false;
};

const goBackToIntent = () => {
    showExchangeDialog.value = false;
    showIntentDialog.value = true;
};

const confirmExchange = () => {
    exchangeForm.post('/paneta/fx-marketplace/execute', {
        onSuccess: () => {
            showExchangeDialog.value = false;
            showIntentDialog.value = false;
            selectedProvider.value = null;
            exchangeForm.reset();
        },
        onError: (errors) => {
            console.error('Exchange failed:', errors);
        },
    });
};

const calculateExchangeRate = computed(() => {
    // Mock exchange rate calculation
    const rates: Record<string, number> = {
        'USD-EUR': 0.85,
        'EUR-USD': 1.18,
        'USD-GBP': 0.79,
        'GBP-USD': 1.27,
    };
    const key = `${exchangeForm.source_currency}-${exchangeForm.destination_currency}`;
    return rates[key] || 1;
});

const calculateDestinationAmount = computed(() => {
    return (exchangeForm.amount * calculateExchangeRate.value).toFixed(2);
});

const calculateFees = computed(() => {
    const panetaFee = exchangeForm.amount * 0.0099;
    const providerFee = exchangeForm.amount * 0.002;
    return {
        paneta: panetaFee.toFixed(2),
        provider: providerFee.toFixed(2),
        total: (panetaFee + providerFee).toFixed(2),
        netAmount: (exchangeForm.amount + panetaFee + providerFee).toFixed(2),
    };
});
</script>

<template>
    <Head title="Currency Exchange - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Currency Exchange</h1>
                    <p class="text-muted-foreground">
                        Create offers, trade peer-to-peer, access FX marketplace, and set smart alerts
                    </p>
                </div>
                <Badge variant="outline" class="text-orange-600 border-orange-600">
                    Zero-Custody Mode
                </Badge>
            </div>

            <!-- Horizontal Tab Navigation -->
            <Tabs default-value="create-offer" class="w-full">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="create-offer" class="flex items-center gap-2">
                        <Plus class="h-4 w-4" />
                        Create Offer
                    </TabsTrigger>
                    <TabsTrigger value="peer-to-peer" class="flex items-center gap-2">
                        <Users class="h-4 w-4" />
                        Peer-to-Peer
                    </TabsTrigger>
                    <TabsTrigger value="fx-marketplace" class="flex items-center gap-2">
                        <Globe class="h-4 w-4" />
                        FX Marketplace
                    </TabsTrigger>
                    <TabsTrigger value="smart-alerts" class="flex items-center gap-2">
                        <Bell class="h-4 w-4" />
                        Smart Alerts
                    </TabsTrigger>
                </TabsList>

                <!-- Create Offer Tab -->
                <TabsContent value="create-offer" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Plus class="h-5 w-5" />
                                Create P2P Exchange Offer
                            </CardTitle>
                            <CardDescription>
                                Create an offer that will appear on the Peer-to-Peer marketplace for other users to accept
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="createOffer" class="space-y-6">
                                <div class="grid gap-6 md:grid-cols-2">
                                    <!-- Source Account -->
                                    <div class="space-y-2">
                                        <Label>Source Account (You're Selling)</Label>
                                        <Select v-model="offerForm.source_account_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select source account" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem 
                                                    v-for="account in linkedAccounts" 
                                                    :key="account.id" 
                                                    :value="account.id"
                                                >
                                                    {{ account.institution?.name }} - {{ account.currency }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Destination Account -->
                                    <div class="space-y-2">
                                        <Label>Destination Account (You're Buying)</Label>
                                        <Select v-model="offerForm.destination_account_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select destination account" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem 
                                                    v-for="account in linkedAccounts" 
                                                    :key="account.id" 
                                                    :value="account.id"
                                                >
                                                    {{ account.institution?.name }} - {{ account.currency }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Sell Currency -->
                                    <div class="space-y-2">
                                        <Label>Selling Currency</Label>
                                        <Select v-model="offerForm.sell_currency">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem 
                                                    v-for="currency in currencies" 
                                                    :key="currency" 
                                                    :value="currency"
                                                >
                                                    {{ currency }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Buy Currency -->
                                    <div class="space-y-2">
                                        <Label>Buying Currency</Label>
                                        <Select v-model="offerForm.buy_currency">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem 
                                                    v-for="currency in currencies" 
                                                    :key="currency" 
                                                    :value="currency"
                                                >
                                                    {{ currency }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Exchange Rate -->
                                    <div class="space-y-2">
                                        <Label>Exchange Rate</Label>
                                        <Input 
                                            v-model.number="offerForm.rate" 
                                            type="number" 
                                            step="0.01"
                                            min="0.01"
                                            placeholder="e.g., 2456.8"
                                        />
                                        <p class="text-xs text-muted-foreground">
                                            1 {{ offerForm.sell_currency }} = {{ offerForm.rate }} {{ offerForm.buy_currency }}
                                        </p>
                                    </div>

                                    <!-- Amount -->
                                    <div class="space-y-2">
                                        <Label>Amount to Exchange</Label>
                                        <Input 
                                            v-model.number="offerForm.amount" 
                                            type="number" 
                                            step="0.01"
                                            min="0.01"
                                            placeholder="Enter amount"
                                        />
                                    </div>

                                    <!-- Min Amount -->
                                    <div class="space-y-2">
                                        <Label>Minimum Amount</Label>
                                        <Input 
                                            v-model.number="offerForm.min_amount" 
                                            type="number" 
                                            step="0.01"
                                            min="0.01"
                                            placeholder="Minimum trade amount"
                                        />
                                    </div>

                                    <!-- Expiry Days -->
                                    <div class="space-y-2">
                                        <Label>Offer Expires In (Days)</Label>
                                        <Input 
                                            v-model.number="offerForm.expires_in_days" 
                                            type="number" 
                                            min="1"
                                            max="90"
                                            placeholder="1-90 days"
                                        />
                                        <p class="text-xs text-muted-foreground">Maximum 90 days</p>
                                    </div>
                                </div>

                                <!-- Preferred Settlement Methods -->
                                <div class="space-y-3">
                                    <Label>Preferred Settlement Methods</Label>
                                    <div class="flex flex-wrap gap-3">
                                        <div 
                                            @click="toggleSettlementMethod('bank')"
                                            :class="[
                                                'flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer transition-colors',
                                                offerForm.settlement_methods.includes('bank') 
                                                    ? 'bg-primary text-primary-foreground border-primary' 
                                                    : 'hover:border-primary/50'
                                            ]"
                                        >
                                            <div :class="[
                                                'h-4 w-4 rounded border-2 flex items-center justify-center',
                                                offerForm.settlement_methods.includes('bank') ? 'bg-white border-white' : 'border-gray-400'
                                            ]">
                                                <svg v-if="offerForm.settlement_methods.includes('bank')" class="h-3 w-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </div>
                                            <span class="font-medium">Bank Transfer</span>
                                        </div>

                                        <div 
                                            @click="toggleSettlementMethod('mobile_wallet')"
                                            :class="[
                                                'flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer transition-colors',
                                                offerForm.settlement_methods.includes('mobile_wallet') 
                                                    ? 'bg-primary text-primary-foreground border-primary' 
                                                    : 'hover:border-primary/50'
                                            ]"
                                        >
                                            <div :class="[
                                                'h-4 w-4 rounded border-2 flex items-center justify-center',
                                                offerForm.settlement_methods.includes('mobile_wallet') ? 'bg-white border-white' : 'border-gray-400'
                                            ]">
                                                <svg v-if="offerForm.settlement_methods.includes('mobile_wallet')" class="h-3 w-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </div>
                                            <span class="font-medium">Mobile Money</span>
                                        </div>

                                        <div 
                                            @click="toggleSettlementMethod('card')"
                                            :class="[
                                                'flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer transition-colors',
                                                offerForm.settlement_methods.includes('card') 
                                                    ? 'bg-primary text-primary-foreground border-primary' 
                                                    : 'hover:border-primary/50'
                                            ]"
                                        >
                                            <div :class="[
                                                'h-4 w-4 rounded border-2 flex items-center justify-center',
                                                offerForm.settlement_methods.includes('card') ? 'bg-white border-white' : 'border-gray-400'
                                            ]">
                                                <svg v-if="offerForm.settlement_methods.includes('card')" class="h-3 w-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </div>
                                            <span class="font-medium">Cash</span>
                                        </div>
                                    </div>
                                </div>

                                <Button 
                                    type="submit" 
                                    class="w-full"
                                    :disabled="offerForm.processing || !offerForm.source_account_id || !offerForm.destination_account_id || offerForm.settlement_methods.length === 0"
                                >
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create Offer
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Peer-to-Peer Tab -->
                <TabsContent value="peer-to-peer" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Users class="h-6 w-6 text-primary" />
                            <h2 class="text-2xl font-bold">Live P2P Offers</h2>
                            <Badge variant="default" class="bg-green-600">{{ p2pOffers?.length || 0 }} Online</Badge>
                        </div>
                        <Button variant="outline" size="sm">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Refresh Rates
                        </Button>
                    </div>

                    <div v-if="!p2pOffers || p2pOffers.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                        <Users class="mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-lg font-medium">No active offers at the moment</p>
                        <p class="text-sm text-muted-foreground">Create an offer in the "Create Offer" tab to get started</p>
                    </div>

                    <div v-else class="space-y-3">
                        <Card 
                            v-for="offer in p2pOffers" 
                            :key="offer.id"
                            class="hover:shadow-lg transition-all border-l-4 border-l-transparent hover:border-l-primary"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between gap-8">
                                    <!-- User Info -->
                                    <div class="flex items-start gap-3 flex-1">
                                        <!-- Avatar with online indicator -->
                                        <div class="relative">
                                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-700 text-white font-bold text-xl shadow-md">
                                                {{ offer.user.name.split(' ').map(n => n[0]).join('').toUpperCase() }}
                                            </div>
                                            <div class="absolute bottom-0 right-0 h-4 w-4 bg-green-500 border-2 border-white rounded-full"></div>
                                        </div>

                                        <div class="flex-1 space-y-2">
                                            <!-- Name and verification -->
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold text-base">{{ offer.user.name }}</h3>
                                                <svg class="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-yellow-500 font-semibold text-sm">★ {{ offer.user.trust_score }}</span>
                                            </div>

                                            <!-- Location and stats -->
                                            <div class="flex items-center gap-3 text-xs">
                                                <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                                                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ offer.user.location }}
                                                </Badge>
                                                <span class="text-muted-foreground">Trust Score: {{ (offer.user.trust_score * 20).toFixed(0) }}%</span>
                                                <span class="text-muted-foreground">{{ offer.user.total_trades }} trades</span>
                                            </div>

                                            <!-- Description -->
                                            <p class="text-sm text-muted-foreground leading-relaxed">
                                                Premium Zimbabwean trader with instant processing. Bank-verified rates. Available 24/7.
                                            </p>

                                            <!-- Settlement methods -->
                                            <div v-if="offer.settlement_methods && offer.settlement_methods.length > 0" class="flex gap-2">
                                                <Badge 
                                                    v-for="method in offer.settlement_methods" 
                                                    :key="method"
                                                    variant="secondary"
                                                    class="text-xs bg-gray-100 text-gray-700 hover:bg-gray-200"
                                                >
                                                    {{ method === 'bank' ? 'Bank Transfer' : method === 'mobile_wallet' ? 'Mobile Money' : method === 'card' ? 'Cash' : method }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Exchange Rate & Actions -->
                                    <div class="flex flex-col items-end gap-3 min-w-[300px]">
                                        <!-- Rate card -->
                                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-4 w-full border border-blue-100">
                                            <div class="text-right space-y-1">
                                                <div class="text-2xl font-bold text-blue-900">
                                                    1 {{ offer.sell_currency }} = {{ offer.rate.toFixed(4) }} {{ offer.buy_currency }}
                                                </div>
                                                <div class="text-xs text-blue-700">
                                                    Min: {{ offer.min_amount }} - Max: {{ offer.max_amount.toLocaleString() }}
                                                </div>
                                                <div class="flex items-center justify-end gap-1 text-xs text-orange-600 font-medium">
                                                    <Clock class="h-3 w-3" />
                                                    <span>{{ formatTimeAgo(offer.expires_at) }} escrow</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action buttons -->
                                        <div class="flex flex-col gap-2 w-full">
                                            <Button size="sm" class="w-full bg-blue-600 hover:bg-blue-700 text-white shadow-md">
                                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                                </svg>
                                                Start Exchange
                                            </Button>
                                            <Button variant="ghost" size="sm" class="w-full text-blue-600 hover:text-blue-700 hover:bg-blue-50">
                                                <ArrowRightLeft class="mr-2 h-4 w-4" />
                                                Negotiate Rate
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- FX Marketplace Tab -->
                <TabsContent value="fx-marketplace" class="space-y-4">
                    <!-- Header matching P2P format -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Globe class="h-6 w-6 text-primary" />
                            <h2 class="text-2xl font-bold">FX Marketplace</h2>
                            <Badge variant="default" class="bg-green-600">{{ fxProviders?.length || 0 }} Providers</Badge>
                        </div>
                        <Button variant="outline" size="sm">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Refresh Rates
                        </Button>
                    </div>

                    <!-- FX Provider Cards -->
                    <div class="space-y-3">
                        <Card 
                            v-for="provider in fxProviders" 
                            :key="provider.id"
                            class="hover:shadow-lg transition-all"
                        >
                            <CardContent class="p-5">
                                <div class="flex items-start justify-between gap-6">
                                    <!-- Provider Info -->
                                    <div class="flex items-start gap-4 flex-1">
                                        <!-- Logo -->
                                        <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-purple-700 text-white font-bold text-2xl shadow-md">
                                            {{ provider.name.split(' ').map(n => n[0]).join('').substring(0, 3).toUpperCase() }}
                                        </div>

                                        <div class="flex-1 space-y-2">
                                            <!-- Name and verification -->
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold text-lg">{{ provider.name }}</h3>
                                                <svg class="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="text-yellow-500 font-semibold text-sm">★ 4.9</span>
                                            </div>

                                            <!-- Type and Location -->
                                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                                <span class="font-medium">{{ provider.type || 'Financial Institution' }}</span>
                                                <span>•</span>
                                                <span>{{ provider.country || 'London, UK' }}</span>
                                            </div>

                                            <p class="text-sm text-muted-foreground">
                                                Daily Volume: $500M+
                                            </p>

                                            <!-- Licenses -->
                                            <div class="flex gap-2 mt-2">
                                                <Badge variant="outline" class="text-xs bg-blue-50 text-blue-700 border-blue-200">FCA</Badge>
                                                <Badge variant="outline" class="text-xs bg-blue-50 text-blue-700 border-blue-200">PRA</Badge>
                                            </div>

                                            <!-- FX Services & Features -->
                                            <div class="mt-3 space-y-1">
                                                <p class="text-xs font-semibold text-muted-foreground">FX Services & Features:</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <Badge variant="secondary" class="text-xs">Large Volume</Badge>
                                                    <Badge variant="secondary" class="text-xs">Corporate</Badge>
                                                    <Badge variant="secondary" class="text-xs">Instant Settlement</Badge>
                                                </div>
                                            </div>

                                            <!-- Contact Information -->
                                            <div class="mt-2 text-xs text-muted-foreground">
                                                <p><span class="font-semibold">Contact:</span> support@{{ provider.name.toLowerCase().replace(/\s+/g, '') }}.com</p>
                                                <p><span class="font-semibold">Member since:</span> 2020</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Exchange Rate & Actions -->
                                    <div class="flex flex-col items-end gap-3 min-w-[280px]">
                                        <!-- Rate Info -->
                                        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-lg p-4 w-full border border-purple-100">
                                            <div class="text-right space-y-1">
                                                <div class="text-sm text-muted-foreground">Exchange Rate</div>
                                                <div class="text-2xl font-bold text-purple-900">0.8534</div>
                                                <div class="text-xs text-purple-700">Processing Fee: 0.15%</div>
                                                <div class="text-xs text-purple-700">Processing Time: 15 min</div>
                                            </div>
                                        </div>

                                        <!-- Start Exchange Button -->
                                        <Button size="sm" class="w-full bg-purple-600 hover:bg-purple-700 text-white shadow-md" @click="openIntentDialog(provider)">
                                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                            </svg>
                                            Start Exchange
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Disclaimer Footer -->
                    <Card class="bg-amber-50 border-amber-200">
                        <CardContent class="p-4">
                            <div class="flex gap-3">
                                <svg class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <div class="text-xs text-amber-900 space-y-1">
                                    <p class="font-semibold">Important Disclaimer</p>
                                    <p>PANÉTA operates as a zero-custody platform and does not hold, transfer, or execute foreign exchange transactions. All FX operations are conducted directly between users and licensed FX providers. PANÉTA generates cryptographically signed instructions that users present to their chosen providers for execution. Users are responsible for verifying provider credentials, understanding exchange terms, and ensuring compliance with applicable regulations. Exchange rates, fees, and processing times are indicative and subject to change. Past performance does not guarantee future results.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Smart Alerts Tab -->
                <TabsContent value="smart-alerts" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Bell class="h-5 w-5" />
                                Smart Alerts
                            </CardTitle>
                            <CardDescription>
                                Set up alerts for favorable exchange rates and market movements
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <Bell class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-muted-foreground">
                                    Smart Alerts functionality coming soon
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>

        <!-- FX Intent Dialog (Stage 1) -->
        <Dialog :open="showIntentDialog" @update:open="showIntentDialog = $event">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="text-2xl">Define Your Exchange Intent</DialogTitle>
                    <DialogDescription>
                        Enter your exchange details to proceed with {{ selectedProvider?.name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-6 py-4">
                    <!-- Source Account -->
                    <div class="space-y-2">
                        <Label for="source_account">Source Account (You're Sending From)</Label>
                        <Select v-model="exchangeForm.source_account_id">
                            <SelectTrigger id="source_account">
                                <SelectValue placeholder="Select source account" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="account in linkedAccounts" :key="account.id" :value="account.id.toString()">
                                    {{ account.institution?.name }} - ****{{ account.id }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Destination Account -->
                    <div class="space-y-2">
                        <Label for="destination_account">Destination Account (You're Receiving To)</Label>
                        <Select v-model="exchangeForm.destination_account_id">
                            <SelectTrigger id="destination_account">
                                <SelectValue placeholder="Select destination account" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="account in linkedAccounts" :key="account.id" :value="account.id.toString()">
                                    {{ account.institution?.name }} - ****{{ account.id }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Currency Pair -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="source_currency">Source Currency</Label>
                            <Select v-model="exchangeForm.source_currency">
                                <SelectTrigger id="source_currency">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="currency in currencies" :key="currency" :value="currency">
                                        {{ currency }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label for="destination_currency">Destination Currency</Label>
                            <Select v-model="exchangeForm.destination_currency">
                                <SelectTrigger id="destination_currency">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="currency in currencies" :key="currency" :value="currency">
                                        {{ currency }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-2">
                        <Label for="amount">Amount to Exchange</Label>
                        <Input 
                            id="amount" 
                            type="number" 
                            v-model.number="exchangeForm.amount" 
                            placeholder="Enter amount"
                            min="1"
                        />
                        <p class="text-sm text-muted-foreground">
                            You will receive approximately {{ calculateDestinationAmount }} {{ exchangeForm.destination_currency }}
                        </p>
                    </div>

                    <!-- Settlement Preference -->
                    <div class="space-y-2">
                        <Label for="settlement">Settlement Preference</Label>
                        <Select v-model="exchangeForm.settlement_preference">
                            <SelectTrigger id="settlement">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="instant">Instant Settlement</SelectItem>
                                <SelectItem value="standard">Standard (1-2 days)</SelectItem>
                                <SelectItem value="economy">Economy (3-5 days)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <Button variant="outline" class="flex-1" @click="closeIntentDialog">
                        Cancel
                    </Button>
                    <Button class="flex-1 bg-purple-600 hover:bg-purple-700" @click="proceedToConfirmation">
                        Review & Confirm
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Exchange Confirmation Dialog -->
        <Dialog :open="showExchangeDialog" @update:open="showExchangeDialog = $event">
            <DialogContent class="max-w-7xl max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 -m-6 mb-6 p-6 text-white">
                    <DialogHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20">
                                <ArrowRightLeft class="h-6 w-6" />
                            </div>
                            <div>
                                <DialogTitle class="text-2xl text-white">Exchange Market Transaction Confirmation</DialogTitle>
                                <DialogDescription class="text-purple-100">
                                    Review and confirm your currency exchange with premium global provider
                                </DialogDescription>
                            </div>
                            <Badge class="ml-auto bg-white/20 text-white border-white/30">Global Exchange</Badge>
                        </div>
                    </DialogHeader>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Exchange Provider Details -->
                    <Card class="border-2 border-blue-200">
                        <CardHeader class="bg-blue-50">
                            <CardTitle class="flex items-center gap-2 text-blue-900">
                                <Globe class="h-5 w-5" />
                                Exchange Provider Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-6 space-y-4" v-if="selectedProvider">
                            <!-- Provider Info -->
                            <div class="flex items-start gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-purple-700 text-white font-bold text-xl">
                                    {{ selectedProvider.name.split(' ').map(n => n[0]).join('').substring(0, 1).toUpperCase() }}
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-lg">{{ selectedProvider.name }}</h3>
                                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                        <svg class="h-4 w-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ selectedProvider.country || 'South Africa' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-yellow-500 text-sm">★ 4.8</span>
                                        <span class="text-xs text-muted-foreground">Verified</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Processing & Security -->
                            <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                                <div>
                                    <p class="text-xs text-muted-foreground">Processing Time</p>
                                    <p class="font-semibold">5 min</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">Security Level</p>
                                    <p class="font-semibold">Enterprise</p>
                                </div>
                            </div>

                            <!-- Licenses & Certifications -->
                            <div>
                                <p class="text-xs font-semibold text-muted-foreground mb-2">Licenses & Certifications</p>
                                <div class="flex gap-2">
                                    <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">SARB</Badge>
                                    <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">FSCA</Badge>
                                </div>
                            </div>

                            <!-- Provider Features -->
                            <div>
                                <p class="text-xs font-semibold text-muted-foreground mb-2">Provider Features</p>
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary" class="text-xs bg-green-100 text-green-700">Mobile Integration</Badge>
                                    <Badge variant="secondary" class="text-xs bg-green-100 text-green-700">Real-time Rates</Badge>
                                    <Badge variant="secondary" class="text-xs bg-green-100 text-green-700">Local Expertise</Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Transaction Summary -->
                    <Card class="border-2 border-green-200">
                        <CardHeader class="bg-green-50">
                            <CardTitle class="flex items-center gap-2 text-green-900">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Transaction Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-6 space-y-4">
                            <!-- Exchange Amounts -->
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg p-4 border border-green-200">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <p class="text-xs text-muted-foreground">You Send</p>
                                        <p class="text-2xl font-bold text-green-700">{{ exchangeForm.amount.toLocaleString() }} {{ exchangeForm.source_currency }}</p>
                                    </div>
                                    <ArrowRightLeft class="h-6 w-6 text-green-600" />
                                    <div class="text-right">
                                        <p class="text-xs text-muted-foreground">You Receive</p>
                                        <p class="text-2xl font-bold text-green-700">{{ calculateDestinationAmount }} {{ exchangeForm.destination_currency }}</p>
                                    </div>
                                </div>
                                <div class="text-center pt-3 border-t border-green-200">
                                    <p class="text-xs text-muted-foreground">Exchange Rate</p>
                                    <p class="font-semibold text-green-900">1 {{ exchangeForm.source_currency }} = {{ calculateExchangeRate }} {{ exchangeForm.destination_currency }}</p>
                                </div>
                            </div>

                            <!-- Fee Breakdown -->
                            <div class="space-y-2">
                                <p class="text-sm font-semibold">Fee Breakdown</p>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">PANÉTA Processing Fee (0.99%)</span>
                                        <span class="font-medium">{{ calculateFees.paneta }} {{ exchangeForm.source_currency }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Provider Fee (0.20%)</span>
                                        <span class="font-medium">{{ calculateFees.provider }} {{ exchangeForm.source_currency }}</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t font-semibold">
                                        <span>Total Fees</span>
                                        <span class="text-red-600">{{ calculateFees.total }} {{ exchangeForm.source_currency }}</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t font-bold text-base">
                                        <span>Net Amount Debited</span>
                                        <span class="text-blue-600">{{ calculateFees.netAmount }} {{ exchangeForm.source_currency }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Settlement Account Details -->
                <Card class="border-2 border-orange-200">
                    <CardHeader class="bg-orange-50">
                        <CardTitle class="flex items-center gap-2 text-orange-900">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"></path>
                                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                            </svg>
                            Settlement Account Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <svg class="h-8 w-8 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <p class="font-semibold" v-if="selectedProvider">{{ selectedProvider.name }} Settlement Account</p>
                                    <p class="text-sm text-muted-foreground">AfriCurrency Hub - ****7756</p>
                                    <p class="text-xs text-muted-foreground">Exchange Provider Account</p>
                                </div>
                            </div>
                            <Badge class="bg-orange-100 text-orange-700 border-orange-300">Provider Account</Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Security Guarantee -->
                <Card class="bg-green-50 border-2 border-green-200">
                    <CardContent class="p-4">
                        <div class="flex gap-3">
                            <svg class="h-6 w-6 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm">
                                <p class="font-semibold text-green-900">PANÉTA Security Guarantee</p>
                                <p class="text-green-800">This Exchange Market transaction is protected by PANÉTA's advanced security protocols, escrow system, and international compliance standards. Your funds are secured until successful completion.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Action Buttons -->
                <div class="flex gap-4 pt-4">
                    <Button variant="outline" class="flex-1" @click="closeExchangeDialog">
                        Cancel Transaction
                    </Button>
                    <Button class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white" @click="confirmExchange">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirm & Execute Exchange
                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
