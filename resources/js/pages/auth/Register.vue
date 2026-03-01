<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Badge } from '@/components/ui/badge';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { User, Building2 } from 'lucide-vue-next';

const props = defineProps<{
    accountType?: string;
}>();

const isPersonal = props.accountType === 'personal';
const isBusiness = props.accountType === 'business';
const accountTitle = isPersonal ? 'Personal Account' : isBusiness ? 'Business Account' : 'Account';
</script>

<template>
    <AuthBase
        :title="`Create ${accountTitle}`"
        description="Enter your details below to create your account"
    >
        <Head :title="`Register - ${accountTitle}`" />

        <!-- Account Type Badge -->
        <div v-if="accountType" class="flex items-center justify-center gap-2 mb-4">
            <Badge class="bg-blue-100 text-blue-700 px-4 py-2">
                <component :is="isPersonal ? User : Building2" class="h-4 w-4 mr-2" />
                {{ accountTitle }}
            </Badge>
            <Link href="/register" class="text-sm text-gray-600 hover:text-gray-900">
                Change
            </Link>
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <!-- Business Name (Business accounts only) -->
                <div v-if="isBusiness" class="grid gap-2">
                    <Label for="business_name">Business Name</Label>
                    <Input
                        id="business_name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        name="business_name"
                        placeholder="Company or organization name"
                    />
                    <InputError :message="errors.business_name" />
                </div>

                <!-- Registration Number (Business accounts only) -->
                <div v-if="isBusiness" class="grid gap-2">
                    <Label for="registration_number">Registration Number</Label>
                    <Input
                        id="registration_number"
                        type="text"
                        required
                        :tabindex="2"
                        name="registration_number"
                        placeholder="Business registration number"
                    />
                    <InputError :message="errors.registration_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">{{ isBusiness ? 'Contact Person Name' : 'Full Name' }}</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        :autofocus="!isBusiness"
                        :tabindex="isBusiness ? 3 : 1"
                        autocomplete="name"
                        name="name"
                        :placeholder="isBusiness ? 'Contact person full name' : 'Your full name'"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="isBusiness ? 10 : 6"
                    >Log in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
