<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import HeadingSmall from '@/components/HeadingSmall.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {edit} from '@/routes/otp-config'
import { type BreadcrumbItem } from '@/types'

interface Props {
  config: {
    digits: number
    ttl_minutes: number
    max_attempts: number
    send_sms: boolean
    sender_id_source: 'user' | 'otp_config' | 'sms_default' | 'fallback'
    current_sender_id: string
  }
}

defineProps<Props>()

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'OTP settings',
    href: edit().url,
  },
]
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="OTP settings" />

    <SettingsLayout>
      <div class="flex flex-col space-y-6">
        <HeadingSmall
          title="OTP Configuration"
          description="Configure One-Time Password settings for API authentication"
        />

        <form @submit.prevent="$inertia.put(edit().url, {
          digits: $event.target.digits.value,
          ttl_minutes: $event.target.ttl_minutes.value,
          max_attempts: $event.target.max_attempts.value,
          send_sms: $event.target.send_sms.checked,
        })" class="space-y-6">
          
          <!-- Code Length -->
          <div class="grid gap-2">
            <Label for="digits">Code Length (digits)</Label>
            <Input
              id="digits"
              name="digits"
              type="number"
              min="4"
              max="10"
              :default-value="config.digits"
              required
              placeholder="6"
            />
            <p class="text-xs text-muted-foreground">
              Number of digits in OTP codes (4-10). Default: 6
            </p>
          </div>

          <!-- Expiration Time -->
          <div class="grid gap-2">
            <Label for="ttl_minutes">Code Expiration (minutes)</Label>
            <Input
              id="ttl_minutes"
              name="ttl_minutes"
              type="number"
              min="1"
              max="60"
              :default-value="config.ttl_minutes"
              required
              placeholder="5"
            />
            <p class="text-xs text-muted-foreground">
              How long codes remain valid (1-60 minutes). Default: 5
            </p>
          </div>

          <!-- Max Attempts -->
          <div class="grid gap-2">
            <Label for="max_attempts">Maximum Attempts</Label>
            <Input
              id="max_attempts"
              name="max_attempts"
              type="number"
              min="3"
              max="10"
              :default-value="config.max_attempts"
              required
              placeholder="5"
            />
            <p class="text-xs text-muted-foreground">
              Failed attempts before locking (3-10). Default: 5
            </p>
          </div>

          <!-- Send SMS Toggle -->
          <div class="flex items-center space-x-2 rounded-lg border p-4">
            <input
              type="checkbox"
              id="send_sms"
              name="send_sms"
              :checked="config.send_sms"
              class="h-4 w-4 rounded border-gray-300"
            />
            <div class="flex-1">
              <Label for="send_sms" class="text-base cursor-pointer">
                Send OTP via SMS
              </Label>
              <p class="text-sm text-muted-foreground">
                Automatically send codes via SMS when requested
              </p>
            </div>
          </div>

          <!-- Current Sender ID Info -->
          <Card>
            <CardHeader>
              <CardTitle class="text-base">SMS Sender ID</CardTitle>
              <CardDescription>
                Current sender ID used for OTP messages
              </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium">Current Sender:</span>
                <code class="rounded bg-muted px-2 py-1 text-sm">{{ config.current_sender_id }}</code>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium">Source:</span>
                <Badge :variant="config.sender_id_source === 'user' ? 'default' : 'secondary'">
                  {{ config.sender_id_source === 'user' ? 'Your SMS Config' :
                     config.sender_id_source === 'otp_config' ? 'OTP Config' :
                     config.sender_id_source === 'sms_default' ? 'SMS Default' : 'Fallback' }}
                </Badge>
              </div>
              <p class="text-xs text-muted-foreground">
                Configure your SMS sender IDs in
                <a href="/settings/sms" class="underline">Settings → SMS</a>
              </p>
            </CardContent>
          </Card>

          <!-- Save Button -->
          <div class="flex items-center gap-4">
            <Button type="submit">
              Save Configuration
            </Button>
          </div>
        </form>

        <!-- Info Card -->
        <Card class="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/50">
          <CardHeader>
            <CardTitle class="text-base">About OTP Settings</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2 text-sm">
            <p>
              These settings control the OTP (One-Time Password) verification service used for API authentication.
            </p>
            <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
              <li>Changes apply immediately to new OTP requests</li>
              <li>Existing active OTPs are not affected</li>
              <li>OTP codes are hashed with HMAC-SHA256 for security</li>
              <li>View full API documentation at <a href="/otp-api-docs" class="underline">OTP API Docs</a></li>
            </ul>
          </CardContent>
        </Card>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
