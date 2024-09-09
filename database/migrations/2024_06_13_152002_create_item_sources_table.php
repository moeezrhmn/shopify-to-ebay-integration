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
        Schema::create('item_sources', function (Blueprint $table) {
            $table->id();
            $table->string('item_sku')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->string('ebay_item_id')->nullable();
            $table->string('inventory_item_id')->nullable();
            $table->integer('last_stock')->nullable();
            $table->enum('template_applied', ['1', '0'])->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_sources');
    }
};
