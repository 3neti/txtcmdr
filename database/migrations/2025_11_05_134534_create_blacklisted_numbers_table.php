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
        Schema::create('blacklisted_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('mobile')->unique();
            $table->enum('reason', ['opt-out', 'complaint', 'invalid', 'other']);
            $table->text('notes')->nullable();
            $table->string('added_by')->default('system');
            $table->timestamps();

            $table->index('mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklisted_numbers');
    }
};
