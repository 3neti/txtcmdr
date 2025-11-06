# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

**Text Commander** is an SMS broadcasting system for sending targeted messages to groups of contacts using Laravel Actions architecture and queue-based processing.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+) with Fortify for authentication
- **SMS Integration**: lbhurtado/sms (EngageSPARK provider)
- **Contact Management**: lbhurtado/contact (schemaless attributes via JSON meta column)
- **Actions**: lorisleiva/laravel-actions for endpoint logic
- **Phone Validation**: propaganistas/laravel-phone
- **Frontend**: Vue 3 with Inertia.js (SPA-like experience without building an API)
- **UI Components**: Reka UI (headless components) with shadcn-vue styling patterns
- **Styling**: Tailwind CSS v4 with Lucide icons
- **Build Tool**: Vite with TypeScript
- **File Processing**: league/csv and phpoffice/phpspreadsheet for CSV/XLSX parsing
- **Testing**: Pest PHP (Laravel's testing framework)
- **Code Quality**: Laravel Pint (PHP), ESLint + Prettier (JS/TS)
- **Database**: SQLite (default)

## Development Commands

### Initial Setup
```bash
composer setup
# Runs: composer install, copies .env, generates key, runs migrations, npm install, npm run build
```

### Development Server
```bash
composer dev
# Runs concurrently: Laravel server, queue listener, Pail logs, and Vite dev server
```

### Development with SSR (Server-Side Rendering)
```bash
composer dev:ssr
# Includes Inertia SSR server alongside other dev processes
```

### Testing
```bash
# Run all tests
composer test
# Or directly
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/Auth/LoginTest.php

# Run specific test by name
./vendor/bin/pest --filter "test_name"
```

### Linting & Formatting

#### PHP
```bash
# Fix code style with Laravel Pint
vendor/bin/pint

# Check without fixing
vendor/bin/pint --test
```

#### JavaScript/TypeScript
```bash
# Fix linting issues
npm run lint

# Format code
npm run format

# Check formatting without fixing
npm run format:check
```

### Build
```bash
# Production build
npm run build

# Build with SSR support
npm run build:ssr
```

### Database
```bash
# Run migrations
php artisan migrate

# Fresh migration with seeding (creates admin user)
php artisan migrate:fresh --seed

# Rollback
php artisan migrate:rollback
```

**Seeded Admin User:**
- Name: Lester Hurtado
- Email: admin@disburse.cash
- Password: password

### SMS Commands
```bash
# Test SMS sending
php artisan sms:test "+639173011987" "Test message"

# Test with custom sender
php artisan sms:test "+639173011987" "Test" --sender="cashless"

# Process scheduled messages (runs automatically via scheduler every minute)
php artisan messages:process-scheduled
```

### Task Scheduler

**Development:**

During development, the scheduler doesn't run automatically. You have these options:

```bash
# Option 1: Run a specific scheduled command manually
php artisan messages:process-scheduled

# Option 2: Run all scheduled tasks once
php artisan schedule:run

# Option 3: Run scheduler in watch mode (Laravel 11+)
php artisan schedule:work
```

**Production:**

Laravel's Task Scheduler requires a single cron entry on your server:

```bash
# Edit crontab
crontab -e

# Add this line (replace with your actual project path)
* * * * * cd /Users/rli/PhpstormProjects/txtcmdr && php artisan schedule:run >> /dev/null 2>&1
```

This single cron job runs every minute. Laravel's scheduler (defined in `routes/console.php`) then decides which commands to execute based on their schedule.

**Scheduled Tasks:**
- `messages:process-scheduled` - Runs every minute to process scheduled messages

### Other Useful Commands
```bash
# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Queue management (required for SMS sending)
php artisan queue:work
php artisan queue:listen --tries=1

# View logs in real-time
php artisan pail
```

## Architecture Overview

### Backend Structure

#### Inertia Integration
This application uses **Inertia.js**, which bridges Laravel and Vue without needing a traditional REST/GraphQL API. Controllers return Inertia responses that render Vue components directly, sharing data as props.

#### Authentication Flow
- **Laravel Fortify** handles all authentication: login, registration, password reset, email verification, and two-factor authentication
- Auth views are Inertia-rendered Vue components (e.g., `auth/Login.vue`, `auth/Register.vue`)
- Configuration in `config/fortify.php` and `app/Providers/FortifyServiceProvider.php`
- Custom actions in `app/Actions/Fortify/` for user creation and password reset

#### Route Organization
- `routes/web.php` - Main application routes
- `routes/settings.php` - User settings routes (profile, password, 2FA)
- `routes/console.php` - Artisan commands

#### Key Middleware
- `HandleInertiaRequests` - Shares data to all Inertia views (user, flash messages, etc.)
- `HandleAppearance` - Manages theme/appearance settings

### Domain Architecture

#### Contact Management with Schemaless Attributes
The application uses **lbhurtado/contact** package which provides flexible contact storage:

```php
use LBHurtado\Contact\Models\Contact as BaseContact;

class Contact extends BaseContact
{
    // Inherits from package:
    // - mobile, country, bank_account columns
    // - HasMobile, HasMeta, HasAdditionalAttributes traits
    // - fromPhoneNumber() helper method
    
    // Schemaless attributes (stored in 'meta' JSON column):
    // - name, email, address, birth_date, gross_monthly_income
    
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }
}
```

**Usage Examples:**
```php
// Creating contacts with schemaless attributes
$contact = Contact::create([
    'mobile' => '+639173011987',
    'name' => 'John Doe',        // Stored in meta JSON
    'email' => 'john@example.com', // Stored in meta JSON
]);

// Accessing schemaless attributes
echo $contact->name;  // "John Doe"
echo $contact->email; // "john@example.com"

// Adding flexible fields without migrations
$contact->address = '123 Main St';
$contact->birth_date = '1990-01-01';
$contact->gross_monthly_income = 50000;
$contact->save();

// Phone number helpers from package
$contact = Contact::fromPhoneNumber('+639173011987');
echo $contact->e164_mobile; // "+639173011987"
```

#### SMS Broadcasting Architecture

**Jobs:**
- `SendSMSJob` - Handles individual SMS sending with queue support
- `BroadcastToGroupJob` - Dispatches SMS jobs for all contacts in a group
- `ProcessScheduledMessage` - Processes scheduled messages when due
- `ContactImportJob` - Async contact import from CSV/XLSX
- `CheckBlacklist` middleware - Prevents SMS to blacklisted numbers

**Actions (Laravel Actions):**
- `SendToMultipleRecipients` - Send SMS to multiple phone numbers
- `SendToMultipleGroups` - Broadcast SMS to multiple groups
- `CreateGroup`, `ListGroups`, `GetGroup`, `DeleteGroup` - Group management
- `ScheduleMessage` - Schedule SMS for future delivery
- `UpdateScheduledMessage`, `CancelScheduledMessage`, `ListScheduledMessages` - Scheduled message management
- `BulkSendFromFile` - Send SMS to all numbers in CSV/XLSX
- `BulkSendPersonalized` - Personalized bulk SMS with variable substitution
- `ImportContactsFromFile` - Import contacts from CSV/XLSX

**API Routes (Sanctum protected):**
```
# Immediate Sending
POST   /api/send                           → SendToMultipleRecipients
POST   /api/groups/send                    → SendToMultipleGroups

# Group Management
GET    /api/groups                         → ListGroups
POST   /api/groups                         → CreateGroup
GET    /api/groups/{id}                    → GetGroup
DELETE /api/groups/{id}                    → DeleteGroup

# Scheduled Messages (Phase 3)
POST   /api/send/schedule                  → ScheduleMessage
GET    /api/scheduled-messages             → ListScheduledMessages
PUT    /api/scheduled-messages/{id}        → UpdateScheduledMessage
POST   /api/scheduled-messages/{id}/cancel → CancelScheduledMessage

# Bulk Operations (Phase 3)
POST   /api/contacts/import                → ImportContactsFromFile
POST   /api/sms/bulk-send                  → BulkSendFromFile
POST   /api/sms/bulk-send-personalized     → BulkSendPersonalized
```

**Database Schema:**
- `contacts` - Base table with mobile, country, bank_account, **meta (JSON)**
- `groups` - Named groups with user_id ownership
- `contact_group` - Many-to-many pivot
- `blacklisted_numbers` - Phone numbers to exclude from broadcasts
- `scheduled_messages` - SMS scheduling with recipient_type, recipient_data, status tracking

**Configuration:**
- `.env` - SMS_DRIVER, SMS_DEFAULT_SENDER_ID, ENGAGESPARK credentials
- `config/sms.php` - SMS driver configuration
- `config/engagespark.php` - EngageSPARK provider settings

#### Scheduled Messaging (Phase 3)

**Features:**
- Schedule SMS messages for future delivery
- Support for individuals, contacts, or groups
- Edit or cancel scheduled messages before sending
- Automatic processing via Laravel scheduler (runs every minute)
- Status tracking: pending → processing → sent

**Usage:**
```php
// Schedule a message
ScheduleMessage::run(
    recipients: ['09173011987', '09178251991'],
    message: 'Reminder: Your appointment is tomorrow',
    scheduledAt: '2025-11-06 10:00:00',
    senderId: 'Quezon City'
);
```

**Scheduler Configuration:**
```php
// routes/console.php
Schedule::command('messages:process-scheduled')->everyMinute();
```

#### Bulk Import & Personalized Messaging (Phase 3)

**Bulk Contact Import:**
- Import contacts from CSV/XLSX files
- Optional group assignment
- Async processing via queue
- Required columns: `mobile` (or `phone`)
- Optional columns: `name`, `email`

**Bulk SMS from File:**
- Send SMS to all valid numbers in a file
- Blacklist filtering applied
- Column specification: `mobile_column` parameter

**Personalized Bulk Messaging:**
Supports variable substitution with two CSV formats:

**Format 1: 2 columns (mobile, message)**
```csv
mobile,message
09173011987,Your OTP code is 123456
09178251991,Your payment has been received
```

**Format 2: 3 columns (mobile, name, message) with variables**
```csv
mobile,name,message
09173011987,Juan,"Hi {{name}}! Your account {{mobile}} is activated."
09178251991,Maria,"Hello {{name}}, contact {{mobile}} for support."
```

**Supported Variables:**
- `{{mobile}}` - Recipient's E.164 phone number
- `{{name}}` - Recipient's name

**Example Result:**
- Input: `"Hi {{name}}! Your account {{mobile}} is activated."`
- Output: `"Hi Juan! Your account +639173011987 is activated."`

**Services:**
- `FileParser` - Parses CSV and XLSX files with header detection
- `MessagePersonalizer` - Handles variable substitution in message templates

### Frontend Structure (Phase 4)

#### Application Pages

**Dashboard** (`pages/Dashboard.vue`):
- Real-time stats cards: Total groups, contacts, scheduled messages, sent messages
- **Message Analytics**: Today's messages, this week's messages, success rate percentage
- **7-Day Activity Chart**: Visual representation of message volume over the last 7 days
- **Failed Messages Summary**: Recent failed messages with error details and retry buttons
- Quick action buttons: Send SMS, Manage Groups, Manage Contacts
- Recent activity feed: Latest groups and upcoming scheduled messages
- Powered by Inertia with live data from backend

**Send SMS** (`pages/SendSMS.vue`):
- Recipient input with configurable placeholder (via `SMS_RECIPIENTS_PLACEHOLDER`)
- Message textarea with character counter (1600 chars max, 160 chars/SMS)
- Message placeholder configurable via `SMS_MESSAGE_PLACEHOLDER`
- Sender ID selector with configurable options (via `SMS_SENDER_IDS`)
- Schedule for later option with datetime picker
- **Quick Schedule Buttons**: Incremental time add buttons (+1 min, +5 mins, +15 mins, +30 mins, +1 hour, +2 hours, +1 day, +1 week)
- Click buttons multiple times to build up desired time
- Send now vs Schedule for later
- Success/error alerts with form validation
- Button state management with timeout protection
- Toast notifications for real-time feedback

**Bulk Operations** (`pages/BulkOperations/Index.vue`):
- **Import Contacts**: Upload CSV/XLSX with mobile, name, email. Optional group assignment.
- **Bulk Send from File**: Upload file with phone numbers, send same message to all
- **Personalized Bulk Send**: Upload CSV with 2-col or 3-col format, variable substitution ({{name}}, {{mobile}})
- File validation, format instructions, loading states
- All operations processed via queue in background

**Groups Management** (`pages/Groups/Index.vue`, `pages/Groups/Show.vue`):
- List all groups with contact counts
- Create/edit/delete groups with dialog forms
- View group details with member list
- Click group card to view members
- Send SMS to entire group

**Contacts Management** (`pages/Contacts/Index.vue`):
- List all contacts with search/filter
- Create/edit/delete contacts
- **Edit contacts**: Double-click row or click edit icon
- Mobile field with lock/unlock toggle (focus on name by default)
- Import contacts from CSV (also available in Bulk Operations)
- Assign contacts to groups
- Display contact groups as badges

**Scheduled Messages** (`pages/ScheduledMessages/Index.vue`):
- List scheduled messages with pagination
- Filter by status: All, Pending, Processing, Sent, Cancelled
- Cancel pending/processing messages
- View message details: sender, recipients, scheduled time
- Status badges with color coding

**Message History** (`pages/MessageHistory/Index.vue`):
- View all sent messages with comprehensive logging
- Search by recipient or message content
- Filter by status: All, Sent, Failed, Pending
- Display contact names alongside phone numbers (smart lookup)
- Pagination (20 messages per page)
- **Export to CSV**: Export filtered message history with current search/filter
- **Retry Failed Messages**: One-click retry button with authorization checks
- Timestamps for sent/failed/created dates
- Error message display for failed messages
- Toast notifications for all operations

#### Directory Layout
```
resources/js/
├── actions/              # Wayfinder route helpers (organized by namespace)
├── components/           # Reusable Vue components
│   └── ui/              # Shadcn-vue UI components
│       ├── alert/       # Alert components
│       ├── alert-dialog/# Confirmation dialogs
│       ├── button/      # Button variants
│       ├── card/        # Card components
│       ├── dialog/      # Modal dialogs
│       ├── input/       # Form inputs
│       ├── label/       # Form labels
│       ├── select/      # Dropdown selects
│       └── textarea/    # Multi-line text inputs
├── composables/          # Vue composition API utilities
├── layouts/              # Page layouts (app, auth, settings)
├── lib/                  # Utility functions
├── pages/                # Inertia page components (route-mapped)
│   ├── auth/            # Authentication pages
│   ├── settings/        # User settings pages
│   ├── BulkOperations/  # Bulk import & send operations
│   ├── Contacts/        # Contact management
│   ├── Groups/          # Group management
│   └── ScheduledMessages/ # Scheduled message management
├── routes/               # [GENERATED] Wayfinder route definitions
├── types/                # TypeScript type definitions
├── wayfinder/            # Wayfinder core utilities
└── app.ts                # Main application entry point
```

#### Web Routes (Session-authenticated)

```php
# Pages
GET  /dashboard              → Dashboard with stats
GET  /send-sms               → Send SMS page
GET  /bulk-operations        → Bulk operations page
GET  /groups                 → List groups
GET  /groups/{id}            → View group details
GET  /contacts               → List contacts
GET  /scheduled-messages     → List scheduled messages
GET  /message-history        → Message history with search/filter

# Actions
POST   /sms/send             → Send SMS immediately
POST   /sms/schedule         → Schedule SMS for later
POST   /groups               → Create group
DELETE /groups/{id}          → Delete group
POST   /contacts             → Create contact
PUT    /contacts/{id}        → Update contact
DELETE /contacts/{id}        → Delete contact
POST   /contacts/import      → Import contacts from CSV
POST   /bulk/send            → Bulk send from file
POST   /bulk/send-personalized → Personalized bulk send
POST   /scheduled-messages/{id}/cancel → Cancel scheduled message
GET    /message-history/export → Export message history to CSV
POST   /message-logs/{id}/retry → Retry failed message
```

#### Wayfinder Integration
**Laravel Wayfinder** auto-generates type-safe route helpers from Laravel routes:
- Generated files are in `resources/js/routes/` and `resources/js/actions/`
- Import routes via `@/wayfinder` for type-safe navigation
- Configuration in `vite.config.ts` with `formVariants: true`

#### Component Approach
- UI components use **Reka UI** (Vue port of Radix UI) for headless accessibility
- Styling follows **shadcn-vue** patterns with Tailwind and CVA (class-variance-authority)
- Icons from **lucide-vue-next**
- Configuration in `components.json`

#### Path Aliases (TypeScript)
- `@/components` → `resources/js/components`
- `@/composables` → `resources/js/composables`
- `@/lib` → `resources/js/lib`
- `@/` → `resources/js/`

### Testing Patterns
- Tests use **Pest** (modern PHP testing framework)
- Feature tests in `tests/Feature/` (with database)
- Unit tests in `tests/Unit/` (isolated)
- Note: RefreshDatabase is commented out in `tests/Pest.php` - uncomment when needed
- CI runs tests with GitHub Actions (`.github/workflows/tests.yml`)

## Key Conventions

### PHP Code Style
- Laravel Pint enforces PSR-12 with Laravel preset
- Type hints required for method parameters and return types
- Prefer concise syntax (arrow functions, null coalescing, etc.)

### Vue/TypeScript Patterns
- Composition API over Options API
- TypeScript for all new Vue files and utilities
- Use `defineProps` and `defineEmits` with type annotations
- Prefer `<script setup lang="ts">` syntax

### Inertia Best Practices
- Use `router.visit()` or `router.get/post()` for navigation (not `axios`)
- Share frequently-used data via `HandleInertiaRequests` middleware
- Pass data to views as props through `Inertia::render()`

### Route Generation
- Backend routes automatically generate TypeScript helpers via Wayfinder
- After adding/modifying routes, Vite will regenerate route files
- Use generated actions for type-safe routing: `import { login } from '@/actions/Laravel/Fortify'`
