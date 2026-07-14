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
        Schema::table('scraped_stores', function (Blueprint $table) {
            $table->string('store_name')->nullable()->after('domain');
            $table->text('store_logo')->nullable()->after('store_name');
            $table->text('store_description')->nullable()->after('store_logo');
            $table->json('contacts')->nullable()->after('store_description');
            $table->json('features')->nullable()->after('contacts');
            $table->json('full_settings')->nullable()->after('features');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scraped_stores', function (Blueprint $table) {
            $table->dropColumn([
                'store_name',
                'store_logo',
                'store_description',
                'contacts',
                'features',
                'full_settings'
            ]);
        });
    }
};
