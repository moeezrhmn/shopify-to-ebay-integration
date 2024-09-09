<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemSpecific extends Model
{
    use HasFactory;

    protected $fillable = [
        'aspect_name',
        'aspect_values',
    ];
}
