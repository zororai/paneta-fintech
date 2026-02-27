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
import { Wallet, Plus, RefreshCw, XCircle, Building2, Send, CreditCard, ArrowRightLeft, TrendingUp, Landmark, ChevronRight, ChevronLeft, CheckCircle2, Eye, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// Countries supported
const countries = [
    { code: 'ZW', name: 'Zimbabwe', flag: '🇿🇼' },
    { code: 'ZA', name: 'South Africa', flag: '🇿🇦' },
    { code: 'US', name: 'United States', flag: '🇺🇸' },
    { code: 'GB', name: 'United Kingdom', flag: '🇬🇧' },
    { code: 'KE', name: 'Kenya', flag: '🇰🇪' },
    { code: 'NG', name: 'Nigeria', flag: '🇳🇬' },
];

// Provider categories
const providerCategories = [
    { 
        key: 'bank', 
        label: 'Banks', 
        icon: Building2,
        description: 'Commercial Banks & Retail Banking'
    },
    { 
        key: 'wallet', 
        label: 'Digital Wallets', 
        icon: Wallet,
        description: 'Mobile Money & E-Wallets'
    },
    { 
        key: 'card', 
        label: 'Card Networks', 
        icon: CreditCard,
        description: 'Credit & Debit Card Issuers'
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
const linkingStep = ref(1); // 1: Country, 2: Category, 3: Institution, 4: Details, 5: Consent
const selectedCountry = ref<string | null>(null);
const selectedCategory = ref<string | null>(null);
const selectedInstitution = ref<number | null>(null);
const consentGranted = ref(false);

const form = useForm({
    institution_id: null as number | null,
    country: '',
    account_number: '',
    account_holder_name: '',
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

const filteredInstitutions = computed(() => {
    if (!selectedCountry.value || !selectedCategory.value) return [];
    
    // Filter by country and category
    return props.institutions.filter(i => {
        // In a real system, institutions would have a country field
        // For demo, we'll show all institutions for selected category
        return i.type === selectedCategory.value;
    });
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

const resetLinkingFlow = () => {
    linkingStep.value = 1;
    selectedCountry.value = null;
    selectedCategory.value = null;
    selectedInstitution.value = null;
    consentGranted.value = false;
    form.reset();
};

const nextStep = () => {
    if (linkingStep.value < 5) {
        linkingStep.value++;
    }
};

const prevStep = () => {
    if (linkingStep.value > 1) {
        linkingStep.value--;
    }
};

const selectCountry = (countryCode: string) => {
    selectedCountry.value = countryCode;
    form.country = countryCode;
    nextStep();
};

const selectCategory = (category: string) => {
    selectedCategory.value = category;
    nextStep();
};

const selectInstitution = (institutionId: number) => {
    selectedInstitution.value = institutionId;
    form.institution_id = institutionId;
    nextStep();
};

const grantConsent = () => {
    consentGranted.value = true;
    nextStep();
};

const linkAccount = () => {
    console.log('Submitting form data:', {
        institution_id: form.institution_id,
        country: form.country,
        account_number: form.account_number,
        account_holder_name: form.account_holder_name,
        currency: form.currency,
    });
    
    form.post('/paneta/accounts', {
        onSuccess: () => {
            console.log('Account linked successfully');
            showLinkDialog.value = false;
            resetLinkingFlow();
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
        },
    });
};

const getStepTitle = (step: number) => {
    switch (step) {
        case 1: return 'Select Country';
        case 2: return 'Select Provider Category';
        case 3: return 'Select Institution';
        case 4: return 'Enter Account Details';
        case 5: return 'Grant Consent';
        default: return '';
    }
};

const canProceed = computed(() => {
    switch (linkingStep.value) {
        case 1: return selectedCountry.value !== null;
        case 2: return selectedCategory.value !== null;
        case 3: return selectedInstitution.value !== null;
        case 4: return form.account_number && form.account_holder_name && form.currency;
        case 5: return consentGranted.value;
        default: return false;
    }
});

const revokeAccount = (accountId: number) => {
    if (confirm('Are you sure you want to revoke access to this account?')) {
        router.post(`/paneta/accounts/${accountId}/revoke`);
    }
};

const refreshConsent = (accountId: number) => {
    router.post(`/paneta/accounts/${accountId}/refresh`);
};

const showDetailsDialog = ref(false);
const showStatementsDialog = ref(false);
const selectedAccount = ref<LinkedAccount | null>(null);

const viewDetails = (account: LinkedAccount) => {
    selectedAccount.value = account;
    showDetailsDialog.value = true;
};

const viewStatements = (account: LinkedAccount) => {
    selectedAccount.value = account;
    showStatementsDialog.value = true;
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
                    <DialogContent class="sm:max-w-[900px]">
                        <DialogHeader>
                            <DialogTitle>{{ getStepTitle(linkingStep) }}</DialogTitle>
                            <DialogDescription>
                                Step {{ linkingStep }} of 5 - Account Aggregation Initiation
                            </DialogDescription>
                        </DialogHeader>

                        <!-- Progress Indicator -->
                        <div class="flex items-center justify-between mb-4">
                            <div v-for="step in 5" :key="step" class="flex items-center flex-1">
                                <div :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                                    linkingStep > step ? 'bg-primary text-primary-foreground' : 
                                    linkingStep === step ? 'bg-primary text-primary-foreground' : 
                                    'bg-muted text-muted-foreground'
                                ]">
                                    <CheckCircle2 v-if="linkingStep > step" class="h-4 w-4" />
                                    <span v-else>{{ step }}</span>
                                </div>
                                <div v-if="step < 5" :class="[
                                    'h-1 flex-1 mx-2',
                                    linkingStep > step ? 'bg-primary' : 'bg-muted'
                                ]" />
                            </div>
                        </div>

                        <div class="py-4 max-h-[50vh] overflow-y-auto">
                            <!-- Step 1: Country Selection -->
                            <div v-if="linkingStep === 1" class="space-y-4">
                                <p class="text-sm text-muted-foreground mb-4">
                                    Select the country where your financial institution is located
                                </p>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <button
                                        v-for="country in countries"
                                        :key="country.code"
                                        type="button"
                                        :class="[
                                            'flex items-center gap-3 rounded-lg border p-4 transition-colors text-left',
                                            selectedCountry === country.code
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="selectCountry(country.code)"
                                    >
                                        <span class="text-3xl">{{ country.flag }}</span>
                                        <div>
                                            <div class="font-medium">{{ country.name }}</div>
                                            <div class="text-xs text-muted-foreground">{{ country.code }}</div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Provider Category -->
                            <div v-if="linkingStep === 2" class="space-y-4">
                                <p class="text-sm text-muted-foreground mb-4">
                                    Select the type of financial service provider
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <button
                                        v-for="category in providerCategories"
                                        :key="category.key"
                                        type="button"
                                        :class="[
                                            'flex flex-col items-center gap-3 rounded-lg border p-6 transition-colors',
                                            selectedCategory === category.key
                                                ? 'border-primary bg-primary/5'
                                                : 'hover:border-primary/50',
                                        ]"
                                        @click="selectCategory(category.key)"
                                    >
                                        <component :is="category.icon" class="h-12 w-12 text-primary" />
                                        <div class="text-center">
                                            <div class="font-semibold">{{ category.label }}</div>
                                            <div class="text-xs text-muted-foreground mt-1">{{ category.description }}</div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 3: Institution Selection -->
                            <div v-if="linkingStep === 3" class="space-y-4">
                                <p class="text-sm text-muted-foreground mb-4">
                                    Select your financial institution from {{ countries.find(c => c.code === selectedCountry)?.name }}
                                </p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
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
                                        @click="selectInstitution(institution.id)"
                                    >
                                        <component
                                            :is="getTypeIcon(institution.type)"
                                            class="h-8 w-8 text-primary"
                                        />
                                        <span class="text-sm font-medium text-center">{{ institution.name }}</span>
                                        <Badge variant="outline" class="text-xs">{{ institution.type }}</Badge>
                                    </button>
                                </div>
                                <p v-if="filteredInstitutions.length === 0" class="text-center text-muted-foreground py-8">
                                    No institutions found for this category
                                </p>
                            </div>

                            <!-- Step 4: Account Details -->
                            <div v-if="linkingStep === 4" class="space-y-4">
                                <div class="bg-muted/50 rounded-lg p-4 mb-4">
                                    <div class="flex items-center gap-3">
                                        <component :is="getTypeIcon(selectedInstitutionData?.type || 'bank')" class="h-8 w-8 text-primary" />
                                        <div>
                                            <div class="font-semibold">{{ selectedInstitutionData?.name }}</div>
                                            <div class="text-xs text-muted-foreground">{{ countries.find(c => c.code === selectedCountry)?.name }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div>
                                        <Label for="account_holder_name">Account Holder Name</Label>
                                        <Input
                                            id="account_holder_name"
                                            v-model="form.account_holder_name"
                                            type="text"
                                            placeholder="Enter full name as it appears on account"
                                            required
                                        />
                                        <p v-if="form.errors.account_holder_name" class="text-sm text-destructive mt-1">
                                            {{ form.errors.account_holder_name }}
                                        </p>
                                    </div>

                                    <div>
                                        <Label for="account_number">Account Number</Label>
                                        <Input
                                            id="account_number"
                                            v-model="form.account_number"
                                            type="text"
                                            placeholder="Enter your account number"
                                            required
                                        />
                                        <p v-if="form.errors.account_number" class="text-sm text-destructive mt-1">
                                            {{ form.errors.account_number }}
                                        </p>
                                    </div>

                                    <div>
                                        <Label>Account Currency</Label>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <button
                                                v-for="currency in currencies"
                                                :key="currency"
                                                type="button"
                                                :class="[
                                                    'rounded-md border px-4 py-2 text-sm transition-colors',
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
                            </div>

                            <!-- Step 5: Consent & Authorization -->
                            <div v-if="linkingStep === 5" class="space-y-4">
                                <div class="bg-muted/50 rounded-lg p-4 mb-4">
                                    <h4 class="font-semibold mb-2">Consent Summary</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Institution:</span>
                                            <span class="font-medium">{{ selectedInstitutionData?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Country:</span>
                                            <span class="font-medium">{{ countries.find(c => c.code === selectedCountry)?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Account Holder:</span>
                                            <span class="font-medium">{{ form.account_holder_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Account Number:</span>
                                            <span class="font-medium">{{ form.account_number }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Currency:</span>
                                            <span class="font-medium">{{ form.currency }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="border rounded-lg p-4 space-y-3">
                                    <h4 class="font-semibold">Consent & Authorization</h4>
                                    <p class="text-sm text-muted-foreground">
                                        By granting consent, you authorize PANÉTA to:
                                    </p>
                                    <ul class="text-sm space-y-2 ml-4">
                                        <li class="flex items-start gap-2">
                                            <CheckCircle2 class="h-4 w-4 text-primary mt-0.5 flex-shrink-0" />
                                            <span>Access your account balance and transaction history</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle2 class="h-4 w-4 text-primary mt-0.5 flex-shrink-0" />
                                            <span>Initiate payment instructions on your behalf</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <CheckCircle2 class="h-4 w-4 text-primary mt-0.5 flex-shrink-0" />
                                            <span>Receive real-time account updates</span>
                                        </li>
                                    </ul>

                                    <div class="flex items-center gap-2 mt-4 p-3 bg-primary/5 rounded-md">
                                        <input
                                            id="consent_checkbox"
                                            v-model="consentGranted"
                                            type="checkbox"
                                            class="h-4 w-4"
                                        />
                                        <label for="consent_checkbox" class="text-sm font-medium cursor-pointer">
                                            I grant consent and authorize PANÉTA to access my account
                                        </label>
                                    </div>
                                </div>

                                <div v-if="consentGranted" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                    <div class="flex items-center gap-2 text-green-800 dark:text-green-300">
                                        <CheckCircle2 class="h-5 w-5" />
                                        <span class="font-medium">Consent Granted Successfully</span>
                                    </div>
                                    <p class="text-sm text-green-700 dark:text-green-400 mt-1">
                                        Click "Complete Linking" to finalize the account connection.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                v-if="linkingStep > 1"
                                variant="outline"
                                @click="prevStep"
                            >
                                <ChevronLeft class="mr-1 h-4 w-4" />
                                Back
                            </Button>
                            <Button
                                variant="outline"
                                @click="showLinkDialog = false; resetLinkingFlow();"
                            >
                                Cancel
                            </Button>
                            <Button
                                v-if="linkingStep < 5"
                                :disabled="!canProceed"
                                @click="nextStep"
                            >
                                Next
                                <ChevronRight class="ml-1 h-4 w-4" />
                            </Button>
                            <Button
                                v-if="linkingStep === 5"
                                :disabled="!consentGranted || form.processing"
                                @click="linkAccount"
                            >
                                <span v-if="form.processing">Linking...</span>
                                <span v-else>Complete Linking</span>
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

                            <div class="grid grid-cols-3 gap-2 text-sm">
                                <div class="text-center">
                                    <div class="text-xs text-muted-foreground mb-1">Type</div>
                                    <Badge variant="outline" class="text-xs">
                                        {{ account.institution?.type || 'N/A' }}
                                    </Badge>
                                </div>
                                <div class="text-center">
                                    <Button variant="ghost" size="sm" class="h-auto py-1 px-2" @click="viewDetails(account)">
                                        <Eye class="h-3 w-3 mr-1" />
                                        <span class="text-xs">Details</span>
                                    </Button>
                                </div>
                                <div class="text-center">
                                    <Button variant="ghost" size="sm" class="h-auto py-1 px-2" @click="viewStatements(account)">
                                        <FileText class="h-3 w-3 mr-1" />
                                        <span class="text-xs">Statements</span>
                                    </Button>
                                </div>
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

        <!-- Account Details Dialog -->
        <Dialog v-model:open="showDetailsDialog">
            <DialogContent class="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>Account Details</DialogTitle>
                    <DialogDescription>
                        Complete information for your linked account
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedAccount" class="space-y-4 py-4">
                    <div class="flex items-center gap-4 p-4 bg-muted/50 rounded-lg">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <component
                                :is="getTypeIcon(selectedAccount.institution?.type || 'bank')"
                                class="h-6 w-6 text-primary"
                            />
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg">{{ selectedAccount.institution?.name }}</h3>
                            <p class="text-sm text-muted-foreground">{{ selectedAccount.institution?.type }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Account Holder</div>
                            <div class="col-span-2 font-medium">{{ selectedAccount.account_holder_name || 'N/A' }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Account Number</div>
                            <div class="col-span-2 font-medium font-mono">{{ selectedAccount.account_identifier }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Country</div>
                            <div class="col-span-2 font-medium">
                                {{ countries.find(c => c.code === selectedAccount.country)?.name || selectedAccount.country || 'N/A' }}
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Currency</div>
                            <div class="col-span-2 font-medium">{{ selectedAccount.currency }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Current Balance</div>
                            <div class="col-span-2 font-semibold text-lg">
                                {{ formatCurrency(selectedAccount.mock_balance, selectedAccount.currency) }}
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Status</div>
                            <div class="col-span-2">
                                <Badge :class="getStatusColor(selectedAccount.status)">
                                    {{ selectedAccount.status }}
                                </Badge>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Linked On</div>
                            <div class="col-span-2 font-medium">{{ formatDate(selectedAccount.created_at) }}</div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showDetailsDialog = false">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Account Statements Dialog -->
        <Dialog v-model:open="showStatementsDialog">
            <DialogContent class="sm:max-w-[800px]">
                <DialogHeader>
                    <DialogTitle>Account Statements</DialogTitle>
                    <DialogDescription>
                        Transaction history for {{ selectedAccount?.institution?.name }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedAccount" class="space-y-4 py-4">
                    <div class="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
                        <div>
                            <p class="text-sm text-muted-foreground">Account</p>
                            <p class="font-medium">{{ selectedAccount.account_identifier }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-muted-foreground">Balance</p>
                            <p class="font-semibold text-lg">
                                {{ formatCurrency(selectedAccount.mock_balance, selectedAccount.currency) }}
                            </p>
                        </div>
                    </div>

                    <div class="border rounded-lg">
                        <div class="p-4 border-b bg-muted/30">
                            <h4 class="font-semibold">Recent Transactions</h4>
                        </div>
                        <div class="p-8 text-center text-muted-foreground">
                            <FileText class="h-12 w-12 mx-auto mb-3 opacity-50" />
                            <p class="text-sm">No transactions available</p>
                            <p class="text-xs mt-1">Transaction history will appear here once you start using this account</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <div class="text-sm text-blue-800 dark:text-blue-300">
                            <strong>Demo Mode:</strong> In production, this would show real transaction data fetched from the institution via secure API.
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showStatementsDialog = false">
                        Close
                    </Button>
                    <Button disabled>
                        <FileText class="mr-2 h-4 w-4" />
                        Download PDF
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
