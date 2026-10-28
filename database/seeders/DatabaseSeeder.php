<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            MatchaTypeSeeder::class,
            SizeSeeder::class,
            ToppingSeeder::class,
            MenuSeeder::class,
            PosCatalogSeeder::class,
        ]);
    }
}