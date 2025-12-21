<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Download, Key, Lock, CheckCircle2, XCircle, Clock, AlertCircle } from 'lucide-vue-next'

const downloadCollection = () => {
  window.location.href = '/docs/txtcmdr-otp-api.postman_collection.json'
}
</script>

<template>
  <AppLayout>
    <Head title="OTP Module API Documentation" />

    <div class="mx-auto max-w-5xl space-y-8 py-8">
      <!-- Header -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold">OTP Module API Documentation</h1>
            <p class="mt-2 text-muted-foreground">
              SMS-based One-Time Password verification for your applications
            </p>
          </div>
          <Button @click="downloadCollection" size="lg">
            <Download class="mr-2 h-4 w-4" />
            Download Postman Collection
          </Button>
        </div>
      </div>

      <!-- Authentication Section -->
      <Card>
        <CardHeader>
          <div class="flex items-center gap-2">
            <Key class="h-5 w-5" />
            <CardTitle>Authentication</CardTitle>
          </div>
          <CardDescription>
            All OTP API endpoints require Bearer token authentication using Laravel Sanctum.
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-950/50">
            <div class="flex items-start gap-3">
              <AlertCircle class="h-5 w-5 text-amber-600 dark:text-amber-500" />
              <div class="space-y-2">
                <p class="font-medium text-amber-900 dark:text-amber-200">
                  API Token Management Coming Soon
                </p>
                <p class="text-sm text-amber-800 dark:text-amber-300">
                  A user-friendly interface for generating and managing API tokens will be available in
                  <strong>Settings → API Tokens</strong> soon. For now, you can generate tokens programmatically
                  or via tinker.
                </p>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <p class="text-sm font-medium">Temporary Token Generation (until UI is available):</p>
            <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>// Via tinker
php artisan tinker
$user = App\Models\User::where('email', 'your@email.com')->first();
$token = $user->createToken('my-app-name')->plainTextToken;
echo $token;</code></pre>
          </div>

          <div class="space-y-2">
            <p class="text-sm font-medium">Using the token in requests:</p>
            <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>Authorization: Bearer YOUR_API_TOKEN_HERE</code></pre>
          </div>
        </CardContent>
      </Card>

      <!-- Base URL -->
      <Card>
        <CardHeader>
          <CardTitle>Base URL</CardTitle>
        </CardHeader>
        <CardContent>
          <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>{{ window.location.origin }}/api</code></pre>
        </CardContent>
      </Card>

      <!-- Request Format -->
      <Card>
        <CardHeader>
          <CardTitle>Request Format</CardTitle>
          <CardDescription>All requests must include these headers</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-3">
            <h4 class="font-semibold">Required Headers</h4>
            <div class="space-y-2">
              <div class="flex items-start gap-3">
                <code class="text-sm font-medium">Authorization:</code>
                <div class="flex-1 space-y-1">
                  <code class="text-sm text-muted-foreground">Bearer YOUR_API_TOKEN</code>
                  <p class="text-xs text-muted-foreground">Your Sanctum API token for authentication</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <code class="text-sm font-medium">Content-Type:</code>
                <div class="flex-1 space-y-1">
                  <code class="text-sm text-muted-foreground">application/json</code>
                  <p class="text-xs text-muted-foreground">Request body must be JSON</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <code class="text-sm font-medium">Accept:</code>
                <div class="flex-1 space-y-1">
                  <code class="text-sm text-muted-foreground">application/json</code>
                  <p class="text-xs text-muted-foreground">Response will be JSON</p>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <h4 class="font-semibold">Complete Request Example</h4>
            <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>curl -X POST {{ window.location.origin }}/api/otp/request \\
  -H "Authorization: Bearer 1|abcdef123456..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @- &lt;&lt;EOF
{
  "mobile": "+639171234567",
  "purpose": "login"
}
EOF</code></pre>
          </div>
        </CardContent>
      </Card>

      <!-- Endpoints -->
      <div class="space-y-6">
        <h2 class="text-2xl font-semibold">Endpoints</h2>

        <!-- Request OTP -->
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <Badge variant="default">POST</Badge>
                  <code class="text-lg">/api/otp/request</code>
                </div>
                <CardDescription>Request a new OTP code to be sent via SMS</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Request Headers -->
            <div class="space-y-3">
              <h4 class="font-semibold">Request Headers</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-sm block whitespace-pre"><code>Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
Accept: application/json</code></pre>
            </div>

            <!-- Request Body Shape -->
            <div class="space-y-3">
              <h4 class="font-semibold">Request Body Shape</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-sm block whitespace-pre"><code>{
  "mobile": string,          // required - E.164 format
  "purpose": string,         // optional - "login" | "password_reset" | "verification"
  "external_ref": string,    // optional - Your reference ID
  "meta": object             // optional - Additional data
}</code></pre>
            </div>

            <!-- Request Parameters -->
            <div class="space-y-3">
              <h4 class="font-semibold">Request Body Parameters</h4>
              <div class="space-y-2">
                <div class="flex gap-2">
                  <code class="text-sm">mobile</code>
                  <Badge variant="destructive" class="h-5">required</Badge>
                  <span class="text-sm text-muted-foreground">Phone number in E.164 format (e.g., +639171234567)</span>
                </div>
                <div class="flex gap-2">
                  <code class="text-sm">purpose</code>
                  <Badge variant="secondary" class="h-5">optional</Badge>
                  <span class="text-sm text-muted-foreground">Purpose: <code>login</code>, <code>password_reset</code>, <code>verification</code> (default: login)</span>
                </div>
                <div class="flex gap-2">
                  <code class="text-sm">external_ref</code>
                  <Badge variant="secondary" class="h-5">optional</Badge>
                  <span class="text-sm text-muted-foreground">Your application's reference ID</span>
                </div>
                <div class="flex gap-2">
                  <code class="text-sm">meta</code>
                  <Badge variant="secondary" class="h-5">optional</Badge>
                  <span class="text-sm text-muted-foreground">Additional metadata as JSON object</span>
                </div>
              </div>
            </div>

            <!-- Complete Example -->
            <div class="space-y-2">
              <h4 class="font-semibold">Complete curl Example</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>curl -X POST {{ window.location.origin }}/api/otp/request \\
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "mobile": "+639171234567",
    "purpose": "login",
    "external_ref": "user-login-2024-001",
    "meta": {
      "user_id": "12345",
      "ip_address": "192.168.1.1"
    }
  }'</code></pre>
            </div>

            <!-- Response Shape -->
            <div class="space-y-2">
              <h4 class="font-semibold">Response Body Shape</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-sm block whitespace-pre"><code>{
  "verification_id": string,  // UUID for verification session
  "expires_in": number,        // Seconds until expiration (300)
  "dev_code": string | null   // OTP code (local/testing only)
}</code></pre>
            </div>

            <!-- Example Response -->
            <div class="space-y-2">
              <h4 class="font-semibold">Example Response (200 OK)</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>{
  "verification_id": "019b4043-ea61-7287-9793-96512a5cfc18",
  "expires_in": 300,
  "dev_code": "123456"  // Only in local/testing environments
}</code></pre>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
              <h4 class="font-semibold">Notes</h4>
              <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                <li>OTP will be sent via SMS using your configured sender ID</li>
                <li>Codes are valid for 5 minutes (300 seconds) by default</li>
                <li>Maximum 5 verification attempts allowed per code</li>
                <li>The <code>dev_code</code> field is only returned in local/testing environments</li>
              </ul>
            </div>
          </CardContent>
        </Card>

        <!-- Verify OTP -->
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <Badge variant="default">POST</Badge>
                  <code class="text-lg">/api/otp/verify</code>
                </div>
                <CardDescription>Verify an OTP code</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Request Headers -->
            <div class="space-y-3">
              <h4 class="font-semibold">Request Headers</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-sm block whitespace-pre"><code>Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
Accept: application/json</code></pre>
            </div>

            <!-- Request Body Shape -->
            <div class="space-y-3">
              <h4 class="font-semibold">Request Body Shape</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-sm block whitespace-pre"><code>{
  "verification_id": string,  // required - UUID from /otp/request
  "code": string              // required - 4-10 digit OTP code
}</code></pre>
            </div>

            <!-- Request Parameters -->
            <div class="space-y-3">
              <h4 class="font-semibold">Request Body Parameters</h4>
              <div class="space-y-2">
                <div class="flex gap-2">
                  <code class="text-sm">verification_id</code>
                  <Badge variant="destructive" class="h-5">required</Badge>
                  <span class="text-sm text-muted-foreground">UUID from the request response</span>
                </div>
                <div class="flex gap-2">
                  <code class="text-sm">code</code>
                  <Badge variant="destructive" class="h-5">required</Badge>
                  <span class="text-sm text-muted-foreground">OTP code received via SMS (4-10 digits)</span>
                </div>
              </div>
            </div>

            <!-- Complete Example -->
            <div class="space-y-2">
              <h4 class="font-semibold">Complete curl Example</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>curl -X POST {{ window.location.origin }}/api/otp/verify \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "verification_id": "019b4043-ea61-7287-9793-96512a5cfc18",
    "code": "123456"
  }'</code></pre>
            </div>

            <!-- Response Shape - Success -->
            <div class="space-y-2">
              <h4 class="font-semibold">Response Body Shape (Success)</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-sm block whitespace-pre"><code>{
  "ok": boolean,     // true for success
  "reason": string   // "verified"
}</code></pre>
            </div>

            <!-- Success Response -->
            <div class="space-y-2">
              <h4 class="font-semibold">Example Success Response (200 OK)</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>{
  "ok": true,
  "reason": "verified"
}</code></pre>
            </div>

            <!-- Response Shape - Error -->
            <div class="space-y-2">
              <h4 class="font-semibold">Response Body Shape (Error)</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-sm block whitespace-pre"><code>{
  "ok": boolean,           // false for errors
  "reason": string,        // Error reason code
  "attempts": number,      // Current attempt count (optional)
  "status": string         // Verification status (optional)
}</code></pre>
            </div>

            <!-- Error Response -->
            <div class="space-y-2">
              <h4 class="font-semibold">Error Response (200 OK)</h4>
              <pre class="overflow-x-auto rounded-lg bg-muted p-4 block whitespace-pre"><code>{
  "ok": false,
  "reason": "invalid_code",
  "attempts": 1,
  "status": "pending"
}</code></pre>
            </div>

            <!-- Possible Reasons -->
            <div class="space-y-3">
              <h4 class="font-semibold">Response Reasons</h4>
              <div class="space-y-2">
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-green-600" />
                  <code class="text-sm">verified</code>
                  <span class="text-sm text-muted-foreground">Code is correct</span>
                </div>
                <div class="flex items-center gap-2">
                  <XCircle class="h-4 w-4 text-red-600" />
                  <code class="text-sm">not_found</code>
                  <span class="text-sm text-muted-foreground">Invalid verification_id</span>
                </div>
                <div class="flex items-center gap-2">
                  <Clock class="h-4 w-4 text-orange-600" />
                  <code class="text-sm">expired</code>
                  <span class="text-sm text-muted-foreground">Code has expired (>5 minutes)</span>
                </div>
                <div class="flex items-center gap-2">
                  <Lock class="h-4 w-4 text-red-600" />
                  <code class="text-sm">locked</code>
                  <span class="text-sm text-muted-foreground">Too many failed attempts (>5)</span>
                </div>
                <div class="flex items-center gap-2">
                  <XCircle class="h-4 w-4 text-red-600" />
                  <code class="text-sm">invalid_code</code>
                  <span class="text-sm text-muted-foreground">Wrong code entered</span>
                </div>
                <div class="flex items-center gap-2">
                  <AlertCircle class="h-4 w-4 text-amber-600" />
                  <code class="text-sm">already_verified</code>
                  <span class="text-sm text-muted-foreground">Code already used</span>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
              <h4 class="font-semibold">Notes</h4>
              <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                <li>Codes can only be verified once successfully</li>
                <li>After 5 failed attempts, the verification is locked</li>
                <li>Each failed attempt increments the counter</li>
                <li>Codes expire after 5 minutes</li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Rate Limiting -->
      <Card>
        <CardHeader>
          <CardTitle>Rate Limiting</CardTitle>
          <CardDescription>
            OTP endpoints are throttled to prevent abuse
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-2">
            <p class="text-sm">
              <strong>Limit:</strong> 30 requests per minute per IP address
            </p>
            <p class="text-sm text-muted-foreground">
              If you exceed this limit, you'll receive a 429 (Too Many Requests) response.
              Wait for the time window to reset before making additional requests.
            </p>
          </div>
        </CardContent>
      </Card>

      <!-- Security -->
      <Card>
        <CardHeader>
          <CardTitle>Security</CardTitle>
          <CardDescription>
            How we protect your OTP codes
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul class="list-disc space-y-2 pl-5 text-sm">
            <li><strong>No Plaintext Storage:</strong> Codes are hashed with HMAC-SHA256 before database storage</li>
            <li><strong>Timing-Attack Protection:</strong> Constant-time comparison prevents timing attacks</li>
            <li><strong>UUID Primary Keys:</strong> Non-sequential IDs prevent enumeration attacks</li>
            <li><strong>TTL Enforcement:</strong> Automatic expiration after configured seconds</li>
            <li><strong>One-Time Use:</strong> Status transitions prevent code reuse</li>
            <li><strong>Attempt Limits:</strong> Automatic locking after maximum failed attempts</li>
            <li><strong>Audit Trail:</strong> IP address, user agent, and timestamps logged</li>
          </ul>
        </CardContent>
      </Card>

      <!-- Support -->
      <Card>
        <CardHeader>
          <CardTitle>Need Help?</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-sm text-muted-foreground">
            For questions or support regarding the OTP API, please contact your Text Commander administrator
            or refer to the main application documentation.
          </p>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
