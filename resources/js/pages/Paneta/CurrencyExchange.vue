<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

const confirmExchange = () => {
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
                <TabsContent value="fx-marketplace" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                FX Marketplace
                            </CardTitle>
                            <CardDescription>
                                Access institutional-grade FX marketplace with competitive rates
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <Globe class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-muted-foreground">
                                    FX Marketplace functionality coming soon
                                </p>
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
    </AppLayout>
</template>
