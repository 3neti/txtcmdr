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
            $table->text('message');
            $table->json('recipients')->nullable(); // Array of mobile numbers
            $table->json('group_ids')->nullable(); // Array of group IDs
            $table->string('sender_id');
            $table->timestamp('scheduled_at');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'cancelled'])
                  ->default('pending');
            $table->timestamps();
            
            $table->index('scheduled_at');
            $table->index('status');
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
