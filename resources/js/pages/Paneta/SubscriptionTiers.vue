<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { CheckCircle, Sparkles } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Wealth & Investments', href: '/paneta/wealth' },
    { title: 'Subscription Tiers' },
];
</script>

<template>
    <Head title="Subscription Tiers - PANÉTA Wealth & Investments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="text-center">
                <h1 class="text-3xl font-bold mb-2">Subscription Tiers</h1>
                <p class="text-muted-foreground text-lg">
                    Choose the plan that best fits your investment needs
                </p>
            </div>

            <!-- Subscription Tiers Grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Silver Tier -->
                <Card class="border-2 border-purple-200 flex flex-col">
                    <CardHeader class="bg-purple-50">
                        <CardTitle class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            Silver Tier
                        </CardTitle>
                        <div class="text-2xl font-bold">Free</div>
                    </CardHeader>
                    <CardContent class="pt-4 flex-1 flex flex-col">
                        <p class="text-sm text-muted-foreground mb-4">Retail Investors Mode</p>
                        <ul class="space-y-2 text-sm mb-6 flex-1">
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Basic portfolio tracking</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Up to 5 linked accounts</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Daily data refresh</span>
                            </li>
                        </ul>
                        <Badge class="w-full justify-center bg-purple-100 text-purple-700">Current Plan</Badge>
                    </CardContent>
                </Card>

                <!-- Gold Tier -->
                <Card class="border-2 border-yellow-400 flex flex-col">
                    <CardHeader class="bg-yellow-50">
                        <CardTitle class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            Gold Tier
                        </CardTitle>
                        <div class="text-2xl font-bold">$29/mo</div>
                    </CardHeader>
                    <CardContent class="pt-4 flex-1 flex flex-col">
                        <p class="text-sm text-muted-foreground mb-4">Professional Investors</p>
                        <ul class="space-y-2 text-sm mb-6 flex-1">
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Advanced analytics</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Unlimited linked accounts</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Real-time data</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>AI recommendations</span>
                            </li>
                        </ul>
                        <Button class="w-full bg-yellow-600 hover:bg-yellow-700">Upgrade</Button>
                    </CardContent>
                </Card>

                <!-- Platinum Tier -->
                <Card class="border-2 border-slate-400 flex flex-col">
                    <CardHeader class="bg-gradient-to-br from-slate-50 to-slate-100">
                        <CardTitle class="flex items-center gap-2">
                            <Sparkles class="h-5 w-5 text-slate-600" />
                            Platinum Tier
                        </CardTitle>
                        <div class="text-2xl font-bold">$149/mo</div>
                    </CardHeader>
                    <CardContent class="pt-4 flex-1 flex flex-col">
                        <p class="text-sm text-muted-foreground mb-4">High Net Worth Investors</p>
                        <ul class="space-y-2 text-sm mb-6 flex-1">
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>All Gold features</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Scenario analysis</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Custom reports</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Tax optimization tools</span>
                            </li>
                        </ul>
                        <Button class="w-full bg-slate-600 hover:bg-slate-700">Upgrade</Button>
                    </CardContent>
                </Card>

                <!-- Diamond Tier -->
                <Card class="border-2 border-cyan-400 flex flex-col">
                    <CardHeader class="bg-gradient-to-br from-cyan-50 to-blue-50">
                        <CardTitle class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            Diamond Tier
                        </CardTitle>
                        <div class="text-2xl font-bold">$299/mo</div>
                    </CardHeader>
                    <CardContent class="pt-4 flex-1 flex flex-col">
                        <p class="text-sm text-muted-foreground mb-4">Institutional & Family Offices</p>
                        <ul class="space-y-2 text-sm mb-6 flex-1">
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>All Platinum features</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>Dedicated account manager</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>White-label reporting</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-green-600 flex-shrink-0" />
                                <span>API access & Multi-entity management</span>
                            </li>
                        </ul>
                        <Button class="w-full bg-cyan-600 hover:bg-cyan-700">Upgrade</Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Back Button -->
            <div class="flex justify-center mt-6">
                <Button variant="outline" @click="router.visit('/paneta/wealth')">
                    Back to Wealth & Investments
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
