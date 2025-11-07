<?php

use App\Actions\ScheduleMessage;
use App\Actions\SendToMultipleRecipients;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();

    // Message analytics
    $totalMessages = \App\Models\MessageLog::where('user_id', $user->id)->count();
    $sentMessages = \App\Models\MessageLog::where('user_id', $user->id)->sent()->count();
    $failedMessages = \App\Models\MessageLog::where('user_id', $user->id)->failed()->count();
    $todayMessages = \App\Models\MessageLog::where('user_id', $user->id)->today()->count();
    $thisWeekMessages = \App\Models\MessageLog::where('user_id', $user->id)->thisWeek()->count();

    // Success rate
    $successRate = $totalMessages > 0 ? round(($sentMessages / $totalMessages) * 100, 1) : 0;

    // Last 7 days chart data
    $chartData = collect(range(6, 0))->map(function ($daysAgo) use ($user) {
        $date = now()->subDays($daysAgo);
        $count = \App\Models\MessageLog::where('user_id', $user->id)
            ->whereDate('created_at', $date)
            ->count();

        return [
            'date' => $date->format('M d'),
            'count' => $count,
        ];
    });

    return Inertia::render('Dashboard', [
        'stats' => [
            'totalGroups' => \App\Models\Group::where('user_id', $user->id)->count(),
            'totalContacts' => \App\Models\Contact::where('user_id', $user->id)->count(),
            'scheduledMessages' => \App\Models\ScheduledMessage::where('user_id', $user->id)->where('status', 'pending')->count(),
            'totalMessages' => $totalMessages,
            'sentMessages' => $sentMessages,
            'failedMessages' => $failedMessages,
            'todayMessages' => $todayMessages,
            'thisWeekMessages' => $thisWeekMessages,
            'successRate' => $successRate,
        ],
        'chartData' => $chartData,
        'recentGroups' => \App\Models\Group::where('user_id', $user->id)
            ->withCount('contacts')
            ->latest()
            ->take(5)
            ->get(),
        'recentScheduled' => \App\Models\ScheduledMessage::where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest('scheduled_at')
            ->take(5)
            ->get(),
        'recentFailures' => \App\Models\MessageLog::where('user_id', $user->id)
            ->failed()
            ->latest('failed_at')
            ->take(5)
            ->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('send-sms', function () {
    $user = auth()->user();
    $smsConfig = $user->smsConfig('engagespark');

    // Redirect to settings if user doesn't have active SMS config
    if (! $smsConfig || ! $smsConfig->is_active || ! $smsConfig->hasRequiredCredentials()) {
        return redirect()->route('sms-config.edit')
            ->with('error', 'Please configure your SMS account before sending messages.');
    }

    // Build sender IDs array from user's config
    $senderIds = array_merge(
        [$smsConfig->default_sender_id],
        $smsConfig->sender_ids ?? []
    );

    return Inertia::render('SendSMS', [
        'senderIds' => array_unique($senderIds),
        'defaultSenderId' => $smsConfig->default_sender_id,
        'recipientsPlaceholder' => config('sms.recipients_placeholder'),
        'messagePlaceholder' => config('sms.message_placeholder'),
    ]);
})->middleware(['auth', 'verified'])->name('sendSMS');

Route::get('groups', function () {
    $groups = \App\Actions\Groups\ListGroups::run();

    return Inertia::render('Groups/Index', [
        'groups' => $groups,
    ]);
})->middleware(['auth', 'verified'])->name('groups.index');

Route::get('groups/{id}', function (int $id) {
    $group = \App\Actions\Groups\GetGroup::run($id);

    return Inertia::render('Groups/Show', [
        'group' => $group,
    ]);
})->middleware(['auth', 'verified'])->name('groups.show');

Route::get('contacts', function () {
    $contacts = \App\Models\Contact::where('user_id', auth()->id())
        ->with('groups')
        ->orderBy('created_at', 'desc')
        ->get();
    $groups = \App\Models\Group::where('user_id', auth()->id())->get();

    return Inertia::render('Contacts/Index', [
        'contacts' => $contacts,
        'groups' => $groups,
    ]);
})->middleware(['auth', 'verified'])->name('contacts.index');

Route::get('contacts/{id}', function (Request $request, int $id) {
    $statusFilter = $request->query('status', 'all');
    $data = \App\Actions\Contacts\GetContactDetails::run($id, null, $statusFilter);

    // Get user's SMS config for sender IDs
    $user = auth()->user();
    $smsConfig = $user->smsConfig('engagespark');

    // Build sender IDs array
    $senderIds = [];
    $defaultSenderId = null;
    if ($smsConfig && $smsConfig->is_active && $smsConfig->hasRequiredCredentials()) {
        $senderIds = array_unique(array_merge(
            [$smsConfig->default_sender_id],
            $smsConfig->sender_ids ?? []
        ));
        $defaultSenderId = $smsConfig->default_sender_id;
    }

    // Debug log
    \Log::info('Contact detail page sender IDs', [
        'has_config' => $smsConfig !== null,
        'is_active' => $smsConfig?->is_active,
        'has_credentials' => $smsConfig?->hasRequiredCredentials(),
        'sender_ids' => $senderIds,
        'default_sender_id' => $defaultSenderId,
    ]);

    return Inertia::render('Contacts/Show', array_merge($data, [
        'senderIds' => $senderIds,
        'defaultSenderId' => $defaultSenderId,
    ]));
})->middleware(['auth', 'verified'])->name('contacts.show');

Route::get('contacts/{id}/messages', function (int $id) {
    return response()->json(
        \App\Actions\Contacts\GetContactMessages::run($id)
    );
})->middleware(['auth', 'verified'])->name('contacts.messages');

Route::get('scheduled-messages', function (Request $request) {
    $status = $request->query('status', 'all');
    $messages = \App\Actions\ListScheduledMessages::run($status);

    return Inertia::render('ScheduledMessages/Index', [
        'messages' => $messages,
        'currentStatus' => $status,
    ]);
})->middleware(['auth', 'verified'])->name('scheduled.index');

Route::get('message-history', function (Request $request) {
    $status = $request->query('status', 'all');
    $search = $request->query('search', '');
    $dateFrom = $request->query('date_from');
    $dateTo = $request->query('date_to');

    $query = \App\Models\MessageLog::query()
        ->where('user_id', auth()->id())
        ->orderBy('created_at', 'desc');

    // Filter by status
    if ($status !== 'all') {
        $query->where('status', $status);
    }

    // Search by recipient or message
    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('recipient', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%");
        });
    }

    // Filter by date range
    if ($dateFrom) {
        $query->whereDate('created_at', '>=', $dateFrom);
    }
    if ($dateTo) {
        $query->whereDate('created_at', '<=', $dateTo);
    }

    $logs = $query->paginate(20);

    return Inertia::render('MessageHistory/Index', [
        'logs' => $logs,
        'currentStatus' => $status,
        'searchQuery' => $search,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
    ]);
})->middleware(['auth', 'verified'])->name('message-history.index');

Route::get('message-history/export', function (Request $request) {
    return \App\Actions\ExportMessageHistory::run(
        auth()->id(),
        $request->query('status', 'all'),
        $request->query('search', ''),
        $request->query('date_from'),
        $request->query('date_to')
    );
})->middleware(['auth', 'verified'])->name('message-history.export');

Route::get('bulk-operations', function () {
    $groups = \App\Models\Group::where('user_id', auth()->id())->get();

    return Inertia::render('BulkOperations/Index', [
        'groups' => $groups,
    ]);
})->middleware(['auth', 'verified'])->name('bulk.index');

// SMS & Group Actions (for authenticated web interface)
Route::middleware(['auth', 'verified'])->group(function () {
    // SMS Actions
    Route::post('sms/send', function (Request $request) {
        try {
            $result = SendToMultipleRecipients::run(
                $request->input('recipients'),
                $request->input('message'),
                $request->input('sender_id')
            );

            $message = $result['count'] > 0
                ? "Successfully queued {$result['count']} message(s)!"
                : 'No valid recipients found.';

            if ($result['invalid_count'] > 0) {
                $message .= " ({$result['invalid_count']} invalid recipient(s) skipped)";
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send message: '.$e->getMessage());
        }
    })->name('sms.send');

    Route::post('sms/schedule', function (Request $request) {
        try {
            $result = ScheduleMessage::run(
                $request->input('recipients'),
                $request->input('message'),
                $request->input('scheduled_at'),
                $request->input('sender_id')
            );

            return back()->with('success', "Message scheduled for {$result->total_recipients} recipient(s)!");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to schedule message: '.$e->getMessage());
        }
    })->name('sms.schedule');

    // Group Actions
    Route::post('groups', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255|unique:groups,name',
            'description' => 'nullable|string|max:500',
        ]);

        $group = \App\Actions\Groups\CreateGroup::run(
            $request->input('name'),
            $request->input('description')
        );

        return back()->with('success', 'Group created successfully!');
    })->name('groups.store');

    Route::delete('groups/{id}', function (int $id) {
        \App\Actions\Groups\DeleteGroup::run($id);

        return redirect()->route('groups.index')->with('success', 'Group deleted successfully!');
    })->name('groups.destroy');

    // Contact Actions
    Route::post('contacts', function (Request $request) {
        $request->validate([
            'mobile' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,id',
        ]);

        $data = $request->only(['mobile', 'name', 'email']);
        $data['user_id'] = auth()->id();

        $contact = \App\Models\Contact::createFromArray($data);

        if ($contact && $request->has('group_ids')) {
            $contact->groups()->sync($request->input('group_ids'));
        }

        return back()->with('success', 'Contact created successfully!');
    })->name('contacts.store');

    Route::post('contacts/import', function (Request $request) {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,xlsx,xls',
                'group_id' => 'nullable|exists:groups,id',
            ]);

            \App\Actions\Contacts\ImportContactsFromFile::run(
                $request->file('file'),
                auth()->id(),
                $request->input('group_id')
            );

            return back()->with('success', 'Contacts import started! Processing in background.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to import contacts: '.$e->getMessage());
        }
    })->name('contacts.import');

    Route::put('contacts/{id}', function (Request $request, int $id) {
        $contact = \App\Models\Contact::where('user_id', auth()->id())
            ->findOrFail($id);

        $request->validate([
            'mobile' => 'required|string',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,id',
        ]);

        // Update mobile if changed (requires validation)
        if ($request->input('mobile') !== $contact->mobile) {
            try {
                $phone = new \Propaganistas\LaravelPhone\PhoneNumber($request->input('mobile'), 'PH');
                $contact->mobile = $phone->formatE164();
            } catch (\Exception $e) {
                return back()->withErrors(['mobile' => 'Invalid phone number format']);
            }
        }

        // Update schemaless attributes (stored in meta JSON)
        // Convert empty strings to null for proper storage
        $contact->name = $request->input('name') ?: null;
        $contact->email = $request->input('email') ?: null;
        $contact->save();

        // Update group associations
        if ($request->has('group_ids')) {
            $contact->groups()->sync($request->input('group_ids'));
        }

        return back()->with('success', 'Contact updated successfully!');
    })->name('contacts.update');

    Route::delete('contacts/{id}', function (int $id) {
        $contact = \App\Models\Contact::where('user_id', auth()->id())
            ->findOrFail($id);
        $contact->delete();

        return back()->with('success', 'Contact deleted successfully!');
    })->name('contacts.destroy');

    // Scheduled Message Actions
    Route::post('scheduled-messages/{id}/cancel', function (int $id) {
        \App\Actions\CancelScheduledMessage::run($id);

        return back()->with('success', 'Scheduled message cancelled successfully!');
    })->name('scheduled.cancel');

    // Message History Actions
    Route::post('message-logs/{id}/retry', function (int $id) {
        try {
            \App\Actions\RetryFailedMessage::run($id);

            return back()->with('success', 'Message queued for retry!');
        } catch (\Exception $e) {
            return back()->withErrors(['retry' => $e->getMessage()]);
        }
    })->name('message-logs.retry');

    // Bulk Operations
    Route::post('bulk/send', function (Request $request) {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,xlsx,xls',
                'message' => 'required|string|max:1600',
                'sender_id' => 'required|string',
                'mobile_column' => 'nullable|string',
            ]);

            $result = \App\Actions\SMS\BulkSendFromFile::run(
                $request->file('file'),
                $request->input('message'),
                $request->input('sender_id'),
                auth()->id(),
                $request->input('mobile_column', 'mobile')
            );

            $message = "Bulk send started! {$result['queued']} message(s) queued.";
            if ($result['invalid'] > 0) {
                $message .= " ({$result['invalid']} invalid number(s) skipped)";
            }

            return back()->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process bulk send: '.$e->getMessage());
        }
    })->name('bulk.send');

    Route::post('bulk/send-personalized', function (Request $request) {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,xlsx,xls',
                'sender_id' => 'required|string',
                'import_contacts' => 'boolean',
            ]);

            $result = \App\Actions\SMS\BulkSendPersonalized::run(
                $request->file('file'),
                $request->input('sender_id'),
                auth()->id(),
                $request->boolean('import_contacts')
            );

            $message = "Personalized bulk send started! {$result['queued']} message(s) queued.";
            if ($result['invalid'] > 0) {
                $message .= " ({$result['invalid']} invalid row(s) skipped)";
            }
            if ($result['contacts_imported'] > 0) {
                $message .= " {$result['contacts_imported']} contact(s) imported.";
            }

            return back()->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process personalized bulk send: '.$e->getMessage());
        }
    })->name('bulk.send-personalized');
});

require __DIR__.'/settings.php';
