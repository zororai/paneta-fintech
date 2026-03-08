<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { register } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
    showSignUpLink?: boolean;
}>();
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-blue-600 text-white">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-500 to-blue-700 opacity-100"></div>
        </div>

        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-6 py-12">
            <div class="w-full max-w-5xl space-y-10 text-center">
                <div class="space-y-6">
                    <div class="space-y-4">
                        <h2 class="text-5xl font-bold tracking-tight text-white md:text-6xl">
                            Welcome to PANÉTA
                        </h2>
                        <p class="text-lg text-blue-100 md:text-xl">
                            Your Comprehensive Financial Solution for the Modern Digital Economy
                        </p>
                    </div>

                    <div class="flex justify-center">
                        <div class="rounded-full bg-gradient-to-br from-slate-50 via-slate-200 to-slate-400 p-[2px] shadow-[0_20px_60px_rgba(15,23,42,0.6)]">
                            <div class="rounded-full bg-gradient-to-br from-gray-200 via-gray-100 to-gray-300 p-6">
                                <img
                                    src="/images/paneta-logo.png"
                                    alt="PANÉTA logo"
                                    class="h-28 w-auto"
                                />
                            </div>
                        </div>
                    </div>

                    <p class="text-lg font-semibold text-white">
                        The Network of Global Finance
                    </p>

                    <div class="flex flex-wrap justify-center gap-4">
                        <Link
                            :href="register()"
                            class="inline-flex items-center justify-center rounded-full bg-white px-8 py-3 text-base font-semibold text-slate-900 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        >
                            Register Now
                        </Link>
                    </div>
                </div>

                <div
                    id="auth-form"
                    class="mx-auto w-full max-w-xl rounded-3xl bg-white/95 p-8 text-left shadow-2xl shadow-blue-900/40 backdrop-blur"
                >
                    <div class="mb-8 text-slate-900" v-if="title || description">
                        <h2 class="text-3xl font-bold" v-if="title">
                            {{ title }}
                        </h2>
                        <p class="mt-2 text-base text-slate-500" v-if="description">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

