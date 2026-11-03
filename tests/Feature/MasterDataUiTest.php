<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Ingredient;
use App\Models\RecipeItem;
use App\Models\BranchStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_forms_open_in_shared_dialogs_with_search_and_empty_states(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $pages = [
            '/master-menu' => ['menu-create-dialog', 'menu-list'],
            '/inventory/materials' => ['ingredient-create-dialog', 'materials-master'],
            '/tags' => ['tag-create-dialog', 'tag-list'],
            '/addons' => ['addon-create-dialog', 'addon-list'],
            '/recipes' => ['recipe-create-dialog', 'recipe-list'],
            '/tables' => ['table-create-dialog', 'table-list'],
            '/customers' => ['customer-create-dialog', 'customer-list'],
            '/suppliers' => ['supplier-create-dialog', 'supplier-list'],
            '/branches' => ['branch-create-dialog', 'branch-list'],
        ];

        foreach ($pages as $url => [$dialogId, $listId]) {
            $response = $this->actingAs($owner)->get($url)->assertOk();
            $response->assertSee('<dialog id="'.$dialogId.'"', false)
                ->assertSee('data-filter-search="'.$listId.'"', false);

            if ($url !== '/master-menu') {
                $response->assertSee('style="box-sizing: border-box; width: min(40rem, calc(100vw - 2rem)); max-width: calc(100vw - 2rem); margin: auto;"', false);
            }

            if (in_array($url, ['/tables', '/customers'], true)) {
                $response->assertSee('<dialog id="'.$dialogId.'" class="crud-dialog"', false);
            }
            if ($url === '/master-menu') {
                $response->assertSee('<dialog id="'.$dialogId.'" class="crud-dialog crud-dialog-wide"', false)
                    ->assertSee('style="box-sizing: border-box; width: min(56rem, calc(100vw - 2rem)); max-width: calc(100vw - 2rem); margin: auto;"', false);
                $response->assertSee('master-menu-filter-bar', false)
                    ->assertSee('name="category_id"', false)
                    ->assertSee('name="price"', false)
                    ->assertDontSee('Tags produk')
                    ->assertSee('Harga dan ketersediaan', false)
                    ->assertSee('Harga standar', false)
                    ->assertSee('Lengkapi resep serta resep varian', false)
                    ->assertDontSee('name="slug"', false)
                    ->assertSee('master-menu-form-actions', false)
                    ->assertDontSee('class="crud-dialog-content min-w-0 p-4 sm:p-5" style=', false);
                $styles = file_get_contents(resource_path('css/app.css'));
                $this->assertStringContainsString('dialog.crud-dialog-wide[open] { display: flex; flex-direction: column; overflow: hidden; }', $styles);
                $this->assertStringContainsString('dialog.crud-dialog-wide .crud-dialog-content { min-height: 0; max-height: none; flex: 1 1 auto; overflow-x: hidden; overflow-y: auto;', $styles);
                $this->assertStringContainsString('.master-menu-form-actions { position: sticky;', $styles);
                $this->assertStringContainsString('.master-menu-product-info { display: grid;', $styles);
                $this->assertStringContainsString('@media (min-width: 640px) { .master-menu-product-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } }', $styles);
                $this->assertStringContainsString('.master-menu-product-fields { display: grid; min-width: 0; grid-template-columns: minmax(0, 1fr);', $styles);
                $this->assertStringContainsString('.master-menu-branch-row { display: grid;', $styles);
                $form = file_get_contents(resource_path('views/master-menu/_form.blade.php'));
                $this->assertStringContainsString('<div class="master-menu-branch-row">', $form);
                $this->assertStringContainsString('class="master-menu-branch-availability"', $form);
            }
        }
    }

    public function test_master_menu_filters_use_category_and_status_data_and_show_active_branch_price(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $branch = Branch::create(['code' => 'JOOAL-003', 'name' => 'JOOAL Coffee', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $category = Category::create(['name' => 'Coffee', 'slug' => 'ui-coffee', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'UI Latte', 'slug' => 'ui-latte', 'price' => 25000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => 19500, 'is_available' => false, 'catalog_category' => 'kopi']);

        $response = $this->actingAs($owner)->withSession(['active_branch_id' => $branch->id])
            ->get('/master-menu')->assertOk()
            ->assertSee('data-filter-select="menu-list" data-filter-key="category"', false)
            ->assertSee('data-filter-key="status"', false)
            ->assertSee('data-category="kopi" data-status="active"', false)
            ->assertSee('Kopi')
            ->assertSee('JOOAL Coffee')
            ->assertSee('Harga cabang: Rp 19.500')
            ->assertSee('Tidak tersedia di cabang');

        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'class="master-menu-product-info"'), 'Create and edit dialogs both render the shared product form.');
        $this->assertSame(2, substr_count($html, 'class="master-menu-form-actions"'), 'Both dialogs have a single shared footer.');
        preg_match('/<label class="master-menu-category-field[^\"]*">([\s\S]*?)<\/label>/', $html, $categoryField);
        $this->assertNotEmpty($categoryField);
        $this->assertStringNotContainsString('JOOAL Coffee', $categoryField[1], 'The active branch name is not repeated under product category.');
        $headingAt = strpos($html, 'master-menu-product-heading');
        $categoryAt = strpos($html, 'master-menu-category-field');
        $fieldsAt = strpos($html, 'master-menu-product-fields');
        $descriptionAt = strpos($html, 'master-menu-description-field');
        $this->assertTrue($headingAt < $categoryAt && $categoryAt < $fieldsAt && $fieldsAt < $descriptionAt);
        $this->assertStringContainsString('Buat Produk', $html);
        $this->assertStringContainsString('Simpan perubahan', $html);
    }

    public function test_master_menu_shows_branch_recipe_capacity_or_incomplete_status(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $branch = Branch::create(['code' => 'UI-STOCK', 'name' => 'UI Stock Branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $category = Category::create(['name' => 'Coffee', 'slug' => 'ui-stock-coffee', 'sort_order' => 1, 'is_active' => true]);
        $ready = Menu::create(['category_id' => $category->id, 'name' => 'Ready Latte', 'slug' => 'ready-latte', 'price' => 25000, 'is_active' => true]);
        $incomplete = Menu::create(['category_id' => $category->id, 'name' => 'Incomplete Mocha', 'slug' => 'incomplete-mocha', 'price' => 26000, 'is_active' => true]);
        $branch->menus()->attach([$ready->id => ['price' => 25000, 'is_available' => true], $incomplete->id => ['price' => 26000, 'is_available' => true]]);
        $beans = Ingredient::create(['name' => 'Coffee beans', 'slug' => 'ui-stock-beans', 'unit' => 'g', 'minimum_stock' => 0, 'is_active' => true]);
        $milk = Ingredient::create(['name' => 'Milk', 'slug' => 'ui-stock-milk', 'unit' => 'ml', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $ready->id, 'ingredient_id' => $beans->id, 'quantity' => '18.000', 'unit' => 'g', 'is_active' => true]);
        RecipeItem::create(['menu_id' => $ready->id, 'ingredient_id' => $milk->id, 'quantity' => '180.000', 'unit' => 'ml', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $beans->id, 'quantity' => '180.000']);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $milk->id, 'quantity' => '900.000']);

        $this->actingAs($owner)->withSession(['active_branch_id' => $branch->id])->get('/master-menu')->assertOk()
            ->assertSee('Stok terbatas — 5 porsi dapat dibuat')
            ->assertSee('periksa Milk')
            ->assertSee('Resep belum lengkap — kapasitas belum dapat dihitung');
    }

    public function test_system_settings_exposes_browser_theme_preferences_and_palette_tokens(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/settings/system')->assertOk()
            ->assertSee('data-theme-select', false)
            ->assertDontSee('value="auto"', false)
            ->assertSee('value="light"', false)
            ->assertSee('value="dark-teal"', false)
            ->assertSee('value="slate-night"', false)
            ->assertSee('value="coffee-night"', false)
            ->assertSee('Tema awal untuk browser baru: Terang.')
            ->assertSee('jooal-theme-choice');

        $styles = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('html[data-theme="dark"][data-theme-palette="slate-night"]', $styles);
        $this->assertStringContainsString('html[data-theme="dark"][data-theme-palette="coffee-night"]', $styles);
        $this->assertStringContainsString('--theme-body: #202124;', $styles);
        $this->assertStringContainsString('--theme-panel: #303134;', $styles);
    }
}
