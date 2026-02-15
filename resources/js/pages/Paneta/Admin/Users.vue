<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, PaginatedResponse, User } from '@/types';
import { Users, Wallet, ArrowUpRight } from 'lucide-vue-next';

interface UserWithCounts extends User {
    linked_accounts_count: number;
    transaction_intents_count: number;
}

const props = defineProps<{
    users: PaginatedResponse<UserWithCounts>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Admin', href: '/paneta/admin' },
    { title: 'Users' },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getKycColor = (status: string) => {
    switch (status) {
        case 'verified':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getRiskColor = (tier: string) => {
    switch (tier) {
        case 'low':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'high':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};
</script>

<template>
    <Head title="All Users - Admin - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">All Users</h1>
                    <p class="text-muted-foreground">
                        Platform user directory and KYC status
                    </p>
                </div>
                <Badge variant="outline">Read-Only</Badge>
            </div>

            <!-- Users Table -->
            <Card>
                <CardHeader>
                    <CardTitle>User Directory</CardTitle>
                    <CardDescription>
                        Showing {{ users.data.length }} of {{ users.total }} users
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b text-left text-sm text-muted-foreground">
                                    <th class="pb-3 font-medium">User</th>
                                    <th class="pb-3 font-medium">KYC Status</th>
                                    <th class="pb-3 font-medium">Risk Tier</th>
                                    <th class="pb-3 font-medium">Role</th>
                                    <th class="pb-3 font-medium">Accounts</th>
                                    <th class="pb-3 font-medium">Transactions</th>
                                    <th class="pb-3 font-medium">Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-4">
                                        <div>
                                            <p class="font-medium">{{ user.name }}</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ user.email }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <Badge :class="getKycColor(user.kyc_status)">
                                            {{ user.kyc_status }}
                                        </Badge>
                                    </td>
                                    <td class="py-4">
                                        <Badge :class="getRiskColor(user.risk_tier)">
                                            {{ user.risk_tier }}
                                        </Badge>
                                    </td>
                                    <td class="py-4">
                                        <Badge
                                            :variant="user.role === 'admin' ? 'default' : 'outline'"
                                        >
                                            {{ user.role }}
                                        </Badge>
                                    </td>
                                    <td class="py-4">
                                        <div class="flex items-center gap-1">
                                            <Wallet class="h-4 w-4 text-muted-foreground" />
                                            <span>{{ user.linked_accounts_count }}</span>
                                        </div>
                                    </td>
                                    <td class="py-4">
                                        <div class="flex items-center gap-1">
                                            <ArrowUpRight class="h-4 w-4 text-muted-foreground" />
                                            <span>{{ user.transaction_intents_count }}</span>
                                        </div>
                                    </td>
                                    <td class="py-4 text-sm text-muted-foreground">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div
                            v-if="users.data.length === 0"
                            class="py-12 text-center"
                        >
                            <Users class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <p class="text-muted-foreground">No users found</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
