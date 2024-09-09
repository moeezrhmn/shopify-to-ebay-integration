<?php

namespace App\Models;

use App\Services\ShopifyService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    use HasFactory;

    protected $primaryKey = 'shopify_product_id';
    public $incrementing = false;
    protected $keyType = 'bigint';

    protected $fillable = [
        'shopify_product_id',
        'title',
        'body_html',
        'vendor',
        'product_type',
        'tags',
        'status',
        'variation_id',
        'price',
        'sku',
        'inventory_item_id',
        'inventory_quantity',
        'image_urls',
        'created_on_shopify',
        'updated_on_shopify',
        'published_on_shopify',
    ];

    public static function store_product($product)
    {
        $variant = $product['variants'][0];
        $shopifyService = new ShopifyService();
        $images = $shopifyService->extract_all_images($product);
        self::updateOrCreate(
            ['shopify_product_id' => $product['id']],
            [
                'title' => $product['title'],
                'body_html' => $product['body_html'],
                'vendor' => $product['vendor'],
                'product_type' => $product['product_type'],
                'tags' => $product['tags'],
                'status' => $product['status'],
                'variation_id' =>  $variant['id'],
                'price' => (float) $variant['price'],
                'sku' => $variant['sku'],
                'inventory_item_id' => $variant['inventory_item_id'],
                'inventory_quantity' => $variant['inventory_quantity'],
                'image_urls' => json_encode($images),
                'created_on_shopify' => $product['created_at'],
                'updated_on_shopify' => $product['updated_at'],
                'published_on_shopify' => $product['published_at'],
            ]
        );
    }
}
