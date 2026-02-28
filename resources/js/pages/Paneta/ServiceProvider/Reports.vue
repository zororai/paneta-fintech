<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { BarChart3, TrendingUp, DollarSign, Download } from 'lucide-vue-next';

const props = defineProps<{
    provider: any;
    businessReport: any;
    performanceReport: any;
    financialReport: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { label: 'Service Provider', href: '/paneta/service-provider' },
    { label: 'Reports', href: '/paneta/service-provider/reports' },
];

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};
</script>

<template>
    <Head title="Reports - Service Provider - PANÉTA" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Business Reports</h1>
                    <p class="text-muted-foreground mt-1">Performance, financial, and business analytics</p>
                </div>
                <Button class="gap-2">
                    <Download class="h-4 w-4" />
                    Export All Reports
                </Button>
            </div>

            <!-- Business Report -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BarChart3 class="h-5 w-5" />
                        Business Report
                    </CardTitle>
                    <CardDescription>Customer metrics and market share analysis</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground">Total Customers</p>
                            <p class="text-2xl font-bold">{{ businessReport.total_customers }}</p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground">Repeat Customers</p>
                            <p class="text-2xl font-bold">{{ businessReport.repeat_customers }}</p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground">Market Share</p>
                            <p class="text-2xl font-bold">{{ businessReport.market_share }}%</p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground">Growth Rate</p>
                            <p class="text-2xl font-bold text-green-600">+{{ businessReport.growth_rate }}%</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Performance Report -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="h-5 w-5" />
                        Performance Report
                    </CardTitle>
                    <CardDescription>Trading activity and success metrics</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground mb-2">Average Spread</p>
                            <p class="text-2xl font-bold">{{ performanceReport.avg_spread?.toFixed(2) }}%</p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground mb-2">Trades This Month</p>
                            <p class="text-2xl font-bold">{{ performanceReport.trades_by_month?.[0]?.count || 0 }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Financial Report -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <DollarSign class="h-5 w-5" />
                        Financial Report
                    </CardTitle>
                    <CardDescription>Revenue and earnings analysis</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground">Revenue YTD</p>
                            <p class="text-2xl font-bold">{{ formatCurrency(financialReport.total_revenue_ytd) }}</p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground">This Month</p>
                            <p class="text-2xl font-bold">{{ formatCurrency(financialReport.revenue_by_month?.[0]?.revenue || 0) }}</p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <p class="text-sm text-muted-foreground">Projected Annual</p>
                            <p class="text-2xl font-bold">{{ formatCurrency(financialReport.projected_revenue) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
