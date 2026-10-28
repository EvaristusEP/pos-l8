<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('sizes')->insert([

            [
                'name' => 'Regular',
                'slug' => 'regular',
                'price_adjustment' => 0,
                'is_active' => true,
            ],

            [
                'name' => 'Large',
                'slug' => 'large',
                'price_adjustment' => 5000,
                'is_active' => true,
            ],

        ]);
    }
}