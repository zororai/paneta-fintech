<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home, register } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
    showSignUpLink?: boolean;
}>();
</script>

<template>
    <div class="flex min-h-screen bg-slate-100">
        <!-- Left Panel - Welcome Section -->
        <div
            class="relative hidden w-1/2 overflow-hidden lg:block"
        >
            <!-- Gradient Background -->
            <div class="absolute inset-0 bg-gradient-to-br from-blue-900 via-blue-700 to-blue-900">
                <!-- Decorative curves -->
                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 600 800" preserveAspectRatio="xMidYMid slice">
                    <path d="M-100,400 Q150,300 100,600 T400,800" stroke="rgba(99, 200, 200, 0.3)" stroke-width="120" fill="none" />
                    <path d="M600,100 Q450,250 500,0" stroke="rgba(99, 200, 200, 0.2)" stroke-width="100" fill="none" />
                </svg>
            </div>
            
            <!-- Content -->
            <div class="relative z-10 flex h-full flex-col justify-center p-12">
                <h1 class="mb-4 text-5xl font-bold text-white">Welcome</h1>
                <p class="max-w-xs text-lg text-white/80">
                    Your trusted platform<br />
                    for seamless administration<br />
                    and security.
                </p>
                
                <!-- Logo -->
                <div class="mt-12 flex justify-center">
                    <img 
                        src="/images/paneta-logo.png" 
                        alt="Panéta Capital" 
                        class="h-64 w-auto drop-shadow-2xl"
                    />
                </div>
            </div>
        </div>

        <!-- Right Panel - Form Section -->
        <div class="flex w-full flex-col lg:w-1/2">
            <!-- Header with Logo and Sign Up -->
            <div class="flex items-center justify-between p-6 lg:p-8">
                <Link :href="home()" class="flex items-center gap-2">
                    <AppLogoIcon class="size-8 fill-current text-blue-600" />
                    <span class="text-xl font-semibold text-slate-800">{{ name }}</span>
                </Link>
                <Link
                    v-if="showSignUpLink"
                    :href="register()"
                    class="flex items-center gap-1 rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                >
                    Sign Up
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>

            <!-- Form Content -->
            <div class="flex flex-1 items-center justify-center px-6 lg:px-16">
                <div class="w-full max-w-md">
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-slate-800" v-if="title">
                            {{ title }}
                        </h2>
                        <p class="mt-2 text-slate-500" v-if="description">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
