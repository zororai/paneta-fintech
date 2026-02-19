<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
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
import { QrCode, Plus, Clock, CheckCircle, XCircle, Copy, Share2, Play } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface PaymentRequest {
    id: number;
    reference: string;
    amount: number;
    amount_received: number;
    currency: string;
    status: 'pending' | 'partially_fulfilled' | 'completed' | 'cancelled' | 'expired';
    description: string | null;
    allow_partial: boolean;
    qr_code_data: string;
    expires_at: string | null;
    created_at: string;
    linked_account?: {
        institution?: { name: string };
    };
}

interface Stats {
    total_requests: number;
    pending_requests: number;
    completed_requests: number;
    total_received: number;
}

const props = defineProps<{
    paymentRequests: { data: PaymentRequest[] };
    linkedAccounts: LinkedAccount[];
    stats: Stats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Payment Requests' },
];

const showCreateDialog = ref(false);
const showQrDialog = ref(false);
const selectedRequest = ref<PaymentRequest | null>(null);

const currencies = ['USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'BWP', 'KES', 'NGN'];

const form = useForm({
    amount: 0,
    currency: 'USD',
    linked_account_id: null as number | null,
    description: '',
    allow_partial: false,
    expires_in_minutes: 60,
});

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return { variant: 'default' as const, class: 'bg-green-600', icon: CheckCircle };
        case 'pending':
            return { variant: 'outline' as const, class: 'text-yellow-600 border-yellow-600', icon: Clock };
        case 'partially_fulfilled':
            return { variant: 'outline' as const, class: 'text-blue-600 border-blue-600', icon: Clock };
        case 'cancelled':
        case 'expired':
            return { variant: 'destructive' as const, class: '', icon: XCircle };
        default:
            return { variant: 'outline' as const, class: '', icon: Clock };
    }
};

const submitRequest = () => {
    form.post('/paneta/payment-requests', {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
        },
    });
};

const viewQr = (request: PaymentRequest) => {
    selectedRequest.value = request;
    showQrDialog.value = true;
};

const copyLink = (reference: string) => {
    const link = `${window.location.origin}/pay/${reference}`;
    navigator.clipboard.writeText(link);
};

const simulatePay = (requestId: number) => {
    router.post(`/paneta/demo/payment-requests/${requestId}/simulate-pay`);
};
</script>

<template>
    <Head title="Payment Requests - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Payment Requests</h1>
                    <p class="text-muted-foreground">
                        Create and manage payment requests with QR codes
                    </p>
                </div>
                <Dialog v-model:open="showCreateDialog">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create Request
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Create Payment Request</DialogTitle>
                            <DialogDescription>
                                Generate a QR code for others to pay you
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submitRequest" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label>Amount</Label>
                                    <Input v-model.number="form.amount" type="number" min="0.01" step="0.01" required />
                                </div>
                                <div class="space-y-2">
                                    <Label>Currency</Label>
                                    <Select v-model="form.currency">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="c in currencies" :key="c" :value="c">{{ c }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label>Receive To (Optional)</Label>
                                <Select v-model="form.linked_account_id">
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
                                <Label>Description</Label>
                                <Input v-model="form.description" placeholder="What's this payment for?" />
                            </div>
                            <div class="space-y-2">
                                <Label>Expires In (minutes)</Label>
                                <Input v-model.number="form.expires_in_minutes" type="number" min="5" max="10080" />
                            </div>
                            <div class="flex items-center space-x-2">
                                <Checkbox id="allow_partial" v-model:checked="form.allow_partial" />
                                <Label for="allow_partial">Allow partial payments</Label>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing || !form.amount">
                                    Create Request
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Stats -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                                <QrCode class="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Total Requests</p>
                                <p class="text-2xl font-bold">{{ stats.total_requests }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                                <Clock class="h-6 w-6 text-yellow-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Pending</p>
                                <p class="text-2xl font-bold">{{ stats.pending_requests }}</p>
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
                                <p class="text-sm text-muted-foreground">Completed</p>
                                <p class="text-2xl font-bold">{{ stats.completed_requests }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center gap-4">
                            <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                                <QrCode class="h-6 w-6 text-purple-600" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Total Received</p>
                                <p class="text-2xl font-bold">{{ formatCurrency(stats.total_received) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Payment Requests List -->
            <Card>
                <CardHeader>
                    <CardTitle>Your Payment Requests</CardTitle>
                    <CardDescription>Manage and track your payment requests</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="paymentRequests.data.length === 0" class="flex flex-col items-center py-12 text-center">
                        <QrCode class="mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-muted-foreground">No payment requests yet</p>
                        <p class="text-sm text-muted-foreground">Create your first request to receive payments</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="request in paymentRequests.data"
                            :key="request.id"
                            class="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div class="flex items-center gap-4">
                                <div class="rounded-full bg-primary/10 p-2">
                                    <QrCode class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ formatCurrency(request.amount, request.currency) }}</span>
                                        <Badge :class="getStatusBadge(request.status).class">
                                            {{ request.status }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ request.description || 'No description' }} • Ref: {{ request.reference }}
                                    </p>
                                    <p v-if="request.amount_received > 0" class="text-sm text-green-600">
                                        Received: {{ formatCurrency(request.amount_received, request.currency) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="outline" size="sm" @click="viewQr(request)">
                                    <QrCode class="mr-1 h-4 w-4" />
                                    QR
                                </Button>
                                <Button variant="outline" size="sm" @click="copyLink(request.reference)">
                                    <Copy class="mr-1 h-4 w-4" />
                                    Copy
                                </Button>
                                <Button 
                                    v-if="request.status === 'pending' || request.status === 'partially_fulfilled'"
                                    variant="secondary" 
                                    size="sm"
                                    @click="simulatePay(request.id)"
                                    class="bg-purple-100 hover:bg-purple-200 text-purple-700"
                                >
                                    <Play class="mr-1 h-3 w-3" />
                                    Demo Pay
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- QR Dialog -->
            <Dialog v-model:open="showQrDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Payment QR Code</DialogTitle>
                        <DialogDescription>
                            Share this QR code to receive payment
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="selectedRequest" class="flex flex-col items-center py-4">
                        <div class="bg-white p-4 rounded-lg mb-4">
                            <div class="w-48 h-48 bg-gray-200 flex items-center justify-center">
                                <QrCode class="h-32 w-32 text-gray-800" />
                            </div>
                        </div>
                        <p class="text-xl font-bold">{{ formatCurrency(selectedRequest.amount, selectedRequest.currency) }}</p>
                        <p class="text-sm text-muted-foreground">{{ selectedRequest.description || 'Payment Request' }}</p>
                        <p class="text-xs text-muted-foreground mt-2">Ref: {{ selectedRequest.reference }}</p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="copyLink(selectedRequest?.reference || '')">
                            <Share2 class="mr-2 h-4 w-4" />
                            Share Link
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
