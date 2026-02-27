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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BreadcrumbItem, LinkedAccount } from '@/types';
import { Store, Smartphone, QrCode, CreditCard, Plus, Settings, CheckCircle, Clock, XCircle, Building2, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Merchant {
    id: number;
    business_name: string;
    business_registration_number: string | null;
    business_type: string | null;
    business_sector: string | null;
    country: string;
    tax_id: string | null;
    business_logo: string | null;
    kyb_status: 'pending' | 'verified' | 'rejected';
    is_active: boolean;
    default_currency: string;
    reporting_currency: string | null;
    settlement_account_id: number | null;
    other_settlement_accounts: number[] | null;
}

interface MerchantDevice {
    id: number;
    device_identifier: string;
    device_name: string;
    device_type: string;
    status: 'active' | 'inactive';
    last_activity_at: string | null;
}

interface MerchantStats {
    total_transactions: number;
    total_volume: number;
    active_devices: number;
    today_transactions: number;
}

const props = defineProps<{
    merchants: Merchant[];
    devices: MerchantDevice[];
    linkedAccounts: LinkedAccount[];
    recentPayments: any[];
    stats: MerchantStats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Merchant SoftPOS' },
];

const activeTab = ref('register');
const showDeviceDialog = ref(false);
const generatedQr = ref<any>(null);

const registerForm = useForm({
    business_name: '',
    business_type: '',
    business_registration_number: '',
    business_sector: '',
    country: 'ZA',
    business_logo: '',
    tax_id: '',
    reporting_currency: 'USD',
    settlement_account_id: null as number | null,
    other_settlement_accounts: [] as number[],
});

const deviceForm = useForm({
    device_name: '',
    device_type: 'mobile',
});

const qrForm = useForm({
    device_id: null as number | null,
    amount: 0,
    description: '',
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
        case 'verified':
            return { variant: 'default' as const, class: 'bg-green-600', icon: CheckCircle };
        case 'pending':
            return { variant: 'outline' as const, class: 'text-yellow-600 border-yellow-600', icon: Clock };
        case 'rejected':
            return { variant: 'destructive' as const, class: '', icon: XCircle };
        default:
            return { variant: 'outline' as const, class: '', icon: Clock };
    }
};

const submitRegistration = () => {
    registerForm.post('/paneta/merchant/register', {
        onSuccess: () => {
            registerForm.reset();
            registerForm.country = 'ZA';
            registerForm.reporting_currency = 'USD';
        },
    });
};

const addSettlementAccount = () => {
    if (registerForm.settlement_account_id && !registerForm.other_settlement_accounts.includes(registerForm.settlement_account_id)) {
        registerForm.other_settlement_accounts.push(registerForm.settlement_account_id);
        registerForm.settlement_account_id = null;
    }
};

const removeSettlementAccount = (index: number) => {
    registerForm.other_settlement_accounts.splice(index, 1);
};

const submitDevice = () => {
    if (!props.merchants || props.merchants.length === 0) return;
    const firstMerchant = props.merchants[0];
    deviceForm.post(`/paneta/merchant/${firstMerchant.id}/devices`, {
        onSuccess: () => {
            showDeviceDialog.value = false;
            deviceForm.reset();
        },
    });
};

const generateQrCode = async () => {
    if (!props.merchants || props.merchants.length === 0 || !qrForm.device_id || !qrForm.amount) return;
    const firstMerchant = props.merchants[0];

    try {
        const response = await fetch(`/paneta/merchant/${firstMerchant.id}/qr`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                device_id: qrForm.device_id,
                amount: qrForm.amount,
                description: qrForm.description,
            }),
        });

        const data = await response.json();
        if (data.success) {
            generatedQr.value = data.qr_data;
        }
    } catch (error) {
        console.error('Failed to generate QR:', error);
    }
};

const activeDevices = computed(() => props.devices.filter(d => d.status === 'active'));
</script>

<template>
    <Head title="Merchant SoftPOS - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Merchant SoftPOS</h1>
                    <p class="text-muted-foreground">
                        Register your business, activate SoftPOS, and manage your merchant accounts
                    </p>
                </div>
                <Badge variant="outline" class="text-blue-600 border-blue-600">
                    <Store class="mr-1 h-3 w-3" />
                    SoftPOS System
                </Badge>
            </div>

            <!-- Tabs Navigation -->
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="register">
                        <Building2 class="mr-2 h-4 w-4" />
                        Register as Merchant
                    </TabsTrigger>
                    <TabsTrigger value="activate">
                        <CreditCard class="mr-2 h-4 w-4" />
                        Accept Payment
                    </TabsTrigger>
                    <TabsTrigger value="accounts">
                        <FileText class="mr-2 h-4 w-4" />
                        View Consolidated Accounts Summary
                    </TabsTrigger>
                </TabsList>

                <!-- Register as Merchant Tab -->
                <TabsContent value="register" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Register Your Business</CardTitle>
                            <CardDescription>
                                Register your merchant or business accounts for ease, convenience and secure management of business financials
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submitRegistration" class="space-y-6">
                                <div class="grid gap-6 md:grid-cols-2">
                                    <!-- Business Name -->
                                    <div class="space-y-2">
                                        <Label>Business Name *</Label>
                                        <Input v-model="registerForm.business_name" placeholder="Enter business name" required />
                                    </div>

                                    <!-- Company Type -->
                                    <div class="space-y-2">
                                        <Label>Company Type</Label>
                                        <Select v-model="registerForm.business_type">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select company type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Sole Proprietorship">Sole Proprietorship</SelectItem>
                                                <SelectItem value="Pvt Ltd">Pvt Ltd</SelectItem>
                                                <SelectItem value="NGO">NGO</SelectItem>
                                                <SelectItem value="Partnership">Partnership</SelectItem>
                                                <SelectItem value="Public Company">Public Company</SelectItem>
                                                <SelectItem value="Other">Other</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Registration Number -->
                                    <div class="space-y-2">
                                        <Label>Registration Number (Optional)</Label>
                                        <Input v-model="registerForm.business_registration_number" placeholder="Business registration number" />
                                    </div>

                                    <!-- Business Sector -->
                                    <div class="space-y-2">
                                        <Label>Business Sector</Label>
                                        <Select v-model="registerForm.business_sector">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select business sector" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Retail">Retail</SelectItem>
                                                <SelectItem value="Restaurant">Restaurant</SelectItem>
                                                <SelectItem value="Services">Services</SelectItem>
                                                <SelectItem value="E-Commerce">E-Commerce</SelectItem>
                                                <SelectItem value="Healthcare">Healthcare</SelectItem>
                                                <SelectItem value="Education">Education</SelectItem>
                                                <SelectItem value="Technology">Technology</SelectItem>
                                                <SelectItem value="Manufacturing">Manufacturing</SelectItem>
                                                <SelectItem value="Agriculture">Agriculture</SelectItem>
                                                <SelectItem value="Other">Other</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Country -->
                                    <div class="space-y-2">
                                        <Label>Country *</Label>
                                        <Select v-model="registerForm.country">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="ZA">South Africa</SelectItem>
                                                <SelectItem value="ZW">Zimbabwe</SelectItem>
                                                <SelectItem value="US">United States</SelectItem>
                                                <SelectItem value="GB">United Kingdom</SelectItem>
                                                <SelectItem value="KE">Kenya</SelectItem>
                                                <SelectItem value="NG">Nigeria</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Business Logo -->
                                    <div class="space-y-2">
                                        <Label>Business Logo (Optional)</Label>
                                        <Input v-model="registerForm.business_logo" placeholder="Logo URL" />
                                    </div>

                                    <!-- Tax ID -->
                                    <div class="space-y-2">
                                        <Label>Tax ID (Optional)</Label>
                                        <Input v-model="registerForm.tax_id" placeholder="Tax identification number" />
                                    </div>

                                    <!-- Reporting Currency -->
                                    <div class="space-y-2">
                                        <Label>Reporting Currency</Label>
                                        <Select v-model="registerForm.reporting_currency">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="USD">USD - US Dollar</SelectItem>
                                                <SelectItem value="ZAR">ZAR - South African Rand</SelectItem>
                                                <SelectItem value="ZWL">ZWL - Zimbabwe Dollar</SelectItem>
                                                <SelectItem value="EUR">EUR - Euro</SelectItem>
                                                <SelectItem value="GBP">GBP - British Pound</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <!-- Primary Merchant Settlement Account -->
                                <div class="space-y-2">
                                    <Label>Primary Merchant Settlement Account</Label>
                                    <Select v-model="registerForm.settlement_account_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select primary settlement account" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="account in linkedAccounts"
                                                :key="account.id"
                                                :value="account.id"
                                            >
                                                {{ account.institution?.name }} - {{ account.account_identifier }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Other Merchant Settlement Accounts -->
                                <div class="space-y-2">
                                    <Label>Other Merchant Settlement Accounts</Label>
                                    <div class="flex gap-2">
                                        <Select v-model="registerForm.settlement_account_id" class="flex-1">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select additional settlement account" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="account in linkedAccounts"
                                                    :key="account.id"
                                                    :value="account.id"
                                                >
                                                    {{ account.institution?.name }} - {{ account.account_number }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Button type="button" @click="addSettlementAccount" variant="outline">
                                            <Plus class="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div v-if="registerForm.other_settlement_accounts.length > 0" class="mt-2 space-y-2">
                                        <div
                                            v-for="(accountId, index) in registerForm.other_settlement_accounts"
                                            :key="index"
                                            class="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <span class="text-sm">
                                                {{ linkedAccounts.find(a => a.id === accountId)?.institution?.name }} - 
                                                {{ linkedAccounts.find(a => a.id === accountId)?.account_identifier }}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="removeSettlementAccount(index)"
                                            >
                                                <XCircle class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <Button type="submit" :disabled="registerForm.processing">
                                        Register Business
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Registered Merchants List -->
                    <Card v-if="merchants && merchants.length > 0">
                        <CardHeader>
                            <CardTitle>Your Registered Businesses</CardTitle>
                            <CardDescription>You have {{ merchants.length }} registered business{{ merchants.length > 1 ? 'es' : '' }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="merchant in merchants"
                                    :key="merchant.id"
                                    class="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="rounded-full bg-primary/10 p-3">
                                            <Store class="h-5 w-5 text-primary" />
                                        </div>
                                        <div>
                                            <p class="font-medium">{{ merchant.business_name }}</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ merchant.business_type || 'Business' }} • {{ merchant.country }}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge :class="getStatusBadge(merchant.kyb_status).class">
                                        <component :is="getStatusBadge(merchant.kyb_status).icon" class="mr-1 h-3 w-3" />
                                        {{ merchant.kyb_status.charAt(0).toUpperCase() + merchant.kyb_status.slice(1) }}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Registered Devices List -->
                    <Card v-if="devices && devices.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Smartphone class="h-5 w-5" />
                                Registered SoftPOS Devices
                            </CardTitle>
                            <CardDescription>You have {{ devices.length }} registered device{{ devices.length > 1 ? 's' : '' }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="device in devices"
                                    :key="device.id"
                                    class="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="rounded-full bg-primary/10 p-2">
                                            <Smartphone class="h-4 w-4 text-primary" />
                                        </div>
                                        <div>
                                            <p class="font-medium">{{ device.device_name }}</p>
                                            <p class="text-sm text-muted-foreground">{{ device.device_identifier }}</p>
                                        </div>
                                    </div>
                                    <Badge :variant="device.status === 'active' ? 'default' : 'secondary'">
                                        {{ device.status }}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Accept Payment Tab -->
                <TabsContent value="activate" class="space-y-6">

                    <Card v-if="!merchants || merchants.length === 0">
                        <CardContent class="pt-6">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="rounded-full bg-primary/10 p-6 mb-4">
                                    <Smartphone class="h-12 w-12 text-primary" />
                                </div>
                                <h3 class="text-lg font-semibold mb-2">Register Your Business First</h3>
                                <p class="text-muted-foreground max-w-md">
                                    You need to register as a merchant before you can accept payments.
                                    Please go to the "Register as Merchant" tab to get started.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Not Verified State -->
                    <Card v-else-if="merchants[0].kyb_status !== 'verified'">
                        <CardContent class="pt-6">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="rounded-full bg-yellow-100 p-6 mb-4 dark:bg-yellow-900">
                                    <Clock class="h-12 w-12 text-yellow-600" />
                                </div>
                                <h3 class="text-lg font-semibold mb-2">Verification Pending</h3>
                                <p class="text-muted-foreground max-w-md">
                                    Your merchant account is currently under review. You'll be able to accept payments once your account is verified and approved.
                                </p>
                                <Badge class="mt-4" :class="getStatusBadge(merchants[0].kyb_status).class">
                                    <component :is="getStatusBadge(merchants[0].kyb_status).icon" class="mr-1 h-3 w-3" />
                                    {{ merchants[0].kyb_status.charAt(0).toUpperCase() + merchants[0].kyb_status.slice(1) }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Verified and Active - Payment Terminal Interface -->
                    <template v-else>
                        <!-- Terminal Status Header -->
                        <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-lg p-6 text-white">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-white/20 p-3">
                                        <Smartphone class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold">SoftPOS Terminal Active</h3>
                                        <p class="text-sm text-white/80">Ready to accept payments</p>
                                    </div>
                                </div>
                                <Badge class="bg-green-500 text-white border-0">
                                    LIVE
                                </Badge>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <p class="text-2xl font-bold">${{ stats.total_volume.toLocaleString() }}</p>
                                    <p class="text-sm text-white/80">Today's Sales</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">{{ stats.today_transactions }}</p>
                                    <p class="text-sm text-white/80">Transactions</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">98%</p>
                                    <p class="text-sm text-white/80">Success Rate</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Interface -->
                        <div class="grid gap-6 lg:grid-cols-2">
                            <!-- Enter Payment Amount -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Enter Payment Amount</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="space-y-2">
                                        <Input
                                            v-model="qrForm.amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            placeholder="345"
                                            class="text-center text-3xl font-bold h-16"
                                        />
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="qrForm.amount = 10"
                                        >
                                            $10
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="qrForm.amount = 25"
                                        >
                                            $25
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="qrForm.amount = 50"
                                        >
                                            $50
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Payment Options -->
                            <Card>
                                <CardHeader>
                                    <CardTitle>Payment Options</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <Button
                                        @click="generateQrCode"
                                        class="w-full bg-purple-600 hover:bg-purple-700"
                                        :disabled="!qrForm.amount || qrForm.amount <= 0"
                                    >
                                        <QrCode class="mr-2 h-4 w-4" />
                                        Generate QR Code
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="w-full"
                                        :disabled="!qrForm.amount || qrForm.amount <= 0"
                                    >
                                        <Smartphone class="mr-2 h-4 w-4" />
                                        NFC Payment
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="w-full"
                                        :disabled="!qrForm.amount || qrForm.amount <= 0"
                                    >
                                        <CreditCard class="mr-2 h-4 w-4" />
                                        Card Payment
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- QR Code Display -->
                        <Card v-if="generatedQr" class="bg-green-50 dark:bg-green-950">
                            <CardContent class="pt-6">
                                <div class="flex flex-col items-center justify-center py-8 text-center">
                                    <div class="bg-white p-6 rounded-lg inline-block mb-4">
                                        <div class="w-64 h-64 bg-gray-100 flex items-center justify-center rounded-lg border-2 border-green-500">
                                            <QrCode class="h-48 w-48 text-green-600" />
                                        </div>
                                    </div>
                                    <h3 class="text-lg font-semibold text-green-700 dark:text-green-400 mb-2">Payment QR Code Generated</h3>
                                    <p class="text-muted-foreground mb-2">Customer can scan to pay ${{ qrForm.amount }}</p>
                                    <div class="flex gap-2 mt-4">
                                        <Button variant="outline" size="sm">
                                            Download
                                        </Button>
                                        <Button variant="outline" size="sm" @click="generatedQr = null">
                                            Regenerate
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Recent Payments -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CreditCard class="h-5 w-5" />
                            Recent Payments
                        </CardTitle>
                        <CardDescription>
                            Latest transactions received through your SoftPOS terminals
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentPayments.length === 0" class="flex flex-col items-center py-8 text-center">
                            <CreditCard class="mb-4 h-12 w-12 text-muted-foreground" />
                            <p class="text-muted-foreground">No payments received yet</p>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="payment in recentPayments"
                                :key="payment.id"
                                class="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div>
                                    <p class="font-medium">{{ formatCurrency(payment.amount, payment.currency) }}</p>
                                    <p class="text-sm text-muted-foreground">{{ payment.description || 'Payment' }}</p>
                                </div>
                                <div class="text-right">
                                    <Badge variant="default" class="bg-green-600">Completed</Badge>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        {{ new Date(payment.created_at).toLocaleString() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                        </Card>
                    </template>
                </TabsContent>

                <!-- View Consolidated Accounts Summary Tab -->
                <TabsContent value="accounts" class="space-y-6">
                    <Card v-if="!merchants || merchants.length === 0">
                        <CardContent class="pt-6">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="rounded-full bg-primary/10 p-6 mb-4">
                                    <FileText class="h-12 w-12 text-primary" />
                                </div>
                                <h3 class="text-lg font-semibold mb-2">Register Your Business First</h3>
                                <p class="text-muted-foreground max-w-md">
                                    You need to register as a merchant before you can view consolidated accounts.
                                    Please go to the "Register as Merchant" tab to get started.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <template v-else>
                        <!-- Loop through all merchants -->
                        <Card
                            v-for="merchant in merchants"
                            :key="merchant.id"
                            class="mb-6"
                        >
                            <CardHeader>
                                <CardTitle>{{ merchant.business_name }}</CardTitle>
                                <CardDescription>
                                    Business account summary and settlement information
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-6">
                                    <!-- Merchant Info -->
                                    <div class="rounded-lg border p-4">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h3 class="font-semibold text-lg">{{ merchant.business_name }}</h3>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ merchant.business_type || 'Business' }} • {{ merchant.business_sector || 'N/A' }}
                                                </p>
                                            </div>
                                            <Badge :class="getStatusBadge(merchant.kyb_status).class">
                                                <component :is="getStatusBadge(merchant.kyb_status).icon" class="mr-1 h-3 w-3" />
                                                {{ merchant.kyb_status.charAt(0).toUpperCase() + merchant.kyb_status.slice(1) }}
                                            </Badge>
                                        </div>
                                        <div class="grid gap-2 text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Registration Number:</span>
                                                <span>{{ merchant.business_registration_number || 'N/A' }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Tax ID:</span>
                                                <span>{{ merchant.tax_id || 'N/A' }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Country:</span>
                                                <span>{{ merchant.country }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Reporting Currency:</span>
                                                <span>{{ merchant.reporting_currency || merchant.default_currency || 'USD' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Settlement Accounts -->
                                    <div>
                                        <h4 class="font-semibold mb-3">Settlement Accounts</h4>
                                        <div class="space-y-3">
                                            <div v-if="merchant.settlement_account_id" class="rounded-lg border p-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="rounded-full bg-green-100 p-2 dark:bg-green-900">
                                                        <CreditCard class="h-4 w-4 text-green-600" />
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="font-medium">Primary Settlement Account</p>
                                                        <p class="text-sm text-muted-foreground">
                                                            {{ linkedAccounts.find(a => a.id === merchant.settlement_account_id)?.institution?.name || 'Account' }}
                                                        </p>
                                                    </div>
                                                    <Badge variant="default">Primary</Badge>
                                                </div>
                                            </div>
                                            <div v-else class="rounded-lg border border-dashed p-4 text-center">
                                                <p class="text-sm text-muted-foreground">No primary settlement account set</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Overall Transaction Summary -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Overall Transaction Summary</CardTitle>
                                <CardDescription>Combined statistics across all your merchant accounts</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="rounded-lg border p-4">
                                        <p class="text-sm text-muted-foreground">Total Transactions</p>
                                        <p class="text-2xl font-bold">{{ stats.total_transactions }}</p>
                                    </div>
                                    <div class="rounded-lg border p-4">
                                        <p class="text-sm text-muted-foreground">Total Volume</p>
                                        <p class="text-2xl font-bold">{{ formatCurrency(stats.total_volume, 'USD') }}</p>
                                    </div>
                                    <div class="rounded-lg border p-4">
                                        <p class="text-sm text-muted-foreground">Active Devices</p>
                                        <p class="text-2xl font-bold">{{ stats.active_devices }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </template>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
