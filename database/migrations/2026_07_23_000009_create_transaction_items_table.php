<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->foreignId('matcha_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topping_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->json('item_options')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'menu_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
