<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\User;
use App\Services\RecipeStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_is_available_to_owner_manager_and_inventory_but_not_cashier(): void
    {
        [$branch] = $this->branchAndIngredient();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $manager->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $pages = [route('inventory.stock-in.index'), route('inventory.stocks.index'), route('inventory.movements.index')];
        foreach ($pages as $page) {
            $this->actingAs($owner)->get($page)->assertOk();
            $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->get($page)->assertOk();
            $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])->get($page)->assertOk();
            $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]))->get($page)->assertForbidden();
        }
        $this->actingAs($owner)->get(route('inventory.materials.index'))->assertOk()->assertSee('Bahan Baku');
        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->get(route('inventory.materials.index'))->assertForbidden();
        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])->get(route('inventory.materials.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]))->get(route('inventory.materials.index'))->assertForbidden();
    }

    public function test_inventory_submenu_routes_render_separate_screens_and_keep_branch_scope_filter(): void
    {
        [$branch] = $this->branchAndIngredient();
        $other = $this->branch('INV-OTHER');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get(route('inventory.stock-in.index', ['branch_id' => $branch->id]))
            ->assertOk()->assertSee('Stok Masuk')->assertSee('Simpan penerimaan')->assertDontSee('Koreksi stok')
            ->assertSee('lg:col-span-2', false)->assertSee('sm:grid-cols-2', false)
            ->assertSee('Jumlah diterima')->assertSee('Alasan / referensi penerimaan');
        $this->actingAs($owner)->get(route('inventory.stocks.index', ['branch_id' => $branch->id]))
            ->assertOk()->assertSee('Stok per Cabang')->assertSee('Koreksi stok');
        $this->actingAs($owner)->get(route('inventory.movements.index', ['branch_id' => $other->id]))
            ->assertOk()->assertSee('Riwayat Pergerakan Stok')->assertSee('Stock movement');
        $this->actingAs($owner)->get(route('inventory.materials.index'))
            ->assertOk()->assertSee('Tambah Bahan')->assertSee('Bahan baku terpusat');
    }

    public function test_inventory_screens_render_responsive_containers_and_locally_scrollable_tables(): void
    {
        [$branch] = $this->branchAndIngredient();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        foreach ([
            route('inventory.stock-in.index'),
            route('inventory.stocks.index'),
            route('inventory.movements.index'),
        ] as $path) {
            $html = $this->actingAs($owner)->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('inventory-page-content', $html);
            $this->assertStringContainsString('overflow-x-auto', $html);
            $this->assertStringContainsString('inventory-panel', $html);
        }

        $styles = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.inventory-panel { min-width: 0; overflow: hidden;', $styles);
        $this->assertStringContainsString('.inventory-panel .overflow-x-auto', $styles);
        $this->assertStringContainsString('@media (min-width: 1280px) { .inventory-filter-row', $styles);
    }

    public function test_stock_in_and_adjustment_create_movement_and_update_only_selected_branch(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $otherBranch = $this->branch('BDG-01');
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $supplier = $this->supplier();

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->post('/inventory/stock-in', ['branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'items' => [['ingredient_id' => $ingredient->id, 'quantity' => '10.500']], 'reason' => 'Penerimaan supplier'])
            ->assertRedirect(route('inventory.stock-in.index', ['branch_id' => $branch->id]));
        $this->assertSame('10.500', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->first()->quantity);

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->post('/inventory/adjust', ['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'direction' => 'decrease', 'quantity' => '2.500', 'reason' => 'Selisih opname'])
            ->assertRedirect(route('inventory.stocks.index', ['branch_id' => $branch->id]));

        $this->assertSame('8.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->first()->quantity);
        $this->assertSame(2, StockMovement::where('branch_id', $branch->id)->count());
        $this->assertDatabaseMissing('branch_stocks', ['branch_id' => $otherBranch->id, 'ingredient_id' => $ingredient->id]);
    }

    public function test_supplier_receipt_increases_recipe_capacity_from_branch_material_stock(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $ingredient->update(['unit' => 'g']);
        $category = Category::create(['name' => 'Coffee', 'slug' => 'receipt-capacity-coffee', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Capacity Coffee', 'slug' => 'capacity-coffee', 'price' => 20000, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => '500.000', 'unit' => 'g', 'is_active' => true]);
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $stock = BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '1000.000']);
        $service = app(RecipeStockService::class);
        $this->assertSame(2, $service->maximumPortions($menu, $branch));

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])->post(route('inventory.stock-in'), [
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
            'items' => [['ingredient_id' => $ingredient->id, 'quantity' => '1500.000']],
            'reason' => 'Pengisian ulang bahan untuk tes kapasitas',
        ])->assertRedirect(route('inventory.stock-in.index', ['branch_id' => $branch->id]));

        $this->assertSame(5, $service->maximumPortions($menu, $branch));
        $this->assertSame('2500.000', (string) $stock->fresh()->quantity);
        $this->assertSame('1500.000', (string) StockMovement::where('type', 'stock_in')->value('quantity_delta'));
    }

    public function test_stock_in_accepts_up_to_three_decimals_and_keeps_balance_and_ledger_consistent(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        foreach (['1', '1.5', '1.234'] as $quantity) {
            $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
                ->post(route('inventory.stock-in'), [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'items' => [['ingredient_id' => $ingredient->id, 'quantity' => $quantity]],
                    'reason' => 'Penerimaan presisi',
                ])->assertRedirect(route('inventory.stock-in.index', ['branch_id' => $branch->id]));
        }

        $this->assertSame('3.734', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $movements = StockMovement::where('branch_id', $branch->id)->where('type', 'stock_in')->orderBy('id')->get();
        $this->assertSame(['1.000', '1.500', '1.234'], $movements->pluck('quantity_delta')->map(fn ($value) => (string) $value)->all());
        $this->assertSame(['1.000', '2.500', '3.734'], $movements->pluck('quantity_after')->map(fn ($value) => (string) $value)->all());

        $receiptQuantities = \App\Models\StockReceiptItem::query()->orderBy('id')->pluck('quantity')->map(fn ($value) => (string) $value)->all();
        $this->assertSame(['1.000', '1.500', '1.234'], $receiptQuantities);
    }

    public function test_stock_in_rejects_excess_precision_and_values_above_decimal_capacity(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        foreach (['1.2345', '1000000000.000'] as $quantity) {
            $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
                ->post(route('inventory.stock-in'), [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'items' => [['ingredient_id' => $ingredient->id, 'quantity' => $quantity]],
                    'reason' => 'Input tidak valid',
                ])->assertSessionHasErrors('items.0.quantity');
        }

        $this->assertSame(0, StockReceipt::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, BranchStock::count());
    }

    public function test_stock_in_accepts_the_maximum_decimal_value_and_rejects_balance_overflow(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->post(route('inventory.stock-in'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'items' => [['ingredient_id' => $ingredient->id, 'quantity' => '999999999.999']],
                'reason' => 'Batas DECIMAL',
            ])->assertRedirect(route('inventory.stock-in.index', ['branch_id' => $branch->id]));

        $this->assertSame('999999999.999', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
        $movement = StockMovement::where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame('999999999.999', (string) $movement->quantity_delta);
        $this->assertSame('999999999.999', (string) $movement->quantity_after);

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->post(route('inventory.stock-in'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'items' => [['ingredient_id' => $ingredient->id, 'quantity' => '0.001']],
                'reason' => 'Saldo melampaui batas',
            ])->assertSessionHasErrors('items');

        $this->assertSame(1, StockReceipt::count());
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('999999999.999', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
    }

    public function test_replaying_the_same_stock_in_submission_does_not_add_stock_twice(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $payload = [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'submission_key' => '4ba0ef44-6a1f-4ba6-9e3b-0b5f04bdf6af',
            'items' => [['ingredient_id' => $ingredient->id, 'quantity' => '1.234']],
            'reason' => 'Penerimaan idempoten',
        ];

        foreach ([1, 2] as $attempt) {
            $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
                ->post(route('inventory.stock-in'), $payload)
                ->assertRedirect(route('inventory.stock-in.index', ['branch_id' => $branch->id]));
        }

        $this->assertSame(1, StockReceipt::count());
        $this->assertSame(1, StockMovement::where('type', 'stock_in')->count());
        $this->assertSame('1.234', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));

        $payload['items'][0]['quantity'] = '2.000';
        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->post(route('inventory.stock-in'), $payload)->assertSessionHasErrors('submission_key');
        $this->assertSame(1, StockReceipt::count());
        $this->assertSame('1.234', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
    }

    public function test_invalid_adjustment_and_negative_result_are_rejected(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->post('/inventory/adjust', ['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'direction' => 'decrease', 'quantity' => 1, 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->post('/inventory/adjust', ['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'direction' => 'decrease', 'quantity' => 1, 'reason' => 'Terlalu banyak'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('stock_movements', ['branch_id' => $branch->id]);
    }

    public function test_non_owner_cannot_write_to_another_branch(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $otherBranch = $this->branch('BDG-01');
        $supplier = $this->supplier();

        foreach ([User::ROLE_MANAGER, User::ROLE_INVENTORY] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $user->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

            $this->actingAs($user)->withSession(['active_branch_id' => $branch->id])
                ->post('/inventory/stock-in', ['branch_id' => $otherBranch->id, 'supplier_id' => $supplier->id, 'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 5]], 'reason' => 'Percobaan'])
                ->assertForbidden();
        }
    }

    public function test_owner_sees_branches_separately_and_low_stock_warning(): void
    {
        [$firstBranch, $ingredient] = $this->branchAndIngredient();
        $secondBranch = $this->branch('BDG-01');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $supplier = $this->supplier();

        $this->actingAs($owner)->post('/inventory/stock-in', ['branch_id' => $firstBranch->id, 'supplier_id' => $supplier->id, 'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 10]], 'reason' => 'Stok cabang satu']);
        $this->actingAs($owner)->post('/inventory/stock-in', ['branch_id' => $secondBranch->id, 'supplier_id' => $supplier->id, 'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 2]], 'reason' => 'Stok cabang dua']);

        $this->actingAs($owner)->get('/dashboard/inventory')
            ->assertOk()
            ->assertSee($firstBranch->code)
            ->assertSee($secondBranch->code)
            ->assertSee('Stok rendah');
        $this->actingAs($owner)->get(route('inventory.stocks.index', ['branch_id' => $firstBranch->id]))
            ->assertOk()->assertSee('data-filter-text="'.strtolower($firstBranch->code), false)
            ->assertDontSee('data-filter-text="'.strtolower($secondBranch->code), false);
        $this->assertSame('10.000', (string) BranchStock::where(['branch_id' => $firstBranch->id, 'ingredient_id' => $ingredient->id])->first()->quantity);
        $this->assertSame('2.000', (string) BranchStock::where(['branch_id' => $secondBranch->id, 'ingredient_id' => $ingredient->id])->first()->quantity);
    }

    public function test_material_master_create_uses_its_dedicated_owner_route_and_reopens_on_validation_error(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $materialsUrl = route('inventory.materials.index');

        $this->actingAs($owner)->from($materialsUrl)->post(route('inventory.ingredients.store'), [
            '_form' => 'ingredient-create', 'name' => 'Bahan Tanpa Satuan', 'minimum_stock' => 0,
        ])->assertSessionHasErrors('unit');
        $this->get($materialsUrl)->assertOk()->assertSee('data-open-on-load', false)->assertSee('The unit field is required.');

        $this->actingAs($owner)->post(route('inventory.ingredients.store'), [
            '_form' => 'ingredient-create', 'name' => 'Sirup Nusa', 'slug' => 'syrup-nusa-test', 'unit' => 'ml', 'minimum_stock' => '1.000',
        ])->assertRedirect($materialsUrl);
        $this->assertDatabaseHas('ingredients', ['slug' => 'syrup-nusa-test', 'unit' => 'ml']);
    }

    public function test_owner_can_manage_suppliers_and_supplier_used_by_receipt_cannot_be_deleted(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $this->actingAs($owner)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($manager)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($manager)->post(route('suppliers.store'), ['name' => 'Tidak Berwenang'])->assertForbidden();
        $this->actingAs($owner)->post(route('suppliers.store'), ['name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['name', 'email']);
        $this->actingAs($owner)->post(route('suppliers.store'), ['name' => 'PT Kopi Nusantara', 'contact_name' => 'Sari', 'phone' => '0812', 'email' => 'vendor@example.test', 'address' => 'Bandung', 'notes' => 'Biji kopi'])->assertRedirect();
        $supplier = Supplier::where('name', 'PT Kopi Nusantara')->firstOrFail();
        $this->actingAs($owner)->put(route('suppliers.update', $supplier), ['name' => 'Kopi Nusantara', 'contact_name' => 'Sari', 'phone' => '0812', 'email' => 'vendor@example.test', 'address' => 'Bandung', 'notes' => 'Aktif'])->assertRedirect();
        $this->actingAs($owner)->get(route('suppliers.edit', $supplier))->assertOk()->assertSee('Kopi Nusantara');

        [$branch, $ingredient] = $this->branchAndIngredient();
        $receiptBy = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $receiptBy->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $this->actingAs($receiptBy)->withSession(['active_branch_id' => $branch->id])->post(route('inventory.stock-in'), [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 3]],
            'reason' => 'Penerimaan terdokumentasi',
        ])->assertRedirect();

        $this->assertSame(1, $supplier->fresh()->stockReceipts()->count());
        $this->delete('/suppliers/'.$supplier->id)->assertStatus(405);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Kopi Nusantara']);
        $this->actingAs($owner)->patch(route('suppliers.toggle-status', $supplier))->assertRedirect();
        $inactive = $supplier->fresh();
        $this->actingAs($receiptBy)->withSession(['active_branch_id' => $branch->id])->post(route('inventory.stock-in'), [
            'branch_id' => $branch->id,
            'supplier_id' => $inactive->id,
            'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 2]],
            'reason' => 'Ditolak pemasok nonaktif',
        ])->assertSessionHasErrors('supplier_id');
        $this->assertSame(1, StockReceipt::count());
        $receipt = $inactive->stockReceipts()->firstOrFail();
        $this->actingAs($owner)->get(route('inventory.receipts.show', $receipt))->assertOk()->assertSee('(nonaktif)');
    }

    public function test_multi_material_receipt_links_supplier_stock_movements_and_keeps_legacy_movement_visible(): void
    {
        $branch = $this->branch('RCV-01');
        $first = Ingredient::create(['name' => 'Biji Kopi', 'slug' => 'beans-receipt', 'unit' => 'gram', 'minimum_stock' => 5, 'is_active' => true]);
        $second = Ingredient::create(['name' => 'Susu', 'slug' => 'milk-receipt', 'unit' => 'ml', 'minimum_stock' => 10, 'is_active' => true]);
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])->post(route('inventory.stock-in'), [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [['ingredient_id' => $first->id, 'quantity' => '12.500'], ['ingredient_id' => $second->id, 'quantity' => '3.250']],
            'reason' => 'Invoice SUP-77',
        ])->assertRedirect();

        $this->assertSame('12.500', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $first->id])->value('quantity'));
        $this->assertSame('3.250', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $second->id])->value('quantity'));
        $this->assertSame(2, StockMovement::where('branch_id', $branch->id)->where('type', 'stock_in')->count());
        $receipt = StockReceipt::with('items.movement')->firstOrFail();
        $this->assertSame($supplier->id, $receipt->supplier_id);
        $this->assertSame(2, $receipt->items->count());
        $this->assertSame(2, $receipt->items->filter(fn ($line) => $line->movement && $line->movement->branch_id === $branch->id)->count());

        $legacy = StockMovement::create(['branch_id' => $branch->id, 'ingredient_id' => $first->id, 'user_id' => $inventory->id, 'type' => 'stock_in', 'quantity_delta' => 1, 'quantity_before' => 12.5, 'quantity_after' => 13.5, 'reason' => 'Legacy receipt']);
        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])->get(route('inventory.stock-in.index'))
            ->assertOk()->assertSee($receipt->receipt_number)->assertSee($supplier->name);
        $this->get(route('inventory.movements.index'))->assertOk()->assertSee('Penerimaan lama')->assertSee('Tanpa pemasok (lama)');
        $this->get(route('inventory.stock-in.index', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]))
            ->assertOk()->assertSee('Dari tanggal')->assertSee('Sampai tanggal')
            ->assertViewHas('receipts', fn ($receipts) => $receipts->contains('id', $receipt->id));
        $this->get(route('inventory.movements.index', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]))
            ->assertOk()->assertViewHas('movements', fn ($movements) => $movements->count() === 3);
        $this->get(route('inventory.movements.index', ['date_from' => today()->toDateString(), 'date_to' => today()->subDay()->toDateString()]))
            ->assertRedirect()->assertSessionHasErrors('date_to');
        $this->get(route('inventory.receipts.show', $receipt))->assertOk()->assertSee($receipt->receipt_number)->assertSee('Invoice SUP-77');

        $otherBranch = $this->branch('RCV-OTHER');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $manager->branches()->attach($otherBranch, ['is_default' => true, 'is_active' => true]);
        $this->actingAs($manager)->withSession(['active_branch_id' => $otherBranch->id])
            ->get(route('inventory.receipts.show', $receipt))->assertForbidden();
        $this->assertNull($legacy->fresh()->stockReceiptItem);
    }

    public function test_multi_material_stock_in_rolls_back_header_balance_and_ledger_when_a_later_movement_fails(): void
    {
        $branch = $this->branch('RCV-ATOMIC');
        $first = Ingredient::create(['name' => 'Bahan A', 'slug' => 'receipt-a', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        $second = Ingredient::create(['name' => 'Bahan B', 'slug' => 'receipt-b', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $eventName = 'eloquent.creating: '.StockMovement::class;
        \Illuminate\Support\Facades\Event::listen($eventName, function (StockMovement $movement) use ($second) {
            if ((int) $movement->ingredient_id === (int) $second->id) {
                throw new \RuntimeException('Simulated ledger write failure');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])->post(route('inventory.stock-in'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'items' => [['ingredient_id' => $first->id, 'quantity' => 5], ['ingredient_id' => $second->id, 'quantity' => 7]],
                'reason' => 'Uji atomik',
            ]);
            $this->fail('Expected the simulated movement failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated ledger write failure', $exception->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($eventName);
        }

        $this->assertSame(0, StockReceipt::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, BranchStock::count());
    }

    public function test_stock_in_rolls_back_header_balance_and_movement_when_receipt_item_storage_fails(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $supplier = $this->supplier();
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $eventName = 'eloquent.creating: '.\App\Models\StockReceiptItem::class;
        \Illuminate\Support\Facades\Event::listen($eventName, function () {
            throw new \RuntimeException('Simulated receipt item write failure');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])->post(route('inventory.stock-in'), [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'items' => [['ingredient_id' => $ingredient->id, 'quantity' => '1.234']],
                'reason' => 'Uji rollback item',
            ]);
            $this->fail('Expected the simulated receipt item failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated receipt item write failure', $exception->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($eventName);
        }

        $this->assertSame(0, StockReceipt::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, BranchStock::count());
    }

    public function test_material_edit_updates_minimum_but_rejects_unit_change_that_would_break_recipe(): void
    {
        [$branch, $ingredient] = $this->branchAndIngredient();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->get(route('inventory.ingredients.edit', $ingredient))->assertOk()->assertSee($ingredient->name);
        $this->put(route('inventory.ingredients.update', $ingredient), ['name' => 'Coffee Bean Select', 'slug' => $ingredient->slug, 'unit' => 'kg', 'minimum_stock' => '7.500'])
            ->assertRedirect();
        $this->assertDatabaseHas('ingredients', ['id' => $ingredient->id, 'name' => 'Coffee Bean Select', 'minimum_stock' => '7.500']);

        $category = Category::create(['name' => 'Coffee', 'slug' => 'material-coffee', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Coffee', 'slug' => 'material-coffee-item', 'price' => 10, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit' => 'kg', 'is_active' => true]);
        $this->put(route('inventory.ingredients.update', $ingredient), ['name' => 'Coffee Bean Select', 'slug' => $ingredient->slug, 'unit' => 'gram', 'minimum_stock' => '7.500'])
            ->assertSessionHasErrors('unit');
        $this->assertSame('kg', $ingredient->fresh()->unit);
    }

    private function branchAndIngredient(): array
    {
        $branch = $this->branch('JKT-01');
        $ingredient = Ingredient::create(['name' => 'Coffee Bean', 'slug' => 'coffee-bean', 'unit' => 'kg', 'minimum_stock' => 5, 'is_active' => true]);

        return [$branch, $ingredient];
    }

    private function branch(string $code): Branch
    {
        return Branch::create(['code' => $code, 'name' => 'JOOAL '.$code, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Supplier Test', 'is_active' => true]);
    }
}
