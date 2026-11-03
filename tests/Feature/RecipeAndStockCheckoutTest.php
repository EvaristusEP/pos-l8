<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\Topping;
use App\Models\User;
use App\Services\RecipeStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeAndStockCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_recipes_but_other_roles_cannot(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($owner)->get('/recipes')->assertOk()->assertSee('Resep bahan baku');
        $this->actingAs($cashier)->get('/recipes')->assertForbidden();
    }

    public function test_checkout_deducts_multiple_ingredients_and_records_movement(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $water = Ingredient::create(['name' => 'Air', 'slug' => 'air', 'unit' => 'ml', 'minimum_stock' => 20, 'is_active' => true]);
        $beans = Ingredient::create(['name' => 'Biji kopi', 'slug' => 'biji-kopi', 'unit' => 'gram', 'minimum_stock' => 20, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $water->id, 'quantity' => 100, 'unit' => 'ml', 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $beans->id, 'quantity' => 10, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $water->id, 'quantity' => 500]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $beans->id, 'quantity' => 50]);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 2, 'recipe-key'))
            ->post('/checkout', $this->payment())->assertRedirect();

        $this->assertSame('300.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $water->id])->value('quantity'));
        $this->assertSame('30.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $beans->id])->value('quantity'));
        $transaction = Transaction::latest('id')->firstOrFail();
        $this->assertSame(2, StockMovement::where('transaction_id', $transaction->id)->count());
        $this->assertSame($branch->id, StockMovement::latest('id')->first()->branch_id);
    }

    public function test_failed_cash_payment_does_not_create_transaction_or_change_stock(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = $this->recipeAndStock($branch, $menu, 20);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'failed-payment-key'))
            ->from('/checkout')->post('/checkout', array_merge($this->payment(), ['cash_amount' => 1]))
            ->assertRedirect('/checkout')->assertSessionHasErrors('cash_amount');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('20.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_checkout_uses_the_selected_pastry_variant_recipe_for_each_portion(): void
    {
        [$cashier, $branch] = $this->context();
        $category = Category::create(['name' => 'Pastry', 'slug' => 'pastry', 'sort_order' => 3, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Croissant', 'slug' => 'croissant-variant-recipe', 'price' => 25000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);
        $variant = \App\Models\MenuVariant::create(['menu_id' => $menu->id, 'name' => 'Large', 'slug' => 'croissant-large-recipe', 'price_adjustment' => 5000, 'is_active' => true]);
        $ingredient = Ingredient::create(['name' => 'Adonan', 'slug' => 'adonan-variant-recipe', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'menu_variant_id' => $variant->id, 'ingredient_id' => $ingredient->id, 'quantity' => 7, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 30]);
        $item = ['menu_id' => $menu->id, 'topping_ids' => [], 'menu_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 1, 'line_total' => 2, 'category_slug' => 'pastry'];
        $item['recipe_fingerprint'] = app(RecipeStockService::class)->recipeFingerprintForItem($item);

        $this->actingAs($cashier)->withSession([
            'active_branch_id' => $branch->id,
            'checkout_idempotency_key' => 'pastry-variant-recipe-key',
            'pos_cart' => [$item],
        ])->post('/checkout', $this->payment())->assertRedirect()->assertSessionHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame('16.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $this->assertSame('-14.000', (string) StockMovement::where('transaction_id', $transaction->id)->value('quantity_delta'));
    }

    public function test_unassigned_branch_id_cannot_redirect_cashier_stock_deduction(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = $this->recipeAndStock($branch, $menu, 20);
        $unassignedBranch = Branch::create(['code' => 'OTHER-01', 'name' => 'Unassigned branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        BranchStock::create(['branch_id' => $unassignedBranch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100]);
        $session = $this->cartSession($branch, $menu, 1, 'unassigned-branch-key');
        $session['active_branch_id'] = $unassignedBranch->id;

        $this->actingAs($cashier)->withSession($session)
            ->post('/checkout', array_merge($this->payment(), ['branch_id' => $unassignedBranch->id]))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('active_branch_id', $branch->id);

        $transaction = Transaction::firstOrFail();
        $this->assertSame($branch->id, $transaction->branch_id);
        $this->assertSame('15.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $this->assertSame('100.000', (string) BranchStock::where(['branch_id' => $unassignedBranch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
    }
    public function test_insufficient_stock_rolls_back_transaction_and_movement(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = Ingredient::create(['name' => 'Susu', 'slug' => 'susu', 'unit' => 'ml', 'minimum_stock' => 5, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100, 'unit' => 'ml', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 50]);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'short-key'))
            ->from('/checkout')->post('/checkout', $this->payment())->assertRedirect('/checkout')->assertSessionHasErrors('cart');

        $this->assertSame(0, Transaction::count());
        $this->assertSame('50.000', (string) BranchStock::first()->quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_repeated_payment_key_does_not_deduct_twice(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = Ingredient::create(['name' => 'Teh', 'slug' => 'teh', 'unit' => 'gram', 'minimum_stock' => 10, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 20]);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'same-key'))->post('/checkout', $this->payment())->assertRedirect();
        $this->post('/checkout', $this->payment() + ['idempotency_key' => 'same-key'])->assertRedirect();

        $this->assertSame(1, Transaction::where('idempotency_key', 'same-key')->count());
        $this->assertSame(1, Transaction::where('idempotency_key', 'same-key')->firstOrFail()->items()->count());
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('15.000', (string) BranchStock::first()->quantity);
    }

    public function test_idempotency_key_cannot_be_reused_from_another_branch(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = $this->recipeAndStock($branch, $menu, 20);
        $otherBranch = Branch::create(['code' => 'SBY-01', 'name' => 'JOOAL Surabaya', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $cashier->branches()->attach($otherBranch, ['is_default' => false, 'is_active' => true]);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'branch-key'))
            ->post('/checkout', $this->payment())->assertRedirect();

        $this->withSession($this->cartSession($otherBranch, $menu, 1, 'branch-key'))
            ->postJson('/checkout', $this->payment())->assertStatus(409)
            ->assertJson(['message' => 'Permintaan checkout tidak dapat diproses. Muat ulang halaman checkout lalu coba lagi.']);

        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Transaction::firstOrFail()->items()->count());
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('15.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $this->assertDatabaseMissing('branch_stocks', ['branch_id' => $otherBranch->id, 'ingredient_id' => $ingredient->id]);
    }

    public function test_idempotency_key_cannot_be_reused_by_another_cashier(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = $this->recipeAndStock($branch, $menu, 20);
        $otherCashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $otherCashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'cashier-key'))
            ->post('/checkout', $this->payment())->assertRedirect();

        $this->actingAs($otherCashier)->withSession($this->cartSession($branch, $menu, 1, 'cashier-key'))
            ->postJson('/checkout', $this->payment())->assertStatus(409)
            ->assertJson(['message' => 'Permintaan checkout tidak dapat diproses. Muat ulang halaman checkout lalu coba lagi.']);

        $this->assertSame(1, Transaction::count());
        $this->assertSame(1, Transaction::firstOrFail()->items()->count());
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('15.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
    }

    public function test_idempotency_key_rejects_a_different_cart_or_payment_payload(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = $this->recipeAndStock($branch, $menu, 30);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'payload-key'))
            ->post('/checkout', $this->payment())->assertRedirect();

        $differentCart = $this->cartSession($branch, $menu, 2, 'payload-key');
        $this->withSession($differentCart)->post('/checkout', $this->payment())->assertStatus(409);

        $this->withSession($this->cartSession($branch, $menu, 1, 'payload-key'))
            ->post('/checkout', array_merge($this->payment(), ['customer_name' => 'Different Customer']))
            ->assertStatus(409);

        $this->assertSame(1, Transaction::count());
        $transaction = Transaction::firstOrFail();
        $this->assertSame(1, $transaction->items()->count());
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('25.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
    }

    public function test_recipe_unit_must_match_ingredient_unit(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Sirup', 'slug' => 'sirup-menu', 'price' => 1000, 'is_active' => true]);
        $ingredient = Ingredient::create(['name' => 'Sirup', 'slug' => 'sirup', 'unit' => 'ml', 'minimum_stock' => 1, 'is_active' => true]);

        $this->actingAs($owner)->post('/recipes', ['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 1, 'unit' => 'gram', 'is_active' => 1])
            ->assertSessionHasErrors('unit');
    }

    public function test_checkout_converts_kg_and_gram_and_pos_shows_branch_specific_maximum_portions(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = Ingredient::create(['name' => 'Biji kopi', 'slug' => 'biji-kopi-kg', 'unit' => 'kg', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 250, 'unit' => 'g', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '0.500']);
        $otherBranch = Branch::create(['code' => 'SBY-UNIT', 'name' => 'JOOAL Surabaya', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        BranchStock::create(['branch_id' => $otherBranch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '5.000']);

        $this->assertSame(2, app(RecipeStockService::class)->maximumPortions($menu, $branch));
        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'unit-conversion-key'))
            ->post('/checkout', $this->payment())->assertRedirect();

        $this->assertSame('0.250', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $this->assertSame('-0.250', (string) StockMovement::where('type', 'sale')->value('quantity_delta'));
        $this->assertSame('5.000', (string) BranchStock::where(['branch_id' => $otherBranch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $this->withSession(['active_branch_id' => $branch->id])->get('/dashboard/pos')->assertOk()->assertSee('Terbatas');
    }

    public function test_menu_without_recipe_is_rejected_and_not_sold(): void
    {
        [$cashier, $branch, $menu] = $this->context();

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'optional-recipe-key'))
            ->from('/checkout')->post('/checkout', $this->payment())->assertRedirect('/checkout')->assertSessionHasErrors('cart');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_cart_cannot_be_checked_out_after_its_recipe_changes(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $ingredient = $this->recipeAndStock($branch, $menu, 20);
        $session = $this->cartSession($branch, $menu, 1, 'recipe-change-key');
        RecipeItem::where('menu_id', $menu->id)->update(['quantity' => '6.000']);

        $this->actingAs($cashier)->withSession($session)->from('/checkout')
            ->post('/checkout', $this->payment())->assertRedirect('/checkout')->assertSessionHasErrors('cart');
        $this->assertSame(0, Transaction::count());
        $this->assertSame('20.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_recipe_readiness_requires_active_recipe_for_sellable_menu(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $service = app(RecipeStockService::class);
        $this->assertFalse($service->menuRecipeReady($menu));
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertSee($menu->name)
            ->assertSee('Resep belum lengkap')
            ->assertSee('disabled', false);
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->post('/dashboard/cart', ['menu_id' => $menu->id, 'quantity' => 1])->assertSessionHasErrors('menu_id');

        $ingredient = Ingredient::create(['name' => 'Cup', 'slug' => 'cup-ready', 'unit' => 'pcs', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => '1.000', 'unit' => 'pcs', 'is_active' => true]);
        $this->assertTrue($service->menuRecipeReady($menu));
    }

    public function test_menu_capacity_uses_the_limiting_required_ingredient_and_branch_balance(): void
    {
        [, $branch, $menu] = $this->context();
        $beans = Ingredient::create(['name' => 'Beans', 'slug' => 'capacity-beans', 'unit' => 'g', 'minimum_stock' => 0, 'is_active' => true]);
        $milk = Ingredient::create(['name' => 'Milk', 'slug' => 'capacity-milk', 'unit' => 'ml', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $beans->id, 'quantity' => '18.000', 'unit' => 'g', 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $milk->id, 'quantity' => '180.000', 'unit' => 'ml', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $beans->id, 'quantity' => '180.000']);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $milk->id, 'quantity' => '900.000']);
        $otherBranch = Branch::create(['code' => 'CAP-02', 'name' => 'Other branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        BranchStock::create(['branch_id' => $otherBranch->id, 'ingredient_id' => $beans->id, 'quantity' => '1800.000']);
        BranchStock::create(['branch_id' => $otherBranch->id, 'ingredient_id' => $milk->id, 'quantity' => '9000.000']);

        $availability = app(RecipeStockService::class)->availability($menu, $branch);
        $this->assertSame(5, $availability['available_portions']);
        $this->assertSame('low_stock', $availability['status']);
        $this->assertSame($milk->id, $availability['limiting_ingredient']->id);
        $this->assertSame(50, app(RecipeStockService::class)->maximumPortions($menu, $otherBranch));
    }

    public function test_missing_or_zero_stock_and_incomplete_recipe_are_reported_explicitly(): void
    {
        [, $branch, $menu] = $this->context();
        $service = app(RecipeStockService::class);
        $this->assertSame('recipe_incomplete', $service->availability($menu, $branch)['status']);
        $this->assertNull($service->availability($menu, $branch)['available_portions']);

        $ingredient = Ingredient::create(['name' => 'Cup', 'slug' => 'capacity-zero-cup', 'unit' => 'pcs', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => '1.000', 'unit' => 'pcs', 'is_active' => true]);
        $this->assertSame('stock_unavailable', $service->availability($menu, $branch)['status']);
        $this->assertNull($service->availability($menu, $branch)['available_portions']);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '0.000']);
        $availability = $service->availability($menu, $branch);
        $this->assertSame('out_of_stock', $availability['status']);
        $this->assertSame(0, $availability['available_portions']);
    }

    public function test_recipe_capacity_uses_exact_supported_unit_conversion_and_stock_in_is_reflected(): void
    {
        [, $branch, $menu] = $this->context();
        $beans = Ingredient::create(['name' => 'Beans', 'slug' => 'capacity-kg-beans', 'unit' => 'kg', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $beans->id, 'quantity' => '250.000', 'unit' => 'g', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $beans->id, 'quantity' => '0.500']);
        $service = app(RecipeStockService::class);
        $this->assertSame(2, $service->maximumPortions($menu, $branch));

        BranchStock::query()->where(['branch_id' => $branch->id, 'ingredient_id' => $beans->id])->update(['quantity' => '1.500']);
        $this->assertSame(6, $service->maximumPortions($menu, $branch));
        $this->assertFalse($service->canConvertUnits('g', 'ml'));
    }

    public function test_shared_ingredient_capacity_is_per_menu_not_summed_as_guaranteed_output(): void
    {
        [, $branch, $menu] = $this->context();
        $beans = Ingredient::create(['name' => 'Beans', 'slug' => 'capacity-shared-beans', 'unit' => 'g', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $beans->id, 'quantity' => '10.000', 'unit' => 'g', 'is_active' => true]);
        $otherMenu = Menu::create(['category_id' => $menu->category_id, 'name' => 'Other coffee', 'slug' => 'other-coffee-capacity', 'price' => 1000, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $otherMenu->id, 'ingredient_id' => $beans->id, 'quantity' => '20.000', 'unit' => 'g', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $beans->id, 'quantity' => '100.000']);

        $service = app(RecipeStockService::class);
        $this->assertSame(10, $service->maximumPortions($menu, $branch));
        $this->assertSame(5, $service->maximumPortions($otherMenu, $branch));
        $this->assertSame(100, (int) BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $beans->id)->value('quantity'));
    }

    public function test_addon_consumes_its_recipe_material_without_separate_menu_stock_deduction(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $beans = Ingredient::create(['name' => 'Beans', 'slug' => 'addon-beans', 'unit' => 'g', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $beans->id, 'quantity' => '10.000', 'unit' => 'g', 'is_active' => true]);
        $extraShot = Topping::create(['name' => 'Extra shot', 'slug' => 'extra-shot-addon-test', 'price' => 5000, 'is_active' => true]);
        RecipeItem::create(['topping_id' => $extraShot->id, 'ingredient_id' => $beans->id, 'quantity' => '5.000', 'unit' => 'g', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $beans->id, 'quantity' => '50.000']);
        $item = ['menu_id' => $menu->id, 'topping_ids' => [$extraShot->id], 'matcha_type_id' => null, 'matcha_origin_id' => null, 'menu_variant_id' => null, 'quantity' => 2, 'unit_price' => 1, 'line_total' => 2, 'category_slug' => 'non-matcha'];
        $item['recipe_fingerprint'] = app(RecipeStockService::class)->recipeFingerprintForItem($item);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id, 'checkout_idempotency_key' => 'addon-recipe-key', 'pos_cart' => [$item]])
            ->post('/checkout', $this->payment())->assertRedirect()->assertSessionHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame('20.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $beans->id])->value('quantity'));
        $this->assertSame(1, StockMovement::where('transaction_id', $transaction->id)->count());
        $this->assertSame('-30.000', (string) StockMovement::where('transaction_id', $transaction->id)->value('quantity_delta'));
    }

    public function test_checkout_converts_liter_to_milliliter_and_piece_units(): void
    {
        [$cashier, $branch, $menu] = $this->context();
        $milk = Ingredient::create(['name' => 'Susu', 'slug' => 'susu-liter-test', 'unit' => 'ml', 'minimum_stock' => 0, 'is_active' => true]);
        $cups = Ingredient::create(['name' => 'Cup', 'slug' => 'cup-piece-test', 'unit' => 'pcs', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $milk->id, 'quantity' => '0.250', 'unit' => 'l', 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $cups->id, 'quantity' => '1.000', 'unit' => 'pc', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $milk->id, 'quantity' => '1000.000']);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $cups->id, 'quantity' => '2.000']);

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 1, 'liter-piece-key'))
            ->post('/checkout', $this->payment())->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame('750.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $milk->id])->value('quantity'));
        $this->assertSame('1.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $cups->id])->value('quantity'));
    }

    private function context(): array
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = Branch::create(['code' => 'JKT-01', 'name' => 'JOOAL Jakarta', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Latte', 'slug' => 'latte', 'price' => 25000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);

        return [$cashier, $branch, $menu];
    }

    private function cartSession(Branch $branch, Menu $menu, int $quantity, string $key): array
    {
        $item = [
            'menu_id' => $menu->id, 'topping_ids' => [], 'matcha_type_id' => null, 'matcha_origin_id' => null,
            'menu_variant_id' => null, 'quantity' => $quantity, 'unit_price' => 1, 'line_total' => 1, 'category_slug' => 'non-matcha',
        ];
        $item['recipe_fingerprint'] = app(RecipeStockService::class)->recipeFingerprintForItem($item);
        return ['active_branch_id' => $branch->id, 'checkout_idempotency_key' => $key, 'pos_cart' => [[
            ...$item,
        ]]];
    }

    private function payment(): array
    {
        return ['customer_name' => 'Customer', 'service_type' => 'take_away', 'pager_number' => 'P-01', 'payment_method' => 'cash', 'cash_amount' => 100000];
    }

    private function recipeAndStock(Branch $branch, Menu $menu, int $stock): Ingredient
    {
        $ingredient = Ingredient::create(['name' => 'Teh', 'slug' => 'teh', 'unit' => 'gram', 'minimum_stock' => 10, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => $stock]);

        return $ingredient;
    }
}
