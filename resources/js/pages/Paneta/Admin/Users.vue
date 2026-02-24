<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem, PaginatedResponse, User } from '@/types';
import { Users, Wallet, ArrowUpRight, Eye, UserCog, Ban, CheckCircle, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

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

const showDetailsDialog = ref(false);
const showManageDialog = ref(false);
const selectedUser = ref<UserWithCounts | null>(null);

const manageForm = useForm({
    kyc_status: '',
    risk_tier: '',
    role: '',
    is_suspended: false,
});

const viewUserDetails = (user: UserWithCounts) => {
    selectedUser.value = user;
    showDetailsDialog.value = true;
};

const manageUser = (user: UserWithCounts) => {
    selectedUser.value = user;
    manageForm.kyc_status = user.kyc_status;
    manageForm.risk_tier = user.risk_tier;
    manageForm.role = user.role;
    manageForm.is_suspended = (user as any).is_suspended || false;
    showManageDialog.value = true;
};

const updateUser = () => {
    if (!selectedUser.value) return;
    
    manageForm.put(`/paneta/admin/users/${selectedUser.value.id}`, {
        onSuccess: () => {
            showManageDialog.value = false;
            selectedUser.value = null;
        },
    });
};

const suspendUser = (userId: number) => {
    if (confirm('Are you sure you want to suspend this user? They will not be able to access the platform.')) {
        router.post(`/paneta/admin/users/${userId}/suspend`);
    }
};

const activateUser = (userId: number) => {
    if (confirm('Are you sure you want to activate this user?')) {
        router.post(`/paneta/admin/users/${userId}/activate`);
    }
};

const deleteUser = (userId: number) => {
    if (confirm('Are you sure you want to DELETE this user? This action cannot be undone and will remove all associated data.')) {
        router.delete(`/paneta/admin/users/${userId}`);
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
                                    <th class="pb-3 font-medium">Actions</th>
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
                                    <td class="py-4">
                                        <div class="flex items-center gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="viewUserDetails(user)"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="manageUser(user)"
                                            >
                                                <UserCog class="h-4 w-4" />
                                            </Button>
                                        </div>
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

        <!-- User Details Dialog -->
        <Dialog v-model:open="showDetailsDialog">
            <DialogContent class="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>User Details</DialogTitle>
                    <DialogDescription>
                        Complete information for {{ selectedUser?.name }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedUser" class="space-y-4 py-4">
                    <div class="grid gap-3">
                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Name</div>
                            <div class="col-span-2 font-medium">{{ selectedUser.name }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Email</div>
                            <div class="col-span-2 font-medium">{{ selectedUser.email }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Role</div>
                            <div class="col-span-2">
                                <Badge :variant="selectedUser.role === 'admin' ? 'default' : 'outline'">
                                    {{ selectedUser.role }}
                                </Badge>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">KYC Status</div>
                            <div class="col-span-2">
                                <Badge :class="getKycColor(selectedUser.kyc_status)">
                                    {{ selectedUser.kyc_status }}
                                </Badge>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Risk Tier</div>
                            <div class="col-span-2">
                                <Badge :class="getRiskColor(selectedUser.risk_tier)">
                                    {{ selectedUser.risk_tier }}
                                </Badge>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Status</div>
                            <div class="col-span-2">
                                <Badge :variant="selectedUser.is_suspended ? 'destructive' : 'default'">
                                    {{ selectedUser.is_suspended ? 'Suspended' : 'Active' }}
                                </Badge>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Linked Accounts</div>
                            <div class="col-span-2 font-medium">{{ selectedUser.linked_accounts_count }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Transactions</div>
                            <div class="col-span-2 font-medium">{{ selectedUser.transaction_intents_count }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 p-3 border rounded-lg">
                            <div class="text-sm text-muted-foreground">Joined</div>
                            <div class="col-span-2 font-medium">{{ formatDate(selectedUser.created_at) }}</div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showDetailsDialog = false">
                        Close
                    </Button>
                    <Button @click="manageUser(selectedUser!)">
                        <UserCog class="mr-2 h-4 w-4" />
                        Manage User
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Manage User Dialog -->
        <Dialog v-model:open="showManageDialog">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Manage User</DialogTitle>
                    <DialogDescription>
                        Update settings for {{ selectedUser?.name }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedUser" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>KYC Status</Label>
                        <Select v-model="manageForm.kyc_status">
                            <SelectTrigger>
                                <SelectValue placeholder="Select KYC status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="verified">Verified</SelectItem>
                                <SelectItem value="rejected">Rejected</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Risk Tier</Label>
                        <Select v-model="manageForm.risk_tier">
                            <SelectTrigger>
                                <SelectValue placeholder="Select risk tier" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Role</Label>
                        <Select v-model="manageForm.role">
                            <SelectTrigger>
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="user">User</SelectItem>
                                <SelectItem value="admin">Admin</SelectItem>
                                <SelectItem value="regulator">Regulator</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div>
                            <p class="font-medium">Account Status</p>
                            <p class="text-sm text-muted-foreground">
                                {{ selectedUser.is_suspended ? 'User is suspended' : 'User is active' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                v-if="!selectedUser.is_suspended"
                                variant="destructive"
                                size="sm"
                                @click="suspendUser(selectedUser.id)"
                            >
                                <Ban class="mr-2 h-4 w-4" />
                                Suspend
                            </Button>
                            <Button
                                v-else
                                variant="default"
                                size="sm"
                                @click="activateUser(selectedUser.id)"
                            >
                                <CheckCircle class="mr-2 h-4 w-4" />
                                Activate
                            </Button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-destructive/50 rounded-lg bg-destructive/5">
                        <div>
                            <p class="font-medium text-destructive">Danger Zone</p>
                            <p class="text-sm text-muted-foreground">
                                Permanently delete this user
                            </p>
                        </div>
                        <Button
                            variant="destructive"
                            size="sm"
                            @click="deleteUser(selectedUser.id)"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showManageDialog = false">
                        Cancel
                    </Button>
                    <Button @click="updateUser" :disabled="manageForm.processing">
                        <span v-if="manageForm.processing">Saving...</span>
                        <span v-else>Save Changes</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
