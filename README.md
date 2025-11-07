# Text Commander (txtcmdr)

**Version 1.0.0**

A modern, multi-tenant SMS broadcasting system built with Laravel 12 and Vue 3 for sending targeted messages to groups of contacts with comprehensive audit trails and user-specific configurations.

## Features

### Core Functionality
- **Multi-Tenancy** - Complete user isolation with per-user SMS credentials and data
- **SMS Broadcasting** - Send messages to individuals, groups, or bulk recipients
- **Contact Management** - Organize contacts with groups, custom attributes, and conversation history
- **Scheduled Messages** - Schedule SMS for future delivery with full management
- **Message History** - Complete audit trail with search, filters, date ranges, and CSV export
- **Bulk Operations** - Import contacts and send personalized bulk messages from CSV/Excel

### Advanced Features
- **Contact Detail Page** - Comprehensive view with stats, message timeline, and quick send form
- **Message History Modal** - Quick peek at conversation history without navigation
- **User-Specific SMS Config** - Each user can configure their own EngageSPARK credentials
- **Hybrid SMS Fallback** - CLI commands use .env config, web users use personal config
- **Smart Contact Lookup** - Display contact names alongside phone numbers
- **Failed Message Retry** - One-click retry for failed SMS with authorization checks
- **Real-time Analytics** - Dashboard with message stats, 7-day activity chart, and failed message summaries
- **Date Range Filtering** - Filter message history by date range for compliance reports
- **Phone Number Normalization** - All numbers stored in E.164 format for consistency
- **Two-Factor Authentication** - Secure account access with 2FA support
- **Quick Schedule** - Incremental time buttons (+1 min, +5 mins, +1 hour, +1 day, etc.)
- **CSV Export** - Export message history with all filters and date ranges
- **Configurable UI** - Customizable placeholders and sender IDs via environment variables

## Tech Stack

### Backend
- **Laravel 12** (PHP 8.2+)
- **Laravel Fortify** - Authentication
- **Laravel Actions** - Endpoint logic
- **Laravel Sanctum** - API authentication
- **lbhurtado/sms** - SMS integration (EngageSPARK)
- **lbhurtado/contact** - Contact management with schemaless attributes

### Frontend
- **Vue 3** - Modern reactive framework
- **Inertia.js** - SPA-like experience without API
- **Reka UI** - Headless accessible components
- **shadcn-vue** - Beautiful UI components
- **Tailwind CSS v4** - Utility-first styling
- **Lucide Icons** - Icon set
- **TypeScript** - Type safety

### Tools & Libraries
- **Vite** - Fast build tool
- **Pest PHP** - Testing framework
- **Laravel Pint** - Code formatting
- **League CSV** - CSV parsing and export
- **PHPOffice/PhpSpreadsheet** - Excel support

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js & npm
- SQLite (default) or MySQL/PostgreSQL

### Setup

```bash
# Clone the repository
git clone https://github.com/3neti/txtcmdr.git
cd txtcmdr

# Install dependencies and setup
composer setup
# This runs: composer install, .env setup, key generation, migrations, npm install, build

# Or manually:
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build

# Start development server
composer dev
# Runs: Laravel server, queue worker, Pail logs, and Vite dev server
```

### Configuration

Edit `.env` file:

```env
APP_NAME="Text Commander"
APP_URL=http://localhost:8000

# Database (SQLite by default)
DB_CONNECTION=sqlite

# SMS Provider (EngageSPARK)
SMS_DRIVER=engagespark
SMS_DEFAULT_SENDER_ID=TXTCMDR
SMS_SENDER_IDS="cashless,Quezon City,TXTCMDR"
SMS_RECIPIENTS_PLACEHOLDER="+639171234567, 09181234567, Sales"
SMS_MESSAGE_PLACEHOLDER="Your message here..."
ENGAGESPARK_API_KEY=your-api-key
ENGAGESPARK_API_URL=https://start.engagespark.com/api/v1
ENGAGESPARK_ORGANIZATION_ID=your-org-id

# Queue (for SMS sending)
QUEUE_CONNECTION=database
```

### Default Admin User

After seeding:
- **Email**: admin@disburse.cash
- **Password**: password
- **SMS Config**: Automatically configured from .env if credentials are present

## Usage

### User Configuration

Each user must configure their own SMS credentials:

1. **During Registration** (if `REGISTRATION_SMS_CONFIG` is enabled)
2. **Settings → SMS** page after login

The seeder automatically configures admin user from `.env` credentials if present.

### Sending SMS

#### Immediate Send
```php
use App\Actions\SendToMultipleRecipients;

SendToMultipleRecipients::run(
    recipients: ['+639173011987', '+639178251991'],
    message: 'Hello from Text Commander!',
    senderId: 'TXTCMDR'
);
```

#### Schedule for Later
```php
use App\Actions\ScheduleMessage;

ScheduleMessage::run(
    recipients: ['+639173011987'],
    message: 'Reminder: Your appointment tomorrow',
    scheduledAt: '2025-12-01 10:00:00',
    senderId: 'TXTCMDR'
);
```

#### Bulk Personalized
Upload CSV with columns: `mobile,name,message`
```csv
mobile,name,message
09173011987,Juan,"Hi {{name}}! Your account {{mobile}} is ready."
09178251991,Maria,"Hello {{name}}, welcome!"
```

### Contact Management

#### Contact Detail Page (`/contacts/{id}`)
- **Single click contact** → Navigate to comprehensive detail view
- **Header**: Avatar, name, phone, group badges, edit button
- **Stats Cards**: Total messages, success rate, failed count, last message time
- **Quick Send Form**: Send SMS directly to contact
- **Message Timeline**: Paginated history (20 per page) with status filters
- **Retry Failed**: One-click retry for failed messages

#### Quick Message History Modal
- **MessageSquare icon** → Quick peek without navigation
- Shows up to 50 recent messages
- Status badges, relative timestamps, error messages
- Retry button for failed messages

### Message History & Audit

- View all sent messages with status (sent/failed/pending)
- Search by recipient or message content
- Filter by status (all/sent/failed/pending)
- **Date Range Filtering**: Filter by From/To dates for compliance reports
- Export to CSV with all filters (search, status, date range)
- Retry failed messages with one click
- View contact names alongside phone numbers
- Complete audit trail: WHO sent WHAT to WHOM, WHEN, HOW, and RESULT

### UI Customization

Customize placeholders and sender IDs in `.env`:

```env
# Sender ID dropdown options (comma-separated)
SMS_SENDER_IDS="cashless,Quezon City,TXTCMDR"

# Placeholder for recipients field
SMS_RECIPIENTS_PLACEHOLDER="+639171234567, 09181234567, Sales"

# Placeholder for message field
SMS_MESSAGE_PLACEHOLDER="Your message here..."
```

### Quick Schedule Feature

When scheduling messages, use quick action buttons to increment time:
- **+1 min, +5 mins, +15 mins, +30 mins** - Quick minute increments
- **+1 hour, +2 hours** - Hour increments
- **+1 day, +1 week** - Day/week increments

Click multiple times to build up the desired time (e.g., +1 hour × 2 + 30 mins = 2h 30m from now)

## Development

### Running Tests
```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/Actions/SendToMultipleRecipientsTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

### Code Quality
```bash
# Fix PHP code style
./vendor/bin/pint

# Fix JavaScript/TypeScript
npm run lint
npm run format
```

### Database

```bash
# Run migrations
php artisan migrate

# Fresh database with seeding
php artisan migrate:fresh --seed

# Rollback
php artisan migrate:rollback
```

### Queue Worker

SMS sending requires a queue worker:

```bash
# Development
php artisan queue:work

# Or use the dev command which includes queue worker
composer dev
```

### Scheduler

Scheduled messages are processed every minute:

```bash
# Development - run manually
php artisan messages:process-scheduled

# Or run scheduler in watch mode
php artisan schedule:work

# Production - add to crontab
* * * * * cd /path/to/txtcmdr && php artisan schedule:run >> /dev/null 2>&1
```

## API Endpoints

### SMS
- `POST /api/send` - Send to multiple recipients
- `POST /api/groups/send` - Send to groups
- `POST /api/send/schedule` - Schedule message
- `POST /api/sms/bulk-send` - Bulk send from file
- `POST /api/sms/bulk-send-personalized` - Personalized bulk send

### Contacts
- `POST /api/contacts/import` - Import contacts from CSV

### Management
- `GET /api/groups` - List groups
- `POST /api/groups` - Create group
- `DELETE /api/groups/{id}` - Delete group
- `GET /api/scheduled-messages` - List scheduled messages
- `PUT /api/scheduled-messages/{id}` - Update scheduled message
- `POST /api/scheduled-messages/{id}/cancel` - Cancel scheduled message
- `GET /message-history/export` - Export message history to CSV
- `POST /message-logs/{id}/retry` - Retry failed message

All API endpoints require Sanctum token authentication.

## Project Structure

```
app/
├── Actions/           # Laravel Actions for business logic
│   ├── Groups/       # Group management actions
│   ├── BulkSendFromFile.php
│   ├── BulkSendPersonalized.php
│   ├── ExportMessageHistory.php
│   ├── RetryFailedMessage.php
│   └── ScheduleMessage.php
├── Jobs/             # Queue jobs (SendSMSJob, ProcessScheduledMessage, etc.)
├── Models/           # Eloquent models (Contact, Group, MessageLog, ScheduledMessage)
└── Services/         # Services (FileParser, MessagePersonalizer)

resources/js/
├── components/       # Vue components
│   └── ui/          # shadcn-vue UI components
├── pages/           # Inertia pages (route-mapped)
│   ├── Dashboard.vue
│   ├── SendSMS.vue
│   ├── MessageHistory/
│   ├── ScheduledMessages/
│   ├── Groups/
│   ├── Contacts/
│   └── BulkOperations/
├── layouts/         # Page layouts (AppLayout, AuthLayout)
├── composables/     # Vue composables
└── routes/          # Generated Wayfinder route helpers

tests/
├── Feature/         # Feature tests (49 tests, 148 assertions)
│   ├── Actions/     # SendToMultipleRecipientsTest, ScheduleMessageTest, etc.
│   └── Jobs/        # SendSMSJobTest
└── Unit/           # Unit tests

packages/
└── lbhurtado/contact/  # Custom contact management package (embedded)
```

## Deployment

### Production Checklist

1. **Environment Configuration**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   DB_CONNECTION=mysql  # or pgsql
   QUEUE_CONNECTION=redis
   ```

2. **Build Assets**
   ```bash
   npm run build
   ```

3. **Optimize Laravel**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Setup Queue Worker** (Supervisor)
5. **Setup Cron** for scheduler
6. **Run Migrations**
   ```bash
   php artisan migrate --force
   ```

See [WARP.md](WARP.md) for detailed development and deployment guide.

## Testing

The project includes comprehensive test coverage:

- **126 tests** passing with **481 assertions**
- Feature tests for all SMS workflows
- Multi-tenancy and user isolation tests
- Job execution and status tracking
- Bulk operations and file parsing
- Retry mechanism and authorization
- Phone number normalization tests

## Contributing

This is a private project. For inquiries, contact the repository maintainers.

## License

Proprietary - All rights reserved

## Credits

Built with:
- [Laravel](https://laravel.com)
- [Vue.js](https://vuejs.org)
- [Inertia.js](https://inertiajs.com)
- [Tailwind CSS](https://tailwindcss.com)
- [shadcn-vue](https://www.shadcn-vue.com)

## Support

For issues or questions:
- GitHub Issues: https://github.com/3neti/txtcmdr/issues
- Documentation: [WARP.md](WARP.md)
