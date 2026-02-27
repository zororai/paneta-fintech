<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import type { BreadcrumbItem, LinkedAccount } from '@/types';
import { Send, Wallet, AlertCircle, CreditCard, QrCode, Link2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Institution {
    id: number;
    name: string;
    type: string;
    country: string;
    is_active: boolean;
}

const props = defineProps<{
    accounts: LinkedAccount[];
    institutions: Institution[];
}>();

const activeTab = ref<'manual' | 'scan' | 'link'>('manual');
const scanStep = ref<'camera' | 'details'>('camera');
const scannedPaymentRequest = ref<any>(null);
const selectedSourceAccountForScan = ref<number | null>(null);
const showCamera = ref(false);

// Pay via Link state
const linkStep = ref<'input' | 'details'>('input');
const paymentLink = ref('');
const linkPaymentRequest = ref<any>(null);
const selectedSourceAccountForLink = ref<number | null>(null);

const countries = [
    { code: 'ZA', name: 'South Africa', currency: 'ZAR' },
    { code: 'ZW', name: 'Zimbabwe', currency: 'USD' },
    { code: 'US', name: 'United States', currency: 'USD' },
    { code: 'GB', name: 'United Kingdom', currency: 'GBP' },
    { code: 'KE', name: 'Kenya', currency: 'KES' },
    { code: 'NG', name: 'Nigeria', currency: 'NGN' },
    { code: 'GH', name: 'Ghana', currency: 'GHS' },
];

const currencies = ['USD', 'ZAR', 'GBP', 'EUR', 'KES', 'NGN', 'GHS'];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Send Money' },
];

const form = useForm({
    payment_method: 'manual' as 'manual' | 'scan' | 'link',
    source_account_id: null as number | null,
    amount: '',
    source_currency: '',
    description: '',
    destination_country: '',
    destination_institution_id: null as number | null,
    destination_account: '',
    destination_currency: '',
});

const selectedAccount = computed(() => {
    return props.accounts.find((a) => a.id === form.source_account_id);
});

const destinationInstitutions = computed(() => {
    if (!form.destination_country) return props.institutions;
    return props.institutions.filter(inst => inst.country === form.destination_country);
});

const isCrossBorder = computed(() => {
    if (!selectedAccount.value || !form.destination_currency) return false;
    return selectedAccount.value.currency !== form.destination_currency;
});

const selectedDestinationCountry = computed(() => {
    return countries.find(c => c.code === form.destination_country);
});

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

// Watch for source account changes to auto-fill source currency
watch(() => form.source_account_id, (newAccountId) => {
    const account = props.accounts.find(a => a.id === newAccountId);
    if (account) {
        form.source_currency = account.currency;
    }
});

// Watch for destination country changes to auto-fill destination currency
watch(() => form.destination_country, (newCountry) => {
    const country = countries.find(c => c.code === newCountry);
    if (country) {
        form.destination_currency = country.currency;
    }
    // Reset destination institution when country changes
    form.destination_institution_id = null;
});

const submit = () => {
    // This will route to Pre-Execution Controls stage
    form.post('/paneta/transactions');
};

const openCamera = () => {
    showCamera.value = true;
};

const handleQRCodeScanned = (qrData: string) => {
    try {
        // Parse QR code data (assuming JSON format)
        const paymentRequest = JSON.parse(qrData);
        scannedPaymentRequest.value = paymentRequest;
        scanStep.value = 'details';
        showCamera.value = false;
    } catch (error) {
        console.error('Invalid QR code data:', error);
        alert('Invalid QR code. Please try again.');
    }
};

const processScannedPayment = () => {
    if (!selectedSourceAccountForScan.value || !scannedPaymentRequest.value) return;
    
    const sourceAccount = props.accounts.find(a => a.id === selectedSourceAccountForScan.value);
    if (!sourceAccount) return;

    // Create form data from scanned payment request
    const paymentForm = useForm({
        payment_method: 'scan',
        source_account_id: selectedSourceAccountForScan.value,
        amount: scannedPaymentRequest.value.amount,
        source_currency: sourceAccount.currency,
        description: scannedPaymentRequest.value.description || '',
        destination_country: scannedPaymentRequest.value.destination_country || '',
        destination_institution_id: scannedPaymentRequest.value.destination_institution_id || null,
        destination_account: scannedPaymentRequest.value.destination_account || scannedPaymentRequest.value.payee_account,
        destination_currency: scannedPaymentRequest.value.currency || scannedPaymentRequest.value.destination_currency,
    });

    // Submit to Pre-Execution Controls
    paymentForm.post('/paneta/transactions');
};

const resetScanFlow = () => {
    scanStep.value = 'camera';
    scannedPaymentRequest.value = null;
    selectedSourceAccountForScan.value = null;
    showCamera.value = false;
};

const processPaymentLink = async () => {
    if (!paymentLink.value) {
        alert('Please enter a payment link');
        return;
    }

    try {
        // Extract payment request ID from link
        // Format: https://paneta.app/pay/{payment_request_id}
        const urlParts = paymentLink.value.split('/');
        const paymentRequestId = urlParts[urlParts.length - 1];

        // In production, this would fetch from API
        // For now, simulate fetching payment request details
        // TODO: Replace with actual API call
        // const response = await fetch(`/api/payment-requests/${paymentRequestId}`);
        // const paymentRequest = await response.json();

        // Simulated payment request data
        const paymentRequest = {
            payment_request_id: paymentRequestId,
            payee_name: 'Jane Smith',
            payee_account: '9876543210',
            amount: '250.00',
            currency: 'USD',
            description: 'Invoice #INV-2024-001',
            destination_country: 'US',
            destination_institution_id: props.institutions.find(i => i.country === 'US')?.id,
            destination_currency: 'USD',
            expires_at: new Date(Date.now() + 7200000).toISOString()
        };

        linkPaymentRequest.value = paymentRequest;
        linkStep.value = 'details';
    } catch (error) {
        console.error('Error processing payment link:', error);
        alert('Invalid payment link. Please check and try again.');
    }
};

const processLinkPayment = () => {
    if (!selectedSourceAccountForLink.value || !linkPaymentRequest.value) return;
    
    const sourceAccount = props.accounts.find(a => a.id === selectedSourceAccountForLink.value);
    if (!sourceAccount) return;

    // Create form data from payment link request
    const paymentForm = useForm({
        payment_method: 'link',
        source_account_id: selectedSourceAccountForLink.value,
        amount: linkPaymentRequest.value.amount,
        source_currency: sourceAccount.currency,
        description: linkPaymentRequest.value.description || '',
        destination_country: linkPaymentRequest.value.destination_country || '',
        destination_institution_id: linkPaymentRequest.value.destination_institution_id || null,
        destination_account: linkPaymentRequest.value.destination_account || linkPaymentRequest.value.payee_account,
        destination_currency: linkPaymentRequest.value.currency || linkPaymentRequest.value.destination_currency,
    });

    // Submit to Pre-Execution Controls
    paymentForm.post('/paneta/transactions');
};

const resetLinkFlow = () => {
    linkStep.value = 'input';
    paymentLink.value = '';
    linkPaymentRequest.value = null;
    selectedSourceAccountForLink.value = null;
};

const isValidAmount = computed(() => {
    const amount = parseFloat(form.amount);
    if (isNaN(amount) || amount <= 0) return false;
    if (!selectedAccount.value) return false;
    return amount <= selectedAccount.value.mock_balance;
});
</script>

<template>
    <Head title="Send Money - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold">Send Money</h1>
                <p class="text-muted-foreground">
                    Send money locally or across borders
                </p>
            </div>

            <!-- Payment Method Tabs -->
            <div class="flex gap-2 border-b">
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-2 px-6 py-3 font-medium transition-colors border-b-2',
                        activeTab === 'manual'
                            ? 'border-primary text-primary bg-primary/5'
                            : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/50',
                    ]"
                    @click="activeTab = 'manual'"
                >
                    <CreditCard class="h-4 w-4" />
                    Manual Payment
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-2 px-6 py-3 font-medium transition-colors border-b-2',
                        activeTab === 'scan'
                            ? 'border-primary text-primary bg-primary/5'
                            : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/50',
                    ]"
                    @click="activeTab = 'scan'"
                >
                    <QrCode class="h-4 w-4" />
                    Scan to Pay
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-2 px-6 py-3 font-medium transition-colors border-b-2',
                        activeTab === 'link'
                            ? 'border-primary text-primary bg-primary/5'
                            : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/50',
                    ]"
                    @click="activeTab = 'link'"
                >
                    <Link2 class="h-4 w-4" />
                    Pay via Link
                </button>
            </div>

            <!-- Manual Payment Tab -->
            <div v-if="activeTab === 'manual'" class="grid gap-6 lg:grid-cols-2">
                <!-- Payment Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>Manual Payment</CardTitle>
                        <CardDescription>
                            Fill in the payment details below
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submit">
                            <!-- 1. Source Account -->
                            <div class="space-y-3">
                                <Label>Source Account</Label>
                                <div class="grid gap-3">
                                    <button
                                        v-for="account in accounts"
                                        :key="account.id"
                                        type="button"
                                        :class="[
                                            'flex items-center justify-between rounded-lg border p-4 text-left transition-colors',
                                            form.source_account_id === account.id
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="form.source_account_id = account.id"
                                    >
                                        <div class="flex items-center gap-3">
                                            <Wallet class="h-5 w-5 text-primary" />
                                            <div>
                                                <p class="font-medium">
                                                    {{ account.institution?.name }}
                                                </p>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ account.account_identifier }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium">
                                                {{
                                                    formatCurrency(
                                                        account.mock_balance,
                                                        account.currency
                                                    )
                                                }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ account.currency }}
                                            </p>
                                        </div>
                                    </button>
                                </div>
                                <p v-if="form.errors.source_account_id" class="text-sm text-red-500">
                                    {{ form.errors.source_account_id }}
                                </p>
                            </div>

                            <!-- 2. Amount & Currency -->
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="amount">Amount</Label>
                                    <Input
                                        id="amount"
                                        v-model="form.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                    />
                                    <p v-if="form.errors.amount" class="text-sm text-red-500">
                                        {{ form.errors.amount }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="source_currency">Currency</Label>
                                    <Input
                                        id="source_currency"
                                        v-model="form.source_currency"
                                        disabled
                                        placeholder="Auto-filled"
                                        class="bg-muted"
                                    />
                                </div>
                            </div>

                            <!-- 3. Description (Optional) -->
                            <div class="space-y-2">
                                <Label for="description">Description (Optional)</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Enter reason for payment"
                                    rows="3"
                                />
                            </div>

                            <!-- 4. Destination Country -->
                            <div class="space-y-2">
                                <Label for="destination_country">Destination Country</Label>
                                <Select v-model="form.destination_country">
                                    <SelectTrigger id="destination_country">
                                        <SelectValue placeholder="Select destination country" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="country in countries"
                                            :key="country.code"
                                            :value="country.code"
                                        >
                                            {{ country.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.destination_country" class="text-sm text-red-500">
                                    {{ form.errors.destination_country }}
                                </p>
                            </div>

                            <!-- 5. Destination Institution -->
                            <div class="space-y-2">
                                <Label for="destination_institution">Destination Institution</Label>
                                <Select v-model="form.destination_institution_id">
                                    <SelectTrigger id="destination_institution">
                                        <SelectValue placeholder="Select destination institution" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="institution in destinationInstitutions"
                                            :key="institution.id"
                                            :value="institution.id.toString()"
                                        >
                                            {{ institution.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.destination_institution_id" class="text-sm text-red-500">
                                    {{ form.errors.destination_institution_id }}
                                </p>
                            </div>

                            <!-- 6. Destination Account & Currency -->
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="destination_account">Destination Account</Label>
                                    <Input
                                        id="destination_account"
                                        v-model="form.destination_account"
                                        placeholder="Enter account number"
                                    />
                                    <p v-if="form.errors.destination_account" class="text-sm text-red-500">
                                        {{ form.errors.destination_account }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="destination_currency">Destination Currency</Label>
                                    <Input
                                        id="destination_currency"
                                        v-model="form.destination_currency"
                                        disabled
                                        placeholder="Auto-filled"
                                        class="bg-muted"
                                    />
                                </div>
                            </div>

                            <!-- Cross-border Alert -->
                            <div
                                v-if="isCrossBorder"
                                class="flex items-start gap-3 rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-900 dark:bg-orange-950"
                            >
                                <AlertCircle class="h-5 w-5 flex-shrink-0 text-orange-600" />
                                <div class="text-sm text-orange-700 dark:text-orange-300">
                                    <p class="font-semibold">Cross-Border Transaction</p>
                                    <p class="mt-1">
                                        This transaction involves different currencies ({{ form.source_currency }} → {{ form.destination_currency }}) and will be flagged as an international transaction.
                                    </p>
                                </div>
                            </div>

                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="
                                    form.processing ||
                                    !form.source_account_id ||
                                    !form.amount ||
                                    !form.destination_country ||
                                    !form.destination_institution_id ||
                                    !form.destination_account ||
                                    !isValidAmount
                                "
                            >
                                <Send class="mr-2 h-4 w-4" />
                                <span v-if="form.processing">Processing...</span>
                                <span v-else>Process Payment</span>
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Summary Card -->
                <div class="space-y-6">
                    <Card v-if="selectedAccount && form.amount">
                        <CardHeader>
                            <CardTitle>Transaction Summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">From</span>
                                <span class="font-medium">{{ selectedAccount.institution?.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Account</span>
                                <span class="text-sm">{{ selectedAccount.account_identifier }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">To</span>
                                <span class="font-medium">{{ form.destination_account || '-' }}</span>
                            </div>
                            <div v-if="form.destination_country" class="flex justify-between">
                                <span class="text-muted-foreground">Destination Country</span>
                                <span>{{ selectedDestinationCountry?.name || form.destination_country }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Amount</span>
                                <span class="text-xl font-bold">
                                    {{
                                        formatCurrency(
                                            parseFloat(form.amount) || 0,
                                            selectedAccount.currency
                                        )
                                    }}
                                </span>
                            </div>
                            <div v-if="isCrossBorder" class="rounded-md bg-orange-50 p-3 dark:bg-orange-950">
                                <p class="text-xs font-semibold text-orange-700 dark:text-orange-300">
                                    Cross-Border Transaction
                                </p>
                                <p class="mt-1 text-xs text-orange-600 dark:text-orange-400">
                                    {{ form.source_currency }} → {{ form.destination_currency }}
                                </p>
                            </div>
                            <hr />
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Available Balance</span>
                                <span>
                                    {{
                                        formatCurrency(
                                            selectedAccount.mock_balance,
                                            selectedAccount.currency
                                        )
                                    }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Remaining Balance</span>
                                <span
                                    :class="
                                        selectedAccount.mock_balance - parseFloat(form.amount) < 0
                                            ? 'text-red-500'
                                            : 'text-green-500'
                                    "
                                >
                                    {{
                                        formatCurrency(
                                            selectedAccount.mock_balance -
                                                (parseFloat(form.amount) || 0),
                                            selectedAccount.currency
                                        )
                                    }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Info Card -->
                    <Card class="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950">
                        <CardContent class="pt-6">
                            <div class="flex gap-3">
                                <AlertCircle class="h-5 w-5 flex-shrink-0 text-blue-600" />
                                <div class="text-sm text-blue-700 dark:text-blue-300">
                                    <p class="font-semibold">Next: Pre-Execution Controls</p>
                                    <p class="mt-1">
                                        After clicking "Process Payment", you will be taken to the Pre-Execution Controls stage where compliance checks and validations will be performed before the transaction is executed.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Empty State -->
                    <Card v-if="accounts.length === 0">
                        <CardContent class="py-12 text-center">
                            <Wallet class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 class="text-lg font-semibold">No accounts available</h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Link an account first to send money
                            </p>
                            <Button as="a" href="/paneta/accounts">Link Account</Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Scan to Pay Tab -->
            <div v-if="activeTab === 'scan'" class="grid gap-6">
                <!-- Step 1: Camera Scanner -->
                <Card v-if="scanStep === 'camera'">
                    <CardHeader>
                        <CardTitle>Scan to Pay</CardTitle>
                        <CardDescription>
                            Point your camera at a payment QR code
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col items-center justify-center py-12 space-y-6">
                            <div class="rounded-full bg-primary/10 p-6">
                                <QrCode class="h-16 w-16 text-primary" />
                            </div>
                            <div class="text-center space-y-2">
                                <h3 class="text-lg font-semibold">Scan QR Code</h3>
                                <p class="text-sm text-muted-foreground max-w-md">
                                    Point your camera at a payment QR code to automatically extract transaction details
                                </p>
                            </div>
                            <Button @click="openCamera" size="lg" class="mt-4">
                                <QrCode class="mr-2 h-5 w-5" />
                                Open Camera
                            </Button>
                            
                            <!-- Demo: Simulate QR Scan for Testing -->
                            <div class="mt-8 pt-8 border-t w-full">
                                <p class="text-xs text-muted-foreground text-center mb-4">
                                    For testing: Simulate a scanned payment request
                                </p>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    @click="handleQRCodeScanned(JSON.stringify({
                                        payment_request_id: 'PR-' + Date.now(),
                                        payee_name: 'John Doe',
                                        payee_account: '1234567890',
                                        amount: '100.00',
                                        currency: 'USD',
                                        description: 'Payment for services',
                                        destination_country: 'US',
                                        destination_institution_id: institutions.find(i => i.country === 'US')?.id,
                                        destination_currency: 'USD',
                                        expires_at: new Date(Date.now() + 3600000).toISOString()
                                    }))"
                                    class="w-full"
                                >
                                    Simulate QR Scan (Demo)
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 2: Payment Request Details & Source Account Selection -->
                <div v-if="scanStep === 'details' && scannedPaymentRequest" class="grid gap-6 lg:grid-cols-2">
                    <!-- Payment Request Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Request Details</CardTitle>
                            <CardDescription>
                                Review the scanned payment request
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="rounded-lg bg-muted/50 p-4 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground">Payee</span>
                                    <span class="font-medium">{{ scannedPaymentRequest.payee_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground">Account</span>
                                    <span class="font-mono text-sm">{{ scannedPaymentRequest.payee_account }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground">Amount</span>
                                    <span class="text-xl font-bold text-primary">
                                        {{ formatCurrency(parseFloat(scannedPaymentRequest.amount), scannedPaymentRequest.currency) }}
                                    </span>
                                </div>
                                <div v-if="scannedPaymentRequest.description" class="pt-3 border-t">
                                    <p class="text-sm text-muted-foreground mb-1">Description</p>
                                    <p class="text-sm">{{ scannedPaymentRequest.description }}</p>
                                </div>
                            </div>

                            <!-- Select Source Account -->
                            <div class="space-y-3 pt-4">
                                <Label>Select Source Account</Label>
                                <div class="grid gap-3">
                                    <button
                                        v-for="account in accounts"
                                        :key="account.id"
                                        type="button"
                                        :class="[
                                            'flex items-center justify-between rounded-lg border p-4 text-left transition-colors',
                                            selectedSourceAccountForScan === account.id
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="selectedSourceAccountForScan = account.id"
                                    >
                                        <div class="flex items-center gap-3">
                                            <Wallet class="h-5 w-5 text-primary" />
                                            <div>
                                                <p class="font-medium">
                                                    {{ account.institution?.name }}
                                                </p>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ account.account_identifier }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium">
                                                {{
                                                    formatCurrency(
                                                        account.mock_balance,
                                                        account.currency
                                                    )
                                                }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ account.currency }}
                                            </p>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <Button 
                                    variant="outline" 
                                    @click="resetScanFlow"
                                    class="flex-1"
                                >
                                    Cancel
                                </Button>
                                <Button 
                                    @click="processScannedPayment"
                                    :disabled="!selectedSourceAccountForScan"
                                    class="flex-1"
                                >
                                    <Send class="mr-2 h-4 w-4" />
                                    Process Payment
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Transaction Summary -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Transaction Summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Payment Method</span>
                                <Badge variant="outline">QR Code Scan</Badge>
                            </div>
                            <div v-if="selectedSourceAccountForScan" class="flex justify-between">
                                <span class="text-muted-foreground">From</span>
                                <span class="font-medium">
                                    {{ accounts.find(a => a.id === selectedSourceAccountForScan)?.institution?.name }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">To</span>
                                <span class="font-medium">{{ scannedPaymentRequest.payee_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Amount</span>
                                <span class="text-xl font-bold">
                                    {{ formatCurrency(parseFloat(scannedPaymentRequest.amount), scannedPaymentRequest.currency) }}
                                </span>
                            </div>
                            <hr />
                            <div v-if="selectedSourceAccountForScan" class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Available Balance</span>
                                    <span>
                                        {{
                                            formatCurrency(
                                                accounts.find(a => a.id === selectedSourceAccountForScan)?.mock_balance || 0,
                                                accounts.find(a => a.id === selectedSourceAccountForScan)?.currency || 'USD'
                                            )
                                        }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Remaining Balance</span>
                                    <span
                                        :class="
                                            (accounts.find(a => a.id === selectedSourceAccountForScan)?.mock_balance || 0) - parseFloat(scannedPaymentRequest.amount) < 0
                                                ? 'text-red-500'
                                                : 'text-green-500'
                                        "
                                    >
                                        {{
                                            formatCurrency(
                                                (accounts.find(a => a.id === selectedSourceAccountForScan)?.mock_balance || 0) -
                                                    parseFloat(scannedPaymentRequest.amount),
                                                accounts.find(a => a.id === selectedSourceAccountForScan)?.currency || 'USD'
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Info Card -->
                    <Card class="lg:col-span-2 border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950">
                        <CardContent class="pt-6">
                            <div class="flex gap-3">
                                <AlertCircle class="h-5 w-5 flex-shrink-0 text-blue-600" />
                                <div class="text-sm text-blue-700 dark:text-blue-300">
                                    <p class="font-semibold">Next: Pre-Execution Controls</p>
                                    <p class="mt-1">
                                        After clicking "Process Payment", you will be taken to the Pre-Execution Controls stage where compliance checks and validations will be performed before the transaction is executed.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Pay via Link Tab -->
            <div v-if="activeTab === 'link'" class="grid gap-6">
                <!-- Step 1: Payment Link Input -->
                <Card v-if="linkStep === 'input'">
                    <CardHeader>
                        <CardTitle>Pay via Link</CardTitle>
                        <CardDescription>
                            Paste a payment link to proceed
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col items-center justify-center py-12 space-y-6">
                            <div class="rounded-full bg-primary/10 p-6">
                                <Link2 class="h-16 w-16 text-primary" />
                            </div>
                            <div class="text-center space-y-2">
                                <h3 class="text-lg font-semibold">Payment Link Setup</h3>
                                <p class="text-sm text-muted-foreground max-w-md">
                                    Paste a payment link to automatically extract transaction details
                                </p>
                            </div>
                            
                            <div class="w-full max-w-2xl space-y-4">
                                <div class="space-y-2">
                                    <Label for="payment_link">Paste Payment Link</Label>
                                    <div class="flex gap-2">
                                        <Input
                                            id="payment_link"
                                            v-model="paymentLink"
                                            type="url"
                                            placeholder="https://paneta.app/pay/..."
                                            class="flex-1"
                                        />
                                        <Button 
                                            @click="processPaymentLink"
                                            :disabled="!paymentLink"
                                        >
                                            Parse Link
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Demo: Simulate Payment Link for Testing -->
                            <div class="mt-8 pt-8 border-t w-full max-w-2xl">
                                <p class="text-xs text-muted-foreground text-center mb-4">
                                    For testing: Use a sample payment link
                                </p>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    @click="() => {
                                        paymentLink = 'https://paneta.app/pay/PR-' + Date.now();
                                        processPaymentLink();
                                    }"
                                    class="w-full"
                                >
                                    Use Sample Link (Demo)
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 2: Payment Request Details & Source Account Selection -->
                <div v-if="linkStep === 'details' && linkPaymentRequest" class="grid gap-6 lg:grid-cols-2">
                    <!-- Payment Request Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Request Details</CardTitle>
                            <CardDescription>
                                Review the payment request from link
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="rounded-lg bg-muted/50 p-4 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground">Payee</span>
                                    <span class="font-medium">{{ linkPaymentRequest.payee_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground">Account</span>
                                    <span class="font-mono text-sm">{{ linkPaymentRequest.payee_account }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-muted-foreground">Amount</span>
                                    <span class="text-xl font-bold text-primary">
                                        {{ formatCurrency(parseFloat(linkPaymentRequest.amount), linkPaymentRequest.currency) }}
                                    </span>
                                </div>
                                <div v-if="linkPaymentRequest.description" class="pt-3 border-t">
                                    <p class="text-sm text-muted-foreground mb-1">Description</p>
                                    <p class="text-sm">{{ linkPaymentRequest.description }}</p>
                                </div>
                            </div>

                            <!-- Select Source Account -->
                            <div class="space-y-3 pt-4">
                                <Label>Select Source Account</Label>
                                <div class="grid gap-3">
                                    <button
                                        v-for="account in accounts"
                                        :key="account.id"
                                        type="button"
                                        :class="[
                                            'flex items-center justify-between rounded-lg border p-4 text-left transition-colors',
                                            selectedSourceAccountForLink === account.id
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="selectedSourceAccountForLink = account.id"
                                    >
                                        <div class="flex items-center gap-3">
                                            <Wallet class="h-5 w-5 text-primary" />
                                            <div>
                                                <p class="font-medium">
                                                    {{ account.institution?.name }}
                                                </p>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ account.account_identifier }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium">
                                                {{
                                                    formatCurrency(
                                                        account.mock_balance,
                                                        account.currency
                                                    )
                                                }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ account.currency }}
                                            </p>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-4">
                                <Button 
                                    variant="outline" 
                                    @click="resetLinkFlow"
                                    class="flex-1"
                                >
                                    Cancel
                                </Button>
                                <Button 
                                    @click="processLinkPayment"
                                    :disabled="!selectedSourceAccountForLink"
                                    class="flex-1"
                                >
                                    <Send class="mr-2 h-4 w-4" />
                                    Process Payment
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Transaction Summary -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Transaction Summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Payment Method</span>
                                <Badge variant="outline">Payment Link</Badge>
                            </div>
                            <div v-if="selectedSourceAccountForLink" class="flex justify-between">
                                <span class="text-muted-foreground">From</span>
                                <span class="font-medium">
                                    {{ accounts.find(a => a.id === selectedSourceAccountForLink)?.institution?.name }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">To</span>
                                <span class="font-medium">{{ linkPaymentRequest.payee_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Amount</span>
                                <span class="text-xl font-bold">
                                    {{ formatCurrency(parseFloat(linkPaymentRequest.amount), linkPaymentRequest.currency) }}
                                </span>
                            </div>
                            <hr />
                            <div v-if="selectedSourceAccountForLink" class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Available Balance</span>
                                    <span>
                                        {{
                                            formatCurrency(
                                                accounts.find(a => a.id === selectedSourceAccountForLink)?.mock_balance || 0,
                                                accounts.find(a => a.id === selectedSourceAccountForLink)?.currency || 'USD'
                                            )
                                        }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Remaining Balance</span>
                                    <span
                                        :class="
                                            (accounts.find(a => a.id === selectedSourceAccountForLink)?.mock_balance || 0) - parseFloat(linkPaymentRequest.amount) < 0
                                                ? 'text-red-500'
                                                : 'text-green-500'
                                        "
                                    >
                                        {{
                                            formatCurrency(
                                                (accounts.find(a => a.id === selectedSourceAccountForLink)?.mock_balance || 0) -
                                                    parseFloat(linkPaymentRequest.amount),
                                                accounts.find(a => a.id === selectedSourceAccountForLink)?.currency || 'USD'
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Info Card -->
                    <Card class="lg:col-span-2 border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950">
                        <CardContent class="pt-6">
                            <div class="flex gap-3">
                                <AlertCircle class="h-5 w-5 flex-shrink-0 text-blue-600" />
                                <div class="text-sm text-blue-700 dark:text-blue-300">
                                    <p class="font-semibold">Next: Pre-Execution Controls</p>
                                    <p class="mt-1">
                                        After clicking "Process Payment", you will be taken to the Pre-Execution Controls stage where compliance checks and validations will be performed before the transaction is executed.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
