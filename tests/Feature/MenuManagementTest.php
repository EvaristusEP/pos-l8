<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\Category;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\TransactionItem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_access_master_menu(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/master-menu')->assertOk()->assertSee('Master Menu');
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Coffee Latte', 'slug' => 'coffee-latte', 'price' => 25000, 'is_active' => true]);
        $this->actingAs($owner)->get('/master-menu/'.$menu->id.'/edit')->assertOk()->assertSee('Harga dan ketersediaan');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]))->get('/master-menu')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]))->get('/master-menu')->assertForbidden();
    }

    public function test_master_menu_edit_compiles_conditional_attributes_without_leaking_blade(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Matcha', 'slug' => 'matcha', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Matcha Latte', 'slug' => 'matcha-latte', 'price' => 25000, 'is_active' => true]);

        $this->actingAs($owner)->get('/master-menu/'.$menu->id.'/edit')
            ->assertOk()
            ->assertSee('name="category_id" value="'.$category->id.'"', false)
            ->assertDontSee('name="branch_category"', false)
            ->assertSee('value="1" checked', false)
            ->assertDontSee('@selected')
            ->assertDontSee('@checked');
    }

    public function test_master_menu_index_renders_edit_modal_and_validation_reopens_it(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Matcha', 'slug' => 'matcha-modal', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Modal Latte', 'slug' => 'modal-latte', 'price' => 25000, 'is_active' => true]);

        $this->actingAs($owner)->get('/master-menu')
            ->assertOk()
            ->assertSee('<dialog id="menu-create-dialog"', false)
            ->assertSee('data-filter-search="menu-list"', false)
            ->assertSee(route('master-menu.update', $menu), false)
            ->assertDontSee(route('master-menu.edit', $menu), false);

        $this->actingAs($owner)->put(route('master-menu.update', $menu), [
            'category_id' => $category->id,
            'name' => '',
            'slug' => $menu->slug,
            'price' => 25000,
            'description' => '',
            'is_active' => 1,
        ])->assertRedirect(route('master-menu.index', ['edit_menu' => $menu->id]))
            ->assertSessionHasErrors('name');
    }

    public function test_new_menu_is_inactive_and_cannot_be_activated_until_recipe_is_complete(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Coffee', 'slug' => 'coffee-new-menu', 'sort_order' => 1, 'is_active' => true]);
        $this->actingAs($owner)->post('/master-menu', [
            'category_id' => $category->id, 'name' => 'New Latte', 'slug' => 'new-latte', 'price' => 15000,
            'description' => '', 'is_active' => 1,
        ])->assertRedirect();
        $menu = Menu::where('slug', 'new-latte')->firstOrFail();
        $this->assertSame(0, (int) $menu->is_active);
        $this->actingAs($owner)->patch('/master-menu/'.$menu->id.'/toggle-status')
            ->assertSessionHasErrors('recipe');
        $this->assertSame(0, (int) $menu->fresh()->is_active);
    }

    public function test_new_menu_generates_unique_slug_and_keeps_branch_tag_and_recipe_workflow(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Roti', 'slug' => 'roti-create', 'sort_order' => 1, 'is_active' => true]);
        Category::create(['name' => 'Pastry', 'slug' => 'pastry', 'sort_order' => 2, 'is_active' => true]);
        $branch = Branch::create(['code' => 'JOOAL-001', 'name' => 'JOOAL Bakery', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $tag = Tag::create(['name' => 'Roti', 'slug' => 'tag-roti-slug-test', 'is_active' => true]);
        $existing = Menu::create(['category_id' => $category->id, 'name' => 'Roti Sourdough', 'slug' => 'roti-sourdough', 'price' => 10000, 'is_active' => false]);

        $payload = [
            'category_id' => $category->id,
            'branch_category' => 'roti',
            'name' => 'Roti Sourdough',
            'price' => 12500,
            'description' => 'Roti sourdough segar',
            'is_active' => 1,
            'sync_tag_ids' => 1,
            'tag_ids' => [$tag->id],
            'branch_settings' => [$branch->id => ['price' => 13000, 'is_available' => 1]],
        ];

        $this->actingAs($owner)->post(route('master-menu.store'), $payload)->assertRedirect();
        $created = Menu::query()->where('slug', 'roti-sourdough-2')->firstOrFail();
        $this->assertSame(0, (int) $created->is_active);
        $this->assertSame('13000', (string) $created->branches()->first()->pivot->price);
        $this->assertSame(1, (int) $created->branches()->first()->pivot->is_available);
        $this->assertTrue($created->tags()->whereKey($tag->id)->exists());
        $this->assertSame('roti-sourdough', $existing->fresh()->slug);
        $this->assertDatabaseCount('recipe_items', 0);
    }

    public function test_editing_menu_without_a_slug_field_preserves_existing_slug(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Pastry', 'slug' => 'pastry-slug-preserve', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Old Name', 'slug' => 'legacy-stable-slug', 'price' => 10000, 'is_active' => false]);

        $this->actingAs($owner)->put(route('master-menu.update', $menu), [
            'category_id' => $category->id,
            'name' => 'Renamed Product',
            'price' => 11000,
            'description' => '',
            'is_active' => 0,
        ])->assertRedirect(route('master-menu.index'));

        $this->assertSame('legacy-stable-slug', $menu->fresh()->slug);
    }

    public function test_catalog_uses_standard_price_fallback_and_branch_override(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $this->addRecipeStock($branch, $menu);
        $branch->menus()->attach($menu, ['price' => 18000, 'is_available' => true]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertSee('18.000');

        $menu->update(['price' => 22000]);
        $this->assertSame('18000', (string) $menu->branches()->first()->pivot->price);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertSee('18.000');

        $menu->branches()->updateExistingPivot($branch->id, ['price' => null]);
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertSee('22.000');
    }

    public function test_unavailable_menu_is_hidden_and_rejected_by_backend(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => false]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertDontSee($menu->name);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->post('/dashboard/cart', ['menu_id' => $menu->id, 'quantity' => 1])
            ->assertSessionHasErrors('menu_id');
    }

    public function test_pos_uses_database_categories_and_only_configured_branch_menus(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $otherCategory = Category::create(['name' => 'Gelato', 'slug' => 'gelato-menu-test', 'sort_order' => 3, 'is_active' => true]);
        $otherMenu = Menu::create(['category_id' => $otherCategory->id, 'name' => 'Gelato Test', 'slug' => 'gelato-test', 'price' => 19000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);
        $secondBranch = Branch::create(['code' => 'JKT-02', 'name' => 'JOOAL Gelato', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $cashier->branches()->attach($secondBranch, ['is_default' => false, 'is_active' => true]);
        $secondBranch->menus()->attach($otherMenu, ['price' => null, 'is_available' => true]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])->get('/dashboard/pos')
            ->assertOk()->assertSee($branch->name)->assertSee($cashier->name)->assertSee($menu->name)->assertDontSee($otherMenu->name)
            ->assertSee('data-category-filter="needs-classification"', false)
            ->assertDontSee('data-category-filter="'.$otherCategory->id.'"', false);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $secondBranch->id])->get('/dashboard/pos')
            ->assertOk()->assertSee($otherMenu->name)->assertDontSee($menu->name)
            ->assertSee('data-category-filter="needs-classification"', false)
            ->assertDontSee('data-category-filter="gelato-menu-test"', false);
    }

    public function test_master_menu_category_choices_follow_active_branch_and_save_without_reclassifying_global_product(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $matcha = Category::create(['name' => 'Matcha', 'slug' => 'matcha', 'sort_order' => 1, 'is_active' => true]);
        $nonMatcha = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $pastry = Category::create(['name' => 'Pastry', 'slug' => 'pastry', 'sort_order' => 3, 'is_active' => true]);
        $bakery = Branch::create(['code' => 'JOOAL-001', 'name' => 'JOOAL Bakery', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $coffee = Branch::create(['code' => 'JOOAL-003', 'name' => 'JOOAL Coffee', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $gelato = Branch::create(['code' => 'JOOAL-002', 'name' => 'JOOAL Gelato', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        foreach ([
            [$bakery, ['roti', 'pastry', 'kue', 'dessert'], ['kopi', 'non-kopi', 'teh', 'makanan']],
            [$coffee, ['kopi', 'non-kopi', 'teh', 'makanan'], ['gelato', 'es-krim', 'sundae', 'dessert']],
            [$gelato, ['gelato', 'es-krim', 'sundae', 'dessert'], ['roti', 'kopi']],
        ] as [$branch, $expected, $excluded]) {
            $response = $this->actingAs($owner)->withSession(['active_branch_id' => $branch->id])->get('/master-menu')->assertOk();
            foreach ($expected as $slug) $response->assertSee('value="'.$slug.'"', false);
            foreach ($excluded as $slug) $response->assertDontSee('value="'.$slug.'"', false);
            $response->assertDontSee('value="matcha"', false)->assertDontSee('value="non-matcha"', false);
        }

        $payload = [
            'category_id' => $matcha->id,
            'branch_category' => 'kue',
            'name' => 'Cookie Baru',
            'price' => 25000,
            'description' => 'Produk uji kategori cabang',
            'is_active' => 0,
            'branch_settings' => [
                $bakery->id => ['price' => 27000, 'is_available' => 1],
                $coffee->id => ['price' => '', 'is_available' => 0],
                $gelato->id => ['price' => '', 'is_available' => 0],
            ],
        ];
        $this->actingAs($owner)->withSession(['active_branch_id' => $bakery->id])->post(route('master-menu.store'), $payload)->assertRedirect();
        $menu = Menu::query()->where('slug', 'cookie-baru')->firstOrFail();
        $this->assertSame($pastry->id, $menu->category_id, 'The legacy category is assigned by branch compatibility, not the submitted hidden field.');
        $this->assertSame('27000', (string) $menu->branches()->whereKey($bakery->id)->firstOrFail()->pivot->price);
        $this->assertSame('kue', $menu->branches()->whereKey($bakery->id)->firstOrFail()->pivot->catalog_category);
        $this->assertSame(0, (int) $menu->branches()->whereKey($coffee->id)->firstOrFail()->pivot->is_available);
        $this->assertNull($menu->branches()->whereKey($coffee->id)->firstOrFail()->pivot->catalog_category);

        $ingredient = Ingredient::create(['name' => 'Tepung', 'slug' => 'category-test-flour', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        $stock = BranchStock::create(['branch_id' => $bakery->id, 'ingredient_id' => $ingredient->id, 'quantity' => '500.000']);
        $variant = \App\Models\MenuVariant::create(['menu_id' => $menu->id, 'name' => 'Regular', 'slug' => 'cookie-baru-regular', 'price_adjustment' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 20, 'unit' => 'gram', 'is_active' => true]);

        $this->actingAs($owner)->withSession(['active_branch_id' => $coffee->id])->get('/master-menu/'.$menu->id.'/edit')
            ->assertOk()->assertSee('value="kopi"', false)->assertSee('value="non-kopi"', false)->assertDontSee('value="roti"', false);
        $this->actingAs($owner)->withSession(['active_branch_id' => $coffee->id])->put(route('master-menu.update', $menu), [
            'category_id' => $menu->category_id,
            'branch_category' => 'non-kopi',
            'name' => $menu->name,
            'price' => $menu->price,
            'description' => $menu->description,
            'is_active' => 0,
            'branch_settings' => [
                $bakery->id => ['price' => 27000, 'is_available' => 1],
                $coffee->id => ['price' => '', 'is_available' => 0],
                $gelato->id => ['price' => '', 'is_available' => 0],
            ],
        ])->assertRedirect(route('master-menu.index'));

        $this->assertSame($pastry->id, $menu->fresh()->category_id);
        $this->assertSame('kue', $menu->branches()->whereKey($bakery->id)->firstOrFail()->pivot->catalog_category);
        $this->assertSame('27000', (string) $menu->branches()->whereKey($bakery->id)->firstOrFail()->pivot->price);
        $this->assertSame('non-kopi', $menu->branches()->whereKey($coffee->id)->firstOrFail()->pivot->catalog_category);
        $this->assertSame($variant->id, $menu->variants()->firstOrFail()->id);
        $this->assertDatabaseHas('recipe_items', ['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id]);
        $this->assertSame('500.000', (string) $stock->fresh()->quantity);
    }

    public function test_demo_branch_catalogs_use_product_type_categories_without_changing_database_categories(): void
    {
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $cases = [
            ['JOOAL-001', 'JOOAL Bakery', [['jooal-001-roti-sourdough', 'Roti Sourdough', 'roti'], ['jooal-001-muffin-cokelat', 'Muffin Cokelat', 'kue'], ['jooal-shared-kopi-susu-gula-aren', 'Kopi Susu Gula Aren', 'needs-classification']]],
            ['JOOAL-003', 'JOOAL Coffee', [['jooal-003-cappuccino', 'Cappuccino', 'kopi'], ['jooal-003-matcha-latte', 'Matcha Latte', 'non-kopi'], ['jooal-shared-cokelat-signature', 'Cokelat Signature', 'non-kopi']]],
            ['JOOAL-002', 'JOOAL Gelato', [['jooal-002-gelato-vanilla-bean', 'Gelato Vanilla Bean', 'gelato'], ['jooal-002-sundae-stroberi', 'Sundae Stroberi', 'sundae'], ['jooal-002-waffle-gelato', 'Waffle Gelato', 'dessert'], ['jooal-shared-matcha-susu', 'Matcha Susu', 'needs-classification']]],
        ];

        foreach ($cases as [$code, $name, $products]) {
            $branch = Branch::create(['code' => $code, 'name' => $name, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
            $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
            $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
            foreach ($products as [$slug, $productName]) {
                $menu = Menu::create(['category_id' => $category->id, 'name' => $productName, 'slug' => $slug, 'price' => 10000, 'is_active' => true]);
                $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);
            }

            $response = $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])->get('/dashboard/pos')->assertOk();
            foreach ($products as [, $productName, $expectedCategory]) {
                $response->assertSee($productName)->assertSee('data-category-filter="'.$expectedCategory.'"', false);
            }
            $response->assertDontSee('data-category-filter="matcha"', false)
                ->assertDontSee('data-category-filter="non-matcha"', false)
                ->assertSee('data-menu-search', false)
                ->assertSee('data-product-detail', false)
                ->assertSee('data-cart-items', false);
        }

        $this->assertSame(1, Category::query()->count());
        $this->assertSame($category->id, Menu::query()->firstOrFail()->category_id);
    }

    public function test_pos_rejects_menu_without_branch_configuration(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertDontSee($menu->name);
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->post('/dashboard/cart', ['menu_id' => $menu->id, 'quantity' => 1])
            ->assertSessionHasErrors('menu_id');
    }

    public function test_pos_menu_row_opens_details_with_only_its_configured_variant(): void
    {
        [$cashier, $branch] = $this->cashierMenuContext();
        $category = Category::create(['name' => 'Pastry', 'slug' => 'pastry', 'sort_order' => 3, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Croissant Detail', 'slug' => 'croissant-detail-pos', 'price' => 24000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);
        $variant = \App\Models\MenuVariant::create(['menu_id' => $menu->id, 'name' => 'Original', 'slug' => 'croissant-detail-original', 'price_adjustment' => 0, 'is_active' => true]);
        \App\Models\Topping::create(['name' => 'Global add-on test', 'slug' => 'global-addon-detail-test', 'price' => 5000, 'is_active' => true]);
        $ingredient = Ingredient::create(['name' => 'Flour', 'slug' => 'detail-flour', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])->get('/dashboard/pos')
            ->assertOk()
            ->assertSee('class="pos-menu-list"', false)
            ->assertSee('class="pos-menu-open"', false)
            ->assertSee('data-product-open="product-detail-'.$menu->id.'"', false)
            ->assertSee('<dialog id="product-detail-'.$menu->id.'"', false)
            ->assertSee('name="menu_variant_id"', false)
            ->assertSee($variant->name)
            ->assertSee('data-product-total', false)
            ->assertSee('Tambahkan ke Keranjang')
            ->assertDontSee('Global add-on test')
            ->assertDontSee('Croissant Butter')
            ->assertDontSee('Non Matcha');
    }
    public function test_checkout_reprices_tampered_cart_and_saves_snapshot(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $branch->menus()->attach($menu, ['price' => 17500, 'is_available' => true]);
        $ingredient = Ingredient::create(['name' => 'Coffee beans', 'slug' => 'coffee-beans', 'unit' => 'gram', 'minimum_stock' => 10, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100]);

        $this->actingAs($cashier)->withSession([
            'active_branch_id' => $branch->id,
            'checkout_idempotency_key' => 'menu-checkout-snapshot-test',
            'pos_cart' => [[
                'menu_id' => $menu->id,
                'topping_ids' => [],
                'matcha_type_id' => null,
                'matcha_origin_id' => null,
                'menu_variant_id' => null,
                'quantity' => 2,
                'unit_price' => 1,
                'line_total' => 2,
                'category_slug' => 'non-matcha',
                'recipe_fingerprint' => app(\App\Services\RecipeStockService::class)->recipeFingerprintForItem(['menu_id' => $menu->id, 'topping_ids' => []]),
            ]],
        ])->post('/checkout', [
            'customer_name' => 'Customer',
            'service_type' => 'take_away',
            'pager_number' => 'P-01',
            'payment_method' => 'cash',
            'cash_amount' => 40000,
        ])->assertRedirect();

        $item = TransactionItem::query()->latest('id')->firstOrFail();
        $this->assertSame('17500.00', (string) $item->unit_price);
        $this->assertSame('17500.00', (string) $item->branch_price);
        $this->assertSame('25000.00', (string) $item->menu_price);
        $this->assertSame($menu->name, $item->menu_name);
        $this->assertSame('35000.00', (string) $item->line_total);
        $this->assertSame('17500', (string) $item->item_options['branch_price']);
    }

    private function cashierMenuContext(): array
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = Branch::create(['code' => 'JKT-01', 'name' => 'JOOAL Jakarta', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Coffee Latte', 'slug' => 'coffee-latte', 'description' => 'Coffee', 'price' => 25000, 'is_active' => true]);

        return [$cashier, $branch, $menu];
    }

    private function addRecipeStock(Branch $branch, Menu $menu): void
    {
        $ingredient = Ingredient::create(['name' => 'Coffee beans', 'slug' => 'catalog-beans', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => '10.000', 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '100.000']);
    }
}
