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
        Schema::create('deleted_ebay_items', function (Blueprint $table) {
            $table->id();
            $table->string('ebay_item_id');
            $table->integer('stock')->nullable();
            $table->string('sku')->nullable();
            $table->string('item_start_time')->nullable();
            $table->float('price')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_ebay_items');
    }
};
