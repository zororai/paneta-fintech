<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthSplitLayout
        title="Sign in"
        description="Please login to continue to your account"
        :showSignUpLink="canRegister"
    >
        <Head title="Sign in" />

        <div
            v-if="status"
            class="mb-4 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="email" class="text-sm text-slate-500">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                        class="h-12 rounded-lg border-slate-200 bg-white px-4 focus:border-blue-600 focus:ring-blue-600"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password" class="text-sm text-slate-500">Password</Label>
                    <div class="relative">
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Password"
                            class="h-12 rounded-lg border-slate-200 bg-white px-4 pr-10 focus:border-blue-600 focus:ring-blue-600"
                        />
                    </div>
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center gap-2 text-sm text-slate-600">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Keep me logged in</span>
                    </Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm font-medium text-blue-600 hover:text-blue-500"
                        :tabindex="5"
                    >
                        Forgot Password?
                    </TextLink>
                </div>

                <Button
                    type="submit"
                    class="mt-2 h-12 w-full rounded-lg bg-blue-600 text-base font-medium text-white shadow-lg shadow-blue-500/30 transition hover:bg-blue-700"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Sign In
                </Button>

                <!-- Divider -->
                <div class="relative my-2">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-slate-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="bg-slate-100 px-4 text-slate-400">or</span>
                    </div>
                </div>

                <!-- Google Sign In -->
                <Button
                    type="button"
                    variant="outline"
                    class="h-12 w-full rounded-lg border-slate-200 bg-white text-base font-medium text-slate-600 hover:bg-slate-50"
                    :tabindex="6"
                >
                    <svg class="mr-2 h-5 w-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign in with Google
                </Button>
            </div>
        </Form>
    </AuthSplitLayout>
</template>
