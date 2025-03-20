<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Services\ReceiptParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptParserService $receiptParser)
    {
    }

    public function index()
    {
        // Get monthly breakdown by category
        $monthlyBreakdown = Receipt::select(
            DB::raw('YEAR(purchase_date) as year'),
            DB::raw('MONTH(purchase_date) as month'),
            'categories.name as category',
            DB::raw('SUM(receipt_categories.amount) as total_amount')
        )
            ->join('receipt_categories', 'receipts.id', '=', 'receipt_categories.receipt_id')
            ->join('categories', 'receipt_categories.category_id', '=', 'categories.id')
            ->groupBy('year', 'month', 'categories.name')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->groupBy(['year', 'month'])
            ->map(function ($months) {
                return $months->map(function ($categories) {
                    return $categories->sortByDesc('total_amount')->values();
                });
            });

        // Get monthly totals
        $monthlyTotals = Receipt::select(
            DB::raw('YEAR(purchase_date) as year'),
            DB::raw('MONTH(purchase_date) as month'),
            DB::raw('SUM(total_amount) as total_amount')
        )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->groupBy(['year', 'month']);

        return view('receipts.index', compact('monthlyBreakdown', 'monthlyTotals'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receipt_url' => 'required|url',
            'purchase_date' => 'nullable|date',
            'overwrite' => 'boolean',
        ]);

        try {
            // Check if receipt already exists
            $existingReceipt = Receipt::where('receipt_url', $validated['receipt_url'])->first();
            
            if ($existingReceipt) {
                if ($request->input('overwrite', false)) {
                    // Delete the existing receipt and its related data
                    $this->deleteReceipt($existingReceipt);
                    
                    // Process the new receipt
                    $receipt = $this->receiptParser->parseReceipt(
                        $validated['receipt_url'],
                        $validated['purchase_date'] ?? null
                    );

                    return redirect()->route('receipts.index')
                        ->with('success', 'Receipt updated successfully!');
                } else {
                    // Return to the form with a warning and the existing receipt ID
                    return back()
                        ->withInput()
                        ->with('warning', 'A receipt with this URL already exists.')
                        ->with('existing_receipt_id', $existingReceipt->id);
                }
            }

            // Process new receipt
            $receipt = $this->receiptParser->parseReceipt(
                $validated['receipt_url'],
                $validated['purchase_date'] ?? null
            );

            return redirect()->route('receipts.index')
                ->with('success', 'Receipt processed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process receipt: ' . $e->getMessage());
        }
    }

    /**
     * Delete a receipt and all its related data
     */
    private function deleteReceipt(Receipt $receipt): void
    {
        // Delete all item category mappings
        \App\Models\ReceiptItemCategory::where('receipt_id', $receipt->id)->delete();
        
        // Detach all categories
        $receipt->categories()->detach();
        
        // Delete the receipt
        $receipt->delete();
    }

    public function show(Receipt $receipt)
    {
        // Get all categories for this receipt
        $receiptCategories = $receipt->categories()->get();
        
        // Get item categorizations
        $itemCategories = $receipt->itemCategories()
            ->with('category')
            ->orderBy('category_id')
            ->get()
            ->groupBy('category_id');
        
        // Group by category for display
        $categorizedItems = [];
        foreach ($receiptCategories as $category) {
            $items = $itemCategories[$category->id] ?? collect();
            
            $categorizedItems[$category->name] = [
                'total' => $category->pivot->amount,
                'items' => $items->map(function ($item) use ($receipt) {
                    $rawItem = $receipt->raw_items[$item->item_index] ?? null;
                    
                    // Skip bundled items in the mapped items array (they'll still be in the database)
                    if ($rawItem && isset($rawItem['bundled_into'])) {
                        // This is a bundled item, mark it for filtering
                        $rawItem['_filtered'] = true;
                    }
                    
                    return [
                        'receipt_item_id' => $item->id,
                        'name' => $item->item_name,
                        'price' => $item->item_price,
                        'raw_data' => $rawItem ? array_merge(['index' => $item->item_index], $rawItem) : null,
                        '_filtered' => $rawItem['_filtered'] ?? false
                    ];
                })
                // Filter out items marked for filtering (bundled items)
                ->filter(function ($item) {
                    return !$item['_filtered'];
                })
                ->values()
                ->all()
            ];
        }
        
        return view('receipts.show', [
            'receipt' => $receipt,
            'categorizedItems' => $categorizedItems
        ]);
    }

    /**
     * Strip counter from item name (e.g., "Item (2x)" -> "Item")
     */
    private function stripCounterFromName(string $name): string
    {
        return preg_replace('/\s*\(\d+x\)$/', '', $name);
    }

    /**
     * Change the category of an item
     */
    public function changeItemCategory(Request $request)
    {
        $validated = $request->validate([
            'receipt_item_id' => 'required|exists:receipt_item_categories,id',
            'new_category_id' => 'required|exists:categories,id',
            'learn_pattern' => 'boolean',
            'pattern_match_type' => 'nullable|in:exact,contains,starts_with,ends_with',
        ]);

        $receiptItem = \App\Models\ReceiptItemCategory::findOrFail($validated['receipt_item_id']);
        $receipt = $receiptItem->receipt;
        $newCategory = \App\Models\Category::findOrFail($validated['new_category_id']);
        $oldCategoryId = $receiptItem->category_id;
        $oldCategory = \App\Models\Category::find($oldCategoryId);
        
        // Update the item's category
        $receiptItem->category_id = $newCategory->id;
        $receiptItem->save();
        
        // If we should learn this pattern for future categorization
        if ($request->input('learn_pattern', true)) {
            $matchType = $request->input('pattern_match_type', 'exact');
            
            // Strip counter from item name before adding pattern
            $patternName = $this->stripCounterFromName($receiptItem->item_name);
            
            // Add the pattern to the new category
            $newCategory->addPattern($patternName, $matchType);
            
            // Delete any matching patterns in other categories to avoid conflicts
            \App\Models\ItemCategoryPattern::where('pattern', $patternName)
                ->where('category_id', '!=', $newCategory->id)
                ->delete();
        }
        
        // Recalculate totals for the affected categories
        $this->recalculateCategoryTotals($receipt, [$oldCategoryId, $newCategory->id]);
        
        return redirect()->route('receipts.show', $receipt->id)
            ->with('success', "Item '{$receiptItem->item_name}' moved from '{$oldCategory->name}' to '{$newCategory->name}'");
    }
    
    /**
     * Recalculate category totals after category changes
     */
    private function recalculateCategoryTotals(Receipt $receipt, array $categoryIds)
    {
        foreach ($categoryIds as $categoryId) {
            // Skip invalid category IDs
            if (!$categoryId) continue;
            
            // Get all items in this category
            $categoryItems = $receipt->itemCategories()
                ->where('category_id', $categoryId)
                ->get();
            
            $total = 0;
            
            // Only count non-bundled items in the total
            foreach ($categoryItems as $item) {
                $rawItem = $receipt->raw_items[$item->item_index] ?? null;
                
                // Skip items that have been bundled into another item
                if ($rawItem && isset($rawItem['bundled_into'])) {
                    continue;
                }
                
                $total += $item->item_price;
            }
            
            // If there are items in this category
            if ($total > 0) {
                // Update or create the pivot record
                $receipt->categories()->syncWithoutDetaching([
                    $categoryId => ['amount' => $total]
                ]);
            } else {
                // Remove the category if it has no items
                $receipt->categories()->detach($categoryId);
            }
        }
    }

    /**
     * Bundle similar items in a receipt
     */
    public function bundleItems(Request $request)
    {
        $validated = $request->validate([
            'receipt_id' => 'required|exists:receipts,id',
            'item_indices' => 'required|array|min:2',
            'item_indices.*' => 'integer|min:0',
            'new_name' => 'nullable|string|max:255',
        ]);

        $receipt = Receipt::findOrFail($validated['receipt_id']);
        $itemIndices = $validated['item_indices'];
        $newName = $validated['new_name'] ?? null;
        
        // Get the original raw items
        $rawItems = $receipt->raw_items;
        
        // Calculate the combined values
        $totalQuantity = 0;
        $totalPrice = 0;
        $combinedName = '';
        $categoryId = null;
        $itemNames = [];
        
        // Keep track of affected categories for recalculation later
        $affectedCategories = [];
        
        // Keep track of item categories to remove
        $itemsToRemove = [];
        
        // First pass: collect data and validate all indices exist
        foreach ($itemIndices as $index) {
            if (!isset($rawItems[$index])) {
                return back()->with('error', "Item with index {$index} not found in receipt");
            }
            
            // Skip items that are already bundled
            if (isset($rawItems[$index]['bundled_into'])) {
                return back()->with('error', "One or more items are already part of a bundle");
            }
            
            $item = $rawItems[$index];
            $totalQuantity += $item['quantity'] ?? 1;
            $totalPrice += $item['total_price'];
            $itemNames[] = $item['name'];
            
            // Determine category for this item
            $itemCategory = \App\Models\ReceiptItemCategory::where('receipt_id', $receipt->id)
                ->where('item_index', $index)
                ->first();
            
            if ($itemCategory) {
                if ($categoryId === null) {
                    $categoryId = $itemCategory->category_id;
                } elseif ($categoryId !== $itemCategory->category_id) {
                    return back()->with('error', "Cannot bundle items from different categories");
                }
                
                $affectedCategories[$categoryId] = true;
                $itemsToRemove[] = $itemCategory->id;
            }
        }
        
        // Generate a combined name if not provided
        if (!$newName) {
            // Use the first item's name with a count suffix
            $newName = $itemNames[0] . " ({$totalQuantity}x)";
        }
        
        // Create a new bundled item
        $newItemIndex = count($rawItems);
        $unitPrice = $totalQuantity > 0 ? $totalPrice / $totalQuantity : 0;
        
        $bundledItem = [
            'name' => $newName,
            'quantity' => $totalQuantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'bundled_from' => $itemIndices, // Store original indices
        ];
        
        // Add the new bundled item
        $rawItems[] = $bundledItem;
        
        // Second pass: mark original items as bundled, but don't remove them
        // Just set quantity and price to 0 so they don't affect totals
        foreach ($itemIndices as $index) {
            // Mark as bundled but preserve the original data
            $rawItems[$index]['bundled_into'] = $newItemIndex;
            $rawItems[$index]['quantity'] = 0;
            $rawItems[$index]['total_price'] = 0;
        }
        
        // Update the receipt with the modified items
        $receipt->raw_items = $rawItems;
        $receipt->save();
        
        // Add the new bundled item to the category
        if ($categoryId !== null) {
            \App\Models\ReceiptItemCategory::create([
                'receipt_id' => $receipt->id,
                'category_id' => $categoryId,
                'item_index' => $newItemIndex,
                'item_name' => $newName,
                'item_price' => $totalPrice,
            ]);
            
            // Recalculate category totals
            $this->recalculateCategoryTotals($receipt, array_keys($affectedCategories));
        }
        
        return redirect()->route('receipts.show', $receipt->id)
            ->with('success', "Successfully bundled " . count($itemIndices) . " items");
    }

    /**
     * Remove the specified receipt from storage.
     */
    public function destroy(Receipt $receipt)
    {
        try {
            // Begin transaction to ensure all related data is deleted properly
            DB::beginTransaction();
            
            // Get the receipt ID for logging
            $receiptId = $receipt->id;
            $purchaseDate = $receipt->purchase_date->format('Y-m-d');
            
            // Delete related data first
            // This will cascade to receipt_item_categories due to foreign key constraints
            $receipt->categories()->detach();
            $receipt->itemCategories()->delete();
            
            // Delete the receipt itself
            $receipt->delete();
            
            // Commit transaction
            DB::commit();
            
            return redirect()->route('receipts.index')
                ->with('success', "Receipt from {$purchaseDate} has been deleted successfully");
                
        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();
            
            return redirect()->route('receipts.index')
                ->with('error', 'Failed to delete receipt: ' . $e->getMessage());
        }
    }
} 
