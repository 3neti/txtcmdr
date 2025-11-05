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

    return Inertia::render('Dashboard', [
        'stats' => [
            'totalGroups' => \App\Models\Group::where('user_id', $user->id)->count(),
            'totalContacts' => \App\Models\Contact::count(),
            'scheduledMessages' => \App\Models\ScheduledMessage::where('status', 'pending')->count(),
            'sentMessages' => \App\Models\ScheduledMessage::where('status', 'sent')->count(),
        ],
        'recentGroups' => \App\Models\Group::where('user_id', $user->id)
            ->withCount('contacts')
            ->latest()
            ->take(5)
            ->get(),
        'recentScheduled' => \App\Models\ScheduledMessage::where('status', 'pending')
            ->latest('scheduled_at')
            ->take(5)
            ->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('send-sms', function () {
    return Inertia::render('SendSMS');
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
    $contacts = \App\Models\Contact::with('groups')
        ->orderBy('created_at', 'desc')
        ->get();
    $groups = \App\Models\Group::where('user_id', auth()->id())->get();

    return Inertia::render('Contacts/Index', [
        'contacts' => $contacts,
        'groups' => $groups,
    ]);
})->middleware(['auth', 'verified'])->name('contacts.index');

Route::get('scheduled-messages', function (Request $request) {
    $status = $request->query('status', 'all');
    $messages = \App\Actions\ListScheduledMessages::run($status);

    return Inertia::render('ScheduledMessages/Index', [
        'messages' => $messages,
        'currentStatus' => $status,
    ]);
})->middleware(['auth', 'verified'])->name('scheduled.index');

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
        $result = SendToMultipleRecipients::run(
            $request->input('recipients'),
            $request->input('message'),
            $request->input('sender_id')
        );

        return back()->with('success', 'Message sent successfully!');
    })->name('sms.send');

    Route::post('sms/schedule', function (Request $request) {
        $result = ScheduleMessage::run(
            $request->input('recipients'),
            $request->input('message'),
            $request->input('scheduled_at'),
            $request->input('sender_id')
        );

        return back()->with('success', 'Message scheduled successfully!');
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

        $contact = \App\Models\Contact::createFromArray($request->only(['mobile', 'name', 'email']));

        if ($contact && $request->has('group_ids')) {
            $contact->groups()->sync($request->input('group_ids'));
        }

        return back()->with('success', 'Contact created successfully!');
    })->name('contacts.store');

    Route::post('contacts/import', function (Request $request) {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        \App\Actions\Contacts\ImportContactsFromFile::run(
            $request->file('file'),
            $request->input('group_id')
        );

        return back()->with('success', 'Contacts import started! Processing in background.');
    })->name('contacts.import');

    Route::put('contacts/{id}', function (Request $request, int $id) {
        $contact = \App\Models\Contact::findOrFail($id);

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
        $contact = \App\Models\Contact::findOrFail($id);
        $contact->delete();

        return back()->with('success', 'Contact deleted successfully!');
    })->name('contacts.destroy');

    // Scheduled Message Actions
    Route::post('scheduled-messages/{id}/cancel', function (int $id) {
        \App\Actions\CancelScheduledMessage::run($id);

        return back()->with('success', 'Scheduled message cancelled successfully!');
    })->name('scheduled.cancel');

    // Bulk Operations
    Route::post('bulk/send', function (Request $request) {
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
            $request->input('mobile_column', 'mobile')
        );

        return back()->with('success', "Bulk send started! {$result['queued']} messages queued.");
    })->name('bulk.send');

    Route::post('bulk/send-personalized', function (Request $request) {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'sender_id' => 'required|string',
            'import_contacts' => 'boolean',
        ]);

        $result = \App\Actions\SMS\BulkSendPersonalized::run(
            $request->file('file'),
            $request->input('sender_id'),
            $request->boolean('import_contacts')
        );

        return back()->with('success', "Personalized bulk send started! {$result['queued']} messages queued.");
    })->name('bulk.send-personalized');
});

require __DIR__.'/settings.php';
