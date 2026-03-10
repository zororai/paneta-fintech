<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Wallet, ArrowUpRight, Send, FileText, Shield, Users, ArrowRightLeft, TrendingUp, Store, Handshake, Globe, QrCode, Receipt } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import { dashboard } from '@/routes';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user as { role?: string; account_type?: string } | undefined);
const entitlements = computed(() => (page.props.auth as { entitlements?: Record<string, boolean> })?.entitlements ?? {});
const isAdmin = computed(() => user.value?.role === 'admin');
const isRegulator = computed(() => user.value?.role === 'regulator');
const isServiceProvider = computed(() => user.value?.role === 'fx_provider');
const isBusinessAccount = computed(() => user.value?.account_type === 'business');

// Base nav items available to all users
const baseNavItems: NavItem[] = [
    {
        title: 'PANÉTA Dashboard',
        href: '/paneta',
        icon: LayoutGrid,
    },
    {
        title: 'Linked Accounts',
        href: '/paneta/accounts',
        icon: Wallet,
    },
    {
        title: 'Send Money',
        href: '/paneta/transactions/create',
        icon: Send,
    },
    {
        title: 'Transactions',
        href: '/paneta/transactions',
        icon: ArrowUpRight,
    },
    {
        title: 'Currency Exchange',
        href: '/paneta/currency-exchange',
        icon: ArrowRightLeft,
    },
    {
        title: 'Wealth & Investments',
        href: '/paneta/wealth',
        icon: TrendingUp,
    },
    {
        title: 'Payment Requests',
        href: '/paneta/payment-requests',
        icon: QrCode,
    },
    {
        title: 'Bills & Airtime',
        href: '/paneta/bills',
        icon: Receipt,
    },
];

// Business-only nav items (restricted for personal accounts)
const businessOnlyNavItems: NavItem[] = [
    {
        title: 'Merchant SoftPOS',
        href: '/paneta/merchant',
        icon: Store,
    },
    {
        title: 'FX Provider',
        href: '/paneta/fx-provider',
        icon: Handshake,
    },
];

// Computed nav items based on account type
const panetaNavItems = computed(() => {
    if (isBusinessAccount.value) {
        // Business accounts get all items, insert business items in appropriate positions
        const items = [...baseNavItems];
        // Insert FX Provider after Currency Exchange (index 4)
        items.splice(5, 0, businessOnlyNavItems[1]);
        // Insert Merchant SoftPOS before Audit Logs (will be second to last)
        items.splice(items.length - 1, 0, businessOnlyNavItems[0]);
        return items;
    }
    return baseNavItems;
});

const adminNavItems: NavItem[] = [
    {
        title: 'Admin Dashboard',
        href: '/paneta/admin',
        icon: Shield,
    },
    {
        title: 'All Transactions',
        href: '/paneta/admin/transactions',
        icon: ArrowUpRight,
    },
    {
        title: 'All Audit Logs',
        href: '/paneta/admin/audit-logs',
        icon: FileText,
    },
    {
        title: 'Users',
        href: '/paneta/admin/users',
        icon: Users,
    },
];

const regulatorNavItems: NavItem[] = [
    {
        title: 'Regulator Panel',
        href: '/paneta/regulator',
        icon: Shield,
    },
    {
        title: 'All Transactions',
        href: '/paneta/regulator/transactions',
        icon: ArrowUpRight,
    },
    {
        title: 'Audit Trail',
        href: '/paneta/regulator/audit-trail',
        icon: FileText,
    },
];

const serviceProviderNavItems: NavItem[] = [
    {
        title: 'Provider Dashboard',
        href: '/paneta/service-provider',
        icon: LayoutGrid,
    },
    {
        title: 'FX Offers',
        href: '/paneta/service-provider/offers',
        icon: TrendingUp,
    },
    {
        title: 'Trade Requests',
        href: '/paneta/service-provider/trades',
        icon: ArrowRightLeft,
    },
    {
        title: 'Business Reports',
        href: '/paneta/service-provider/reports',
        icon: FileText,
    },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- PANÉTA Section (only for regular users, not service providers) -->
            <SidebarGroup v-if="!isAdmin && !isRegulator && !isServiceProvider" class="px-2 py-0">
                <SidebarGroupLabel>PANÉTA</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in panetaNavItems" :key="item.title">
                        <SidebarMenuButton as-child :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Admin Section (only for admins) -->
            <SidebarGroup v-if="isAdmin" class="px-2 py-0">
                <SidebarGroupLabel>Admin</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in adminNavItems" :key="item.title">
                        <SidebarMenuButton as-child :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Regulator Section (only for regulators) -->
            <SidebarGroup v-if="isRegulator" class="px-2 py-0">
                <SidebarGroupLabel>Regulator</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in regulatorNavItems" :key="item.title">
                        <SidebarMenuButton as-child :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Service Provider Section (only for FX providers) -->
            <SidebarGroup v-if="isServiceProvider" class="px-2 py-0">
                <SidebarGroupLabel>Service Provider</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in serviceProviderNavItems" :key="item.title">
                        <SidebarMenuButton as-child :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
