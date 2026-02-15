<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { BreadcrumbItem, AuditLog, PaginatedResponse } from '@/types';
import { FileText, User, Wallet, ArrowUpRight, Shield } from 'lucide-vue-next';

const props = defineProps<{
    logs: PaginatedResponse<AuditLog>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'PANÉTA', href: '/paneta' },
    { title: 'Audit Logs' },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const getActionColor = (action: string) => {
    if (action.includes('created') || action.includes('registered') || action.includes('linked')) {
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
    }
    if (action.includes('failed') || action.includes('revoked')) {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
    }
    if (action.includes('completed') || action.includes('executed')) {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
};

const getEntityIcon = (entityType: string) => {
    switch (entityType) {
        case 'user':
            return User;
        case 'linked_account':
            return Wallet;
        case 'transaction_intent':
        case 'payment_instruction':
            return ArrowUpRight;
        default:
            return FileText;
    }
};

const formatAction = (action: string) => {
    return action
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};
</script>

<template>
    <Head title="Audit Logs - PANÉTA" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold">Audit Logs</h1>
                <p class="text-muted-foreground">
                    Immutable record of all actions on your account
                </p>
            </div>

            <!-- Info Banner - PANÉTA Branded -->
            <Card class="border-primary/20 bg-primary/5 dark:border-primary/30 dark:bg-primary/10">
                <CardContent class="flex items-center gap-4 pt-6">
                    <Shield class="h-8 w-8 text-primary" />
                    <div>
                        <h3 class="font-semibold text-primary dark:text-primary-foreground">
                            Immutable Audit Trail
                        </h3>
                        <p class="text-sm text-primary/80 dark:text-primary-foreground/80">
                            Every action is logged and cannot be modified or deleted. This ensures
                            full transparency and regulatory compliance.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Audit Logs -->
            <Card>
                <CardHeader>
                    <CardTitle>Activity Log</CardTitle>
                    <CardDescription>
                        Showing {{ logs.data.length }} of {{ logs.total }} log entries
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div
                            v-for="log in logs.data"
                            :key="log.id"
                            class="flex items-start gap-4 rounded-lg border p-4"
                        >
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-muted"
                            >
                                <component
                                    :is="getEntityIcon(log.entity_type)"
                                    class="h-5 w-5 text-muted-foreground"
                                />
                            </div>
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <Badge :class="getActionColor(log.action)">
                                        {{ formatAction(log.action) }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        {{ log.entity_type }}
                                        <span v-if="log.entity_id">#{{ log.entity_id }}</span>
                                    </span>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatDate(log.created_at) }}
                                </p>
                                <div
                                    v-if="log.metadata && Object.keys(log.metadata).length > 0"
                                    class="mt-2"
                                >
                                    <details class="text-sm">
                                        <summary
                                            class="cursor-pointer text-muted-foreground hover:text-foreground"
                                        >
                                            View metadata
                                        </summary>
                                        <pre
                                            class="mt-2 rounded bg-muted p-2 text-xs overflow-auto"
                                        >{{ JSON.stringify(log.metadata, null, 2) }}</pre>
                                    </details>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="logs.data.length === 0"
                            class="py-12 text-center"
                        >
                            <FileText class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 class="text-lg font-semibold">No audit logs yet</h3>
                            <p class="text-sm text-muted-foreground">
                                Activity will appear here as you use the platform
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
