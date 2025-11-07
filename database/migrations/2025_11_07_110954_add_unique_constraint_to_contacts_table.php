<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, remove duplicate contacts - keep the oldest one for each user_id + mobile combination
        $duplicates = \DB::table('contacts')
            ->select('user_id', 'mobile', \DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'mobile')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            \DB::table('contacts')
                ->where('user_id', $duplicate->user_id)
                ->where('mobile', $duplicate->mobile)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        // Add unique constraint
        Schema::table('contacts', function (Blueprint $table) {
            $table->unique(['user_id', 'mobile']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'mobile']);
        });
    }
};
