<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import { Plus, Activity, Edit, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    provider: any;
    offers: any;
    filters: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Service Provider', href: '/paneta/service-provider' },
    { label: 'Offers', href: '/paneta/service-provider/offers' },
];

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const selectedOffer = ref<any>(null);

const createForm = useForm({
    base_currency: '',
    quote_currency: '',
    bid_rate: '',
    ask_rate: '',
    expires_at: '',
});

const editForm = useForm({
    bid_rate: '',
    ask_rate: '',
    status: '',
    expires_at: '',
});

const currencies = [
    'USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'JPY', 'CHF', 'AUD', 'CAD', 'CNY'
];

const getStatusBadge = (status: string) => {
    const badges: Record<string, { class: string; label: string }> = {
        active: { class: 'bg-green-100 text-green-700', label: 'Active' },
        expired: { class: 'bg-gray-100 text-gray-700', label: 'Expired' },
        cancelled: { class: 'bg-red-100 text-red-700', label: 'Cancelled' },
    };
    return badges[status] || { class: 'bg-gray-100 text-gray-700', label: status };
};

const openCreateDialog = () => {
    createForm.reset();
    showCreateDialog.value = true;
};

const createOffer = () => {
    console.log('Creating offer with data:', createForm.data());
    createForm.post('/paneta/service-provider/offers', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
};

const openEditDialog = (offer: any) => {
    selectedOffer.value = offer;
    editForm.bid_rate = offer.bid_rate;
    editForm.ask_rate = offer.ask_rate;
    editForm.status = offer.status;
    editForm.expires_at = offer.expires_at ? new Date(offer.expires_at).toISOString().slice(0, 16) : '';
    showEditDialog.value = true;
};

const updateOffer = () => {
    if (!selectedOffer.value) return;
    
    editForm.put(`/paneta/service-provider/offers/${selectedOffer.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            editForm.reset();
        },
    });
};

const deleteOffer = (offer: any) => {
    if (confirm('Are you sure you want to cancel this offer?')) {
        useForm({}).delete(`/paneta/service-provider/offers/${offer.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="FX Offers - Service Provider - PANÉTA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">FX Offers</h1>
                    <p class="text-muted-foreground mt-1">Manage your currency exchange offers</p>
                </div>
                <Button @click="openCreateDialog" class="gap-2">
                    <Plus class="h-4 w-4" />
                    Create New Offer
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Activity class="h-5 w-5" />
                        Your Offers ({{ offers.total }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="offers.data.length === 0" class="text-center py-12 text-muted-foreground">
                        No offers yet. Create your first offer to start trading.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="offer in offers.data"
                            :key="offer.id"
                            class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50"
                        >
                            <div class="flex-1">
                                <p class="font-semibold text-lg">{{ offer.currency_pair }}</p>
                                <p class="text-sm text-muted-foreground">Rate: {{ offer.rate }} | Spread: {{ offer.spread_percentage }}%</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <Badge :class="getStatusBadge(offer.status).class">
                                    {{ getStatusBadge(offer.status).label }}
                                </Badge>
                                <Button @click="openEditDialog(offer)" variant="outline" size="sm">
                                    <Edit class="h-4 w-4" />
                                </Button>
                                <Button @click="deleteOffer(offer)" variant="destructive" size="sm">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Create Offer Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Create New FX Offer</DialogTitle>
                    <DialogDescription>
                        Set your exchange rates for a currency pair.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="base_currency">Base Currency</Label>
                            <Select v-model="createForm.base_currency">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select currency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="currency in currencies" :key="currency" :value="currency">
                                        {{ currency }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createForm.errors.base_currency" class="text-sm text-red-600">{{ createForm.errors.base_currency }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="quote_currency">Quote Currency</Label>
                            <Select v-model="createForm.quote_currency">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select currency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="currency in currencies" :key="currency" :value="currency">
                                        {{ currency }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createForm.errors.quote_currency" class="text-sm text-red-600">{{ createForm.errors.quote_currency }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="bid_rate">Bid Rate (Buy)</Label>
                            <Input
                                id="bid_rate"
                                v-model="createForm.bid_rate"
                                type="number"
                                step="0.00000001"
                                placeholder="0.00000000"
                            />
                            <p v-if="createForm.errors.bid_rate" class="text-sm text-red-600">{{ createForm.errors.bid_rate }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="ask_rate">Ask Rate (Sell)</Label>
                            <Input
                                id="ask_rate"
                                v-model="createForm.ask_rate"
                                type="number"
                                step="0.00000001"
                                placeholder="0.00000000"
                            />
                            <p v-if="createForm.errors.ask_rate" class="text-sm text-red-600">{{ createForm.errors.ask_rate }}</p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="expires_at">Expires At (Optional)</Label>
                        <Input
                            id="expires_at"
                            v-model="createForm.expires_at"
                            type="datetime-local"
                        />
                        <p class="text-xs text-muted-foreground">Leave empty for 24-hour expiration</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCreateDialog = false">Cancel</Button>
                    <Button @click="createOffer" :disabled="createForm.processing">
                        Create Offer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Edit Offer Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit FX Offer</DialogTitle>
                    <DialogDescription>
                        Update exchange rates and status for {{ selectedOffer?.base_currency }}/{{ selectedOffer?.quote_currency }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit_bid_rate">Bid Rate (Buy)</Label>
                            <Input
                                id="edit_bid_rate"
                                v-model="editForm.bid_rate"
                                type="number"
                                step="0.00000001"
                                placeholder="0.00000000"
                            />
                            <p v-if="editForm.errors.bid_rate" class="text-sm text-red-600">{{ editForm.errors.bid_rate }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit_ask_rate">Ask Rate (Sell)</Label>
                            <Input
                                id="edit_ask_rate"
                                v-model="editForm.ask_rate"
                                type="number"
                                step="0.00000001"
                                placeholder="0.00000000"
                            />
                            <p v-if="editForm.errors.ask_rate" class="text-sm text-red-600">{{ editForm.errors.ask_rate }}</p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="editForm.status">
                            <SelectTrigger>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="expired">Expired</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit_expires_at">Expires At</Label>
                        <Input
                            id="edit_expires_at"
                            v-model="editForm.expires_at"
                            type="datetime-local"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showEditDialog = false">Cancel</Button>
                    <Button @click="updateOffer" :disabled="editForm.processing">
                        Update Offer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
