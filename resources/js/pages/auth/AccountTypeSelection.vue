<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { User, Building2 } from 'lucide-vue-next';

const accountTypes = [
    {
        type: 'personal',
        title: 'Personal Account',
        description: 'For individual users and personal banking',
        icon: User,
        href: '/register?type=personal',
    },
    {
        type: 'business',
        title: 'Business Account',
        description: 'For companies and organizations',
        icon: Building2,
        href: '/register?type=business',
    },
];
</script>

<template>
    <Head title="Choose Account Type - PANÉTA" />
    <AuthLayout>
        <div class="w-full max-w-md mx-auto">
            <Card class="border-2">
                <CardContent class="p-8">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold text-gray-900">Choose Account Type</h1>
                        <p class="text-sm text-gray-600 mt-2">Select the type of account you want to create</p>
                    </div>

                    <div class="space-y-4">
                        <Link
                            v-for="account in accountTypes"
                            :key="account.type"
                            :href="account.href"
                            class="block"
                        >
                            <Card class="border-2 hover:border-blue-500 hover:bg-blue-50 transition-all cursor-pointer">
                                <CardContent class="p-6">
                                    <div class="flex items-start gap-4">
                                        <div class="p-3 bg-blue-100 rounded-lg">
                                            <component :is="account.icon" class="h-6 w-6 text-blue-600" />
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-lg text-gray-900">{{ account.title }}</h3>
                                            <p class="text-sm text-gray-600 mt-1">{{ account.description }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    </div>

                    <div class="mt-8 text-center">
                        <Link href="/" class="text-sm text-gray-600 hover:text-gray-900">
                            Back to Home
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthLayout>
</template>
