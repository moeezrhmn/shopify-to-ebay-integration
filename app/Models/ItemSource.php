<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_sku',
        'shopify_product_id',
        'ebay_item_id',
        'inventory_item_id',
        'last_stock',
        'template_applied'
    ];

    public static function store_item($product, $ebay_item_id){
        return self::create([
            'item_sku' => $product['variants'][0]['sku'] ?? '',
            'shopify_product_id' => (string) $product['id'],
            'ebay_item_id' => (string) $ebay_item_id,
            'inventory_item_id' => (string) $product['variants'][0]['inventory_item_id'],
            'last_stock' => $product['variants'][0]['inventory_quantity'],
        ]);
    }
    public static function is_exists($shopify_product_id = null, $ebay_item_id = null){
       return self::when($shopify_product_id, function ($q) use ($shopify_product_id) {
            return $q->where('shopify_product_id', strval($shopify_product_id));
        })
        ->when($ebay_item_id, function ($q) use ($ebay_item_id){
            return $q->where('ebay_item_id', strval($ebay_item_id));
        })->first();
    } 
}
