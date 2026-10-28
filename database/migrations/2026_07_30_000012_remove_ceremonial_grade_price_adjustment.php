<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('matcha_types')
            ->where('slug', 'ceremonial-grade')
            ->update(['price_adjustment' => 0]);

        DB::table('menu_variants')->where('slug', 'croissant-chocolate')->update([
            'name' => 'Coklat',
            'slug' => 'croissant-coklat',
        ]);
        DB::table('menu_variants')->where('slug', 'brownies-chocolate')->update([
            'name' => 'Coklat',
            'slug' => 'brownies-coklat',
        ]);
        DB::table('menu_variants')->where('slug', 'brownies-cheese')->update([
            'name' => 'Keju',
            'slug' => 'brownies-keju',
        ]);
    }

    public function down(): void
    {
        DB::table('matcha_types')
            ->where('slug', 'ceremonial-grade')
            ->update(['price_adjustment' => 15000]);
    }
};
