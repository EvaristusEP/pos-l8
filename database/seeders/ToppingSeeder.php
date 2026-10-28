<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ToppingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('toppings')->insert([

            [
                'name' => 'Cheese Foam',
                'slug' => 'cheese-foam',
                'price' => 7000,
                'is_active' => true,
            ],

            [
                'name' => 'Vanilla Ice Cream',
                'slug' => 'vanilla-ice-cream',
                'price' => 8000,
                'is_active' => true,
            ],

            [
                'name' => 'Extra Matcha Shot',
                'slug' => 'extra-matcha-shot',
                'price' => 10000,
                'is_active' => true,
            ],

            [
                'name' => 'Boba',
                'slug' => 'boba',
                'price' => 5000,
                'is_active' => true,
            ],

        ]);
    }
}
