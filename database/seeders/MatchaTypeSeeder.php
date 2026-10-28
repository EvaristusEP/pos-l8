<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MatchaTypeSeeder extends Seeder
{
   public function run(): void
    {
        DB::table('matcha_types')->insert([

            [
                'name' => 'Regular Grade',
                'slug' => 'regular-grade',
                'price_adjustment' => 0,
                'is_active' => true,
            ],

            [
                'name' => 'Ceremonial Grade',
                'slug' => 'ceremonial-grade',
                'price_adjustment' => 0,
                'is_active' => true,
            ],

            [
                'name' => 'Kyoto',
                'slug' => 'kyoto',
                'price_adjustment' => 10000,
                'is_active' => true,
            ],

            [
                'name' => 'Osaka',
                'slug' => 'osaka',
                'price_adjustment' => 12000,
                'is_active' => true,
            ],

            [
                'name' => 'Ginza',
                'slug' => 'ginza',
                'price_adjustment' => 15000,
                'is_active' => true,
            ],

            [
                'name' => 'Uji',
                'slug' => 'uji',
                'price_adjustment' => 18000,
                'is_active' => true,
            ],

        ]);
    }
}
