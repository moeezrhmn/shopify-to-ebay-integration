<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedEbaySyncItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopify_product_id',
        'errors',
        'shopify_title',
        'shopify_body_html',
        'ebay_title',
        'ebay_body_html',
        'tried',
    ];

    public static function is_exists($shopify_product_id, $tried = null){
        return self::where('shopify_product_id', strval($shopify_product_id))
        ->when(!is_null($tried) , function ($q) use ($tried){
            return $q->where('tried', $tried);
        })->first();
    }
}
