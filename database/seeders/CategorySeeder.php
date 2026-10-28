<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->insert([

            [
                'name' => 'Matcha',
                'slug' => 'matcha',
                'sort_order' => 1,
                'is_active' => true,
            ],

            [
                'name' => 'Non Matcha',
                'slug' => 'non-matcha',
                'sort_order' => 2,
                'is_active' => true,
            ],

            [
                'name' => 'Pastry',
                'slug' => 'pastry',
                'sort_order' => 3,
                'is_active' => true,
            ],

        ]);
    }
}