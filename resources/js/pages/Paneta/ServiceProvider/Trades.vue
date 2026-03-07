<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { BreadcrumbItem } from '@/types';
import { CheckCircle, Clock, XCircle, ArrowRightLeft, Wallet } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    provider: any;
    exchangeRequests: any;
    providerAccounts: any;
    stats: any;
    filters: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Service Provider', href: '/paneta/service-provider' },
    { label: 'Trades', href: '/paneta/service-provider/trades' },
];

const selectedRequest = ref<any>(null);
const showRejectDialog = ref(false);
const showCompleteDialog = ref(false);

const rejectForm = useForm({
    reason: '',
});

const completeForm = useForm({
    provider_source_account_id: '',
});

const formatCurrency = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount) + ' ' + currency;
};

const getStatusBadge = (status: string) => {
    const badges: Record<string, { variant: string; label: string }> = {
        pending: { variant: 'default', label: 'Pending Review' },
        accepted: { variant: 'secondary', label: 'Awaiting User Payment' },
        rejected: { variant: 'destructive', label: 'Rejected' },
        user_paid: { variant: 'default', label: 'User Paid - Action Required' },
        provider_paid: { variant: 'secondary', label: 'Provider Paid' },
        completed: { variant: 'outline', label: 'Completed' },
        cancelled: { variant: 'outline', label: 'Cancelled' },
        failed: { variant: 'destructive', label: 'Failed' },
    };
    return badges[status] || { variant: 'outline', label: status };
};

const acceptRequest = (request: any) => {
    router.post(`/paneta/service-provider/exchange-requests/${request.id}/accept`, {}, {
        preserveScroll: true,
    });
};

const openRejectDialog = (request: any) => {
    selectedRequest.value = request;
    showRejectDialog.value = true;
};

const rejectRequest = () => {
    if (!selectedRequest.value) return;
    
    rejectForm.post(`/paneta/service-provider/exchange-requests/${selectedRequest.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            showRejectDialog.value = false;
            rejectForm.reset();
        },
    });
};

const openCompleteDialog = (request: any) => {
    selectedRequest.value = request;
    showCompleteDialog.value = true;
};

const completePayment = () => {
    if (!selectedRequest.value) return;
    
    completeForm.post(`/paneta/service-provider/exchange-requests/${selectedRequest.value.id}/complete-payment`, {
        preserveScroll: true,
        onSuccess: () => {
            showCompleteDialog.value = false;
            completeForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Trades - Service Provider - PANÉTA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Exchange Requests</h1>
                <p class="text-muted-foreground mt-1">Review and manage currency exchange requests from users</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Pending Review</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.pending }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Awaiting User Payment</CardDescription>
                        <CardTitle class="text-3xl">{{ stats.awaiting_user_payment }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Action Required</CardDescription>
                        <CardTitle class="text-3xl text-orange-600">{{ stats.awaiting_provider_payment }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Completed Today</CardDescription>
                        <CardTitle class="text-3xl text-green-600">{{ stats.completed_today }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <!-- Exchange Requests List -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ArrowRightLeft class="h-5 w-5" />
                        Exchange Requests ({{ exchangeRequests.total }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="exchangeRequests.data.length === 0" class="text-center py-12 text-muted-foreground">
                        No exchange requests yet
                    </div>
                    <div v-else class="space-y-4">
                        <div
                            v-for="request in exchangeRequests.data"
                            :key="request.id"
                            class="border rounded-lg p-6 hover:bg-muted/30 transition-colors"
                        >
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-lg font-semibold">{{ request.currency_pair }}</h3>
                                        <Badge :variant="getStatusBadge(request.status).variant">
                                            {{ getStatusBadge(request.status).label }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">Ref: {{ request.reference_number }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-muted-foreground">{{ new Date(request.created_at).toLocaleString() }}</p>
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-4 mb-4">
                                <div class="space-y-2">
                                    <p class="text-sm font-medium text-muted-foreground">Customer</p>
                                    <p class="font-semibold">{{ request.user?.name }}</p>
                                </div>
                                <div class="space-y-2">
                                    <p class="text-sm font-medium text-muted-foreground">Exchange Details</p>
                                    <div class="space-y-1">
                                        <p class="text-sm">Sell: <span class="font-semibold">{{ formatCurrency(request.sell_amount, request.sell_currency) }}</span></p>
                                        <p class="text-sm">Buy: <span class="font-semibold text-green-600">{{ formatCurrency(request.buy_amount, request.buy_currency) }}</span></p>
                                        <p class="text-sm">Rate: <span class="font-semibold">{{ request.exchange_rate }}</span></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2 pt-4 border-t">
                                <template v-if="request.status === 'pending'">
                                    <Button @click="acceptRequest(request)" class="flex-1" variant="default">
                                        <CheckCircle class="h-4 w-4 mr-2" />
                                        Accept Offer
                                    </Button>
                                    <Button @click="openRejectDialog(request)" class="flex-1" variant="destructive">
                                        <XCircle class="h-4 w-4 mr-2" />
                                        Reject Offer
                                    </Button>
                                </template>
                                <template v-else-if="request.status === 'user_paid'">
                                    <Button @click="openCompleteDialog(request)" class="w-full" variant="default">
                                        <Wallet class="h-4 w-4 mr-2" />
                                        Complete Payment
                                    </Button>
                                </template>
                                <template v-else-if="request.status === 'accepted'">
                                    <div class="w-full p-3 bg-blue-50 rounded-md text-sm text-blue-700">
                                        <Clock class="h-4 w-4 inline mr-2" />
                                        Waiting for user to confirm payment
                                    </div>
                                </template>
                                <template v-else-if="request.status === 'completed'">
                                    <div class="w-full p-3 bg-green-50 rounded-md text-sm text-green-700">
                                        <CheckCircle class="h-4 w-4 inline mr-2" />
                                        Exchange completed on {{ new Date(request.completed_at).toLocaleString() }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Reject Dialog -->
        <Dialog v-model:open="showRejectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Exchange Request</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for rejecting this exchange request.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="reason">Rejection Reason (Optional)</Label>
                        <Textarea
                            id="reason"
                            v-model="rejectForm.reason"
                            placeholder="e.g., Insufficient liquidity, Rate not competitive, etc."
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showRejectDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="rejectRequest" :disabled="rejectForm.processing">
                        Reject Request
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Complete Payment Dialog -->
        <Dialog v-model:open="showCompleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Complete Exchange Payment</DialogTitle>
                    <DialogDescription>
                        User has confirmed payment. Select your source account to complete the exchange.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="selectedRequest" class="space-y-4 py-4">
                    <div class="p-4 bg-muted rounded-lg space-y-2">
                        <p class="text-sm font-medium">Payment Details</p>
                        <div class="space-y-1 text-sm">
                            <p>Amount to Pay: <span class="font-semibold text-green-600">{{ formatCurrency(selectedRequest.buy_amount, selectedRequest.buy_currency) }}</span></p>
                            <p>Recipient: <span class="font-semibold">{{ selectedRequest.user?.name }}</span></p>
                            <p>Destination Account: <span class="font-semibold">{{ selectedRequest.user_destination_account?.institution_name }}</span></p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="source_account">Select Source Account</Label>
                        <Select v-model="completeForm.provider_source_account_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Choose account to pay from" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="account in providerAccounts"
                                    :key="account.id"
                                    :value="account.id.toString()"
                                >
                                    {{ account.institution_name }} - {{ account.account_number }} ({{ formatCurrency(account.balance, account.currency) }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCompleteDialog = false">Cancel</Button>
                    <Button @click="completePayment" :disabled="completeForm.processing || !completeForm.provider_source_account_id">
                        Confirm Payment
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
