<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Topping;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonAndOperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-06-15';

    public function test_owner_can_manage_addons_and_non_owner_cannot_access_administration(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->get(route('addons.index'))->assertOk()
            ->assertSee('Add-on')
            ->assertSee('<dialog id="addon-create-dialog"', false)
            ->assertSee('data-filter-search="addon-list"', false);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]))->get(route('addons.index'))->assertForbidden();

        $this->actingAs($owner)->post(route('addons.store'), ['name' => 'Extra Espresso', 'slug' => '', 'price' => '5000'])
            ->assertRedirect(route('addons.index'));
        $addon = Topping::where('name', 'Extra Espresso')->firstOrFail();
        $this->assertSame('extra-espresso', $addon->slug);

        $this->put(route('addons.update', $addon), ['name' => 'Double Espresso', 'slug' => 'double-espresso', 'price' => '7000'])
            ->assertRedirect(route('addons.index'));
        $this->get(route('addons.edit', $addon))->assertOk()->assertSee('Double Espresso');
        $this->assertDatabaseHas('toppings', ['id' => $addon->id, 'name' => 'Double Espresso', 'price' => '7000.00']);
        $this->patch(route('addons.toggle-status', $addon))->assertRedirect(route('addons.index'));
        $this->assertDatabaseHas('toppings', ['id' => $addon->id, 'is_active' => 0]);
    }

    public function test_addon_validation_pos_pricing_and_recipe_relationship_remain_integrated(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->post(route('addons.store'), ['name' => '', 'price' => '-1'])
            ->assertSessionHasErrors(['name', 'price']);
        $this->post(route('addons.store'), ['name' => 'Extra Shot', 'price' => '4500'])->assertRedirect();
        $addon = Topping::where('name', 'Extra Shot')->firstOrFail();

        $branch = $this->branch('ADDON-01');
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $category = Category::create(['name' => 'Coffee', 'slug' => 'addon-coffee', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Latte', 'slug' => 'addon-latte', 'price' => 20000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);
        $ingredient = Ingredient::create(['name' => 'Espresso', 'slug' => 'addon-espresso', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit' => 'gram', 'is_active' => true]);
        $recipe = RecipeItem::create(['topping_id' => $addon->id, 'ingredient_id' => $ingredient->id, 'quantity' => 18, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100]);
        $this->assertSame($addon->id, $recipe->fresh()->topping_id);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->post(route('dashboard.cart.store'), ['menu_id' => $menu->id, 'topping_ids' => [$addon->id], 'quantity' => 1])
            ->assertSessionHas('pos_cart', function ($cart) use ($addon) {
                return (float) $cart[0]['unit_price'] === 24500.0 && in_array($addon->id, $cart[0]['topping_ids'], true);
            });
    }

    public function test_payment_method_report_aggregates_completed_transactions_by_date_and_branch(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        [$first, $second, $menu] = $this->reportContext();
        $this->transaction('PAY-CASH', $first, $menu, 'cash', 10000, self::DATE.' 10:00:00');
        $this->transaction('PAY-CASH-2', $first, $menu, 'cash', 5000, self::DATE.' 11:00:00');
        $this->transaction('PAY-QRIS', $second, $menu, 'qris', 20000, self::DATE.' 12:00:00');
        $this->transaction('PAY-OLD-DATE', $first, $menu, 'qris', 9000, '2026-06-14 12:00:00');
        $this->transaction('PAY-OUTSIDE-RANGE', $first, $menu, 'qris', 8000, '2026-06-16 12:00:00');
        $this->transaction('PAY-UNMAPPED', null, $menu, 'cash', 7000, self::DATE.' 13:00:00');

        $response = $this->actingAs($owner)->get(route('reports.payment-methods', ['date' => self::DATE]))->assertOk();
        $response->assertViewHas('transactionCount', 3)->assertViewHas('totalAmount', 35000.0);
        $response->assertViewHas('rows', function ($rows) {
            return $rows->count() === 2
                && (int) $rows->firstWhere('payment_method', 'cash')->transaction_count === 2
                && (float) $rows->firstWhere('payment_method', 'cash')->total_amount === 15000.0;
        });
        $this->get(route('reports.payment-methods', ['date' => self::DATE, 'branch_id' => $second->id]))
            ->assertOk()->assertViewHas('transactionCount', 1)->assertViewHas('totalAmount', 20000.0);

        $this->get(route('reports.payment-methods', ['date_from' => '2026-06-14', 'date_to' => self::DATE, 'branch_id' => $first->id]))
            ->assertOk()->assertSee('Dari tanggal')->assertSee('Sampai tanggal')
            ->assertViewHas('transactionCount', 3)->assertViewHas('totalAmount', 24000.0);
        $this->get(route('reports.payment-methods', ['date_from' => '2026-06-16', 'date_to' => self::DATE]))
            ->assertRedirect()->assertSessionHasErrors('date_to');
    }

    public function test_manager_payment_method_report_cannot_be_widened_to_another_branch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        [$assigned, $other, $menu] = $this->reportContext();
        $manager->branches()->attach($assigned, ['is_default' => true, 'is_active' => true]);
        $this->transaction('MGR-PAY-1', $assigned, $menu, 'cash', 12000, self::DATE.' 10:00:00');
        $this->transaction('MGR-PAY-2', $other, $menu, 'qris', 40000, self::DATE.' 10:00:00');

        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])
            ->get(route('reports.payment-methods', ['date' => self::DATE, 'branch_id' => $other->id]))->assertForbidden();
        $this->withSession(['active_branch_id' => $assigned->id])->get(route('reports.payment-methods', ['date' => self::DATE]))
            ->assertOk()->assertViewHas('transactionCount', 1)->assertViewHas('totalAmount', 12000.0);
    }

    public function test_owner_refund_report_filters_recorded_date_includes_inactive_branch_and_counts_refund_header_once(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        [$active, $inactive, $menu] = $this->reportContext();
        $inactive->update(['is_active' => false]);
        $tx = $this->transaction('REF-REPORT-1', $active, $menu, 'cash', 20000, self::DATE.' 10:00:00');
        $itemA = $tx->items()->firstOrFail();
        $itemB = TransactionItem::create(['transaction_id' => $tx->id, 'menu_id' => $menu->id, 'menu_name' => $menu->name, 'quantity' => 1, 'unit_price' => 10000, 'menu_price' => 10000, 'branch_price' => 10000, 'line_total' => 10000]);
        $refund = $this->refund($tx, $active, $owner, 'RF-REPORT-MULTI', 7500, 'recorded', self::DATE.' 14:00:00', [[$itemA, 1, 2500], [$itemB, 1, 5000]]);
        $inactiveTx = $this->transaction('REF-REPORT-2', $inactive, $menu, 'qris', 15000, self::DATE.' 11:00:00');
        $inactiveRefund = $this->refund($inactiveTx, $inactive, $owner, 'RF-REPORT-INACTIVE', 3000, 'manually_confirmed', self::DATE.' 15:00:00', [[$inactiveTx->items()->firstOrFail(), 1, 3000]]);
        $mismatchedRefund = $this->refund($inactiveTx, $active, $owner, 'RF-REPORT-MISMATCH', 9000, 'recorded', self::DATE.' 16:00:00', [[$inactiveTx->items()->firstOrFail(), 1, 9000]]);
        $this->refund($tx, $active, $owner, 'RF-REPORT-OLD', 500, 'recorded', '2026-06-14 16:00:00', [[$itemA, 1, 500]]);
        $this->refund($tx, $active, $owner, 'RF-REPORT-FUTURE', 700, 'recorded', '2026-06-16 10:00:00', [[$itemA, 1, 700]]);

        $this->actingAs($owner)->get(route('reports.refunds', ['date' => self::DATE]))->assertOk()
            ->assertSee('Dicatat — belum terverifikasi')->assertSee('Dikonfirmasi manual — tidak diverifikasi sistem')
            ->assertSee('sistem tidak memverifikasi', false)
            ->assertViewHas('totalAmount', 10500.0)
            ->assertViewHas('refunds', function ($refunds) use ($refund, $inactiveRefund) {
                return $refunds->count() === 2 && $refunds->contains('id', $refund->id) && $refunds->contains('id', $inactiveRefund->id);
            });

        $this->get(route('reports.refunds', ['date' => self::DATE, 'branch_id' => $inactive->id]))
            ->assertOk()->assertViewHas('totalAmount', 3000.0)->assertViewHas('refunds', function ($refunds) use ($inactiveRefund) {
                return $refunds->count() === 1 && $refunds->first()->id === $inactiveRefund->id;
            });
        $this->assertNotContains($mismatchedRefund->id, $this->get(route('reports.refunds', ['date' => self::DATE]))->viewData('refunds')->pluck('id')->all());
        $this->assertSame(2, RefundItem::where('refund_id', $refund->id)->count());

        $before = $this->get(route('reports.refunds', ['date_from' => '2026-06-14', 'date_to' => self::DATE, 'branch_id' => $active->id]))
            ->assertOk()->viewData('refunds');
        $this->assertSame(2, $before->count());
        $this->assertSame($refund->id, $before->first()->id);
    }

    public function test_manager_refund_report_is_limited_to_assigned_active_branch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        [$assigned, $other, $menu] = $this->reportContext();
        $manager->branches()->attach($assigned, ['is_default' => true, 'is_active' => true]);
        $txA = $this->transaction('MGR-RF-A', $assigned, $menu, 'cash', 10000, self::DATE.' 10:00:00');
        $txB = $this->transaction('MGR-RF-B', $other, $menu, 'cash', 10000, self::DATE.' 10:00:00');
        $this->refund($txA, $assigned, $manager, 'MGR-RF-1', 2000, 'recorded', self::DATE.' 12:00:00', [[$txA->items()->firstOrFail(), 1, 2000]]);
        $this->refund($txB, $other, $manager, 'MGR-RF-2', 3000, 'recorded', self::DATE.' 12:00:00', [[$txB->items()->firstOrFail(), 1, 3000]]);

        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])
            ->get(route('reports.refunds', ['date' => self::DATE, 'branch_id' => $other->id]))->assertForbidden();
        $this->withSession(['active_branch_id' => $assigned->id])->get(route('reports.refunds', ['date' => self::DATE]))
            ->assertOk()->assertViewHas('totalAmount', 2000.0)->assertViewHas('refunds', function ($refunds) {
                return $refunds->count() === 1 && $refunds->first()->refund_number === 'MGR-RF-1';
            });
    }

    private function reportContext(): array
    {
        $first = $this->branch('RPT-01');
        $second = $this->branch('RPT-02');
        $category = Category::create(['name' => 'Report', 'slug' => 'report-category', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Report Drink', 'slug' => 'report-drink', 'price' => 10000, 'is_active' => true]);

        return [$first, $second, $menu];
    }

    private function branch(string $code): Branch
    {
        return Branch::create(['code' => $code, 'name' => $code.' Branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }

    private function transaction(string $number, ?Branch $branch, Menu $menu, string $method, int $amount, string $soldAt): Transaction
    {
        $tx = Transaction::create(['transaction_number' => $number, 'branch_id' => optional($branch)->id, 'customer_name' => 'Report test', 'service_type' => 'take_away', 'fulfillment_type' => 'self_service', 'subtotal' => $amount, 'grand_total' => $amount, 'amount_paid' => $amount, 'payment_method' => $method, 'status' => 'completed', 'sold_at' => $soldAt]);
        TransactionItem::create(['transaction_id' => $tx->id, 'menu_id' => $menu->id, 'menu_name' => $menu->name, 'quantity' => 1, 'unit_price' => $amount, 'menu_price' => $menu->price, 'branch_price' => $menu->price, 'line_total' => $amount]);

        return $tx;
    }

    private function refund(Transaction $tx, Branch $branch, User $actor, string $number, int $amount, string $status, string $recordedAt, array $items): Refund
    {
        $refund = Refund::create(['refund_number' => $number, 'transaction_id' => $tx->id, 'branch_id' => $branch->id, 'recorded_by' => $actor->id, 'confirmed_by' => $status === 'manually_confirmed' ? $actor->id : null, 'refund_type' => 'partial', 'amount' => $amount, 'status' => $status, 'manual_method' => 'cash', 'reason' => 'Test report refund', 'idempotency_key' => 'KEY-'.$number, 'request_fingerprint' => str_repeat('b', 64), 'recorded_at' => $recordedAt]);
        foreach ($items as [$transactionItem, $quantity, $itemAmount]) {
            RefundItem::create(['refund_id' => $refund->id, 'transaction_item_id' => $transactionItem->id, 'quantity' => $quantity, 'amount' => $itemAmount]);
        }

        return $refund;
    }
}
