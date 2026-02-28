<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { CheckCircle, Clock } from 'lucide-vue-next';

const props = defineProps<{
    provider: any;
    trades: any;
    filters: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Service Provider', href: '/paneta/service-provider' },
    { label: 'Trades', href: '/paneta/service-provider/trades' },
];

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const getStatusBadge = (status: string) => {
    const badges: Record<string, { class: string; label: string }> = {
        pending: { class: 'bg-yellow-100 text-yellow-700', label: 'Pending' },
        executed: { class: 'bg-green-100 text-green-700', label: 'Executed' },
        failed: { class: 'bg-red-100 text-red-700', label: 'Failed' },
    };
    return badges[status] || { class: 'bg-gray-100 text-gray-700', label: status };
};
</script>

<template>
    <Head title="Trades - Service Provider - PANÉTA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Trade Requests</h1>
                <p class="text-muted-foreground mt-1">View and execute customer trade requests</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CheckCircle class="h-5 w-5" />
                        All Trades ({{ trades.total }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="trades.data.length === 0" class="text-center py-12 text-muted-foreground">
                        No trade requests yet
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="trade in trades.data"
                            :key="trade.id"
                            class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50"
                        >
                            <div class="flex-1">
                                <p class="font-semibold text-lg">{{ formatCurrency(trade.amount) }} {{ trade.currency }}</p>
                                <p class="text-sm text-muted-foreground">
                                    Customer: {{ trade.user?.name || 'Unknown' }} | 
                                    {{ new Date(trade.created_at).toLocaleDateString() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <Badge :class="getStatusBadge(trade.status).class">
                                    {{ getStatusBadge(trade.status).label }}
                                </Badge>
                                <Button v-if="trade.status === 'pending'" size="sm">Execute</Button>
                                <Button v-else variant="outline" size="sm">View</Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
