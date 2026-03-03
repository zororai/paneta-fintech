<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import { Building2, Upload, FileText, DollarSign, Calendar, MapPin, Phone, Mail, Briefcase, TrendingUp, FileCheck, Shield } from 'lucide-vue-next';

const form = useForm({
    trading_name: '',
    trading_volume: '',
    daily_limit: '',
    licenses: null as File | null,
    certificates: null as File | null,
    license_validity: '',
    email: '',
    phone: '',
    physical_address: '',
    country_of_origin: '',
    settlement_accounts: '',
    key_services: '',
    member_since: '',
    trading_as: '',
    processing_fee: '',
    tax_clearance: null as File | null,
    tax_id: '',
});

const handleFileChange = (event: Event, field: 'licenses' | 'certificates' | 'tax_clearance') => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form[field] = target.files[0];
    }
};

const submitRegistration = () => {
    form.post('/paneta/fx-provider/register', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="FX Provider" />

    <AppLayout>
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">FX Provider</h1>
                    <p class="text-muted-foreground">Manage your foreign exchange services and offerings</p>
                </div>
            </div>

            <Tabs default-value="register" class="w-full">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="register">Register as FX Dealer</TabsTrigger>
                    <TabsTrigger value="offers">Create Offers</TabsTrigger>
                    <TabsTrigger value="requests">Received Exchange Request</TabsTrigger>
                    <TabsTrigger value="summary">View Consolidated Accounts Summary</TabsTrigger>
                </TabsList>

                <TabsContent value="register" class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Building2 class="h-5 w-5" />
                                Register as FX Dealer
                            </CardTitle>
                            <CardDescription>
                                Complete the registration form to become a verified FX dealer on PANÉTA. Your application will be reviewed by our admin team.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Trading Name -->
                            <div class="grid gap-2">
                                <Label for="trading_name" class="flex items-center gap-2">
                                    <Briefcase class="h-4 w-4" />
                                    Trading Name
                                </Label>
                                <Input 
                                    id="trading_name" 
                                    v-model="form.trading_name" 
                                    placeholder="Enter your trading name" 
                                    required 
                                />
                                <InputError :message="form.errors.trading_name" />
                            </div>

                            <!-- Trading Volume and Daily Limit -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="trading_volume" class="flex items-center gap-2">
                                        <TrendingUp class="h-4 w-4" />
                                        Trading Volume
                                    </Label>
                                    <Input 
                                        id="trading_volume" 
                                        v-model="form.trading_volume" 
                                        placeholder="e.g., $1M - $5M monthly" 
                                        required 
                                    />
                                    <InputError :message="form.errors.trading_volume" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="daily_limit" class="flex items-center gap-2">
                                        <DollarSign class="h-4 w-4" />
                                        Permitted Daily Trading Limit
                                    </Label>
                                    <Input 
                                        id="daily_limit" 
                                        v-model="form.daily_limit" 
                                        placeholder="e.g., $100,000" 
                                        required 
                                    />
                                    <InputError :message="form.errors.daily_limit" />
                                </div>
                            </div>

                            <!-- Licenses and Certificates -->
                            <div class="space-y-4">
                                <Label class="flex items-center gap-2">
                                    <FileCheck class="h-4 w-4" />
                                    Licenses and Certificates
                                </Label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label for="licenses" class="text-sm text-muted-foreground">Upload Licenses</Label>
                                        <div class="flex items-center gap-2">
                                            <Input 
                                                id="licenses" 
                                                type="file" 
                                                accept=".pdf,.jpg,.jpeg,.png" 
                                                @change="(e) => handleFileChange(e, 'licenses')"
                                            />
                                            <Upload class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="certificates" class="text-sm text-muted-foreground">Upload Trading Certificates</Label>
                                        <div class="flex items-center gap-2">
                                            <Input 
                                                id="certificates" 
                                                type="file" 
                                                accept=".pdf,.jpg,.jpeg,.png" 
                                                @change="(e) => handleFileChange(e, 'certificates')"
                                            />
                                            <Upload class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="license_validity" class="flex items-center gap-2">
                                        <Calendar class="h-4 w-4" />
                                        License Valid Until
                                    </Label>
                                    <Input 
                                        id="license_validity" 
                                        v-model="form.license_validity" 
                                        type="date" 
                                        required 
                                    />
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="space-y-4">
                                <Label class="flex items-center gap-2">
                                    <Phone class="h-4 w-4" />
                                    Contact Information
                                </Label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label for="email" class="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Mail class="h-4 w-4" />
                                            Email Address
                                        </Label>
                                        <Input 
                                            id="email" 
                                            v-model="form.email" 
                                            type="email" 
                                            placeholder="contact@example.com" 
                                            required 
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="phone" class="text-sm text-muted-foreground">Phone Number</Label>
                                        <Input 
                                            id="phone" 
                                            v-model="form.phone" 
                                            type="tel" 
                                            placeholder="+263 123 456 789" 
                                            required 
                                        />
                                    </div>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="physical_address" class="flex items-center gap-2 text-sm text-muted-foreground">
                                        <MapPin class="h-4 w-4" />
                                        Physical Address
                                    </Label>
                                    <Input 
                                        id="physical_address" 
                                        v-model="form.physical_address" 
                                        placeholder="Enter your business address" 
                                        required 
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="country_of_origin">Country of Origin</Label>
                                    <select 
                                        id="country_of_origin" 
                                        v-model="form.country_of_origin" 
                                        required 
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <option value="">Select country</option>
                                        <option value="ZW">Zimbabwe</option>
                                        <option value="ZA">South Africa</option>
                                        <option value="US">United States</option>
                                        <option value="GB">United Kingdom</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Settlement Accounts -->
                            <div class="grid gap-2">
                                <Label for="settlement_accounts">Settlement Accounts</Label>
                                <textarea 
                                    id="settlement_accounts" 
                                    v-model="form.settlement_accounts" 
                                    placeholder="List all accounts which support different currencies (e.g., USD Account: 123456789, EUR Account: 987654321)" 
                                    rows="3"
                                    required 
                                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                                <p class="text-xs text-muted-foreground">Provide account details for each currency you support</p>
                            </div>

                            <!-- Key FX Services & Features -->
                            <div class="grid gap-2">
                                <Label for="key_services">Key FX Services & Features</Label>
                                <Textarea 
                                    id="key_services" 
                                    v-model="form.key_services" 
                                    placeholder="e.g., African markets, European markets, ZWL expertise, Local market rates, Fast processing, RTGS integration, Local Currency Exchange, Zimbabwe Corridor Cross-border Remittances" 
                                    rows="4"
                                    required 
                                />
                                <p class="text-xs text-muted-foreground">This information will appear on the FX Marketplace</p>
                            </div>

                            <!-- Member Since & Trading As -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="member_since" class="flex items-center gap-2">
                                        <Calendar class="h-4 w-4" />
                                        Member Since
                                    </Label>
                                    <Input 
                                        id="member_since" 
                                        v-model="form.member_since" 
                                        type="date" 
                                        required 
                                    />
                                    <p class="text-xs text-muted-foreground">Date started trading or registered</p>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="trading_as">Trading As</Label>
                                    <select 
                                        id="trading_as" 
                                        v-model="form.trading_as" 
                                        required 
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <option value="">Select type</option>
                                        <option value="bank_treasury">Bank Treasury</option>
                                        <option value="bureau_de_change">Bureau De Change</option>
                                        <option value="registered_fx_dealer">Registered FX Dealer</option>
                                        <option value="remittance">Remittance</option>
                                        <option value="digital_wallet">Digital Wallet</option>
                                        <option value="authorized_dealer">Authorized Dealer</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Processing Fee -->
                            <div class="grid gap-2">
                                <Label for="processing_fee" class="flex items-center gap-2">
                                    <DollarSign class="h-4 w-4" />
                                    Processing Fee
                                </Label>
                                <Input 
                                    id="processing_fee" 
                                    v-model="form.processing_fee" 
                                    placeholder="e.g., 1.5% or $10 per transaction" 
                                    required 
                                />
                                <p class="text-xs text-muted-foreground">Average fee charged by your service</p>
                            </div>

                            <!-- Tax Clearance Certificate and Tax ID -->
                            <div class="space-y-4">
                                <Label class="flex items-center gap-2">
                                    <Shield class="h-4 w-4" />
                                    Tax Compliance
                                </Label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-2">
                                        <Label for="tax_clearance" class="text-sm text-muted-foreground">Tax Clearance Certificate</Label>
                                        <div class="flex items-center gap-2">
                                            <Input 
                                                id="tax_clearance" 
                                                type="file" 
                                                accept=".pdf,.jpg,.jpeg,.png" 
                                                @change="(e) => handleFileChange(e, 'tax_clearance')"
                                            />
                                            <Upload class="h-4 w-4 text-muted-foreground" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="tax_id" class="text-sm text-muted-foreground">Tax ID</Label>
                                        <Input 
                                            id="tax_id" 
                                            v-model="form.tax_id" 
                                            placeholder="Enter tax identification number" 
                                            required 
                                        />
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">Valid for compliance purposes</p>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end gap-3 pt-4">
                                <Button variant="outline" type="button">Cancel</Button>
                                <Button @click="submitRegistration" type="button" :disabled="form.processing" class="gap-2">
                                    <Spinner v-if="form.processing" />
                                    <FileText v-else class="h-4 w-4" />
                                    Submit for Verification
                                </Button>
                            </div>

                            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                                <p class="font-semibold mb-1">📋 What happens next?</p>
                                <p>Your application will be sent to PANÉTA Admin for verification. Once approved, you'll be able to create offers and start trading on the FX Marketplace.</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="offers">
                    <Card>
                        <CardHeader>
                            <CardTitle>Create Offers</CardTitle>
                            <CardDescription>Create and manage your FX exchange offers</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-center h-64 text-muted-foreground">
                                <p>Complete registration to create offers</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="requests">
                    <Card>
                        <CardHeader>
                            <CardTitle>Received Exchange Requests</CardTitle>
                            <CardDescription>View and manage incoming exchange requests</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-center h-64 text-muted-foreground">
                                <p>Complete registration to view requests</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="summary">
                    <Card>
                        <CardHeader>
                            <CardTitle>Consolidated Accounts Summary</CardTitle>
                            <CardDescription>Overview of all your settlement accounts and balances</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-center h-64 text-muted-foreground">
                                <p>Complete registration to view account summary</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
