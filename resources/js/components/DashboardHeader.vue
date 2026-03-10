<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { Home, Bell, ChevronDown, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';

const page = usePage();
const auth = computed(() => page.props.auth);
const showNotifications = ref(false);

const notificationCount = computed(() => {
    return 1;
});

const messageCount = computed(() => {
    return 2;
});

const notifications = [
    {
        id: 1,
        title: 'Welcome to PANÉTA',
        message: 'Your account has been successfully created. Start exploring our platform.',
        timestamp: 'Just now',
        type: 'info'
    },
    {
        id: 2,
        title: 'Transaction Completed',
        message: 'Your recent transaction has been processed successfully.',
        timestamp: '5 minutes ago',
        type: 'success'
    },
    {
        id: 3,
        title: 'Security Alert',
        message: 'New login detected from a new device. Please verify if this was you.',
        timestamp: '1 hour ago',
        type: 'warning'
    }
];

const navigateToDashboard = () => {
    router.visit(dashboard());
};
</script>

<template>
    <div class="border-b border-sidebar-border/80 bg-white dark:bg-neutral-950">
        <div class="mx-auto flex h-16 items-center justify-between px-4 md:max-w-7xl">
            <!-- Left Section: Logo and Dashboard Label -->
            <div class="flex items-center gap-3">
                <Link :href="dashboard()" class="flex items-center gap-2">
                    <div class="flex items-center gap-2">
                        <img src="/images/paneta-logo.png" alt="Panéta" class="h-6 w-6 object-contain" />
                        <span class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">PANÉTA</span>
                    </div>
                </Link>
                <div class="h-6 w-px bg-neutral-200 dark:bg-neutral-800"></div>
                <span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 rounded">
                    Dashboard
                </span>
            </div>

            <!-- Right Section: Icons and User Menu -->
            <div class="flex items-center gap-4">
                <!-- Home Icon -->
                <Button 
                    variant="ghost" 
                    size="icon" 
                    class="h-9 w-9 relative"
                    @click="navigateToDashboard"
                >
                    <Home class="h-5 w-5 text-neutral-600 dark:text-neutral-400" />
                    <span v-if="notificationCount > 0" class="absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full"></span>
                </Button>

                <!-- Notification Bell -->
                <Button 
                    variant="ghost" 
                    size="icon" 
                    class="h-9 w-9 relative"
                    @click="showNotifications = true"
                >
                    <Bell class="h-5 w-5 text-neutral-600 dark:text-neutral-400" />
                    <span v-if="messageCount > 0" class="absolute top-1 right-1 h-5 w-5 bg-red-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        {{ messageCount }}
                    </span>
                </Button>

                <!-- User Profile Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <Button variant="ghost" class="h-9 gap-2 px-2 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            <Avatar class="h-7 w-7">
                                <AvatarImage
                                    v-if="auth.user.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback class="bg-neutral-200 text-neutral-900 dark:bg-neutral-700 dark:text-neutral-100 text-xs font-semibold">
                                    {{ getInitials(auth.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100 hidden sm:inline">
                                Demo
                            </span>
                            <ChevronDown class="h-4 w-4 text-neutral-600 dark:text-neutral-400" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </div>

    <!-- Notifications Dialog -->
    <Dialog v-model:open="showNotifications">
        <DialogContent class="max-w-md">
            <DialogHeader class="flex flex-row items-center justify-between space-y-0">
                <DialogTitle>Notifications</DialogTitle>
                <Button 
                    variant="ghost" 
                    size="icon" 
                    class="h-6 w-6"
                    @click="showNotifications = false"
                >
                    <X class="h-4 w-4" />
                </Button>
            </DialogHeader>
            
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <div 
                    v-for="notification in notifications" 
                    :key="notification.id"
                    class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
                    :class="{
                        'bg-blue-50 dark:bg-blue-950/20': notification.type === 'info',
                        'bg-green-50 dark:bg-green-950/20': notification.type === 'success',
                        'bg-yellow-50 dark:bg-yellow-950/20': notification.type === 'warning',
                    }"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <h4 class="font-semibold text-sm text-neutral-900 dark:text-neutral-100">
                                {{ notification.title }}
                            </h4>
                            <p class="text-xs text-neutral-600 dark:text-neutral-400 mt-1">
                                {{ notification.message }}
                            </p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-500 mt-2">
                                {{ notification.timestamp }}
                            </p>
                        </div>
                        <div 
                            class="h-2 w-2 rounded-full flex-shrink-0 mt-1"
                            :class="{
                                'bg-blue-500': notification.type === 'info',
                                'bg-green-500': notification.type === 'success',
                                'bg-yellow-500': notification.type === 'warning',
                            }"
                        ></div>
                    </div>
                </div>

                <div v-if="notifications.length === 0" class="text-center py-8">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        No notifications yet
                    </p>
                </div>
            </div>

            <div class="border-t border-neutral-200 pt-3 dark:border-neutral-800">
                <Button variant="outline" class="w-full text-xs">
                    View All Notifications
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
