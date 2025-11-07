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

#### Registration with SMS Configuration
The application supports configurable multi-step registration with optional SMS credentials:

**Configuration Mode** (`REGISTRATION_SMS_CONFIG` in `.env`):
- `optional` (default): Users can skip SMS config and use app defaults
- `required`: Users must provide their own SMS credentials to register
- `disabled`: SMS configuration step is completely hidden

**Multi-Step Registration Flow:**
1. **Step 1**: Basic account info (name, email, password)
2. **Step 2**: SMS config (API key, org ID, sender IDs) - shown only if mode is not "disabled"

**Implementation:**
- `config/registration.php` - Registration configuration
- `app/Actions/Fortify/CreateNewUser.php` - Handles user creation with conditional SMS config
- `resources/js/pages/auth/Register.vue` - Multi-step registration UI with step indicator and progress indicator
- **TagInput integration**: Same sender ID management UI as Settings page
- Field order matches Settings: API Key → Org ID → Sender IDs (TagInput) → Default Sender ID (Select)
- SMS credentials encrypted and stored in `user_sms_configs` table
- Seamless integration with existing hybrid SMS fallback system

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
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }
}
```

**Multi-Tenancy:**
- Every contact is owned by a specific user (enforced with `user_id` foreign key)
- Users can only see and manage their own contacts
- Two users can have contacts with the same phone number but different names (personal contact books)
- Unique constraint: `['user_id', 'mobile']` prevents duplicate contacts per user

**Phone Number Normalization:**
- All phone numbers stored in **E.164 format** (e.g., `+639173011987`)
- Consistent normalization using `propaganistas/laravel-phone` package
- Normalization happens at all entry points: contact creation, SMS sending, import, scheduled messages
- Database migration ensures all existing contacts are normalized

**Usage Examples:**
```php
// Creating contacts with schemaless attributes
$contact = Contact::create([
    'mobile' => '+639173011987',
    'user_id' => auth()->id(),
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

// Phone number normalization (consistent across all code)
use Propaganistas\LaravelPhone\PhoneNumber;

$phone = new PhoneNumber('09173011987', 'PH');
$e164 = $phone->formatE164();  // "+639173011987"

// Finding/creating contacts with normalized numbers
$contact = Contact::firstOrCreate(
    ['mobile' => $e164, 'user_id' => auth()->id()],
    ['country' => 'PH', 'name' => 'John Doe']
);

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
- `GetContactMessages` - Get message history for a specific contact (modal view)
- `GetContactDetails` - Get comprehensive contact details with stats and paginated message history
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
- `contacts` - Base table with **user_id**, mobile (E.164), country, bank_account, **meta (JSON)**
  - Unique constraint: `['user_id', 'mobile']`
  - Foreign key: `user_id` → `users.id` (cascade on delete)
- `groups` - Named groups with **user_id** ownership
  - Foreign key: `user_id` → `users.id` (cascade on delete)
- `contact_group` - Many-to-many pivot
- `blacklisted_numbers` - Phone numbers to exclude from broadcasts
- `scheduled_messages` - SMS scheduling with **user_id**, recipient_type, recipient_data, status tracking
  - Foreign key: `user_id` → `users.id` (cascade on delete)
- `message_logs` - Audit trail with **user_id**, recipient, message, sender_id, status, timestamps
  - Foreign key: `user_id` → `users.id` (cascade on delete)
  - Tracks WHO sent WHAT to WHOM, WHEN, and whether it succeeded

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
- **Multi-tenant**: Users can only see and manage their own scheduled messages
- **Phone normalization**: All recipients normalized to E.164 before storage
- **User context preserved**: ProcessScheduledMessage passes user_id to SendSMSJob for credential retrieval

**Usage:**
```php
// Schedule a message
ScheduleMessage::run(
    recipients: ['09173011987', '09178251991'],
    message: 'Reminder: Your appointment is tomorrow',
    scheduledAt: '2025-11-06 10:00:00',
    senderId: 'Quezon City',
    userId: auth()->id()  // Required for multi-tenancy
);
```

**Scheduler Configuration:**
```php
// routes/console.php
Schedule::command('messages:process-scheduled')->everyMinute();
```

**Implementation Notes:**
- Recipients parsed and normalized before storing in `recipient_data` JSON
- `ProcessScheduledMessage` retrieves scheduled message and dispatches `SendSMSJob` for each recipient
- `SendSMSJob` receives both `scheduled_message_id` and `user_id` to properly log and authenticate
- Failed scheduled messages can be identified and retried from Message History

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

**Contacts Management** (`pages/Contacts/Index.vue`, `pages/Contacts/Show.vue`):
- **Index Page**: List all contacts with search/filter
  - Create/edit/delete contacts
  - **Single click contact row** → Navigate to detail page
  - **MessageSquare icon** → Quick modal view (see below)
  - **Double-click row** → Edit dialog
  - Mobile field with lock/unlock toggle (focus on name by default)
  - Import contacts from CSV (also available in Bulk Operations)
  - Assign contacts to groups
  - Display contact groups as badges

- **Detail Page** (`/contacts/{id}`): Comprehensive contact view with two-way messaging
  - **Header Section**: Large avatar, contact name, phone, group badges, edit button
  - **Stats Cards**: Total messages, success rate, failed count, last message time
  - **Quick Send Form**: Send SMS directly to contact with character counter and sender ID selection
  - **Message Timeline**: Paginated message history (20 per page) with status filters
  - **Status Filter**: Filter by all/sent/failed/pending
  - **Retry Button**: Retry failed messages inline
  - **Breadcrumb Navigation**: Dashboard → Contacts → Contact Name

- **Quick Modal View** (MessageSquare icon):
  - Modal dialog shows recent messages (up to 50)
  - Chronological message list with status badges
  - Relative timestamps ("2 hours ago", "Yesterday")
  - Sender ID display for each message
  - Error messages for failed SMS
  - Retry button for failed messages
  - Empty state for contacts with no messages
  - Uses E.164 format for accurate message matching

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
- **Date Range Filtering**: Filter by From Date and To Date for audit reports
- Display contact names alongside phone numbers (smart lookup)
- Pagination (20 messages per page)
- **Export to CSV**: Export filtered message history with current search/filter/date range
- **Retry Failed Messages**: One-click retry button with authorization checks
- Timestamps for sent/failed/created dates
- Error message display for failed messages
- Toast notifications for all operations

**Audit & Reporting:**
- Complete audit trail in `message_logs` table tracks:
  - **WHO**: `user_id` (which user sent the message)
  - **WHAT**: `message` content
  - **TO WHOM**: `recipient` (E.164 phone number)
  - **WHEN**: `sent_at`, `failed_at`, `created_at` timestamps
  - **HOW**: `sender_id` (official SMS sender identifier)
  - **RESULT**: `status` (sent/failed/pending), `error_message` if failed
  - **CAMPAIGN**: `scheduled_message_id` links to bulk/scheduled campaigns
- Date range filtering for compliance and accountability reports
- CSV export includes all audit fields for external analysis
- User-specific isolation: Each user can only see their own message history
- Failed message retry preserves audit trail with new log entry

**Settings Pages** (`pages/settings/`):
- **Profile**: Update name and email with email verification
- **Password**: Change password with current password verification
- **Two-Factor Auth**: Enable/disable 2FA with QR code setup
- **SMS**: Configure EngageSPARK credentials (API key, org ID, sender IDs)
  - User-specific SMS configuration with hybrid fallback
  - **Optional credentials**: Leave API key/org ID blank to keep current (helper text included)
  - **TagInput component**: Add/remove sender IDs with Enter/comma, dismiss with X button
  - Field order: API Key → Org ID → Sender IDs (TagInput) → Default Sender ID (Select)
  - API key and org ID stored encrypted in database
  - Override application defaults or use app-wide config
  - Active/inactive toggle to temporarily disable custom config
  - Delete configuration to revert to app defaults
- **Appearance**: Theme and appearance settings

All settings pages follow the same pattern:
- Route: `/settings/{page}`
- Layout: `AppLayout` → `SettingsLayout` (with left nav menu)
- Components: `HeadingSmall`, shadcn-vue form components
- Actions: Wayfinder-generated form actions

#### Directory Layout
```
resources/js/
├── actions/              # Wayfinder route helpers (organized by namespace)
├── components/           # Reusable Vue components
│   ├── MessageHistoryDialog.vue  # Contact message history modal
│   └── ui/              # Shadcn-vue UI components
│       ├── alert/       # Alert components
│       ├── alert-dialog/# Confirmation dialogs
│       ├── badge/       # Status badges
│       ├── button/      # Button variants
│       ├── card/        # Card components
│       ├── dialog/      # Modal dialogs
│       ├── input/       # Form inputs
│       ├── label/       # Form labels
│       ├── scroll-area/ # Scrollable containers
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
GET  /contacts/{id}          → Contact detail page with stats and message history
GET  /contacts/{id}/messages → Get message history for contact (JSON, for modal)
GET  /scheduled-messages     → List scheduled messages
GET  /message-history        → Message history with search/filter

# Settings
GET    /settings/profile       → Profile settings
PATCH  /settings/profile       → Update profile
DELETE /settings/profile       → Delete account
GET    /settings/password      → Password settings
PUT    /settings/password      → Update password
GET    /settings/two-factor    → Two-factor auth settings
GET    /settings/sms           → SMS configuration settings
PUT    /settings/sms           → Update SMS configuration
DELETE /settings/sms           → Delete SMS configuration
GET    /settings/appearance    → Appearance settings

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
GET    /message-history/export → Export message history to CSV (with date_from/date_to params)
POST   /message-logs/{id}/retry → Retry failed message
```

**Multi-Tenancy Notes:**
- All routes automatically filter by `auth()->id()` to ensure user isolation
- Users cannot access other users' contacts, groups, scheduled messages, or message logs
- Database enforces foreign key constraints with cascade on delete
- Phone numbers normalized to E.164 at all entry points for consistency

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
- `RefreshDatabase` trait enabled in `tests/Pest.php` for database tests
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
