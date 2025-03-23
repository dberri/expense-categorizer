<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'user_id',
    ];

    /**
     * Scope a query to only include categories for the current user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the user that owns the category.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receipts(): BelongsToMany
    {
        return $this->belongsToMany(Receipt::class, 'receipt_categories')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(ReceiptItemCategory::class);
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(ItemCategoryPattern::class);
    }

    /**
     * Get all items from all receipts in this category
     */
    public function getAllItems()
    {
        return $this->receiptItems()
            ->with('receipt')
            ->get()
            ->map(function ($item) {
                $receipt = $item->receipt;
                $rawItem = $receipt->raw_items[$item->item_index] ?? null;
                
                return [
                    'receipt_id' => $receipt->id,
                    'purchase_date' => $receipt->purchase_date,
                    'item_index' => $item->item_index,
                    'item_name' => $item->item_name,
                    'item_price' => $item->item_price,
                    'raw_item' => $rawItem
                ];
            });
    }

    /**
     * Add a new pattern for this category based on an item name
     */
    public function addPattern(string $itemName, string $matchType = 'exact'): ItemCategoryPattern
    {
        return $this->patterns()->create([
            'pattern' => $itemName,
            'match_type' => $matchType,
            'priority' => 1,
        ]);
    }
} 
