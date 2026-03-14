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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { BreadcrumbItem } from '@/types';
import { Upload, FileText, CheckCircle, Shield, Download, BarChart3 } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface BatchPaymentItem {
    receiver_name: string;
    institution_name: string;
    account_number: string;
    amount: number;
    currency: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Batch Payment Processing' },
];

const uploadedFile = ref<File | null>(null);
const parsedPayments = ref<BatchPaymentItem[]>([]);
const showSummary = ref(false);

const uploadForm = useForm({
    file: null as File | null,
    processing_mode: 'immediate',
    error_handling: 'skip',
    email_notifications: true,
    sms_alerts: false,
    dashboard_updates: true,
});

const PANETA_FEE_RATE = 0.0099; // 0.99%

const totalAmount = computed(() => {
    return parsedPayments.value.reduce((sum, payment) => sum + payment.amount, 0);
});

const panetaFee = computed(() => {
    return totalAmount.value * PANETA_FEE_RATE;
});

const totalWithFee = computed(() => {
    return totalAmount.value + panetaFee.value;
});

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        uploadedFile.value = target.files[0];
        uploadForm.file = target.files[0];
        parseFile(target.files[0]);
    }
};

const handleFileDrop = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        uploadedFile.value = event.dataTransfer.files[0];
        uploadForm.file = event.dataTransfer.files[0];
        parseFile(event.dataTransfer.files[0]);
    }
};

const parseFile = (file: File) => {
    // Mock parsing - in production, this would parse CSV/Excel files
    // For demo purposes, we'll create sample data
    parsedPayments.value = [
        {
            receiver_name: 'John Doe',
            institution_name: 'ABC Bank',
            account_number: '1234567890',
            amount: 500.00,
            currency: 'USD',
        },
        {
            receiver_name: 'Jane Smith',
            institution_name: 'XYZ Bank',
            account_number: '0987654321',
            amount: 750.00,
            currency: 'USD',
        },
        {
            receiver_name: 'Robert Johnson',
            institution_name: 'Global Bank',
            account_number: '5555555555',
            amount: 1200.00,
            currency: 'USD',
        },
    ];
    showSummary.value = true;
};

const processBatchPayment = async () => {
    if (!uploadedFile.value) {
        alert('Please upload a file first');
        return;
    }

    try {
        // Create FormData for file upload
        const formData = new FormData();
        formData.append('file', uploadedFile.value);
        formData.append('processing_mode', uploadForm.processing_mode);
        formData.append('error_handling', uploadForm.error_handling);
        formData.append('email_notifications', uploadForm.email_notifications);
        formData.append('sms_alerts', uploadForm.sms_alerts);
        formData.append('dashboard_updates', uploadForm.dashboard_updates);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Use fetch to submit the form with proper file handling
        const response = await fetch('/paneta/batch-payments/process', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
        });

        if (response.ok) {
            // Redirect to transactions page
            window.location.href = '/paneta/transactions';
        } else {
            const errorData = await response.json().catch(() => ({}));
            alert(errorData.message || 'Error processing batch payment. Please try again.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error processing batch payment. Please try again.');
    }
};

const downloadTemplate = () => {
    // In production, this would download an actual CSV template
    const csvContent = "Receiver Full Name,Destination Institution,Account Number,Amount,Currency\nJohn Doe,ABC Bank,1234567890,500.00,USD\n";
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'batch_payment_template.csv';
    a.click();
};

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};
</script>

<template>
    <Head title="Batch Payment Processing - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h1 class="text-2xl font-bold">Batch Payment Processing</h1>
                        <Badge class="bg-gradient-to-r from-pink-500 to-rose-500 text-white">Enterprise</Badge>
                    </div>
                    <p class="text-muted-foreground">
                        Process multiple payments simultaneously for payroll, vendor payments, and bulk transactions. Streamline your business operations with powerful batch processing.
                    </p>
                </div>
            </div>

            <!-- Upload Section -->
            <Card v-if="!showSummary" class="border-2 border-dashed">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Upload class="h-5 w-5 text-pink-600" />
                        Upload Batch Payment File
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div 
                        class="border-2 border-dashed border-pink-300 rounded-lg p-12 text-center bg-gradient-to-br from-pink-50 to-rose-50 hover:border-pink-400 transition-colors cursor-pointer"
                        @drop="handleFileDrop"
                        @dragover.prevent
                        @click="$refs.fileInput?.click()"
                    >
                        <Upload class="h-16 w-16 text-pink-400 mx-auto mb-4" />
                        <p class="text-lg font-semibold text-pink-900 mb-2">Drop your payment file here</p>
                        <p class="text-sm text-pink-700 mb-4">Supports CSV, Excel, and JSON formats</p>
                        <input 
                            ref="fileInput"
                            type="file" 
                            accept=".csv,.xlsx,.xls,.json"
                            class="hidden"
                            @change="handleFileUpload"
                        />
                        <Button variant="outline" class="border-pink-600 text-pink-600 hover:bg-pink-50">
                            <Upload class="mr-2 h-4 w-4" />
                            Browse Files
                        </Button>
                    </div>

                    <!-- Helper Cards -->
                    <div class="grid grid-cols-3 gap-4 mt-6">
                        <Card class="bg-pink-50 border-pink-200 cursor-pointer hover:shadow-md transition-shadow" @click="downloadTemplate">
                            <CardContent class="p-4 text-center">
                                <FileText class="h-8 w-8 text-pink-600 mx-auto mb-2" />
                                <p class="font-semibold text-sm text-pink-900">CSV Template</p>
                                <Button variant="link" class="text-xs text-pink-600 p-0 h-auto">
                                    <Download class="mr-1 h-3 w-3" />
                                    Download
                                </Button>
                            </CardContent>
                        </Card>
                        <Card class="bg-purple-50 border-purple-200">
                            <CardContent class="p-4 text-center">
                                <BarChart3 class="h-8 w-8 text-purple-600 mx-auto mb-2" />
                                <p class="font-semibold text-sm text-purple-900">Validation</p>
                                <p class="text-xs text-purple-700">Auto-check format</p>
                            </CardContent>
                        </Card>
                        <Card class="bg-blue-50 border-blue-200">
                            <CardContent class="p-4 text-center">
                                <Shield class="h-8 w-8 text-blue-600 mx-auto mb-2" />
                                <p class="font-semibold text-sm text-blue-900">Security</p>
                                <p class="text-xs text-blue-700">Encrypted processing</p>
                            </CardContent>
                        </Card>
                    </div>
                </CardContent>
            </Card>

            <!-- Transaction Summary -->
            <div v-if="showSummary" class="space-y-6">
                <!-- Summary Header -->
                <Card class="border-2 border-pink-200 bg-gradient-to-br from-pink-50 to-rose-50">
                    <CardHeader>
                        <CardTitle class="text-pink-900">Transaction Summary</CardTitle>
                        <CardDescription>
                            Review all transactions before processing
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-3 gap-6">
                            <div class="text-center p-4 bg-white rounded-lg border border-pink-200">
                                <p class="text-sm text-muted-foreground mb-1">Total Transactions</p>
                                <p class="text-3xl font-bold text-pink-900">{{ parsedPayments.length }}</p>
                            </div>
                            <div class="text-center p-4 bg-white rounded-lg border border-pink-200">
                                <p class="text-sm text-muted-foreground mb-1">Total PANÉTA Fee (0.99%)</p>
                                <p class="text-3xl font-bold text-pink-900">{{ formatCurrency(panetaFee) }}</p>
                            </div>
                            <div class="text-center p-4 bg-gradient-to-br from-pink-600 to-rose-600 rounded-lg">
                                <p class="text-sm text-pink-100 mb-1">Total Amount</p>
                                <p class="text-3xl font-bold text-white">{{ formatCurrency(totalWithFee) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payments Table -->
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Details</CardTitle>
                        <CardDescription>{{ parsedPayments.length }} recipients</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>#</TableHead>
                                    <TableHead>Receiver Name</TableHead>
                                    <TableHead>Institution</TableHead>
                                    <TableHead>Account Number</TableHead>
                                    <TableHead class="text-right">Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(payment, index) in parsedPayments" :key="index">
                                    <TableCell class="font-medium">{{ index + 1 }}</TableCell>
                                    <TableCell>{{ payment.receiver_name }}</TableCell>
                                    <TableCell>{{ payment.institution_name }}</TableCell>
                                    <TableCell class="font-mono">{{ payment.account_number }}</TableCell>
                                    <TableCell class="text-right font-semibold">{{ formatCurrency(payment.amount, payment.currency) }}</TableCell>
                                    <TableCell>
                                        <Badge class="bg-green-100 text-green-700">
                                            <CheckCircle class="mr-1 h-3 w-3" />
                                            Validated
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Processing Options -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z"/>
                            </svg>
                            Processing Options
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <Label>Processing Mode</Label>
                                    <Select v-model="uploadForm.processing_mode">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="immediate">Immediate Processing</SelectItem>
                                            <SelectItem value="scheduled">Scheduled Processing</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Error Handling</Label>
                                    <Select v-model="uploadForm.error_handling">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="skip">Skip Failed Transactions</SelectItem>
                                            <SelectItem value="stop">Stop on First Error</SelectItem>
                                            <SelectItem value="retry">Auto-Retry Failed</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <Label>Notification Settings</Label>
                                <div class="space-y-3 bg-gray-50 rounded-lg p-4 border">
                                    <div class="flex items-center space-x-3">
                                        <Checkbox id="email_notif" v-model:checked="uploadForm.email_notifications" />
                                        <Label for="email_notif" class="text-sm font-normal cursor-pointer">
                                            Email notifications
                                        </Label>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <Checkbox id="sms_alerts" v-model:checked="uploadForm.sms_alerts" />
                                        <Label for="sms_alerts" class="text-sm font-normal cursor-pointer">
                                            SMS alerts
                                        </Label>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <Checkbox id="dashboard_updates" v-model:checked="uploadForm.dashboard_updates" />
                                        <Label for="dashboard_updates" class="text-sm font-normal cursor-pointer">
                                            Dashboard updates
                                        </Label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between">
                    <Button variant="outline" @click="showSummary = false; parsedPayments = []; uploadedFile = null">
                        Cancel
                    </Button>
                    <Button 
                        class="bg-gradient-to-r from-pink-600 to-rose-600 hover:from-pink-700 hover:to-rose-700 text-white px-8"
                        @click="processBatchPayment"
                        :disabled="uploadForm.processing"
                    >
                        <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                        Process Batch Payments
                    </Button>
                </div>
            </div>

            <!-- Batch Processing Power Stats -->
            <Card class="bg-gradient-to-br from-pink-50 to-purple-50 border-pink-200">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-pink-900">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                        </svg>
                        Batch Processing Power
                    </CardTitle>
                    <CardDescription class="text-pink-800">
                        Enterprise-grade payment processing capabilities
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-4 gap-6 text-center">
                        <div>
                            <p class="text-3xl font-bold text-pink-900 mb-1">10,000</p>
                            <p class="text-sm text-pink-700">Max Payments/Batch</p>
                        </div>
                        <div>
                            <p class="text-3xl font-bold text-pink-900 mb-1">99.9%</p>
                            <p class="text-sm text-pink-700">Success Rate</p>
                        </div>
                        <div>
                            <p class="text-3xl font-bold text-pink-900 mb-1">30s</p>
                            <p class="text-sm text-pink-700">Avg Processing Time</p>
                        </div>
                        <div>
                            <p class="text-3xl font-bold text-pink-900 mb-1">24/7</p>
                            <p class="text-sm text-pink-700">Processing Available</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
