<script setup lang="ts">

import {
    DateFormatter,
    type DateValue,
    getLocalTimeZone,
} from '@internationalized/date'
import { ref } from 'vue';
import { CalendarIcon, Plus, X } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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

const sendNotification = ref(false);
const selectedSchedule = ref('now');
const frequency = ref('weekly');
const dayOfWeek = ref('1');
const scheduleTime = ref('09:00');
const urls = ref(['']);
const ipAddresses = ref(['']);
const df = new DateFormatter('en-US', {
    dateStyle: 'long',
});
const value = ref<DateValue>();

function addUrl() {
    urls.value.push('');
}

function removeUrl(index: number) {
    if (urls.value.length > 1) {
        urls.value.splice(index, 1);
    }
}

function addIpAddress() {
    ipAddresses.value.push('');
}

function removeIpAddress(index: number) {
    if (ipAddresses.value.length > 1) {
        ipAddresses.value.splice(index, 1);
    }
}

</script>

<template>
    <Head title="Create Scan" />
    <AppLayout>
        <div class="flex justify-center px-6 mt-6">
            <form class="space-y-6 w-full max-w-2xl">
                <HeadingSmall title="Create New Scan" description="Set up a security scan for your URLs and IP addresses" />
                <div class="space-y-2">
                    <Label for="companyName">Company Name</Label>
                    <Input id="companyName" />
                </div>
                <div class="space-y-2">
                    <Label for="scanName    ">Scan Name</Label>
                    <Input id="scanName " />
                </div>
                <div class="space-y-2">
                    <Label>URLs to Scan</Label>
                    <div v-for="(url, index) in urls" :key="index" class="flex gap-2 items-end">
                        <div class="flex-1">
                            <Input
                                v-model="urls[index]"
                                type="url"
                                placeholder="https://example.com"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="removeUrl(index)"
                            :disabled="urls.length === 1"
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
                </div>
                <div class="space-y-2">
                    <Label>IP Addresses to Scan</Label>
                    <div v-for="(ip, index) in ipAddresses" :key="index" class="flex gap-2 items-end">
                        <div class="flex-1">
                            <Input
                                v-model="ipAddresses[index]"
                                placeholder="192.168.1.1"
                                pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="removeIpAddress(index)"
                            :disabled="ipAddresses.length === 1"
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
                </div>
                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="send_notification"
                        v-model="sendNotification"
                    />
                    <Label for="send_notification">Notify when complete</Label>
                </div>
                <!-- Show email field only when checkbox is checked -->
                <div class="space-y-2" v-if="sendNotification">
                    <Label for="notification_email">Email Address</Label>
                    <Input id="notification_email" type="email" />
                </div>
                <div class="space-y-2">
                    <Label>Schedule</Label>
                    <RadioGroup v-model="selectedSchedule">
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem id="now" value="now" />
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
                                        v-model="scheduleTime" 
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
                                    <Select v-model="frequency">
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
                                <div class="space-y-2" v-if="frequency === 'weekly'">
                                    <Label>On</Label>
                                    <Select v-model="dayOfWeek">
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
                                        v-model="scheduleTime"
                                        class="w-full"
                                    />
                                </div>
                            </div>
                        </div>
                    </RadioGroup>
                </div>
                <div>

                </div>
                <Button>Submit</Button>
            </form>
        </div>
    </AppLayout>
</template>
