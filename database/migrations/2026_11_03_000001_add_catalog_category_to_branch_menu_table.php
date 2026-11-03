<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_menu', function (Blueprint $table) {
            $table->string('catalog_category', 40)->nullable()->after('is_available');
            $table->index(['branch_id', 'catalog_category', 'is_available'], 'branch_menu_catalog_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('branch_menu', function (Blueprint $table) {
            $table->dropIndex('branch_menu_catalog_category_index');
            $table->dropColumn('catalog_category');
        });
    }
};
