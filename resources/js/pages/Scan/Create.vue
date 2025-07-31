<script setup lang="ts">

import {
    DateFormatter,
    type DateValue,
    getLocalTimeZone,
} from '@internationalized/date'
import { ref } from 'vue';
import { CalendarIcon, Plus, X } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Calendar } from '@/components/ui/calendar'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Popover,
    PopoverTrigger,
    PopoverContent
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import HeadingSmall from '@/components/HeadingSmall.vue';

interface Props {
    companies: Array<{id: number, name: string}>;
}

const props = defineProps<Props>();

const selectedSchedule = ref('immediate');
const frequency = ref('weekly');
const dayOfWeek = ref('1');
const urls = ref(['']);
const ipAddresses = ref(['']);
const df = new DateFormatter('en-US', {
    dateStyle: 'long',
});
const value = ref<DateValue>();

const form = useForm({
    company_id: '',
    company_name: '',
    name: '',
    description: '',
    urls: [''],
    ip_addresses: [''],
    send_notification: false,
    notification_email: '',
    schedule_type: 'immediate',
    scheduled_date: '',
    schedule_time: '09:00',
    frequency: 'weekly',
    day_of_week: 1,
});

const showCreateCompany = ref(false);

function addUrl() {
    form.urls.push('');
}

function removeUrl(index: number) {
    if (form.urls.length > 1) {
        form.urls.splice(index, 1);
    }
}

function addIpAddress() {
    form.ip_addresses.push('');
}

function removeIpAddress(index: number) {
    if (form.ip_addresses.length > 1) {
        form.ip_addresses.splice(index, 1);
    }
}

function updateScheduleType(type: string) {
    form.schedule_type = type;
    selectedSchedule.value = type;
}

function updateFrequency(freq: string) {
    form.frequency = freq;
    frequency.value = freq;
}

function updateDayOfWeek(day: string) {
    form.day_of_week = parseInt(day);
    dayOfWeek.value = day;
}

function formatDateForSubmission() {
    if (value.value && selectedSchedule.value === 'once') {
        // Convert DateValue to YYYY-MM-DD format
        const date = value.value.toDate(getLocalTimeZone());
        form.scheduled_date = date.toISOString().split('T')[0];
    }
}

function toggleCreateCompany() {
    showCreateCompany.value = !showCreateCompany.value;
    if (showCreateCompany.value) {
        form.company_id = '';
    } else {
        form.company_name = '';
    }
}

function submitForm() {
    // Sync reactive values with form
    formatDateForSubmission();
    
    form.post(route('scans.store'), {
        onSuccess: () => {
            // Form submitted successfully
        },
        onError: (errors) => {
            console.error('Form errors:', errors);
        }
    });
}

</script>

<template>
    <Head title="Create Scan" />
    <AppLayout>
        <div class="flex justify-center px-6 mt-6">
            <form @submit.prevent="submitForm" class="space-y-6 w-full max-w-2xl">
                <HeadingSmall title="Create New Scan" description="Set up a security scan for your URLs and IP addresses" />
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="company">Company</Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="toggleCreateCompany"
                            class="text-sm"
                        >
                            {{ showCreateCompany ? 'Select existing' : 'Create new' }}
                        </Button>
                    </div>
                    
                    <div v-if="!showCreateCompany">
                        <Select v-model="form.company_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a company" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="company in companies" :key="company.id" :value="company.id.toString()">
                                    {{ company.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    
                    <div v-else>
                        <Input 
                            id="company_name"
                            v-model="form.company_name" 
                            placeholder="Enter new company name"
                        />
                    </div>
                    <InputError :message="form.errors.company_id || form.errors.company_name" />
                </div>
                <div class="space-y-2">
                    <Label for="scanName">Scan Name</Label>
                    <Input id="scanName" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="form.description" />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="space-y-2">
                    <Label>URLs to Scan</Label>
                    <div v-for="(url, index) in form.urls" :key="index" class="flex gap-2 items-end">
                        <div class="flex-1">
                            <Input
                                v-model="form.urls[index]"
                                type="url"
                                placeholder="https://example.com"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="removeUrl(index)"
                            :disabled="form.urls.length === 1"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addUrl"
                        class="w-full"
                    >
                        <Plus class="h-4 w-4 mr-2" />
                        Add URL
                    </Button>
                    <InputError :message="form.errors.urls || form.errors['urls.0'] || form.errors['urls.1']" />
                </div>
                <div class="space-y-2">
                    <Label>IP Addresses to Scan</Label>
                    <div v-for="(ip, index) in form.ip_addresses" :key="index" class="flex gap-2 items-end">
                        <div class="flex-1">
                            <Input
                                v-model="form.ip_addresses[index]"
                                placeholder="192.168.1.1"
                                pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="removeIpAddress(index)"
                            :disabled="form.ip_addresses.length === 1"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addIpAddress"
                        class="w-full"
                    >
                        <Plus class="h-4 w-4 mr-2" />
                        Add IP Address
                    </Button>
                    <InputError :message="form.errors.ip_addresses || form.errors['ip_addresses.0'] || form.errors['ip_addresses.1']" />
                </div>
                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="send_notification"
                        v-model="form.send_notification"
                    />
                    <Label for="send_notification">Notify when complete</Label>
                </div>
                <!-- Show email field only when checkbox is checked -->
                <div class="space-y-2" v-if="form.send_notification">
                    <Label for="notification_email">Email Address</Label>
                    <Input id="notification_email" type="email" v-model="form.notification_email" />
                    <InputError :message="form.errors.notification_email" />
                </div>
                <div class="space-y-2">
                    <Label>Schedule</Label>
                    <RadioGroup v-model="selectedSchedule" @update:model-value="updateScheduleType">
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem id="now" value="immediate" />
                            <Label for="now">Now</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem id="once" value="once" />
                            <Label for="once">One Time</Label>
                        </div>
                        <div class="ml-6 mt-2 space-y-2" v-if="selectedSchedule === 'once'">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-2">
                                    <Label>Date</Label>
                                    <Popover>
                                        <PopoverTrigger as-child>
                                            <Button
                                                variant="outline"
                                                :class="cn(
                                                  'w-full justify-start text-left font-normal',
                                                  !value && 'text-muted-foreground',
                                                )"
                                            >
                                                <CalendarIcon class="mr-2 h-4 w-4" />
                                                {{ value ? df.format(value.toDate(getLocalTimeZone())) : "Pick date" }}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-auto p-0">
                                            <Calendar v-model="value" initial-focus />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <div class="space-y-2">
                                    <Label for="onceTime">Time</Label>
                                    <Input 
                                        id="onceTime"
                                        type="time" 
                                        v-model="form.schedule_time" 
                                        class="w-full"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem id="recurring" value="recurring" />
                            <Label for="recurring">Recurring</Label>
                        </div>
                        <div class="ml-6 mt-2 space-y-2" v-if="selectedSchedule === 'recurring'">
                            <div class="grid grid-cols-3 gap-2">
                                <div class="space-y-2">
                                    <Label>Every</Label>
                                    <Select v-model="form.frequency" @update:model-value="updateFrequency">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="daily">Day</SelectItem>
                                            <SelectItem value="weekly">Week</SelectItem>
                                            <SelectItem value="monthly">Month</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2" v-if="form.frequency === 'weekly'">
                                    <Label>On</Label>
                                    <Select :model-value="form.day_of_week.toString()" @update:model-value="updateDayOfWeek">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Day" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">Monday</SelectItem>
                                            <SelectItem value="2">Tuesday</SelectItem>
                                            <SelectItem value="3">Wednesday</SelectItem>
                                            <SelectItem value="4">Thursday</SelectItem>
                                            <SelectItem value="5">Friday</SelectItem>
                                            <SelectItem value="6">Saturday</SelectItem>
                                            <SelectItem value="0">Sunday</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="recurringTime">At</Label>
                                    <Input 
                                        id="recurringTime"
                                        type="time" 
                                        v-model="form.schedule_time"
                                        class="w-full"
                                    />
                                </div>
                            </div>
                        </div>
                    </RadioGroup>
                    <InputError :message="form.errors.schedule_type || form.errors.scheduled_at || form.errors.frequency || form.errors.day_of_week || form.errors.schedule_time" />
                </div>
                <div>

                </div>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Creating...' : 'Create Scan' }}
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
