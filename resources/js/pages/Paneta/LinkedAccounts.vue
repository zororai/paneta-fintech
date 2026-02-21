<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { BreadcrumbItem, LinkedAccount, Institution } from '@/types';
import { Wallet, Plus, RefreshCw, XCircle, Building2, Send, CreditCard, ArrowRightLeft, TrendingUp, Landmark } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const institutionCategories = [
    { 
        key: 'banking', 
        label: '🏦 Banking & Wallets', 
        types: ['bank', 'wallet', 'card'],
        description: 'Commercial Banks, Digital Wallets, Card Issuers'
    },
    { 
        key: 'fx', 
        label: '💱 Currency Exchange', 
        types: ['fx_provider', 'remittance'],
        description: 'FX Providers, Cross-Border Remittance'
    },
    { 
        key: 'wealth', 
        label: '📈 Wealth & Investment', 
        types: ['broker', 'custodian'],
        description: 'Brokerage Accounts, Custodians'
    },
];

const props = defineProps<{
    accounts: LinkedAccount[];
    institutions: Institution[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Linked Accounts' },
];

const showLinkDialog = ref(false);
const selectedInstitution = ref<number | null>(null);

const form = useForm({
    institution_id: null as number | null,
    account_number: '',
    currency: 'USD',
});

const currencies = ['USD', 'ZWL', 'EUR', 'GBP', 'ZAR'];

const selectedInstitutionData = computed(() => {
    return props.institutions.find((i) => i.id === selectedInstitution.value);
});

const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'revoked':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'expired':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const selectedCategory = ref('banking');

const filteredInstitutions = computed(() => {
    const category = institutionCategories.find(c => c.key === selectedCategory.value);
    if (!category) return props.institutions;
    return props.institutions.filter(i => category.types.includes(i.type));
});

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'bank':
            return Building2;
        case 'wallet':
            return Wallet;
        case 'remittance':
            return Send;
        case 'card':
            return CreditCard;
        case 'fx_provider':
            return ArrowRightLeft;
        case 'broker':
            return TrendingUp;
        case 'custodian':
            return Landmark;
        default:
            return Building2;
    }
};

const linkAccount = () => {
    form.institution_id = selectedInstitution.value;
    form.post('/paneta/accounts', {
        onSuccess: () => {
            showLinkDialog.value = false;
            selectedInstitution.value = null;
            form.reset();
        },
        onError: () => {
            // Errors will be displayed inline
        },
    });
};

const revokeAccount = (accountId: number) => {
    if (confirm('Are you sure you want to revoke access to this account?')) {
        router.post(`/paneta/accounts/${accountId}/revoke`);
    }
};

const refreshConsent = (accountId: number) => {
    router.post(`/paneta/accounts/${accountId}/refresh`);
};
</script>

<template>
    <Head title="Linked Accounts - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Linked Accounts</h1>
                    <p class="text-muted-foreground">
                        Manage your connected external accounts
                    </p>
                </div>
                <Dialog v-model:open="showLinkDialog">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Link Account
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[800px]">
                        <DialogHeader>
                            <DialogTitle>Link External Account</DialogTitle>
                            <DialogDescription>
                                Select an institution to connect your account via simulated consent
                                flow.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-4 py-4 max-h-[60vh] overflow-y-auto">
                            <!-- Category Tabs -->
                            <div class="space-y-3">
                                <Label>Select Category</Label>
                                <div class="flex gap-2 flex-wrap">
                                    <button
                                        v-for="category in institutionCategories"
                                        :key="category.key"
                                        type="button"
                                        :class="[
                                            'rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
                                            selectedCategory === category.key
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="selectedCategory = category.key; selectedInstitution = null"
                                    >
                                        {{ category.label }}
                                    </button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ institutionCategories.find(c => c.key === selectedCategory)?.description }}
                                </p>
                            </div>

                            <!-- Institution Selection -->
                            <div class="space-y-3">
                                <Label>Select Institution</Label>
                                <div class="grid grid-cols-4 gap-3 max-h-[250px] overflow-y-auto pr-2">
                                    <button
                                        v-for="institution in filteredInstitutions"
                                        :key="institution.id"
                                        type="button"
                                        :class="[
                                            'flex flex-col items-center gap-2 rounded-lg border p-4 transition-colors',
                                            selectedInstitution === institution.id
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="selectedInstitution = institution.id"
                                    >
                                        <component
                                            :is="getTypeIcon(institution.type)"
                                            class="h-8 w-8 text-primary"
                                        />
                                        <span class="text-sm font-medium text-center">{{
                                            institution.name
                                        }}</span>
                                        <Badge variant="outline" class="text-xs">
                                            {{ institution.type }}
                                        </Badge>
                                    </button>
                                </div>
                            </div>

                            <!-- Account Number Input -->
                            <div v-if="selectedInstitution" class="space-y-3">
                                <Label for="account_number">Account Number</Label>
                                <Input
                                    id="account_number"
                                    v-model="form.account_number"
                                    type="text"
                                    placeholder="Enter your account number"
                                    required
                                />
                                <p v-if="form.errors.account_number" class="text-sm text-destructive">
                                    {{ form.errors.account_number }}
                                </p>
                            </div>

                            <!-- Currency Selection -->
                            <div v-if="selectedInstitution" class="space-y-3">
                                <Label>Select Currency</Label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="currency in currencies"
                                        :key="currency"
                                        type="button"
                                        :class="[
                                            'rounded-md border px-3 py-1 text-sm transition-colors',
                                            form.currency === currency
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="form.currency = currency"
                                    >
                                        {{ currency }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                variant="outline"
                                @click="showLinkDialog = false"
                            >
                                Cancel
                            </Button>
                            <Button
                                :disabled="!selectedInstitution || !form.account_number || form.processing"
                                @click="linkAccount"
                            >
                                <span v-if="form.processing">Linking...</span>
                                <span v-else>Link Account</span>
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <!-- Accounts Grid -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="account in accounts" :key="account.id" class="relative">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10"
                                >
                                    <component
                                        :is="getTypeIcon(account.institution?.type || 'bank')"
                                        class="h-5 w-5 text-primary"
                                    />
                                </div>
                                <div>
                                    <CardTitle class="text-base">
                                        {{ account.institution?.name }}
                                    </CardTitle>
                                    <CardDescription class="text-xs">
                                        {{ account.account_identifier }}
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge :class="getStatusColor(account.status)">
                                {{ account.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm text-muted-foreground">Balance</span>
                                <span class="text-xl font-bold">
                                    {{ formatCurrency(account.mock_balance, account.currency) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Consent expires</span>
                                <span>{{ formatDate(account.consent_expires_at) }}</span>
                            </div>

                            <div
                                v-if="account.status === 'active'"
                                class="flex gap-2 pt-2"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="flex-1"
                                    @click="refreshConsent(account.id)"
                                >
                                    <RefreshCw class="mr-1 h-3 w-3" />
                                    Refresh
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    class="flex-1"
                                    @click="revokeAccount(account.id)"
                                >
                                    <XCircle class="mr-1 h-3 w-3" />
                                    Revoke
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty State -->
                <Card
                    v-if="accounts.length === 0"
                    class="col-span-full flex flex-col items-center justify-center py-12"
                >
                    <Wallet class="mb-4 h-12 w-12 text-muted-foreground" />
                    <h3 class="text-lg font-semibold">No accounts linked</h3>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Connect your external accounts to get started
                    </p>
                    <Button @click="showLinkDialog = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Link Your First Account
                    </Button>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
