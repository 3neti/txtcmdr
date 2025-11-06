# Text Commander (txtcmdr)

A modern SMS broadcasting system built with Laravel 12 and Vue 3 for sending targeted messages to groups of contacts.

## Features

### Core Functionality
- **SMS Broadcasting** - Send messages to individuals, groups, or bulk recipients
- **Contact Management** - Organize contacts with groups and custom attributes
- **Scheduled Messages** - Schedule SMS for future delivery with full management
- **Message History** - Complete audit trail with search, filters, and CSV export
- **Bulk Operations** - Import contacts and send personalized bulk messages from CSV/Excel

### Advanced Features
- **Smart Contact Lookup** - Display contact names alongside phone numbers
- **Failed Message Retry** - One-click retry for failed SMS
- **Real-time Analytics** - Dashboard with message stats and 7-day activity chart
- **Toast Notifications** - Real-time feedback for all operations
- **Error Tracking** - Comprehensive logging with error summaries

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

## Usage

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
- `GET /api/scheduled-messages` - List scheduled messages
- `POST /api/scheduled-messages/{id}/cancel` - Cancel scheduled message

All API endpoints require Sanctum token authentication.

## Project Structure

```
app/
├── Actions/           # Laravel Actions for business logic
├── Jobs/             # Queue jobs (SendSMSJob, etc.)
└── Models/           # Eloquent models

resources/js/
├── components/       # Vue components
├── pages/           # Inertia pages (route-mapped)
├── layouts/         # Page layouts
└── composables/     # Vue composables

tests/
├── Feature/         # Feature tests
└── Unit/           # Unit tests

packages/
└── lbhurtado/contact/  # Custom contact management package
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

- **49 tests** with **148 assertions**
- Feature tests for all SMS workflows
- Job execution and status tracking
- Bulk operations and file parsing
- Retry mechanism and authorization

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
