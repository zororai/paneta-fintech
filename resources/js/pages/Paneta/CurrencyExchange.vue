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

const props = defineProps<{
    linkedAccounts: LinkedAccount[];
    fxProviders: Institution[];
    recentQuotes: any[];
    currencies: string[];
    panetaFeePercent: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Currency Exchange' },
];

const quotes = ref<FxQuote[]>([]);
const isLoading = ref(false);
const selectedQuote = ref<FxQuote | null>(null);

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

                    <div class="grid gap-6 lg:grid-cols-3">
                <!-- Convert Currency Widget -->
                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ArrowRightLeft class="h-5 w-5" />
                            Convert Currency
                        </CardTitle>
                        <CardDescription>
                            Get quotes from multiple FX providers
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Source Account -->
                        <div class="space-y-2">
                            <Label>Source Account</Label>
                            <Select v-model="form.source_account_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select account" />
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

                        <!-- Amount -->
                        <div class="space-y-2">
                            <Label>Amount</Label>
                            <Input 
                                v-model.number="form.amount" 
                                type="number" 
                                min="1"
                                placeholder="Enter amount"
                            />
                        </div>

                        <!-- Source Currency -->
                        <div class="space-y-2">
                            <Label>From Currency</Label>
                            <Select v-model="form.source_currency">
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

                        <!-- Destination Currency -->
                        <div class="space-y-2">
                            <Label>To Currency</Label>
                            <Select v-model="form.destination_currency">
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

                        <!-- PANÉTA Fee -->
                        <div class="rounded-lg bg-muted p-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">PANÉTA Fee</span>
                                <span>{{ panetaFeePercent }}%</span>
                            </div>
                        </div>

                        <Button 
                            class="w-full" 
                            @click="getQuotes"
                            :disabled="!form.source_account_id || !form.amount || isLoading"
                        >
                            <RefreshCw v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                            <span v-else>Get Quotes</span>
                        </Button>
                    </CardContent>
                </Card>

                <!-- RFQ Comparison Table -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <TrendingUp class="h-5 w-5" />
                            Available Quotes
                        </CardTitle>
                        <CardDescription>
                            Compare rates from FX providers - sorted by best rate
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="quotes.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                            <DollarSign class="mb-4 h-12 w-12 text-muted-foreground" />
                            <p class="text-muted-foreground">
                                Enter conversion details and click "Get Quotes" to compare rates
                            </p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(quote, index) in quotes"
                                :key="quote.id"
                                :class="[
                                    'flex items-center justify-between rounded-lg border p-4 cursor-pointer transition-colors',
                                    selectedQuote?.id === quote.id
                                        ? 'border-primary bg-primary/5'
                                        : 'hover:border-primary/50',
                                    index === 0 ? 'ring-2 ring-green-500/20' : ''
                                ]"
                                @click="selectQuote(quote)"
                            >
                                <div class="flex items-center gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold">{{ quote.provider }}</span>
                                            <Badge v-if="index === 0" variant="default" class="bg-green-600">
                                                Best Rate
                                            </Badge>
                                        </div>
                                        <div class="text-sm text-muted-foreground">
                                            Rate: {{ formatRate(quote.rate) }} | Spread: {{ (quote.spread * 100).toFixed(2) }}%
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-green-600">
                                        {{ formatCurrency(quote.destination_amount, form.destination_currency) }}
                                    </div>
                                    <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                        <Clock class="h-3 w-3" />
                                        {{ quote.eta }}
                                    </div>
                                </div>
                            </div>

                            <Button 
                                v-if="selectedQuote" 
                                class="w-full mt-4"
                                @click="confirmExchange"
                            >
                                Generate FX Instruction
                            </Button>
                        </div>
                    </CardContent>
                </Card>
                    </div>

                    <!-- FX Providers -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Active FX Providers</CardTitle>
                            <CardDescription>
                                Connected providers for currency exchange
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-4">
                                <div 
                                    v-for="provider in fxProviders" 
                                    :key="provider.id"
                                    class="flex items-center gap-3 rounded-lg border p-3"
                                >
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <ArrowRightLeft class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ provider.name }}</div>
                                        <Badge variant="outline" class="text-xs">{{ provider.type }}</Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Peer-to-Peer Tab -->
                <TabsContent value="peer-to-peer" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="h-5 w-5" />
                                Peer-to-Peer Currency Exchange
                            </CardTitle>
                            <CardDescription>
                                Trade directly with other users in a secure escrow environment
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <Users class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-muted-foreground">
                                    Peer-to-Peer functionality coming soon
                                </p>
                            </div>
                        </CardContent>
                    </Card>
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
