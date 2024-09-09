<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeletedEbayItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'ebay_item_id',
        'stock',
        'sku',
        'item_start_time',
        'price',
    ];

    public static function store_ebay_deleted($item){
        $item_id = (string) $item['itemId'];
        $itemSource = ItemSource::where('ebay_item_id', $item['itemId'])->first();
        if($itemSource) $itemSource->last_stock = 0;
        $shopify_product_id = (string) $itemSource->shopify_product_id;
        $itemSource->save();
        $shopifyProduct = ShopifyProduct::find($shopify_product_id);

        self::create([
            'ebay_item_id' => $item_id,
            'stock' => $shopifyProduct->inventory_quantity,
            'sku' => $shopifyProduct->sku,
            'item_start_time' => $item['listingInfo']['startTime'],
            'price' => $item['sellingStatus']['currentPrice'],
        ]);
    }
}
