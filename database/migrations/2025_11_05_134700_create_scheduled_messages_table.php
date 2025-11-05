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
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();

            // Message content
            $table->text('message');
            $table->string('sender_id')->default('TXTCMDR');

            // Recipients (polymorphic)
            $table->string('recipient_type'); // 'numbers', 'group', 'mixed'
            $table->json('recipient_data');   // Store numbers, group IDs, or both

            // Scheduling
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'cancelled'])
                ->default('pending');

            // Metadata
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->json('errors')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};
