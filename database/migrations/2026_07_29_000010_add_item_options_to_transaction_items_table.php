<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transaction_items', 'item_options')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->json('item_options')->nullable()->after('line_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaction_items', 'item_options')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->dropColumn('item_options');
            });
        }
    }
};