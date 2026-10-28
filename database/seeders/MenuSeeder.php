<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('menus')->insert([

            [
                'category_id' => 1,
                'name' => 'Matcha Latte',
                'slug' => 'matcha-latte',
                'description' => 'Premium Matcha Latte',
                'price' => 28000,
                'is_active' => true,
            ],

            [
                'category_id' => 1,
                'name' => 'Matcha Pistachio',
                'slug' => 'matcha-pistachio',
                'description' => 'Premium Matcha Pistachio',
                'price' => 32000,
                'is_active' => true,
            ],

            [
                'category_id' => 1,
                'name' => 'Matcha Strawberry',
                'slug' => 'matcha-strawberry',
                'description' => 'Matcha Strawberry',
                'price' => 30000,
                'is_active' => true,
            ],

            [
                'category_id' => 1,
                'name' => 'Matcha Mango',
                'slug' => 'matcha-mango',
                'description' => 'Matcha Mango',
                'price' => 30000,
                'is_active' => true,
            ],

            [
                'category_id' => 1,
                'name' => 'Matcha Coconut',
                'slug' => 'matcha-coconut',
                'description' => 'Matcha Coconut',
                'price' => 30000,
                'is_active' => true,
            ],

            [
                'category_id' => 2,
                'name' => 'Chocolate',
                'slug' => 'chocolate',
                'description' => 'Chocolate Drink',
                'price' => 25000,
                'is_active' => true,
            ],

            [
                'category_id' => 2,
                'name' => 'Coffee Latte',
                'slug' => 'coffee-latte',
                'description' => 'Coffee Latte',
                'price' => 26000,
                'is_active' => true,
            ],

            [
                'category_id' => 2,
                'name' => 'Caramel Latte',
                'slug' => 'caramel-latte',
                'description' => 'Caramel Latte',
                'price' => 26000,
                'is_active' => true,
            ],

            [
                'category_id' => 2,
                'name' => 'Milk Tea',
                'slug' => 'milk-tea',
                'description' => 'Milk Tea',
                'price' => 24000,
                'is_active' => true,
            ],

            [
                'category_id' => 2,
                'name' => 'Red Velvet',
                'slug' => 'red-velvet',
                'description' => 'Red Velvet',
                'price' => 25000,
                'is_active' => true,
            ],

            [
                'category_id' => 3,
                'name' => 'Butter Croissant',
                'slug' => 'butter-croissant',
                'description' => 'Freshly baked croissant',
                'price' => 18000,
                'is_active' => true,
            ],

            [
                'category_id' => 3,
                'name' => 'Matcha Roll Cake',
                'slug' => 'matcha-roll-cake',
                'description' => 'Soft pastry with matcha cream',
                'price' => 22000,
                'is_active' => true,
            ],

        ]);
    }
}

