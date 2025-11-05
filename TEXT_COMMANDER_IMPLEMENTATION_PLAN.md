# Text Commander Implementation Plan

This document provides a **step-by-step implementation roadmap** for building Text Commander according to the comprehensive documentation in `/Users/rli/Documents/txtcmdr-docs/docs`.

## Project Overview

**Text Commander** is a fast-deployable SMS broadcasting system with branded sender IDs, built with:
- **Backend:** Laravel 12 + Inertia.js
- **Frontend:** Vue 3 + Reka UI + shadcn-vue  
- **SMS:** EngageSPARK integration via `lbhurtado/sms` package
- **Database:** PostgreSQL (configured for SQLite in development)
- **Architecture:** Laravel Actions (`lorisleiva/laravel-actions`)

## Implementation Phases

### Phase 1: Foundation & SMS Integration
### Phase 2: Core Features (Contacts, Groups, Blacklist)
### Phase 3: Advanced Features (Scheduling, Bulk Import)
### Phase 4: Frontend & UI
### Phase 5: Testing & Deployment

---

# Phase 1: Foundation & SMS Integration

## 1.1 Install Required Packages

```bash
# SMS packages (lbhurtado/engagespark is auto-installed as dependency)
composer require lbhurtado/sms

# Laravel Actions (for controllers)
composer require lorisleiva/laravel-actions

# Note: propaganistas/laravel-phone is already installed via lbhurtado/contact
# Note: lbhurtado/contact is already installed via symlink from x-change/packages
```

**Reference:** [Quick Start Guide](../txtcmdr-docs/docs/quick-start.md), [Package Architecture](../txtcmdr-docs/docs/package-architecture.md)

---

## 1.2 Configure Environment Variables

Add to `.env`:

```dotenv
# EngageSPARK Credentials
ENGAGESPARK_API_KEY=your_api_key_here
ENGAGESPARK_ORGANIZATION_ID=your_org_id_here
ENGAGESPARK_SENDER_ID=TXTCMDR

# Webhooks (for delivery reports)
ENGAGESPARK_SMS_WEBHOOK=${APP_URL}/api/webhooks/engagespark/sms
ENGAGESPARK_AIRTIME_WEBHOOK=${APP_URL}/api/webhooks/engagespark/airtime

# SMS Driver
SMS_DRIVER=engagespark

# Default sender ID
SMS_DEFAULT_SENDER_ID=TXTCMDR

# Database - switch to PostgreSQL for production
DB_CONNECTION=sqlite
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=txtcmdr
# DB_USERNAME=postgres
# DB_PASSWORD=

# Queue - use database for development, Redis for production
QUEUE_CONNECTION=database
```

---

## 1.3 Publish Package Configurations

```bash
# Publish EngageSPARK config
php artisan vendor:publish --provider="LBHurtado\EngageSpark\EngageSparkServiceProvider"

# Publish Laravel Actions config (optional)
php artisan vendor:publish --provider="Lorisleiva\Actions\ActionServiceProvider"

# Create notifications table (for Laravel notification channel)
php artisan notifications:table
php artisan migrate
```

---

## 1.4 Test SMS Integration

Create a test command to verify EngageSPARK integration:

```bash
php artisan make:command TestSMS
```

**File:** `app/Console/Commands/TestSMS.php`

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\SMS\Facades\SMS;

class TestSMS extends Command
{
    protected $signature = 'sms:test {mobile} {message?}';
    protected $description = 'Test SMS sending via EngageSPARK';

    public function handle()
    {
        $mobile = $this->argument('mobile');
        $message = $this->argument('message') ?? 'Test message from Text Commander!';
        
        $this->info("Sending SMS to {$mobile}...");
        
        try {
            SMS::channel('engagespark')
                ->from(config('sms.default_sender_id', 'TXTCMDR'))
                ->to($mobile)
                ->content($message)
                ->send();
            
            $this->info('✅ SMS sent successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send SMS: ' . $e->getMessage());
        }
    }
}
```

**Test it:**
```bash
php artisan sms:test "+639171234567" "Hello from Text Commander!"
```

**⚠️ Important: Run Queue Worker**

SMS jobs are queued, so you need a queue worker running to process them:

```bash
# In a separate terminal window
php artisan queue:work

# Or use the dev command which includes queue worker
composer dev
```

**Alternative: Synchronous Queue for Testing**

To send SMS immediately without a queue worker (useful for testing), temporarily set in `.env`:
```dotenv
QUEUE_CONNECTION=sync
```

Remember to change back to `database` or `redis` for production.

---

# Phase 2: Core Features (Contacts, Groups, Blacklist)

## 2.1 Create Database Migrations

**Reference:** [Database Schema](../txtcmdr-docs/docs/database-schema.md)

### Contacts Table

```bash
php artisan make:migration create_contacts_table
```

```php
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('mobile')->unique();
    $table->string('email')->nullable();
    $table->json('extra')->nullable(); // For custom fields
    $table->timestamps();
    
    $table->index('mobile');
    $table->index('email');
});
```

### Groups Table

```bash
php artisan make:migration create_groups_table
```

```php
Schema::create('groups', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->text('description')->nullable();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->timestamps();
    
    $table->index('name');
});
```

### Contact-Group Pivot Table

```bash
php artisan make:migration create_contact_group_table
```

```php
Schema::create('contact_group', function (Blueprint $table) {
    $table->foreignId('contact_id')->constrained()->onDelete('cascade');
    $table->foreignId('group_id')->constrained()->onDelete('cascade');
    $table->timestamps();
    
    $table->primary(['contact_id', 'group_id']);
});
```

### Blacklisted Numbers Table

```bash
php artisan make:migration create_blacklisted_numbers_table
```

```php
Schema::create('blacklisted_numbers', function (Blueprint $table) {
    $table->id();
    $table->string('mobile')->unique();
    $table->enum('reason', ['opt-out', 'complaint', 'invalid', 'other']);
    $table->text('notes')->nullable();
    $table->string('added_by')->default('system');
    $table->timestamps();
    
    $table->index('mobile');
});
```

### Scheduled Messages Table

```bash
php artisan make:migration create_scheduled_messages_table
```

```php
Schema::create('scheduled_messages', function (Blueprint $table) {
    $table->id();
    $table->text('message');
    $table->json('recipients')->nullable(); // Array of mobile numbers
    $table->json('group_ids')->nullable(); // Array of group IDs
    $table->string('sender_id');
    $table->timestamp('scheduled_at');
    $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'cancelled'])
          ->default('pending');
    $table->timestamps();
    
    $table->index('scheduled_at');
    $table->index('status');
});
```

### Sender IDs Table (Optional)

```bash
php artisan make:migration create_sender_ids_table
```

```php
Schema::create('sender_ids', function (Blueprint $table) {
    $table->id();
    $table->string('sender_id')->unique();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Run migrations:**
```bash
php artisan migrate
```

---

## 2.2 Create Models

### Contact Model

```bash
php artisan make:model Contact
```

**File:** `app/Models/Contact.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Propaganistas\LaravelPhone\PhoneNumber;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'mobile',
        'email',
        'extra',
    ];

    protected $casts = [
        'extra' => 'array',
    ];

    // Relationships
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withTimestamps();
    }

    // Helper: Create from PhoneNumber object
    public static function fromPhoneNumber(PhoneNumber $phone): self
    {
        return self::firstOrCreate(
            ['mobile' => $phone->formatE164()],
            ['name' => '']
        );
    }

    // Accessor: Get E.164 formatted mobile
    public function getE164MobileAttribute(): string
    {
        return (new PhoneNumber($this->mobile, 'PH'))->formatE164();
    }
}
```

### Group Model

```bash
php artisan make:model Group
```

**File:** `app/Models/Group.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->withTimestamps();
    }
}
```

### BlacklistedNumber Model

```bash
php artisan make:model BlacklistedNumber
```

**File:** `app/Models/BlacklistedNumber.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Propaganistas\LaravelPhone\PhoneNumber;

class BlacklistedNumber extends Model
{
    protected $fillable = [
        'mobile',
        'reason',
        'notes',
        'added_by',
    ];

    // Helper: Check if number is blacklisted
    public static function isBlacklisted(string $mobile): bool
    {
        // Normalize to E.164
        try {
            $phone = new PhoneNumber($mobile, 'PH');
            $e164 = $phone->formatE164();
            
            return self::where('mobile', $e164)->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    // Helper: Add to blacklist
    public static function addToBlacklist(
        string $mobile,
        string $reason = 'opt-out',
        ?string $notes = null,
        string $addedBy = 'system'
    ): self {
        $phone = new PhoneNumber($mobile, 'PH');
        
        return self::firstOrCreate(
            ['mobile' => $phone->formatE164()],
            [
                'reason' => $reason,
                'notes' => $notes,
                'added_by' => $addedBy,
            ]
        );
    }
}
```

### ScheduledMessage Model

```bash
php artisan make:model ScheduledMessage
```

**File:** `app/Models/ScheduledMessage.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledMessage extends Model
{
    protected $fillable = [
        'message',
        'recipients',
        'group_ids',
        'sender_id',
        'scheduled_at',
        'status',
    ];

    protected $casts = [
        'recipients' => 'array',
        'group_ids' => 'array',
        'scheduled_at' => 'datetime',
    ];

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDue($query)
    {
        return $query->where('scheduled_at', '<=', now())
                     ->where('status', 'pending');
    }
}
```

---

## 2.3 Create Jobs

**Reference:** [Backend Services](../txtcmdr-docs/docs/backend-services.md)

### SendSMSJob

```bash
php artisan make:job SendSMSJob
```

**File:** `app/Jobs/SendSMSJob.php`

```php
namespace App\Jobs;

use App\Jobs\Middleware\CheckBlacklist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\SMS\Facades\SMS;

class SendSMSJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $mobile,
        public string $message,
        public string $senderId = 'TXTCMDR'
    ) {}

    public function middleware(): array
    {
        return [new CheckBlacklist($this->mobile)];
    }

    public function handle(): void
    {
        SMS::channel('engagespark')
            ->from($this->senderId)
            ->to($this->mobile)
            ->content($this->message)
            ->send();
    }
}
```

### BroadcastToGroupJob

```bash
php artisan make:job BroadcastToGroupJob
```

**File:** `app/Jobs/BroadcastToGroupJob.php`

```php
namespace App\Jobs;

use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastToGroupJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $groupId,
        public string $message,
        public string $senderId = 'TXTCMDR'
    ) {}

    public function handle(): void
    {
        $group = Group::with('contacts')->findOrFail($this->groupId);

        foreach ($group->contacts as $contact) {
            SendSMSJob::dispatch(
                $contact->e164_mobile,
                $this->message,
                $this->senderId
            );
        }
    }
}
```

---

## 2.4 Create Job Middleware (Blacklist Check)

**Reference:** [Blacklist Feature](../txtcmdr-docs/docs/blacklist-feature.md)

```bash
php artisan make:middleware CheckBlacklist --job
```

**File:** `app/Jobs/Middleware/CheckBlacklist.php`

```php
namespace App\Jobs\Middleware;

use App\Models\BlacklistedNumber;
use Illuminate\Support\Facades\Log;

class CheckBlacklist
{
    public function __construct(private string $mobile)
    {
    }

    public function handle($job, $next)
    {
        if (BlacklistedNumber::isBlacklisted($this->mobile)) {
            Log::info("SMS blocked to blacklisted number: {$this->mobile}");
            
            // Release job without executing
            $job->delete();
            return;
        }

        $next($job);
    }
}
```

---

## 2.5 Create Laravel Actions (Controllers)

**Reference:** [Backend Services](../txtcmdr-docs/docs/backend-services.md)

### SendToMultipleRecipients Action

```bash
php artisan make:action SendToMultipleRecipients
```

**File:** `app/Actions/SendToMultipleRecipients.php`

```php
namespace App\Actions;

use App\Jobs\SendSMSJob;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\PhoneNumber;

class SendToMultipleRecipients
{
    use AsAction;

    public function handle(array|string $recipients, string $message, ?string $senderId = null): array
    {
        $senderId = $senderId ?? config('sms.default_sender_id', 'TXTCMDR');
        
        // Normalize to array
        $recipientArray = is_string($recipients) 
            ? explode(',', $recipients) 
            : $recipients;
        
        $normalizedRecipients = [];
        $dispatchedCount = 0;
        
        foreach ($recipientArray as $mobile) {
            try {
                $phone = new PhoneNumber(trim($mobile), 'PH');
                $contact = Contact::fromPhoneNumber($phone);
                $e164Mobile = $contact->e164_mobile;
                
                SendSMSJob::dispatch($e164Mobile, $message, $senderId);
                
                $normalizedRecipients[] = $e164Mobile;
                $dispatchedCount++;
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return [
            'status' => 'queued',
            'count' => $dispatchedCount,
            'recipients' => $normalizedRecipients,
            'invalid_count' => count($recipientArray) - count($normalizedRecipients),
        ];
    }

    public function rules(): array
    {
        return [
            'recipients' => 'required',
            'message' => 'required|string|max:1600',
            'sender_id' => 'nullable|string|max:11',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $result = $this->handle(
            $request->recipients,
            $request->message,
            $request->sender_id ?? null
        );

        return response()->json($result, 200);
    }
}
```

### SendToMultipleGroups Action

```bash
php artisan make:action SendToMultipleGroups
```

**File:** `app/Actions/SendToMultipleGroups.php`

```php
namespace App\Actions;

use App\Jobs\BroadcastToGroupJob;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SendToMultipleGroups
{
    use AsAction;

    public function handle(array|string $groups, string $message, ?string $senderId = null): array
    {
        $senderId = $senderId ?? config('sms.default_sender_id', 'TXTCMDR');
        
        $groupArray = is_string($groups) 
            ? explode(',', $groups) 
            : $groups;
        
        $groupArray = array_filter(array_map('trim', $groupArray));
        
        $dispatchedGroups = [];
        $totalContacts = 0;
        
        foreach ($groupArray as $groupIdentifier) {
            $group = Group::where('name', $groupIdentifier)
                ->orWhere('id', $groupIdentifier)
                ->first();
            
            if ($group) {
                BroadcastToGroupJob::dispatch(
                    $group->id,
                    $message,
                    $senderId
                );
                
                $contactCount = $group->contacts()->count();
                $totalContacts += $contactCount;
                
                $dispatchedGroups[] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'contacts' => $contactCount,
                ];
            }
        }
        
        return [
            'status' => 'queued',
            'groups' => $dispatchedGroups,
            'total_contacts' => $totalContacts,
        ];
    }

    public function rules(): array
    {
        return [
            'groups' => 'required',
            'message' => 'required|string|max:1600',
            'sender_id' => 'nullable|string|max:11',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $result = $this->handle(
            $request->groups,
            $request->message,
            $request->sender_id ?? null
        );

        return response()->json($result, 200);
    }
}
```

### Group Management Actions

Create directory: `mkdir -p app/Actions/Groups`

**CreateGroup:**
```bash
php artisan make:action Groups/CreateGroup
```

**File:** `app/Actions/Groups/CreateGroup.php`

```php
namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateGroup
{
    use AsAction;

    public function handle(string $name, ?string $description = null, ?int $userId = null): Group
    {
        return Group::create([
            'name' => $name,
            'description' => $description,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:groups,name',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $group = $this->handle(
            $request->name,
            $request->description ?? null
        );

        return response()->json($group, 201);
    }
}
```

**ListGroups, GetGroup, UpdateGroup, DeleteGroup:** Follow similar patterns based on [Backend Services documentation](../txtcmdr-docs/docs/backend-services.md#group-management).

---

## 2.6 Create API Routes

**Reference:** [API Documentation](../txtcmdr-docs/docs/api-documentation.md)

**File:** `routes/api.php`

```php
use App\Actions\SendToMultipleRecipients;
use App\Actions\SendToMultipleGroups;
use App\Actions\Groups\{CreateGroup, ListGroups, GetGroup, UpdateGroup, DeleteGroup};
use Illuminate\Support\Facades\Route;

// SMS Broadcasting
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/send', SendToMultipleRecipients::class);
    Route::post('/groups/send', SendToMultipleGroups::class);
    
    // Group Management
    Route::get('/groups', ListGroups::class);
    Route::post('/groups', CreateGroup::class);
    Route::get('/groups/{id}', GetGroup::class);
    Route::put('/groups/{id}', UpdateGroup::class);
    Route::delete('/groups/{id}', DeleteGroup::class);
    
    // Contact Management (implement similarly)
    // Route::post('/groups/{id}/contacts', AddContactToGroup::class);
    
    // Blacklist Management (implement similarly)
    // Route::post('/blacklist', AddToBlacklist::class);
    
    // Scheduled Messages (implement in Phase 3)
});
```

---

# Phase 3: Advanced Features (Scheduling, Bulk Import)

## 3.1 Scheduled Messaging

**Reference:** [Scheduled Messaging](../txtcmdr-docs/docs/scheduled-messaging.md)

### Create ProcessScheduledMessage Job

```bash
php artisan make:job ProcessScheduledMessage
```

```php
namespace App\Jobs;

use App\Models\ScheduledMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledMessage implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $scheduledMessageId
    ) {}

    public function handle(): void
    {
        $scheduled = ScheduledMessage::findOrFail($this->scheduledMessageId);
        
        if ($scheduled->status !== 'pending') {
            return;
        }
        
        $scheduled->update(['status' => 'processing']);
        
        // Send to direct recipients
        if ($scheduled->recipients) {
            foreach ($scheduled->recipients as $mobile) {
                SendSMSJob::dispatch(
                    $mobile,
                    $scheduled->message,
                    $scheduled->sender_id
                );
            }
        }
        
        // Send to groups
        if ($scheduled->group_ids) {
            foreach ($scheduled->group_ids as $groupId) {
                BroadcastToGroupJob::dispatch(
                    $groupId,
                    $scheduled->message,
                    $scheduled->sender_id
                );
            }
        }
        
        $scheduled->update(['status' => 'sent']);
    }
}
```

### Create Scheduler Command

```bash
php artisan make:command ProcessScheduledMessages
```

```php
namespace App\Console\Commands;

use App\Jobs\ProcessScheduledMessage;
use App\Models\ScheduledMessage;
use Illuminate\Console\Command;

class ProcessScheduledMessages extends Command
{
    protected $signature = 'sms:process-scheduled';
    protected $description = 'Process due scheduled messages';

    public function handle()
    {
        $dueMessages = ScheduledMessage::due()->get();
        
        foreach ($dueMessages as $message) {
            ProcessScheduledMessage::dispatch($message->id);
        }
        
        $this->info("Processed {$dueMessages->count()} scheduled messages");
    }
}
```

### Register in Scheduler

**File:** `app/Console/Kernel.php` or `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sms:process-scheduled')->everyMinute();
```

---

## 3.2 Bulk Import

**Reference:** [Bulk Import](../txtcmdr-docs/docs/bulk-import.md)

### Install Maatwebsite Excel

```bash
composer require maatwebsite/excel
```

### Create Import Class

```bash
php artisan make:import ContactsImport --model=Contact
```

```php
namespace App\Imports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Propaganistas\LaravelPhone\PhoneNumber;

class ContactsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        try {
            $phone = new PhoneNumber($row['mobile'], 'PH');
            
            return Contact::firstOrCreate(
                ['mobile' => $phone->formatE164()],
                [
                    'name' => $row['name'] ?? '',
                    'email' => $row['email'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

### Create Import Job

```bash
php artisan make:job ContactImportJob
```

```php
namespace App\Jobs;

use App\Imports\ContactsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ContactImportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $filePath,
        public ?int $groupId = null
    ) {}

    public function handle(): void
    {
        Excel::import(new ContactsImport, $this->filePath);
        
        // Optionally attach to group
        if ($this->groupId) {
            // Implement group attachment logic
        }
    }
}
```

---

# Phase 4: Frontend & UI

**Reference:** [Frontend Scaffolding](../txtcmdr-docs/docs/frontend-scaffolding.md), [UI/UX Design](../txtcmdr-docs/docs/ui-ux-design.md)

## 4.1 Create Inertia Pages

### Dashboard Page

**File:** `resources/js/pages/Dashboard.vue`

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/app/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Dashboard" />
  
  <AppLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-6">Text Commander Dashboard</h1>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Stats Cards -->
        <div class="bg-white p-6 rounded-lg shadow">
          <h3 class="text-gray-500 text-sm">Total Messages Sent</h3>
          <p class="text-3xl font-bold mt-2">1,234</p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
          <h3 class="text-gray-500 text-sm">Active Groups</h3>
          <p class="text-3xl font-bold mt-2">12</p>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
          <h3 class="text-gray-500 text-sm">Contacts</h3>
          <p class="text-3xl font-bold mt-2">567</p>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="mt-8">
        <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
        
        <div class="flex gap-4">
          <a href="/broadcast" class="btn btn-primary">
            Send Broadcast
          </a>
          <a href="/groups" class="btn btn-secondary">
            Manage Groups
          </a>
          <a href="/contacts" class="btn btn-secondary">
            Manage Contacts
          </a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
```

### Broadcast Page

**File:** `resources/js/pages/Broadcast.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/app/AppLayout.vue';

const form = ref({
  recipients: '',
  message: '',
  sender_id: 'TXTCMDR',
});

const errors = ref({});
const sending = ref(false);

const sendBroadcast = async () => {
  sending.value = true;
  
  router.post('/api/send', form.value, {
    onSuccess: () => {
      alert('✅ Broadcast queued successfully!');
      form.value = { recipients: '', message: '', sender_id: 'TXTCMDR' };
    },
    onError: (err) => {
      errors.value = err;
    },
    onFinish: () => {
      sending.value = false;
    },
  });
};
</script>

<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto p-6">
      <h1 class="text-2xl font-bold mb-6">Send Broadcast</h1>
      
      <form @submit.prevent="sendBroadcast" class="space-y-6">
        <div>
          <label class="block text-sm font-medium mb-2">
            Recipients (comma-separated mobile numbers)
          </label>
          <textarea
            v-model="form.recipients"
            rows="3"
            class="w-full border rounded-lg p-2"
            placeholder="+639171234567, +639181234567"
          />
          <p v-if="errors.recipients" class="text-red-500 text-sm mt-1">
            {{ errors.recipients }}
          </p>
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-2">Message</label>
          <textarea
            v-model="form.message"
            rows="5"
            class="w-full border rounded-lg p-2"
            placeholder="Your message here..."
            maxlength="1600"
          />
          <p class="text-gray-500 text-sm mt-1">
            {{ form.message.length }} / 1600 characters
          </p>
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-2">Sender ID</label>
          <input
            v-model="form.sender_id"
            type="text"
            class="w-full border rounded-lg p-2"
            maxlength="11"
          />
        </div>
        
        <button
          type="submit"
          :disabled="sending"
          class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
        >
          {{ sending ? 'Sending...' : 'Send Broadcast' }}
        </button>
      </form>
    </div>
  </AppLayout>
</template>
```

### Add Routes

**File:** `routes/web.php`

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
    
    Route::get('broadcast', function () {
        return Inertia::render('Broadcast');
    })->name('broadcast');
    
    Route::get('groups', function () {
        return Inertia::render('Groups');
    })->name('groups');
    
    Route::get('contacts', function () {
        return Inertia::render('Contacts');
    })->name('contacts');
});
```

---

# Phase 5: Testing & Deployment

**Reference:** [Test Scaffolding](../txtcmdr-docs/docs/test-scaffolding.md)

## 5.1 Write Pest Tests

### Test SMS Sending

**File:** `tests/Feature/SMS/SendSMSTest.php`

```php
namespace Tests\Feature\SMS;

use App\Jobs\SendSMSJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('can send SMS to single recipient', function () {
    Queue::fake();
    
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/send', [
            'recipients' => '+639171234567',
            'message' => 'Test message',
            'sender_id' => 'TXTCMDR',
        ]);
    
    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'queued',
        'count' => 1,
    ]);
    
    Queue::assertPushed(SendSMSJob::class);
});

test('blacklisted numbers are filtered', function () {
    Queue::fake();
    
    $user = User::factory()->create();
    
    // Add to blacklist
    \App\Models\BlacklistedNumber::addToBlacklist('+639171234567', 'opt-out');
    
    $response = $this->actingAs($user)
        ->postJson('/api/send', [
            'recipients' => '+639171234567',
            'message' => 'Test message',
        ]);
    
    $response->assertStatus(200);
    $response->assertJson([
        'count' => 0,
        'invalid_count' => 1,
    ]);
});
```

### Test Group Management

**File:** `tests/Feature/Groups/GroupManagementTest.php`

```php
namespace Tests\Feature\Groups;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create a group', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/groups', [
            'name' => 'barangay-leaders',
            'description' => 'Test group',
        ]);
    
    $response->assertStatus(201);
    $response->assertJson([
        'name' => 'barangay-leaders',
    ]);
    
    $this->assertDatabaseHas('groups', [
        'name' => 'barangay-leaders',
        'user_id' => $user->id,
    ]);
});

test('can list groups', function () {
    $user = User::factory()->create();
    Group::factory()->count(3)->create(['user_id' => $user->id]);
    
    $response = $this->actingAs($user)
        ->getJson('/api/groups');
    
    $response->assertStatus(200);
    $response->assertJsonCount(3);
});
```

### Run Tests

```bash
./vendor/bin/pest
./vendor/bin/pest --filter SendSMSTest
```

---

## 5.2 Configure Queue Workers

### Development

```bash
php artisan queue:work --tries=3
```

### Production (Supervisor)

Create `/etc/supervisor/conf.d/txtcmdr-worker.conf`:

```ini
[program:txtcmdr-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/txtcmdr/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/txtcmdr/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start txtcmdr-worker:*
```

---

## 5.3 Deployment Checklist

**Reference:** [Deployment](../txtcmdr-docs/docs/deployment.md)

- [ ] Switch to PostgreSQL database
- [ ] Configure Redis for queue and cache
- [ ] Set up supervisor for queue workers
- [ ] Configure scheduler cron job
- [ ] Set up webhook endpoints (SSL required)
- [ ] Configure branded sender IDs with EngageSPARK
- [ ] Set up monitoring (Laravel Pulse/Horizon)
- [ ] Configure backups
- [ ] Set up error tracking (Sentry)
- [ ] Configure rate limiting
- [ ] Set up SSL certificates
- [ ] Configure CORS if needed

---

# Development Workflow

## Daily Development Process

1. **Start development server:**
   ```bash
   composer dev  # Starts Laravel, queue, logs, Vite
   ```

2. **Run tests frequently:**
   ```bash
   ./vendor/bin/pest --filter FeatureName
   ```

3. **Format and lint code:**
   ```bash
   vendor/bin/pint              # PHP
   npm run lint                 # JS/TS
   npm run format               # JS/TS
   ```

4. **Check queue workers:**
   ```bash
   php artisan queue:work
   ```

5. **Monitor logs:**
   ```bash
   php artisan pail
   tail -f storage/logs/laravel.log
   ```

---

# Implementation Priority

## Week 1: Foundation
- ✅ Install packages
- ✅ Create migrations and models
- ✅ Test SMS integration
- ✅ Create basic jobs

## Week 2: Core API
- ✅ Implement SendToMultipleRecipients action
- ✅ Implement SendToMultipleGroups action
- ✅ Implement Group CRUD actions
- ✅ Implement Blacklist middleware
- ✅ Write API tests

## Week 3: Advanced Features
- ✅ Implement scheduled messaging
- ✅ Implement bulk import
- ✅ Add contact management
- ✅ Write integration tests

## Week 4: Frontend & Polish
- ✅ Create Inertia pages
- ✅ Build broadcast UI
- ✅ Build group management UI
- ✅ Add contact management UI
- ✅ Polish and test

## Week 5: Deployment
- ✅ Configure production environment
- ✅ Set up queue workers
- ✅ Configure scheduler
- ✅ Deploy and test
- ✅ Monitor and optimize

---

# Next Steps

1. **Start with Phase 1:** Install packages and configure EngageSPARK
2. **Test SMS integration:** Use the `TestSMS` command to verify connectivity
3. **Build incrementally:** Complete each phase before moving to the next
4. **Refer to documentation:** Use `/Users/rli/Documents/txtcmdr-docs/docs` for detailed specs
5. **Write tests:** Use Pest tests to verify each feature

---

# Key Documentation References

- [Quick Start Guide](../txtcmdr-docs/docs/quick-start.md)
- [Package Architecture](../txtcmdr-docs/docs/package-architecture.md)
- [Backend Services](../txtcmdr-docs/docs/backend-services.md)
- [Database Schema](../txtcmdr-docs/docs/database-schema.md)
- [API Documentation](../txtcmdr-docs/docs/api-documentation.md)
- [Test Scaffolding](../txtcmdr-docs/docs/test-scaffolding.md)
- [Frontend Scaffolding](../txtcmdr-docs/docs/frontend-scaffolding.md)
- [Deployment Guide](../txtcmdr-docs/docs/deployment.md)

---

# Success Criteria

The implementation is complete when:

1. ✅ SMS can be sent to individual numbers via API
2. ✅ SMS can be broadcast to groups via API
3. ✅ Blacklisted numbers are automatically filtered
4. ✅ Scheduled messages are processed correctly
5. ✅ Bulk import works for CSV/Excel files
6. ✅ Frontend UI allows broadcast sending
7. ✅ All Pest tests pass
8. ✅ Queue workers process jobs reliably
9. ✅ Application is deployed to production
10. ✅ Documentation is complete and accurate
