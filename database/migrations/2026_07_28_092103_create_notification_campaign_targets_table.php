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
        Schema::create('notification_campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $table->foreignId('scraped_store_id')->nullable()->constrained('scraped_stores')->nullOnDelete();
            $table->string('email');
            $table->string('store_name')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_campaign_id', 'status'], 'nc_targets_campaign_status_idx');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_campaign_targets');
    }
};
