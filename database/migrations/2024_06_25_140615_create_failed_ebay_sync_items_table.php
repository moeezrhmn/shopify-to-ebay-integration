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
        Schema::create('failed_ebay_sync_items', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_product_id');
            $table->json('errors');
            $table->string('shopify_title')->nullable();
            $table->longText('shopify_body_html')->nullable();
            $table->string('ebay_title')->nullable();
            $table->longText('ebay_body_html')->nullable();
            $table->integer('tried')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_ebay_sync_items');
    }
};
