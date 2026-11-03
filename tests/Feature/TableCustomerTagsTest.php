<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\MenuVariant;
use App\Models\RecipeItem;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableCustomerTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_tables_across_branches_and_duplicate_name_is_rejected_per_branch(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $one = $this->branch('ONE');
        $two = $this->branch('TWO');

        $this->actingAs($owner)->post('/tables', ['branch_id' => $one->id, 'name' => 'A1', 'capacity' => 4])->assertRedirect();
        $this->actingAs($owner)->post('/tables', ['branch_id' => $two->id, 'name' => 'A1', 'capacity' => 2])->assertRedirect();
        $this->assertSame(2, DiningTable::query()->where('name', 'A1')->count());
        $this->actingAs($owner)->post('/tables', ['branch_id' => $one->id, 'name' => 'A1', 'capacity' => 2])->assertSessionHasErrors('name');
        $this->actingAs($owner)->get('/tables')->assertOk()->assertSee('A1');
    }

    public function test_owner_branch_scope_filters_tables_and_customers_without_relabeling_all_as_active(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $one = $this->branch('SCOPE-1');
        $two = $this->branch('SCOPE-2');
        DiningTable::create(['branch_id' => $one->id, 'name' => 'One Table', 'capacity' => 2, 'is_active' => true]);
        DiningTable::create(['branch_id' => $two->id, 'name' => 'Two Table', 'capacity' => 2, 'is_active' => true]);
        Customer::create(['branch_id' => $one->id, 'name' => 'One Customer', 'is_active' => true]);
        Customer::create(['branch_id' => $two->id, 'name' => 'Two Customer', 'is_active' => true]);

        $this->actingAs($owner)->get('/tables?branch_id='.$one->id)->assertOk()->assertSee('One Table')->assertDontSee('Two Table')
            ->assertSessionHas('active_branch_id', $one->id);
        $this->actingAs($owner)->get('/customers?branch_id='.$two->id)->assertOk()->assertSee('Two Customer')->assertDontSee('One Customer')
            ->assertSessionHas('active_branch_id', $two->id);
        $this->actingAs($owner)->get('/tables?branch_id=all')->assertOk()->assertSee('One Table')->assertSee('Two Table')
            ->assertSessionHas('active_branch_id', $two->id);
        $this->actingAs($owner)->get('/customers?branch_id=all')->assertOk()->assertSee('One Customer')->assertSee('Two Customer')
            ->assertSessionHas('active_branch_id', $two->id);
    }

    public function test_manager_cannot_expand_branch_scope_with_query_parameter(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $assigned = $this->branch('SCOPE-M');
        $other = $this->branch('SCOPE-X');
        $manager->branches()->attach($assigned, ['is_default' => true, 'is_active' => true]);
        DiningTable::create(['branch_id' => $assigned->id, 'name' => 'Manager Table', 'capacity' => 2, 'is_active' => true]);
        DiningTable::create(['branch_id' => $other->id, 'name' => 'Foreign Table', 'capacity' => 2, 'is_active' => true]);
        Customer::create(['branch_id' => $assigned->id, 'name' => 'Manager Customer', 'is_active' => true]);
        Customer::create(['branch_id' => $other->id, 'name' => 'Foreign Customer', 'is_active' => true]);

        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])->get('/tables?branch_id='.$other->id)
            ->assertOk()->assertSee('Manager Table')->assertDontSee('Foreign Table');
        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])->get('/customers?branch_id='.$other->id)
            ->assertOk()->assertSee('Manager Customer')->assertDontSee('Foreign Customer');
    }

    public function test_manager_table_access_and_mutations_are_scoped_to_assigned_active_branch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $assigned = $this->branch('MGR');
        $other = $this->branch('OTH');
        $manager->branches()->attach($assigned, ['is_default' => true, 'is_active' => true]);
        $table = DiningTable::create(['branch_id' => $other->id, 'name' => 'Rahasia', 'capacity' => 2, 'is_active' => true]);

        $this->actingAs($manager)->get('/tables')->assertOk()->assertDontSee('Rahasia');
        $this->actingAs($manager)->put('/tables/'.$table->id, ['name' => 'Diubah', 'capacity' => 5])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]))->get('/tables')->assertForbidden();
    }

    public function test_checkout_requires_an_active_table_of_its_branch_and_keeps_table_snapshot(): void
    {
        [$cashier, $branch, $menu, $variant] = $this->pastryContext();
        $ingredient = Ingredient::create(['name' => 'Flour', 'slug' => 'flour-table-test', 'unit' => 'gram', 'minimum_stock' => 1, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100]);
        $inactive = DiningTable::create(['branch_id' => $branch->id, 'name' => 'Meja Lama', 'capacity' => 2, 'is_active' => false]);
        $active = DiningTable::create(['branch_id' => $branch->id, 'name' => 'Meja Teras', 'capacity' => 4, 'is_active' => true]);
        $customer = Customer::create(['branch_id' => $branch->id, 'name' => 'Customer Resmi', 'is_active' => true]);
        $session = $this->pastryCart($branch, $menu, $variant);
        $payload = ['customer_name' => 'Nama Dimanipulasi', 'customer_id' => $customer->id, 'service_type' => 'dine_in', 'pager_number' => 'P-1', 'table_id' => $inactive->id, 'payment_method' => 'cash', 'cash_amount' => 10000];

        $this->actingAs($cashier)->withSession($session)->post('/checkout', $payload)->assertSessionHasErrors('table_id');
        $this->actingAs($cashier)->withSession($session)->post('/checkout', array_merge($payload, ['table_id' => $active->id]))->assertSessionHasNoErrors()->assertRedirect();
        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame($active->id, $transaction->dining_table_id);
        $this->assertSame('Meja Teras', $transaction->table_number);
        $this->assertSame($customer->id, $transaction->customer_id);
        $this->assertSame('Customer Resmi', $transaction->customer_name);
    }

    public function test_customer_crud_validation_scope_and_legacy_transaction_snapshot_are_preserved(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $branch = $this->branch('CUS');
        $other = $this->branch('C2');
        $manager->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->post('/customers', [
            'name' => 'Ayu', 'phone' => '+62-1234', 'email' => 'ayu@example.test',
        ])->assertRedirect();
        $customer = Customer::query()->firstOrFail();
        $this->assertSame($branch->id, $customer->branch_id);
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->put('/customers/'.$customer->id, [
            'name' => 'Ayu Updated', 'phone' => 'nomor-salah', 'email' => 'not-email',
        ])->assertSessionHasErrors(['phone', 'email']);

        $legacy = Transaction::create(['transaction_number' => 'LEGACY-CUSTOMER-1', 'branch_id' => $branch->id, 'customer_name' => 'Nama Snapshot Lama', 'subtotal' => 0, 'grand_total' => 0, 'amount_paid' => 0, 'payment_method' => 'cash', 'status' => 'completed', 'sold_at' => now()]);
        $foreign = Customer::create(['branch_id' => $other->id, 'name' => 'Data Cabang Lain', 'is_active' => true]);
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->get('/customers/'.$foreign->id)->assertNotFound();
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->get('/customers')->assertOk()->assertSee('Ayu')->assertDontSee('Data Cabang Lain');
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->get('/customers/'.$customer->id.'/edit')->assertOk()->assertSee('Edit pelanggan');
        $this->assertNull($legacy->fresh()->customer_id);
        $this->assertSame('Nama Snapshot Lama', $legacy->fresh()->customer_name);
    }

    public function test_owner_customer_detail_works_and_master_tags_sync_many_to_many_products(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $customer = Customer::create(['name' => 'Global Customer', 'email' => null, 'is_active' => true]);
        $this->actingAs($owner)->get('/customers/'.$customer->id)->assertOk()->assertSee('Global Customer');

        $category = Category::create(['name' => 'Coffee', 'slug' => 'coffee-tag-test', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Latte Tag', 'slug' => 'latte-tag-test', 'price' => 20000, 'is_active' => true]);
        $tag = Tag::create(['name' => 'Best Seller', 'slug' => 'best-seller', 'is_active' => true]);
        $this->actingAs($owner)->put('/master-menu/'.$menu->id, [
            'category_id' => $category->id, 'name' => $menu->name, 'slug' => $menu->slug,
            'price' => 20000, 'is_active' => 1, 'sync_tag_ids' => 1, 'tag_ids' => [$tag->id],
        ])->assertRedirect(route('master-menu.index'));
        $this->assertDatabaseHas('menu_tag', ['menu_id' => $menu->id, 'tag_id' => $tag->id]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]))->get('/tags')->assertForbidden();
        $this->actingAs($owner)->post('/tags', ['name' => 'Coffee', 'slug' => 'best-seller'])->assertSessionHasErrors('slug');
    }

    private function branch(string $code): Branch
    {
        return Branch::create(['code' => $code, 'name' => 'Branch '.$code, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }

    private function pastryContext(): array
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = $this->branch('PST');
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $category = Category::create(['name' => 'Pastry', 'slug' => 'pastry', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Croissant', 'slug' => 'croissant-table-test', 'price' => 8000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);
        $variant = MenuVariant::create(['menu_id' => $menu->id, 'name' => 'Plain', 'slug' => 'croissant-plain-table-test', 'price_adjustment' => 0, 'is_active' => true]);
        return [$cashier, $branch, $menu, $variant];
    }

    private function pastryCart(Branch $branch, Menu $menu, MenuVariant $variant): array
    {
        $item = [
            'menu_id' => $menu->id, 'topping_ids' => [], 'matcha_type_id' => null, 'matcha_origin_id' => null,
            'menu_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 1, 'line_total' => 1, 'category_slug' => 'pastry',
        ];
        $item['recipe_fingerprint'] = app(\App\Services\RecipeStockService::class)->recipeFingerprintForItem($item);
        return ['active_branch_id' => $branch->id, 'checkout_idempotency_key' => 'phase10-table-checkout', 'pos_cart' => [$item]];
    }
}
