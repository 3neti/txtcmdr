<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize all Philippine phone numbers to E.164 format
        $contacts = \DB::table('contacts')->get();

        foreach ($contacts as $contact) {
            $mobile = $contact->mobile;

            // Skip if already in E.164 format
            if (str_starts_with($mobile, '+')) {
                continue;
            }

            try {
                // Use the phone helper to properly format to E.164
                $phone = new \Propaganistas\LaravelPhone\PhoneNumber($mobile, $contact->country ?? 'PH');
                $e164 = $phone->formatE164();

                \DB::table('contacts')
                    ->where('id', $contact->id)
                    ->update(['mobile' => $e164]);
            } catch (\Exception $e) {
                // Log problematic numbers but continue
                \Log::warning("Could not normalize contact {$contact->id} phone: {$mobile}");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert E.164 back to local format for Philippines
        $contacts = \DB::table('contacts')->get();

        foreach ($contacts as $contact) {
            $mobile = $contact->mobile;

            // Convert E.164 (+63...) to local format (09...)
            if (str_starts_with($mobile, '+63')) {
                $local = '0'.substr($mobile, 3);
                \DB::table('contacts')
                    ->where('id', $contact->id)
                    ->update(['mobile' => $local]);
            }
        }
    }
};
