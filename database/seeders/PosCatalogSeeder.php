<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MatchaType;
use App\Models\Menu;
use App\Models\MenuVariant;
use Illuminate\Database\Seeder;

class PosCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $matchaTypes = [
            ['name' => 'Regular', 'slug' => 'regular-grade', 'price_adjustment' => 0],
            // Harga Ceremonial mengikuti origin yang dipilih, bukan biaya grade tetap.
            ['name' => 'Ceremonial', 'slug' => 'ceremonial-grade', 'price_adjustment' => 0],
            ['name' => 'Kyoto', 'slug' => 'kyoto', 'price_adjustment' => 15000],
            ['name' => 'Osaka', 'slug' => 'osaka', 'price_adjustment' => 20000],
            ['name' => 'Ginza', 'slug' => 'ginza', 'price_adjustment' => 25000],
            ['name' => 'Uji', 'slug' => 'uji', 'price_adjustment' => 30000],
        ];

        foreach ($matchaTypes as $type) {
            MatchaType::updateOrCreate(['slug' => $type['slug']], [...$type, 'is_active' => true]);
        }

        $pastry = Category::firstOrCreate(
            ['slug' => 'pastry'],
            ['name' => 'Pastry', 'sort_order' => 3, 'is_active' => true],
        );
        $pastry->update(['name' => 'Pastry', 'sort_order' => 3, 'is_active' => true]);

        Menu::query()->where('category_id', $pastry->id)->update(['is_active' => false]);

        $menus = [
            ['name' => 'Croissant', 'slug' => 'croissant', 'description' => 'Croissant hangat dan flaky.', 'price' => 18000, 'variants' => [['Original', 0], ['Beef', 6000], ['Cheese', 4000], ['Coklat', 3000]]],
            ['name' => 'Cheese Cake', 'slug' => 'cheesecake', 'description' => 'Cheese cake lembut pilihan kasir.', 'price' => 28000, 'variants' => [['Original', 0], ['Matcha', 4000]]],
            ['name' => 'Brownies', 'slug' => 'brownies', 'description' => 'Brownies fudgy dengan pilihan rasa.', 'price' => 20000, 'variants' => [['Original', 0], ['Coklat', 2000], ['Keju', 3000], ['Almond', 4000]]],
            ['name' => 'Bagel', 'slug' => 'bagel', 'description' => 'Bagel panggang yang lembut.', 'price' => 17000, 'variants' => [['Original', 0], ['Matcha', 3000]]],
            ['name' => 'Cookies', 'slug' => 'cookies', 'description' => 'Cookies renyah untuk teman minum.', 'price' => 15000, 'variants' => [['Butter', 0], ['Matcha', 3000], ['Chocolate Chip', 3000]]],
        ];

        foreach ($menus as $menuData) {
            $variants = $menuData['variants'];
            unset($menuData['variants']);
            $menu = Menu::updateOrCreate(['slug' => $menuData['slug']], [...$menuData, 'category_id' => $pastry->id, 'is_active' => true]);

            // Menjaga pilihan pada kasir hanya menampilkan varian yang didefinisikan di atas.
            MenuVariant::query()->where('menu_id', $menu->id)->update(['is_active' => false]);

            foreach ($variants as [$name, $priceAdjustment]) {
                MenuVariant::updateOrCreate(
                    ['slug' => $menu->slug . '-' . \Illuminate\Support\Str::slug($name)],
                    [
                        'menu_id' => $menu->id,
                        'name' => $name,
                        'price_adjustment' => $priceAdjustment,
                        'is_active' => true
                    ]
                );
            }
        }
    }
}
