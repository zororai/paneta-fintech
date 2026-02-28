<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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
} from '@/components/ui/dialog';
import type { BreadcrumbItem, LinkedAccount } from '@/types';
import { Zap, Smartphone, Wifi, Building, Plus, Receipt } from 'lucide-vue-next';
import { ref } from 'vue';

interface SavedBiller {
    id: number;
    category: string;
    provider: string;
    account_number: string;
    amount: number;
    currency: string;
    due_date: string;
}

type Props = {
    linkedAccounts: LinkedAccount[];
    savedBillers?: SavedBiller[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Bills & Airtime Services' },
];

const showAddBillerDialog = ref(false);
const selectedCategory = ref<string | null>(null);

const billForm = useForm({
    category: '',
    provider: '',
    account_number: '',
    amount: 0,
    payment_account_id: null as number | null,
});

const addBillerForm = useForm({
    category: '',
    provider: '',
    account_number: '',
    amount: 0,
    currency: 'USD',
    due_date: '',
});

const categories = [
    {
        id: 'electricity',
        title: 'Electricity',
        description: 'ZESA, City Council',
        icon: Zap,
        color: 'orange',
    },
    {
        id: 'airtime',
        title: 'Airtime',
        description: 'All networks',
        icon: Smartphone,
        color: 'green',
    },
    {
        id: 'internet',
        title: 'Internet',
        description: 'WiFi providers',
        icon: Wifi,
        color: 'blue',
    },
    {
        id: 'rent',
        title: 'Rent',
        description: 'Property payments',
        icon: Building,
        color: 'purple',
    },
];

const selectCategory = (categoryId: string) => {
    selectedCategory.value = categoryId;
    billForm.category = categoryId;
};

const submitBill = () => {
    billForm.post('/paneta/bills/pay', {
        onSuccess: () => {
            billForm.reset();
            selectedCategory.value = null;
        },
    });
};

const addBiller = () => {
    addBillerForm.post('/paneta/bills/save-biller', {
        onSuccess: () => {
            addBillerForm.reset();
            showAddBillerDialog.value = false;
        },
    });
};

const payBiller = (biller: SavedBiller) => {
    billForm.category = biller.category;
    billForm.provider = biller.provider;
    billForm.account_number = biller.account_number;
    billForm.amount = biller.amount;
    selectedCategory.value = biller.category;
};

const getColorClass = (color: string) => {
    const colors: Record<string, string> = {
        orange: 'bg-orange-100 text-orange-600 dark:bg-orange-900',
        green: 'bg-green-100 text-green-600 dark:bg-green-900',
        blue: 'bg-blue-100 text-blue-600 dark:bg-blue-900',
        purple: 'bg-purple-100 text-purple-600 dark:bg-purple-900',
    };
    return colors[color] || 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Bills & Airtime Services" />

        <div class="space-y-6">
            <!-- Header -->
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="rounded-lg bg-teal-100 p-2 dark:bg-teal-900">
                        <Receipt class="h-6 w-6 text-teal-600" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">Bills & Airtime Services</h1>
                    </div>
                </div>
                <p class="text-muted-foreground">
                    Pay utility bills, rent, subscriptions, and top up airtime seamlessly. Consolidate all your recurring payments in one convenient platform.
                </p>
            </div>

            <!-- Bill Categories -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card
                    v-for="category in categories"
                    :key="category.id"
                    class="cursor-pointer transition-all hover:shadow-md"
                    :class="selectedCategory === category.id ? 'ring-2 ring-primary' : ''"
                    @click="selectCategory(category.id)"
                >
                    <CardContent class="pt-6">
                        <div class="flex flex-col items-center text-center">
                            <div :class="['rounded-full p-4 mb-3', getColorClass(category.color)]">
                                <component :is="category.icon" class="h-8 w-8" />
                            </div>
                            <h3 class="font-semibold text-lg">{{ category.title }}</h3>
                            <p class="text-sm text-muted-foreground">{{ category.description }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pay Bills Form -->
            <Card v-if="selectedCategory">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Receipt class="h-5 w-5" />
                                Pay Bills
                            </CardTitle>
                            <CardDescription>
                                Enter your bill details to make a payment
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitBill" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Service Provider</Label>
                                <Select v-model="billForm.provider">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <template v-if="selectedCategory === 'electricity'">
                                            <SelectItem value="ZESA">ZESA</SelectItem>
                                            <SelectItem value="City Council">City Council</SelectItem>
                                        </template>
                                        <template v-else-if="selectedCategory === 'airtime'">
                                            <SelectItem value="Econet">Econet</SelectItem>
                                            <SelectItem value="NetOne">NetOne</SelectItem>
                                            <SelectItem value="Telecel">Telecel</SelectItem>
                                        </template>
                                        <template v-else-if="selectedCategory === 'internet'">
                                            <SelectItem value="TelOne">TelOne</SelectItem>
                                            <SelectItem value="Liquid">Liquid</SelectItem>
                                            <SelectItem value="Dandemutande">Dandemutande</SelectItem>
                                        </template>
                                        <template v-else-if="selectedCategory === 'rent'">
                                            <SelectItem value="Property Manager">Property Manager</SelectItem>
                                            <SelectItem value="Landlord">Landlord</SelectItem>
                                        </template>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label>Account Number or Phone</Label>
                                <Input
                                    v-model="billForm.account_number"
                                    placeholder="Enter account number or phone"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Amount</Label>
                                <Input
                                    v-model.number="billForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0.00"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label>Payment Method</Label>
                                <Select v-model="billForm.payment_account_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select payment method" />
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
                        </div>

                        <Button
                            type="submit"
                            class="w-full bg-teal-600 hover:bg-teal-700"
                            :disabled="billForm.processing || !billForm.provider || !billForm.account_number || !billForm.amount || !billForm.payment_account_id"
                        >
                            <Receipt class="mr-2 h-4 w-4" />
                            Pay Bill Now
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Saved Billers -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Receipt class="h-5 w-5" />
                                Saved Billers
                            </CardTitle>
                            <CardDescription>
                                Quick access to your frequently paid bills
                            </CardDescription>
                        </div>
                        <Button size="sm" @click="showAddBillerDialog = true">
                            <Plus class="mr-1 h-4 w-4" />
                            Add Biller
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="!savedBillers || savedBillers.length === 0" class="flex flex-col items-center py-8 text-center">
                        <Receipt class="mb-4 h-12 w-12 text-muted-foreground" />
                        <p class="text-muted-foreground">No saved billers yet</p>
                        <p class="text-sm text-muted-foreground">Add your frequently used billers for quick payments</p>
                    </div>
                    <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="biller in savedBillers"
                            :key="biller.id"
                            class="rounded-lg border p-4"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="font-semibold">{{ biller.provider }}</h3>
                                    <p class="text-sm text-muted-foreground">{{ biller.account_number }}</p>
                                </div>
                                <Badge variant="outline">{{ biller.category }}</Badge>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-lg font-bold">{{ biller.currency }} {{ biller.amount }}</p>
                                    <p class="text-xs text-muted-foreground">Due {{ biller.due_date }}</p>
                                </div>
                                <Button size="sm" @click="payBiller(biller)">
                                    Pay Now
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Add Biller Dialog -->
            <Dialog v-model:open="showAddBillerDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Biller</DialogTitle>
                        <DialogDescription>
                            Save a biller for quick future payments
                        </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="addBiller" class="space-y-4">
                        <div class="space-y-2">
                            <Label>Category</Label>
                            <Select v-model="addBillerForm.category">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="electricity">Electricity</SelectItem>
                                    <SelectItem value="airtime">Airtime</SelectItem>
                                    <SelectItem value="internet">Internet</SelectItem>
                                    <SelectItem value="rent">Rent</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Provider</Label>
                            <Input v-model="addBillerForm.provider" placeholder="e.g., ZESA" />
                        </div>
                        <div class="space-y-2">
                            <Label>Account Number</Label>
                            <Input v-model="addBillerForm.account_number" placeholder="Account number or phone" />
                        </div>
                        <div class="grid gap-4 grid-cols-2">
                            <div class="space-y-2">
                                <Label>Amount</Label>
                                <Input v-model.number="addBillerForm.amount" type="number" step="0.01" placeholder="0.00" />
                            </div>
                            <div class="space-y-2">
                                <Label>Currency</Label>
                                <Select v-model="addBillerForm.currency">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="USD">USD</SelectItem>
                                        <SelectItem value="ZWL">ZWL</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Due Date</Label>
                            <Input v-model="addBillerForm.due_date" placeholder="e.g., Jan 30, Auto" />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="showAddBillerDialog = false">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="addBillerForm.processing">
                                Add Biller
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
