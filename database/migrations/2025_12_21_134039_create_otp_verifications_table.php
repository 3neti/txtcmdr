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
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('otp_app_id')->nullable()->index();

            $table->string('mobile_e164')->index();
            $table->string('purpose')->default('login')->index();

            $table->string('code_hash', 64);
            $table->timestamp('expires_at')->index();

            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);

            $table->unsignedSmallInteger('send_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();

            $table->timestamp('verified_at')->nullable();

            $table->ipAddress('request_ip')->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->string('external_ref')->nullable()->index();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['mobile_e164', 'purpose', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
