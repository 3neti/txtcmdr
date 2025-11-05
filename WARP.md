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

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Rollback
php artisan migrate:rollback
```

### SMS Commands
```bash
# Test SMS sending
php artisan sms:test "+639173011987" "Test message"

# Test with custom sender
php artisan sms:test "+639173011987" "Test" --sender="cashless"
```

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
- `CheckBlacklist` middleware - Prevents SMS to blacklisted numbers

**Actions (Laravel Actions):**
- `SendToMultipleRecipients` - Send SMS to multiple phone numbers
- `SendToMultipleGroups` - Broadcast SMS to multiple groups
- `CreateGroup`, `ListGroups`, `GetGroup`, `DeleteGroup` - Group management

**API Routes (Sanctum protected):**
```
POST   /api/send                  → SendToMultipleRecipients
POST   /api/groups/send           → SendToMultipleGroups
GET    /api/groups                → ListGroups
POST   /api/groups                → CreateGroup
GET    /api/groups/{id}           → GetGroup
DELETE /api/groups/{id}           → DeleteGroup
```

**Database Schema:**
- `contacts` - Base table with mobile, country, bank_account, **meta (JSON)**
- `groups` - Named groups with user_id ownership
- `contact_group` - Many-to-many pivot
- `blacklisted_numbers` - Phone numbers to exclude from broadcasts
- `scheduled_messages` - Future SMS scheduling (Phase 3)

**Configuration:**
- `.env` - SMS_DRIVER, SMS_DEFAULT_SENDER_ID, ENGAGESPARK credentials
- `config/sms.php` - SMS driver configuration
- `config/engagespark.php` - EngageSPARK provider settings

### Frontend Structure

#### Directory Layout
```
resources/js/
├── actions/           # Wayfinder route helpers (organized by namespace)
├── components/        # Reusable Vue components
│   └── ui/           # Shadcn-vue UI components
├── composables/       # Vue composition API utilities
├── layouts/           # Page layouts (app, auth, settings)
├── lib/              # Utility functions
├── pages/            # Inertia page components (route-mapped)
│   ├── auth/         # Authentication pages
│   └── settings/     # User settings pages
├── routes/           # [GENERATED] Wayfinder route definitions
├── types/            # TypeScript type definitions
├── wayfinder/        # Wayfinder core utilities
└── app.ts            # Main application entry point
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
