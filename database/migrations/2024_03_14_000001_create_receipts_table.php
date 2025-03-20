<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->date('purchase_date');
            $table->string('receipt_url');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('total_discount', 10, 2)->default(0);
            $table->json('raw_items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
}; 
