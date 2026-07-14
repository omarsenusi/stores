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
        Schema::create('scraped_stores', function (Blueprint $table) {
            $table->id();
            $table->string('store_id')->unique()->index();
            $table->string('domain')->nullable();
            $table->string('product_name')->nullable();
            $table->text('product_description')->nullable();
            $table->text('product_url')->nullable();
            $table->text('error_log')->nullable();
            $table->boolean('is_found')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraped_stores');
    }
};
