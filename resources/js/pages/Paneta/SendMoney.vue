<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem, LinkedAccount } from '@/types';
import { Send, Wallet, AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    accounts: LinkedAccount[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Send Money' },
];

const form = useForm({
    issuer_account_id: null as number | null,
    acquirer_identifier: '',
    amount: '',
});

const selectedAccount = computed(() => {
    return props.accounts.find((a) => a.id === form.issuer_account_id);
});

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const submit = () => {
    form.post('/paneta/transactions');
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
                    Create a simulated payment instruction
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Send Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Details</CardTitle>
                        <CardDescription>
                            Fill in the payment details below
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submit">
                            <!-- Source Account -->
                            <div class="space-y-3">
                                <Label>Source Account</Label>
                                <div class="grid gap-3">
                                    <button
                                        v-for="account in accounts"
                                        :key="account.id"
                                        type="button"
                                        :class="[
                                            'flex items-center justify-between rounded-lg border p-4 text-left transition-colors',
                                            form.issuer_account_id === account.id
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="form.issuer_account_id = account.id"
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
                                <p v-if="form.errors.issuer_account_id" class="text-sm text-red-500">
                                    {{ form.errors.issuer_account_id }}
                                </p>
                            </div>

                            <!-- Destination -->
                            <div class="space-y-2">
                                <Label for="acquirer_identifier">Destination Identifier</Label>
                                <Input
                                    id="acquirer_identifier"
                                    v-model="form.acquirer_identifier"
                                    placeholder="Enter recipient account or wallet ID"
                                />
                                <p v-if="form.errors.acquirer_identifier" class="text-sm text-red-500">
                                    {{ form.errors.acquirer_identifier }}
                                </p>
                            </div>

                            <!-- Amount -->
                            <div class="space-y-2">
                                <Label for="amount">Amount</Label>
                                <div class="relative">
                                    <Input
                                        id="amount"
                                        v-model="form.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="0.00"
                                        class="pr-16"
                                    />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                                    >
                                        {{ selectedAccount?.currency || 'USD' }}
                                    </span>
                                </div>
                                <p v-if="form.errors.amount" class="text-sm text-red-500">
                                    {{ form.errors.amount }}
                                </p>
                            </div>

                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="
                                    form.processing ||
                                    !form.issuer_account_id ||
                                    !form.acquirer_identifier ||
                                    !isValidAmount
                                "
                            >
                                <Send class="mr-2 h-4 w-4" />
                                <span v-if="form.processing">Processing...</span>
                                <span v-else>Send Payment</span>
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
                                <span class="font-medium">{{
                                    selectedAccount.institution?.name
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">To</span>
                                <span class="font-medium">{{
                                    form.acquirer_identifier || '-'
                                }}</span>
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
                                    <p class="font-semibold">Simulated Transaction</p>
                                    <p class="mt-1">
                                        This is a simulated payment instruction. No real funds will
                                        be transferred. The mock balance will be updated to reflect
                                        the transaction.
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
        </div>
    </AppLayout>
</template>
