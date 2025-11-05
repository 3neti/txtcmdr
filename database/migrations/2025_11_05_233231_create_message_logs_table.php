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
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recipient'); // E.164 phone number
            $table->text('message');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->string('sender_id'); // cashless, Quezon City, TXTCMDR
            $table->foreignId('scheduled_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->decimal('cost', 8, 4)->nullable(); // SMS cost
            $table->timestamps();

            // Indexes for common queries
            $table->index('user_id');
            $table->index('status');
            $table->index('recipient');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
