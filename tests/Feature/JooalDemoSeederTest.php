<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Attendance;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\StockReceiptItem;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\RecipeStockService;
use Database\Seeders\JooalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JooalDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_presentation_dataset_has_three_branches_and_is_idempotent(): void
    {
        $branches = [
            Branch::create(['code' => 'JOOAL-001', 'name' => 'Cabang Utama', 'timezone' => 'Asia/Jakarta', 'is_active' => true]),
            Branch::create(['code' => 'JOOAL-002', 'name' => 'Gelato', 'timezone' => 'Asia/Jakarta', 'is_active' => true]),
            Branch::create(['code' => 'JOOAL-003', 'name' => 'Coffee', 'timezone' => 'Asia/Jakarta', 'is_active' => true]),
        ];
        DiningTable::create(['branch_id' => $branches[1]->id, 'name' => 'VIP', 'capacity' => 4, 'is_active' => true]);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->createLegacyDemoFixture($branches, $owner);
        $unmarked = Transaction::query()->create(['transaction_number' => 'POS-BASE-0001', 'cashier_id' => $owner->id, 'branch_id' => $branches[0]->id, 'subtotal' => 1000, 'grand_total' => 1000, 'amount_paid' => 1000, 'change_amount' => 0, 'payment_method' => 'cash', 'status' => 'completed', 'sold_at' => '2026-09-30 12:00:00']);

        $seeder = app(JooalDemoSeeder::class);
        $seeder->run();
        $firstBalances = BranchStock::query()->orderBy('branch_id')->orderBy('ingredient_id')->pluck('quantity')->all();
        $firstCounts = [
            'transactions' => Transaction::where('transaction_number', 'like', 'JOOAL-%')->count(),
            'movements' => StockMovement::count(), 'menus' => Menu::where('slug', 'like', 'jooal-%')->count(),
            'suppliers' => Supplier::count(), 'customers' => Customer::count(), 'coupons' => Coupon::count(),
            'stocks' => BranchStock::count(), 'receipts' => StockReceipt::count(),
        ];
        $seeder->run();

        $this->assertSame(['JOOAL Bakery', 'JOOAL Gelato', 'JOOAL Coffee'], Branch::query()->orderBy('code')->pluck('name')->all());
        $this->assertSame([$branches[0]->id, $branches[1]->id, $branches[2]->id], Branch::query()->orderBy('code')->pluck('id')->all());
        $this->assertSame('POS-BASE-0001', $unmarked->fresh()->transaction_number);
        $this->assertSame(90, $firstCounts['transactions']);
        $this->assertSame(48, $firstCounts['menus']);
        $this->assertSame(10, $firstCounts['suppliers']);
        $this->assertSame(30, $firstCounts['customers']);
        $this->assertSame(30, Customer::query()->where('email', 'like', '%@customer.example.test')->distinct()->count('name'));
        $this->assertSame(10, $firstCounts['coupons']);
        $this->assertSame(3 * count(Ingredient::all()), $firstCounts['stocks']);
        $this->assertSame(6, $firstCounts['receipts']);
        $this->assertSame($firstCounts, [
            'transactions' => Transaction::where('transaction_number', 'like', 'JOOAL-%')->count(),
            'movements' => StockMovement::count(), 'menus' => Menu::where('slug', 'like', 'jooal-%')->count(),
            'suppliers' => Supplier::count(), 'customers' => Customer::count(), 'coupons' => Coupon::count(),
            'stocks' => BranchStock::count(), 'receipts' => StockReceipt::count(),
        ]);
        $this->assertSame($firstBalances, BranchStock::query()->orderBy('branch_id')->orderBy('ingredient_id')->pluck('quantity')->all());
        $this->assertSame(18, DB::table('branch_menu')->where('branch_id', $branches[0]->id)->where('is_available', true)->count());
        $this->assertSame(18, DB::table('branch_menu')->where('branch_id', $branches[1]->id)->where('is_available', true)->count());
        $this->assertSame(18, DB::table('branch_menu')->where('branch_id', $branches[2]->id)->where('is_available', true)->count());
        foreach ($branches as $branch) $this->assertSame(30, Transaction::query()->where('branch_id', $branch->id)->where('transaction_number', 'like', 'JOOAL-%')->count());
        $this->assertSame(35, Transaction::query()->where('transaction_number', 'like', 'JOOAL-%')->where('payment_method', 'qris')->count());
        $this->assertSame(120, TransactionItem::query()->whereHas('transaction', fn ($query) => $query->where('transaction_number', 'like', 'JOOAL-%'))->count());
        $this->assertSame(60, Attendance::query()->count());
        $this->assertSame(9, User::query()->whereIn('role', [User::ROLE_MANAGER, User::ROLE_CASHIER, User::ROLE_INVENTORY])->where('email', 'like', 'jooal.%@example.test')->count());
        $this->assertSame(9, User::query()->whereIn('role', [User::ROLE_MANAGER, User::ROLE_CASHIER, User::ROLE_INVENTORY])->where('email', 'like', 'jooal.%@example.test')->distinct()->count('name'));
        $this->assertSame(0, Transaction::query()->where('transaction_number', 'like', 'JOOAL-%')->whereRaw('grand_total <> (select coalesce(sum(line_total), 0) from transaction_items where transaction_items.transaction_id = transactions.id)')->count());
        $this->assertTrue(Menu::query()->where('slug', 'like', 'jooal-%')->get()->every(fn ($menu) => app(RecipeStockService::class)->menuRecipeReady($menu)));
        $availability = app(RecipeStockService::class);
        $bakeryMenu = Menu::query()->where('name', 'Banana Bread')->firstOrFail();
        $gelatoMenu = Menu::query()->where('name', 'Gelato Pistachio')->firstOrFail();
        $this->assertSame('out_of_stock', $availability->availability($bakeryMenu, $branches[0])['status']);
        $this->assertSame('low_stock', $availability->availability($gelatoMenu, $branches[1])['status']);
        $this->assertGreaterThan(0, $availability->availability($gelatoMenu, $branches[1])['available_portions']);
        $this->assertSame(0, Menu::where('name', 'like', 'DEMO%')->count());
        $this->assertSame('VIP', DiningTable::query()->where('branch_id', $branches[1]->id)->where('name', 'VIP')->value('name'));
    }

    public function test_branch_label_conflict_is_not_overwritten(): void
    {
        $branch = Branch::create(['code' => 'JOOAL-001', 'name' => 'Nama Milik Pemilik', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        Branch::create(['code' => 'JOOAL-002', 'name' => 'Gelato', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        Branch::create(['code' => 'JOOAL-003', 'name' => 'Coffee', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        User::factory()->create(['role' => User::ROLE_OWNER]);

        try {
            app(JooalDemoSeeder::class)->run();
            $this->fail('Seeder should stop on a branch identity conflict.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('unexpected or inactive', $exception->getMessage());
        }
        $this->assertSame('Nama Milik Pemilik', $branch->fresh()->name);
        $this->assertSame(0, Menu::count());
    }

    private function createLegacyDemoFixture(array $branches, User $owner): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'non-matcha'], ['name' => 'Non Matcha', 'sort_order' => 2, 'is_active' => true]);
        Category::query()->firstOrCreate(['slug' => 'pastry'], ['name' => 'Pastry', 'sort_order' => 3, 'is_active' => true]);
        $ingredientData = [
            ['Biji Kopi', 'demo-jooal-coffee-beans', 'g'], ['Susu', 'demo-jooal-milk', 'ml'],
            ['Gelato Base', 'demo-jooal-gelato-base', 'g'], ['Cup', 'demo-jooal-cup', 'pcs'], ['Pipet', 'demo-jooal-straw', 'pcs'],
        ];
        $ingredients = [];
        foreach ($ingredientData as [$name, $slug, $unit]) $ingredients[] = Ingredient::create(['name' => $name, 'slug' => $slug, 'unit' => $unit, 'minimum_stock' => 1, 'is_active' => true]);
        foreach ($branches as $branch) foreach ($ingredients as $ingredient) BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '100.000']);

        $latte = Menu::create(['category_id' => $category->id, 'name' => 'DEMO - JOOAL Latte', 'slug' => 'demo-jooal-latte', 'description' => 'Demo product; 18 g coffee, 180 ml milk, 1 cup, 1 straw.', 'price' => 26000, 'is_active' => true]);
        $affogato = Menu::create(['category_id' => $category->id, 'name' => 'DEMO - Gelato Affogato', 'slug' => 'demo-jooal-affogato', 'description' => 'Demo product; 18 g coffee, 80 g gelato, 1 cup.', 'price' => 32000, 'is_active' => true]);
        foreach ($branches as $branch) {
            $branch->menus()->attach($latte, ['price' => 26000, 'is_available' => true]);
            $branch->menus()->attach($affogato, ['price' => 32000, 'is_available' => true]);
        }
        $tag = \App\Models\Tag::create(['name' => 'DEMO JOOAL', 'slug' => 'demo-jooal', 'is_active' => true]);
        $latte->tags()->attach($tag); $affogato->tags()->attach($tag);
        foreach ([[$latte, 0, '18.000', 'g'], [$latte, 1, '180.000', 'ml'], [$latte, 3, '1.000', 'pcs'], [$latte, 4, '1.000', 'pcs'], [$affogato, 0, '18.000', 'g'], [$affogato, 2, '80.000', 'g'], [$affogato, 3, '1.000', 'pcs']] as [$menu, $ingredientIndex, $quantity, $unit]) RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredients[$ingredientIndex]->id, 'quantity' => $quantity, 'unit' => $unit, 'is_active' => true]);
        $addon = \App\Models\Topping::create(['name' => 'DEMO - Extra Shot', 'slug' => 'demo-extra-shot', 'price' => 5000, 'is_active' => true]);
        RecipeItem::create(['topping_id' => $addon->id, 'ingredient_id' => $ingredients[0]->id, 'quantity' => '18.000', 'unit' => 'g', 'is_active' => true]);

        $supplier = Supplier::create(['name' => 'DEMO - Pemasok JOOAL', 'contact_name' => 'Tim Demo', 'phone' => '0000000000', 'email' => 'demo-supplier@example.test', 'address' => 'Demo', 'notes' => 'Data demo, bukan pemasok operasional.', 'is_active' => true]);
        foreach ($branches as $i => $branch) {
            Customer::create(['branch_id' => $branch->id, 'name' => 'DEMO - Pelanggan '.$branch->code, 'is_active' => true]);
            DiningTable::create(['branch_id' => $branch->id, 'name' => 'DEMO-'.substr($branch->code, -3), 'capacity' => 2, 'is_active' => true]);
            $receipt = StockReceipt::create(['receipt_number' => 'DEMO-OPEN-JOOAL00'.($i + 1), 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'received_by' => $owner->id, 'reason' => 'DEMO opening stock', 'received_at' => '2026-10-20 08:00:00']);
            foreach ($ingredients as $j => $ingredient) {
                $movement = StockMovement::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'user_id' => $owner->id, 'reference_key' => 'demo:opening:'.$branch->code.':'.$ingredient->id, 'type' => 'stock_in', 'quantity_delta' => '100.000', 'quantity_before' => '0.000', 'quantity_after' => '100.000', 'reason' => 'DEMO opening stock']);
                StockReceiptItem::create(['stock_receipt_id' => $receipt->id, 'ingredient_id' => $ingredient->id, 'stock_movement_id' => $movement->id, 'quantity' => '100.000']);
            }
            $coupon = Coupon::firstOrCreate(['code' => 'JOOALDEMO10'], ['discount_type' => 'percentage', 'discount_value' => '10.00', 'starts_at' => '2026-01-01', 'ends_at' => '2027-01-01', 'usage_limit' => 100, 'used_count' => 0, 'is_active' => true]);
        }
        $customers = Customer::query()->where('name', 'like', 'DEMO - Pelanggan JOOAL-%')->get();
        foreach ($branches as $i => $branch) foreach ([1, 2] as $sequence) {
            $number = 'DEMO-'.$branch->code.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $transaction = Transaction::create(['transaction_number' => $number, 'idempotency_key' => 'demo-seed:'.$branch->code.':sale-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT), 'idempotency_fingerprint' => str_repeat('a', 64), 'cashier_id' => $owner->id, 'branch_id' => $branch->id, 'customer_id' => $customers[$i]->id, 'customer_name' => $customers[$i]->name, 'subtotal' => 26000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 26000, 'amount_paid' => 26000, 'change_amount' => 0, 'payment_method' => 'cash', 'status' => 'completed', 'sold_at' => '2026-10-20 10:00:00']);
            $menu = $sequence === 1 ? $latte : $affogato;
            TransactionItem::create(['transaction_id' => $transaction->id, 'menu_id' => $menu->id, 'menu_name' => $menu->name, 'topping_id' => $sequence === 1 ? $addon->id : null, 'quantity' => 1, 'unit_price' => 26000, 'menu_price' => $menu->price, 'branch_price' => 26000, 'line_total' => 26000, 'item_options' => ['toppings' => $sequence === 1 ? [$addon->name] : []]]);
            $movementCount = $sequence === 1 ? 4 : 3;
            for ($j = 0; $j < $movementCount; $j++) StockMovement::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredients[$j]->id, 'user_id' => $owner->id, 'transaction_id' => $transaction->id, 'reference_key' => 'demo:sale:'.$number.':'.$ingredients[$j]->id, 'type' => 'sale', 'quantity_delta' => '-1.000', 'quantity_before' => '100.000', 'quantity_after' => '99.000', 'reason' => 'DEMO historical sale '.$number]);
        }
    }
}

