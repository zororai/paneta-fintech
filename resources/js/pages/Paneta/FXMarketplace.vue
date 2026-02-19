<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BreadcrumbItem, LinkedAccount } from '@/types';
import { Globe, TrendingUp, ArrowRightLeft, Users, Clock, Zap } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface MarketOffer {
    id: number;
    rate: number;
    amount: number;
    min_amount: number | null;
    user: string;
    expires_at: string | null;
}

interface MarketStats {
    total_open_offers: number;
    total_pairs: number;
    today_volume: number;
}

const props = defineProps<{
    openOffers: Record<string, MarketOffer[]>;
    linkedAccounts: LinkedAccount[];
    currencies: string[];
    marketStats: MarketStats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'FX Marketplace' },
];

const selectedPair = ref<string | null>(null);
const showTakeDialog = ref(false);
const selectedOffer = ref<MarketOffer | null>(null);

const takeForm = useForm({
    source_account_id: null as number | null,
    amount: 0,
});

const availablePairs = computed(() => Object.keys(props.openOffers));

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

const formatRate = (rate: number) => rate.toFixed(6);

const selectOffer = (offer: MarketOffer, pair: string) => {
    selectedOffer.value = offer;
    selectedPair.value = pair;
    takeForm.amount = offer.amount;
    showTakeDialog.value = true;
};

const takeOffer = () => {
    if (!selectedOffer.value) return;
    
    takeForm.post(`/paneta/fx-marketplace/offers/${selectedOffer.value.id}/take`, {
        onSuccess: () => {
            showTakeDialog.value = false;
            takeForm.reset();
        },
    });
};

const refreshOrderBook = async (sellCurrency: string, buyCurrency: string) => {
    try {
        const response = await fetch(`/paneta/fx-marketplace/order-book?sell_currency=${sellCurrency}&buy_currency=${buyCurrency}`);
        const data = await response.json();
        console.log('Order book:', data.offers);
    } catch (error) {
        console.error('Failed to refresh order book:', error);
    }
};
</script>

<template>
    <Head title="FX Marketplace - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Global FX Marketplace</h1>
                    <p class="text-muted-foreground">
                        Access peer-to-peer FX liquidity with neutral orchestration
                    </p>
                </div>
                <Badge variant="outline" class="text-blue-600 border-blue-600">
                    <Globe class="mr-1 h-3 w-3" />
                    Neutral Liquidity
                </Badge>
            </div>

            <!-- Stats -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                                <TrendingUp class="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Open Offers</p>
                                <p class="text-2xl font-bold">{{ marketStats.total_open_offers }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                                <ArrowRightLeft class="h-6 w-6 text-green-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Active Pairs</p>
                                <p class="text-2xl font-bold">{{ marketStats.total_pairs }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                                <Zap class="h-6 w-6 text-purple-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Today's Volume</p>
                                <p class="text-2xl font-bold">{{ formatCurrency(marketStats.today_volume) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Market Overview -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Order Books by Pair -->
                <Card v-for="(offers, pair) in openOffers" :key="pair" class="lg:col-span-1">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <ArrowRightLeft class="h-5 w-5" />
                                {{ pair }}
                            </CardTitle>
                            <Badge variant="outline">{{ offers.length }} offers</Badge>
                        </div>
                        <CardDescription>Live order book</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="offers.length === 0" class="py-8 text-center text-muted-foreground">
                            No offers available
                        </div>
                        <div v-else class="space-y-2">
                            <div class="grid grid-cols-4 text-sm font-medium text-muted-foreground pb-2 border-b">
                                <span>Rate</span>
                                <span>Amount</span>
                                <span>Trader</span>
                                <span></span>
                            </div>
                            <div
                                v-for="offer in offers"
                                :key="offer.id"
                                class="grid grid-cols-4 items-center py-2 hover:bg-muted/50 rounded"
                            >
                                <span class="font-mono text-green-600">{{ formatRate(offer.rate) }}</span>
                                <span>{{ offer.amount.toLocaleString() }}</span>
                                <span class="text-sm text-muted-foreground">{{ offer.user }}</span>
                                <Button size="sm" variant="outline" @click="selectOffer(offer, pair)">
                                    Take
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State if no pairs -->
                <Card v-if="Object.keys(openOffers).length === 0" class="lg:col-span-2">
                    <CardContent class="flex flex-col items-center py-16">
                        <Globe class="mb-4 h-16 w-16 text-muted-foreground" />
                        <h3 class="text-lg font-medium mb-2">No Active Markets</h3>
                        <p class="text-muted-foreground text-center max-w-md">
                            There are currently no open FX offers in the marketplace. 
                            Create an offer in P2P FX Escrow to start trading.
                        </p>
                        <Button class="mt-4" as-child>
                            <a href="/paneta/p2p-escrow">Create Offer</a>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- How It Works -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Globe class="h-5 w-5" />
                        Neutral FX Liquidity Orchestration
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="flex items-start gap-4">
                            <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                                <Users class="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <h4 class="font-medium">Peer Liquidity</h4>
                                <p class="text-sm text-muted-foreground">
                                    Access FX liquidity directly from other users. No intermediary spread.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                                <Zap class="h-6 w-6 text-green-600" />
                            </div>
                            <div>
                                <h4 class="font-medium">Instant Execution</h4>
                                <p class="text-sm text-muted-foreground">
                                    Atomic swaps ensure immediate settlement with zero counterparty risk.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                                <Globe class="h-6 w-6 text-purple-600" />
                            </div>
                            <div>
                                <h4 class="font-medium">Platform Neutral</h4>
                                <p class="text-sm text-muted-foreground">
                                    PANÉTA never holds FX positions. Pure orchestration with full audit trail.
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Take Offer Dialog -->
            <Dialog v-model:open="showTakeDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Take FX Offer</DialogTitle>
                        <DialogDescription>
                            Execute an atomic FX swap with this offer
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="selectedOffer && selectedPair" class="space-y-4">
                        <div class="rounded-lg bg-muted p-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-muted-foreground">Pair</span>
                                <span class="font-medium">{{ selectedPair }}</span>
                            </div>
                            <div class="flex justify-between mb-2">
                                <span class="text-muted-foreground">Rate</span>
                                <span class="font-mono text-green-600">{{ formatRate(selectedOffer.rate) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Available</span>
                                <span>{{ selectedOffer.amount.toLocaleString() }}</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Your Source Account</Label>
                            <Select v-model="takeForm.source_account_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select account" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="account in linkedAccounts" :key="account.id" :value="account.id">
                                        {{ account.institution?.name }} - {{ account.currency }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Amount</Label>
                            <Input v-model.number="takeForm.amount" type="number" :max="selectedOffer.amount" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="showTakeDialog = false">Cancel</Button>
                        <Button @click="takeOffer" :disabled="takeForm.processing || !takeForm.source_account_id">
                            Execute Swap
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
