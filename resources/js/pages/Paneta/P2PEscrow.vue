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
    DialogTrigger,
} from '@/components/ui/dialog';
import type { BreadcrumbItem, LinkedAccount } from '@/types';
import { Handshake, Plus, Clock, CheckCircle, XCircle, ArrowRightLeft, Shield, Play, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';

interface FxOffer {
    id: number;
    sell_currency: string;
    buy_currency: string;
    rate: number;
    amount: number;
    filled_amount: number;
    min_amount: number | null;
    status: 'open' | 'matched' | 'executed' | 'cancelled' | 'expired' | 'failed';
    expires_at: string | null;
    created_at: string;
    source_account?: {
        institution?: { name: string };
    };
    matched_offer?: {
        user?: { name: string };
    };
}

interface Stats {
    active_offers: number;
    completed_swaps: number;
    total_volume: number;
}

const props = defineProps<{
    myOffers: FxOffer[];
    linkedAccounts: LinkedAccount[];
    currencies: string[];
    stats: Stats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'P2P FX Escrow' },
];

const showCreateDialog = ref(false);

const form = useForm({
    source_account_id: null as number | null,
    sell_currency: 'USD',
    buy_currency: 'ZAR',
    rate: 18.5,
    amount: 100,
    min_amount: null as number | null,
    expires_in_hours: 24,
});

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

const formatRate = (rate: number) => rate.toFixed(6);

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'executed':
            return { variant: 'default' as const, class: 'bg-green-600' };
        case 'open':
            return { variant: 'outline' as const, class: 'text-blue-600 border-blue-600' };
        case 'matched':
            return { variant: 'outline' as const, class: 'text-yellow-600 border-yellow-600' };
        case 'cancelled':
        case 'expired':
        case 'failed':
            return { variant: 'destructive' as const, class: '' };
        default:
            return { variant: 'outline' as const, class: '' };
    }
};

const getRemainingAmount = (offer: FxOffer) => offer.amount - offer.filled_amount;

const submitOffer = () => {
    form.post('/paneta/p2p-escrow/offers', {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
        },
    });
};

const cancelOffer = (offerId: number) => {
    router.post(`/paneta/p2p-escrow/offers/${offerId}/cancel`);
};

const findMatches = async (offerId: number) => {
    try {
        const response = await fetch(`/paneta/p2p-escrow/offers/${offerId}/matches`);
        const data = await response.json();
        alert(`Found ${data.matches.length} potential matches. Check console for details.`);
        console.log('Matches:', data.matches);
    } catch (error) {
        console.error('Failed to find matches:', error);
    }
};

const simulateAccept = (offerId: number) => {
    router.post(`/paneta/demo/offers/${offerId}/simulate-accept`);
};
</script>

<template>
    <Head title="P2P FX Escrow - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">P2P FX Smart Escrow</h1>
                    <p class="text-muted-foreground">
                        Exchange currencies peer-to-peer with atomic escrow protection
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="text-green-600 border-green-600">
                        <Shield class="mr-1 h-3 w-3" />
                        Zero-Custody Escrow
                    </Badge>
                    <Dialog v-model:open="showCreateDialog">
                        <DialogTrigger as-child>
                            <Button>
                                <Plus class="mr-2 h-4 w-4" />
                                Create Offer
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Create FX Offer</DialogTitle>
                                <DialogDescription>
                                    Post an offer to exchange currencies with other users
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitOffer" class="space-y-4">
                                <div class="space-y-2">
                                    <Label>Source Account</Label>
                                    <Select v-model="form.source_account_id">
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
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label>Sell Currency</Label>
                                        <Select v-model="form.sell_currency">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="c in currencies" :key="c" :value="c">{{ c }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Buy Currency</Label>
                                        <Select v-model="form.buy_currency">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="c in currencies" :key="c" :value="c">{{ c }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label>Amount ({{ form.sell_currency }})</Label>
                                        <Input v-model.number="form.amount" type="number" min="1" required />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Exchange Rate</Label>
                                        <Input v-model.number="form.rate" type="number" step="0.000001" min="0.000001" required />
                                    </div>
                                </div>
                                <div class="rounded-lg bg-muted p-3">
                                    <p class="text-sm">
                                        You will receive: <strong>{{ formatCurrency(form.amount * form.rate, form.buy_currency) }}</strong>
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label>Min Amount (Optional)</Label>
                                        <Input v-model.number="form.min_amount" type="number" min="1" placeholder="No minimum" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Expires In (hours)</Label>
                                        <Input v-model.number="form.expires_in_hours" type="number" min="1" max="168" />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button type="submit" :disabled="form.processing || !form.source_account_id">
                                        Create Offer
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                                <Handshake class="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Active Offers</p>
                                <p class="text-2xl font-bold">{{ stats.active_offers }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                                <CheckCircle class="h-6 w-6 text-green-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Completed Swaps</p>
                                <p class="text-2xl font-bold">{{ stats.completed_swaps }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                                <ArrowRightLeft class="h-6 w-6 text-purple-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Total Volume</p>
                                <p class="text-2xl font-bold">{{ formatCurrency(stats.total_volume) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- How It Works -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="h-5 w-5" />
                        How Smart Escrow Works
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="text-center p-4">
                            <div class="rounded-full bg-primary/10 w-12 h-12 flex items-center justify-center mx-auto mb-3">
                                <span class="text-lg font-bold text-primary">1</span>
                            </div>
                            <h4 class="font-medium">Create Offer</h4>
                            <p class="text-sm text-muted-foreground">Post your FX offer with desired rate</p>
                        </div>
                        <div class="text-center p-4">
                            <div class="rounded-full bg-primary/10 w-12 h-12 flex items-center justify-center mx-auto mb-3">
                                <span class="text-lg font-bold text-primary">2</span>
                            </div>
                            <h4 class="font-medium">Match Found</h4>
                            <p class="text-sm text-muted-foreground">System finds compatible counter-offer</p>
                        </div>
                        <div class="text-center p-4">
                            <div class="rounded-full bg-primary/10 w-12 h-12 flex items-center justify-center mx-auto mb-3">
                                <span class="text-lg font-bold text-primary">3</span>
                            </div>
                            <h4 class="font-medium">Atomic Swap</h4>
                            <p class="text-sm text-muted-foreground">Both parties confirm, funds swap atomically</p>
                        </div>
                        <div class="text-center p-4">
                            <div class="rounded-full bg-primary/10 w-12 h-12 flex items-center justify-center mx-auto mb-3">
                                <span class="text-lg font-bold text-primary">4</span>
                            </div>
                            <h4 class="font-medium">Complete</h4>
                            <p class="text-sm text-muted-foreground">Exchange complete with full audit trail</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- My Offers -->
            <Card>
                <CardHeader>
                    <CardTitle>My FX Offers</CardTitle>
                    <CardDescription>Your active and past FX exchange offers</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="myOffers.length === 0" class="flex flex-col items-center py-12 text-center">
                        <Handshake class="mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-muted-foreground">No FX offers yet</p>
                        <p class="text-sm text-muted-foreground">Create your first offer to start exchanging</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="offer in myOffers"
                            :key="offer.id"
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div class="flex items-center gap-4">
                                <div class="rounded-full bg-primary/10 p-2">
                                    <ArrowRightLeft class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">
                                            {{ offer.sell_currency }} → {{ offer.buy_currency }}
                                        </span>
                                        <Badge :class="getStatusBadge(offer.status).class">
                                            {{ offer.status }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ formatCurrency(offer.amount, offer.sell_currency) }} @ {{ formatRate(offer.rate) }}
                                    </p>
                                    <p v-if="offer.filled_amount > 0" class="text-sm text-green-600">
                                        Filled: {{ formatCurrency(offer.filled_amount, offer.sell_currency) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button 
                                    v-if="offer.status === 'open'" 
                                    variant="outline" 
                                    size="sm"
                                    @click="findMatches(offer.id)"
                                >
                                    Find Matches
                                </Button>
                                <Button 
                                    v-if="offer.status === 'open'" 
                                    variant="secondary" 
                                    size="sm"
                                    @click="simulateAccept(offer.id)"
                                    class="bg-purple-100 hover:bg-purple-200 text-purple-700"
                                >
                                    <Play class="mr-1 h-3 w-3" />
                                    Demo Accept
                                </Button>
                                <Button 
                                    v-if="['open', 'matched'].includes(offer.status)" 
                                    variant="destructive" 
                                    size="sm"
                                    @click="cancelOffer(offer.id)"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
