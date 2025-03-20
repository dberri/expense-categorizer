<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // We can't drop the unique constraint as it's used in a foreign key relationship
            // Instead, let's clear any existing item category mappings for receipts that have duplicates
            
            // Find receipts with duplicate item indices
            $receiptsWithDuplicates = DB::select("
                SELECT receipt_id
                FROM receipt_item_categories
                GROUP BY receipt_id, item_index
                HAVING COUNT(*) > 1
            ");
            
            // Get unique receipt IDs
            $receiptIds = array_unique(array_map(function($row) {
                return $row->receipt_id;
            }, $receiptsWithDuplicates));
            
            // Log what we're doing
            Log::info("Fixing duplicate entries in receipt_item_categories", [
                'affected_receipts' => count($receiptIds)
            ]);
            
            // For each affected receipt, delete all categories and re-categorize
            foreach ($receiptIds as $receiptId) {
                // Delete all item categories for this receipt
                DB::table('receipt_item_categories')
                    ->where('receipt_id', $receiptId)
                    ->delete();
                
                Log::info("Deleted existing categories for receipt", ['receipt_id' => $receiptId]);
            }
            
            Log::info("Migration completed successfully");
        } catch (\Exception $e) {
            Log::error("Error during migration", [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to do anything in down() as we're just fixing data
    }
};
