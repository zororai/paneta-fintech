<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, TransactionIntent } from '@/types';
import { ArrowLeft, CheckCircle, XCircle, Clock, FileText } from 'lucide-vue-next';

const props = defineProps<{
    transaction: TransactionIntent;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Transactions', href: '/paneta/transactions' },
    { title: props.transaction.reference },
];

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'executed':
        case 'confirmed':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'pending':
        case 'generated':
        case 'sent':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'executed':
        case 'confirmed':
            return CheckCircle;
        case 'failed':
            return XCircle;
        default:
            return Clock;
    }
};

const lifecycleSteps = [
    { status: 'pending', label: 'Intent Created' },
    { status: 'confirmed', label: 'Confirmed' },
    { status: 'executed', label: 'Executed' },
];

const getStepStatus = (stepStatus: string) => {
    const statusOrder = ['pending', 'confirmed', 'executed'];
    const currentIndex = statusOrder.indexOf(props.transaction.status);
    const stepIndex = statusOrder.indexOf(stepStatus);

    if (props.transaction.status === 'failed') {
        return stepIndex === 0 ? 'completed' : 'failed';
    }

    if (stepIndex < currentIndex) return 'completed';
    if (stepIndex === currentIndex) return 'current';
    return 'upcoming';
};
</script>

<template>
    <Head :title="`Transaction ${transaction.reference} - PANÉTA`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link href="/paneta/transactions">
                    <Button variant="outline" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold">Transaction Details</h1>
                    <p class="font-mono text-muted-foreground">{{ transaction.reference }}</p>
                </div>
                <Badge :class="getStatusColor(transaction.status)" class="text-base px-4 py-1">
                    <component :is="getStatusIcon(transaction.status)" class="mr-2 h-4 w-4" />
                    {{ transaction.status }}
                </Badge>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Transaction Details -->
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">Amount</p>
                                <p class="text-2xl font-bold">
                                    {{ formatCurrency(transaction.amount, transaction.currency) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Currency</p>
                                <p class="text-lg font-medium">{{ transaction.currency }}</p>
                            </div>
                        </div>

                        <hr />

                        <div>
                            <p class="text-sm text-muted-foreground">From (Issuer)</p>
                            <p class="font-medium">
                                {{ transaction.issuer_account?.institution?.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ transaction.issuer_account?.account_identifier }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-muted-foreground">To (Acquirer)</p>
                            <p class="font-medium">{{ transaction.acquirer_identifier }}</p>
                        </div>

                        <hr />

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">Created</p>
                                <p class="text-sm">{{ formatDate(transaction.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Updated</p>
                                <p class="text-sm">{{ formatDate(transaction.updated_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Transaction Lifecycle -->
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction Lifecycle</CardTitle>
                        <CardDescription>Track the progress of your payment</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <div
                                v-for="(step, index) in lifecycleSteps"
                                :key="step.status"
                                class="flex items-start gap-4"
                            >
                                <div class="relative">
                                    <div
                                        :class="[
                                            'flex h-8 w-8 items-center justify-center rounded-full',
                                            getStepStatus(step.status) === 'completed'
                                                ? 'bg-green-500 text-white'
                                                : getStepStatus(step.status) === 'current'
                                                  ? 'bg-primary text-primary-foreground'
                                                  : getStepStatus(step.status) === 'failed'
                                                    ? 'bg-red-500 text-white'
                                                    : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        <CheckCircle
                                            v-if="getStepStatus(step.status) === 'completed'"
                                            class="h-5 w-5"
                                        />
                                        <XCircle
                                            v-else-if="getStepStatus(step.status) === 'failed'"
                                            class="h-5 w-5"
                                        />
                                        <span v-else>{{ index + 1 }}</span>
                                    </div>
                                    <div
                                        v-if="index < lifecycleSteps.length - 1"
                                        :class="[
                                            'absolute left-1/2 top-8 h-8 w-0.5 -translate-x-1/2',
                                            getStepStatus(step.status) === 'completed'
                                                ? 'bg-green-500'
                                                : 'bg-muted',
                                        ]"
                                    />
                                </div>
                                <div class="flex-1 pb-6">
                                    <p class="font-medium">{{ step.label }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            step.status === 'pending'
                                                ? 'Transaction intent created and validated'
                                                : step.status === 'confirmed'
                                                  ? 'Compliance checks passed, instruction generated'
                                                  : 'Payment instruction executed successfully'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Instruction -->
                <Card v-if="transaction.payment_instruction" class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Payment Instruction
                        </CardTitle>
                        <CardDescription>
                            The cryptographically signed instruction sent to the institution
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4">
                                <Badge :class="getStatusColor(transaction.payment_instruction.status)">
                                    {{ transaction.payment_instruction.status }}
                                </Badge>
                                <span class="text-sm text-muted-foreground">
                                    Created:
                                    {{ formatDate(transaction.payment_instruction.created_at) }}
                                </span>
                            </div>

                            <div>
                                <p class="mb-2 text-sm font-medium">Signed Hash</p>
                                <code
                                    class="block rounded bg-muted p-3 font-mono text-xs break-all"
                                >
                                    {{ transaction.payment_instruction.signed_hash }}
                                </code>
                            </div>

                            <div>
                                <p class="mb-2 text-sm font-medium">Instruction Payload</p>
                                <pre
                                    class="rounded bg-muted p-4 text-xs overflow-auto"
                                >{{ JSON.stringify(transaction.payment_instruction.instruction_payload, null, 2) }}</pre>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
