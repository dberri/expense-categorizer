<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCategoryPattern extends Model
{
    protected $fillable = [
        'category_id',
        'pattern',
        'match_type',
        'priority',
    ];

    /**
     * Get the category that owns the pattern.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Check if an item name matches this pattern based on match_type
     */
    public function matches(string $itemName): bool
    {
        $itemName = strtolower($itemName);
        $pattern = strtolower($this->pattern);

        return match ($this->match_type) {
            'exact' => $itemName === $pattern,
            'contains' => str_contains($itemName, $pattern),
            'starts_with' => str_starts_with($itemName, $pattern),
            'ends_with' => str_ends_with($itemName, $pattern),
            default => false,
        };
    }

    /**
     * Find a matching pattern for an item name
     */
    public static function findMatchingPattern(string $itemName): ?self
    {
        // First try exact match with higher priority
        $exactMatch = self::where('match_type', 'exact')
            ->orderBy('priority', 'desc')
            ->get()
            ->first(fn($pattern) => $pattern->matches($itemName));

        if ($exactMatch) {
            return $exactMatch;
        }

        // Then try contains/starts_with/ends_with matches with higher priority
        return self::where('match_type', '!=', 'exact')
            ->orderBy('priority', 'desc')
            ->get()
            ->first(fn($pattern) => $pattern->matches($itemName));
    }
} 
