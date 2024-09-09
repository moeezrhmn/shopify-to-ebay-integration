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
        Schema::create('shopify_products', function (Blueprint $table) {
            $table->bigInteger('shopify_product_id');
            $table->string('title');
            $table->longText('body_html')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->text('tags')->nullable();
            $table->string('status')->nullable();
            $table->bigInteger('variation_id')->nullable();
            $table->float('price')->default(0);
            $table->string('sku')->nullable();
            $table->bigInteger('inventory_item_id')->nullable();
            $table->integer('inventory_quantity')->default(0);
            $table->json('image_urls')->nullable();
            $table->string('created_on_shopify')->nullable();
            $table->string('updated_on_shopify')->nullable();
            $table->string('published_on_shopify')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_products');
    }
};
