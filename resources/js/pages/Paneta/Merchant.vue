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
import { Store, Smartphone, QrCode, CreditCard, Plus, Settings, CheckCircle, Clock, XCircle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Merchant {
    id: number;
    business_name: string;
    business_registration_number: string | null;
    business_type: string | null;
    country: string;
    kyb_status: 'pending' | 'verified' | 'rejected';
    is_active: boolean;
    default_currency: string;
    settlement_account_id: number | null;
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
    merchant: Merchant | null;
    devices: MerchantDevice[];
    linkedAccounts: LinkedAccount[];
    recentPayments: any[];
    stats: MerchantStats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Merchant SoftPOS' },
];

const showRegisterDialog = ref(false);
const showDeviceDialog = ref(false);
const showQrDialog = ref(false);
const generatedQr = ref<any>(null);

const registerForm = useForm({
    business_name: '',
    business_registration_number: '',
    business_type: '',
    country: 'ZA',
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
            showRegisterDialog.value = false;
            registerForm.reset();
        },
    });
};

const submitDevice = () => {
    if (!props.merchant) return;
    deviceForm.post(`/paneta/merchant/${props.merchant.id}/devices`, {
        onSuccess: () => {
            showDeviceDialog.value = false;
            deviceForm.reset();
        },
    });
};

const generateQrCode = async () => {
    if (!props.merchant || !qrForm.device_id || !qrForm.amount) return;

    try {
        const response = await fetch(`/paneta/merchant/${props.merchant.id}/qr`, {
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
                        Accept payments using your mobile device as a point-of-sale terminal
                    </p>
                </div>
                <Badge variant="outline" class="text-blue-600 border-blue-600">
                    <Store class="mr-1 h-3 w-3" />
                    SoftPOS Enabled
                </Badge>
            </div>

            <!-- Not Registered State -->
            <div v-if="!merchant" class="flex flex-col items-center justify-center py-16">
                <div class="rounded-full bg-primary/10 p-6 mb-6">
                    <Store class="h-16 w-16 text-primary" />
                </div>
                <h2 class="text-xl font-semibold mb-2">Become a Merchant</h2>
                <p class="text-muted-foreground text-center max-w-md mb-6">
                    Register your business to start accepting payments through PANÉTA's SoftPOS solution. 
                    Turn any smartphone into a payment terminal.
                </p>
                
                <Dialog v-model:open="showRegisterDialog">
                    <DialogTrigger as-child>
                        <Button size="lg">
                            <Plus class="mr-2 h-4 w-4" />
                            Register as Merchant
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Register Your Business</DialogTitle>
                            <DialogDescription>
                                Provide your business details to start accepting payments
                            </DialogDescription>
                        </DialogHeader>
                        <form @submit.prevent="submitRegistration" class="space-y-4">
                            <div class="space-y-2">
                                <Label>Business Name</Label>
                                <Input v-model="registerForm.business_name" placeholder="Your Business Name" required />
                            </div>
                            <div class="space-y-2">
                                <Label>Registration Number (Optional)</Label>
                                <Input v-model="registerForm.business_registration_number" placeholder="Business registration number" />
                            </div>
                            <div class="space-y-2">
                                <Label>Business Type</Label>
                                <Select v-model="registerForm.business_type">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="retail">Retail</SelectItem>
                                        <SelectItem value="restaurant">Restaurant</SelectItem>
                                        <SelectItem value="services">Services</SelectItem>
                                        <SelectItem value="ecommerce">E-Commerce</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Country</Label>
                                <Select v-model="registerForm.country">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="ZA">South Africa</SelectItem>
                                        <SelectItem value="ZW">Zimbabwe</SelectItem>
                                        <SelectItem value="US">United States</SelectItem>
                                        <SelectItem value="GB">United Kingdom</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="registerForm.processing">
                                    Submit for Verification
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Merchant Dashboard -->
            <template v-else>
                <!-- Merchant Info Card -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Store class="h-5 w-5" />
                                    {{ merchant.business_name }}
                                </CardTitle>
                                <CardDescription>
                                    {{ merchant.business_type || 'Business' }} • {{ merchant.country }}
                                </CardDescription>
                            </div>
                            <Badge :class="getStatusBadge(merchant.kyb_status).class">
                                <component :is="getStatusBadge(merchant.kyb_status).icon" class="mr-1 h-3 w-3" />
                                {{ merchant.kyb_status.charAt(0).toUpperCase() + merchant.kyb_status.slice(1) }}
                            </Badge>
                        </div>
                    </CardHeader>
                </Card>

                <!-- Stats Grid -->
                <div class="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-center gap-4">
                                <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                                    <CreditCard class="h-6 w-6 text-blue-600" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Transactions</p>
                                    <p class="text-2xl font-bold">{{ stats.total_transactions }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-center gap-4">
                                <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                                    <Store class="h-6 w-6 text-green-600" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Volume</p>
                                    <p class="text-2xl font-bold">{{ formatCurrency(stats.total_volume, merchant.default_currency || 'USD') }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-center gap-4">
                                <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                                    <Smartphone class="h-6 w-6 text-purple-600" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Active Devices</p>
                                    <p class="text-2xl font-bold">{{ stats.active_devices }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-center gap-4">
                                <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                                    <Clock class="h-6 w-6 text-orange-600" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Today</p>
                                    <p class="text-2xl font-bold">{{ stats.today_transactions }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Devices -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="flex items-center gap-2">
                                        <Smartphone class="h-5 w-5" />
                                        SoftPOS Devices
                                    </CardTitle>
                                    <CardDescription>Manage your payment terminals</CardDescription>
                                </div>
                                <Dialog v-model:open="showDeviceDialog">
                                    <DialogTrigger as-child>
                                        <Button size="sm" :disabled="!merchant.is_active">
                                            <Plus class="mr-1 h-4 w-4" />
                                            Add Device
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Register New Device</DialogTitle>
                                            <DialogDescription>
                                                Add a new SoftPOS terminal device
                                            </DialogDescription>
                                        </DialogHeader>
                                        <form @submit.prevent="submitDevice" class="space-y-4">
                                            <div class="space-y-2">
                                                <Label>Device Name</Label>
                                                <Input v-model="deviceForm.device_name" placeholder="e.g., Front Counter" />
                                            </div>
                                            <div class="space-y-2">
                                                <Label>Device Type</Label>
                                                <Select v-model="deviceForm.device_type">
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="mobile">Mobile Phone</SelectItem>
                                                        <SelectItem value="tablet">Tablet</SelectItem>
                                                        <SelectItem value="terminal">Terminal</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <DialogFooter>
                                                <Button type="submit" :disabled="deviceForm.processing">
                                                    Register Device
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="devices.length === 0" class="flex flex-col items-center py-8 text-center">
                                <Smartphone class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-muted-foreground">No devices registered yet</p>
                            </div>
                            <div v-else class="space-y-3">
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

                    <!-- Generate QR -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <QrCode class="h-5 w-5" />
                                Generate Payment QR
                            </CardTitle>
                            <CardDescription>
                                Create a QR code for customers to scan and pay
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="!merchant.is_active" class="flex flex-col items-center py-8 text-center">
                                <Clock class="mb-4 h-12 w-12 text-yellow-500" />
                                <p class="text-muted-foreground">
                                    Your merchant account is pending verification. QR generation will be available once approved.
                                </p>
                            </div>
                            <div v-else-if="activeDevices.length === 0" class="flex flex-col items-center py-8 text-center">
                                <Smartphone class="mb-4 h-12 w-12 text-muted-foreground" />
                                <p class="text-muted-foreground">
                                    Register a device first to generate payment QR codes
                                </p>
                            </div>
                            <form v-else @submit.prevent="generateQrCode" class="space-y-4">
                                <div class="space-y-2">
                                    <Label>Device</Label>
                                    <Select v-model="qrForm.device_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select device" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="device in activeDevices"
                                                :key="device.id"
                                                :value="device.id"
                                            >
                                                {{ device.device_name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Amount</Label>
                                    <Input v-model.number="qrForm.amount" type="number" min="0.01" step="0.01" placeholder="0.00" />
                                </div>
                                <div class="space-y-2">
                                    <Label>Description (Optional)</Label>
                                    <Input v-model="qrForm.description" placeholder="Payment for..." />
                                </div>
                                <Button type="submit" class="w-full" :disabled="!qrForm.device_id || !qrForm.amount">
                                    <QrCode class="mr-2 h-4 w-4" />
                                    Generate QR Code
                                </Button>

                                <!-- Generated QR Display -->
                                <div v-if="generatedQr" class="mt-6 rounded-lg border p-4 text-center">
                                    <div class="bg-white p-4 rounded-lg inline-block mb-4">
                                        <div class="w-48 h-48 bg-gray-200 flex items-center justify-center">
                                            <QrCode class="h-32 w-32 text-gray-800" />
                                        </div>
                                    </div>
                                    <p class="font-medium">{{ formatCurrency(generatedQr.amount, generatedQr.currency) }}</p>
                                    <p class="text-sm text-muted-foreground">Ref: {{ generatedQr.reference }}</p>
                                    <p class="text-xs text-muted-foreground mt-2">
                                        Expires: {{ new Date(generatedQr.expires_at).toLocaleString() }}
                                    </p>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

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
        </div>
    </AppLayout>
</template>
