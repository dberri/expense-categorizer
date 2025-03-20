<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->integer('item_index');
            $table->string('item_name');
            $table->decimal('item_price', 10, 2);
            $table->timestamps();
            
            // One item in a receipt can only belong to one category
            $table->unique(['receipt_id', 'item_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_item_categories');
    }
}; 
