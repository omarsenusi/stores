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
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('channel', ['email', 'sms', 'whatsapp'])->default('email');
            $table->enum('status', ['draft', 'queued', 'processing', 'completed', 'paused', 'failed'])->default('draft');
            $table->unsignedTinyInteger('step')->default(1);
            $table->string('subject')->nullable();
            $table->mediumText('content')->nullable();
            $table->json('custom_emails')->nullable();
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
