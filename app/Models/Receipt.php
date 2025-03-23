<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    protected $fillable = [
        'receipt_url',
        'purchase_date',
        'raw_items',
        'total_amount',
        'total_discount',
        'user_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'raw_items' => 'array',
        'total_amount' => 'decimal:2',
        'total_discount' => 'decimal:2',
    ];

    /**
     * Scope a query to only include receipts for the current user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the user that owns the receipt.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'receipt_categories')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function itemCategories(): HasMany
    {
        return $this->hasMany(ReceiptItemCategory::class);
    }

    /**
     * Get all items for a specific category
     */
    public function getItemsByCategory(int $categoryId): array
    {
        $itemIndices = $this->itemCategories()
            ->where('category_id', $categoryId)
            ->pluck('item_index')
            ->toArray();
            
        $items = [];
        foreach ($itemIndices as $index) {
            if (isset($this->raw_items[$index])) {
                $items[] = $this->raw_items[$index];
            }
        }
        
        return $items;
    }
} 
