<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptItemCategory extends Model
{
    protected $fillable = [
        'receipt_id',
        'category_id',
        'item_index',
        'item_name',
        'item_price',
    ];

    protected $casts = [
        'item_price' => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
} 
