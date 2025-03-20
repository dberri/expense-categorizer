<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_category_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('pattern');
            $table->enum('match_type', ['exact', 'contains', 'starts_with', 'ends_with'])->default('exact');
            $table->integer('priority')->default(1);
            $table->timestamps();
            
            // Add an index to speed up lookups
            $table->index('pattern');
            
            // Ensure pattern and match_type combination is unique
            $table->unique(['pattern', 'match_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_category_patterns');
    }
}; 
