<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable  = [
        'promotion_url',
        'ebay_item_ids',
        'discount_percentage',
        'old_days',
        'sale_end_date',
    ];
}
