<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Plus, Activity } from 'lucide-vue-next';

const props = defineProps<{
    provider: any;
    offers: any;
    filters: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Service Provider', href: '/paneta/service-provider' },
    { label: 'Offers', href: '/paneta/service-provider/offers' },
];

const getStatusBadge = (status: string) => {
    const badges: Record<string, { class: string; label: string }> = {
        active: { class: 'bg-green-100 text-green-700', label: 'Active' },
        expired: { class: 'bg-gray-100 text-gray-700', label: 'Expired' },
        cancelled: { class: 'bg-red-100 text-red-700', label: 'Cancelled' },
    };
    return badges[status] || { class: 'bg-gray-100 text-gray-700', label: status };
};
</script>

<template>
    <Head title="FX Offers - Service Provider - PANÉTA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">FX Offers</h1>
                    <p class="text-muted-foreground mt-1">Manage your currency exchange offers</p>
                </div>
                <Button class="gap-2">
                    <Plus class="h-4 w-4" />
                    Create New Offer
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Activity class="h-5 w-5" />
                        Your Offers ({{ offers.total }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="offers.data.length === 0" class="text-center py-12 text-muted-foreground">
                        No offers yet. Create your first offer to start trading.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="offer in offers.data"
                            :key="offer.id"
                            class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50"
                        >
                            <div class="flex-1">
                                <p class="font-semibold text-lg">{{ offer.currency_pair }}</p>
                                <p class="text-sm text-muted-foreground">Rate: {{ offer.rate }} | Spread: {{ offer.spread_percentage }}%</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <Badge :class="getStatusBadge(offer.status).class">
                                    {{ getStatusBadge(offer.status).label }}
                                </Badge>
                                <Button variant="outline" size="sm">Edit</Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
