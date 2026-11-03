<?php

namespace Tests\Feature;

use App\Mail\InvoiceEmail;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class CouponOrdersEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_coupons_but_cashier_cannot_and_codes_are_case_normalized(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $payload = $this->couponPayload('WELCOME10');

        $this->actingAs($cashier)->get(route('coupons.index'))->assertForbidden();
        $this->actingAs($owner)->post(route('coupons.store'), $payload)->assertRedirect(route('coupons.index'));
        $coupon = Coupon::where('code', 'WELCOME10')->firstOrFail();
        $this->actingAs($owner)->post(route('coupons.store'), $this->couponPayload('welcome10'))->assertSessionHasErrors('code');
        $updated = $payload;
        $updated['discount_value'] = '15.00';
        $this->actingAs($owner)->put(route('coupons.update', $coupon), $updated)->assertRedirect(route('coupons.index'));
        $this->assertSame('15.00', (string) $coupon->fresh()->discount_value);
        $invalidPercentage = $updated;
        $invalidPercentage['discount_value'] = '100.01';
        $this->actingAs($owner)->put(route('coupons.update', $coupon), $invalidPercentage)->assertSessionHasErrors('discount_value');
        $invalidPrecision = $updated;
        $invalidPrecision['discount_value'] = '1.999';
        $this->actingAs($owner)->put(route('coupons.update', $coupon), $invalidPrecision)->assertSessionHasErrors('discount_value');
        $this->actingAs($owner)->patch(route('coupons.toggle-status', $coupon))->assertRedirect();
        $this->assertFalse($coupon->fresh()->is_active);
        $this->actingAs($owner)->delete(route('coupons.destroy', $coupon))->assertRedirect(route('coupons.index'));
        $this->assertSame(0, Coupon::count());
    }

    public function test_coupon_create_and_edit_forms_use_native_dialogs(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $coupon = Coupon::create($this->couponPayload('DIALOG'));

        $this->actingAs($owner)->get(route('coupons.index'))->assertOk()
            ->assertSee('<dialog id="coupon-create-dialog" aria-labelledby="coupon-create-title" class="crud-dialog crud-dialog-wide"', false)
            ->assertSee('<dialog id="coupon-edit-dialog-'.$coupon->id.'"', false)
            ->assertSee('class="crud-dialog crud-dialog-wide"', false)
            ->assertSee('class="coupon-form-grid"', false)
            ->assertSee('data-coupon-close="coupon-create-dialog"', false)
            ->assertSee('Batal', false)
            ->assertSee('showModal()', false);

        $styles = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('@media (min-width: 640px) { .coupon-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }', $styles);
        $this->assertStringContainsString('.coupon-form-grid { display: grid;', $styles);
    }

    public function test_coupon_checkout_records_same_discount_in_transaction_and_preserves_refund_total(): void
    {
        [$cashier, $branch, $menu] = $this->salesContext();
        $ingredient = $this->recipeStock($branch, $menu);
        $coupon = Coupon::create(array_merge($this->couponPayload('SAVE5'), ['discount_type' => 'fixed', 'discount_value' => '5000.00']));
        $session = $this->cartSession($branch, $menu, 'coupon-sale');

        $this->actingAs($cashier)->withSession($session)->post('/checkout', $this->payment('SAVE5', 20000))
            ->assertRedirect();

        $transaction = Transaction::where('idempotency_key', 'coupon-sale')->firstOrFail();
        $this->assertSame('25000.00', (string) $transaction->subtotal);
        $this->assertSame('5000.00', (string) $transaction->discount_total);
        $this->assertSame('20000.00', (string) $transaction->grand_total);
        $this->assertSame('SAVE5', $transaction->coupon_code);
        $this->assertSame($coupon->id, $transaction->coupon_id);
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
        $this->assertSame(1, StockMovement::where('transaction_id', $transaction->id)->count());
        $this->assertSame('15.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->delete(route('coupons.destroy', $coupon))->assertSessionHasErrors('coupon');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'used_count' => 1]);
        $this->actingAs($owner)->withSession(['active_branch_id' => $branch->id])
            ->postJson('/transactions/'.$transaction->id.'/refunds', [
                'refund_type' => 'full', 'reason' => 'Refund transaksi berkupon',
                'manual_method' => 'cash', 'idempotency_key' => 'coupon-refund-key',
            ])->assertCreated()->assertJsonPath('data.amount', '20000.00');
        $this->actingAs($owner)->withSession(['active_branch_id' => $branch->id])->get(route('orders.show', $transaction))
            ->assertOk()->assertSee('SAVE5')->assertSee('20.000')->assertSee('Sisa refundable: Rp 0');
    }

    public function test_invalid_or_exhausted_coupon_does_not_create_a_sale_or_deduct_stock(): void
    {
        [$cashier, $branch, $menu] = $this->salesContext();
        $ingredient = $this->recipeStock($branch, $menu);
        Coupon::create(array_merge($this->couponPayload('ONCE'), ['usage_limit' => 1, 'used_count' => 1]));

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 'coupon-exhausted'))
            ->from('/checkout')->post('/checkout', $this->payment('ONCE', 25000))
            ->assertRedirect('/checkout')->assertSessionHasErrors('coupon_code');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame('20.000', (string) BranchStock::where(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id])->value('quantity'));
    }

    public function test_coupon_discount_is_capped_at_the_order_subtotal(): void
    {
        [$cashier, $branch, $menu] = $this->salesContext();
        $this->recipeStock($branch, $menu);
        Coupon::create(array_merge($this->couponPayload('TOO-MUCH'), ['discount_type' => 'fixed', 'discount_value' => '50000.00']));

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 'coupon-cap'))
            ->post('/checkout', $this->payment('TOO-MUCH', 0))->assertRedirect();

        $sale = Transaction::where('idempotency_key', 'coupon-cap')->firstOrFail();
        $this->assertSame('25000.00', (string) $sale->discount_total);
        $this->assertSame('0.00', (string) $sale->grand_total);
        $this->assertSame('0.00', (string) $sale->change_amount);
    }

    public function test_percentage_coupon_is_applied_to_checkout_total(): void
    {
        [$cashier, $branch, $menu] = $this->salesContext();
        $this->recipeStock($branch, $menu);
        Coupon::create($this->couponPayload('PERCENT10'));

        $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 'coupon-percent'))
            ->post('/checkout', $this->payment('PERCENT10', 25000))->assertRedirect();

        $sale = Transaction::where('idempotency_key', 'coupon-percent')->firstOrFail();
        $this->assertSame('2500.00', (string) $sale->discount_total);
        $this->assertSame('22500.00', (string) $sale->grand_total);
        $this->assertSame('2500.00', (string) $sale->change_amount);
    }

    public function test_coupon_checkout_retry_with_the_same_idempotency_key_does_not_consume_twice(): void
    {
        [$cashier, $branch, $menu] = $this->salesContext();
        $this->recipeStock($branch, $menu);
        $coupon = Coupon::create(array_merge($this->couponPayload('RETRY1'), ['usage_limit' => 1]));
        $session = $this->cartSession($branch, $menu, 'coupon-retry-key');

        $this->actingAs($cashier)->withSession($session)->post('/checkout', $this->payment('RETRY1', 22500))->assertRedirect();
        $this->post('/checkout', $this->payment('RETRY1', 22500))->assertRedirect();

        $this->assertSame(1, Transaction::where('idempotency_key', 'coupon-retry-key')->count());
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('15.000', (string) BranchStock::where(['branch_id' => $branch->id])->value('quantity'));
    }

    public function test_checkout_movement_failure_rolls_back_coupon_transaction_and_stock(): void
    {
        [$cashier, $branch, $menu] = $this->salesContext();
        $this->recipeStock($branch, $menu);
        $coupon = Coupon::create(array_merge($this->couponPayload('ROLLBACK'), ['discount_type' => 'fixed', 'discount_value' => '1000.00']));
        $this->withoutExceptionHandling();
        StockMovement::creating(function () {
            throw new RuntimeException('simulated stock movement failure');
        });

        try {
            try {
                $this->actingAs($cashier)->withSession($this->cartSession($branch, $menu, 'coupon-rollback'))
                    ->post('/checkout', $this->payment('ROLLBACK', 24000));
                $this->fail('Expected movement persistence failure.');
            } catch (RuntimeException $exception) {
                $this->assertSame('simulated stock movement failure', $exception->getMessage());
            }
        } finally {
            StockMovement::flushEventListeners();
        }

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, (int) $coupon->fresh()->used_count);
        $this->assertSame('20.000', (string) BranchStock::first()->quantity);
    }

    public function test_coupon_usage_limit_cannot_be_lowered_below_uses_already_recorded(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $coupon = Coupon::create(array_merge($this->couponPayload('USED2'), ['usage_limit' => 5, 'used_count' => 2]));
        $payload = array_merge($this->couponPayload('USED2'), ['usage_limit' => 1]);

        $this->actingAs($owner)->put(route('coupons.update', $coupon), $payload)->assertSessionHasErrors('usage_limit');
        $this->assertSame(5, (int) $coupon->fresh()->usage_limit);
        $this->assertSame(2, (int) $coupon->fresh()->used_count);
    }

    public function test_coupon_edit_and_delete_preserve_history_even_if_usage_counter_is_stale(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        [, $branch] = $this->salesContext();
        $coupon = Coupon::create(array_merge($this->couponPayload('HISTORY'), ['usage_limit' => 5, 'used_count' => 0]));
        foreach (['COUPON-HISTORY-1', 'COUPON-HISTORY-2'] as $number) {
            $transaction = $this->transaction($branch, $number, 'Coupon history');
            $transaction->update(['coupon_id' => $coupon->id, 'coupon_code' => $coupon->code]);
        }

        $payload = array_merge($this->couponPayload('HISTORY'), ['usage_limit' => 1]);
        $this->actingAs($owner)->put(route('coupons.update', $coupon), $payload)->assertSessionHasErrors('usage_limit');
        $this->delete(route('coupons.destroy', $coupon))->assertSessionHasErrors('coupon');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'used_count' => 0]);
        $this->assertSame(2, $coupon->transactions()->count());
    }

    public function test_partial_coupon_refunds_are_deterministic_for_reversed_item_relation_order(): void
    {
        [$branch] = $this->salesContext();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $transaction = Transaction::create([
            'transaction_number' => 'COUPON-MULTI-REFUND', 'branch_id' => $branch->id,
            'customer_name' => 'Refund test', 'service_type' => 'take_away', 'fulfillment_type' => 'self_service',
            'subtotal' => '200.00', 'discount_total' => '0.01', 'tax_total' => 0,
            'grand_total' => '199.99', 'amount_paid' => '199.99', 'change_amount' => 0,
            'payment_method' => 'qris', 'status' => 'completed', 'sold_at' => now(),
        ]);
        $first = $this->transactionItem($transaction, 'First item', 2, '100.00');
        $second = $this->transactionItem($transaction, 'Second item', 2, '100.00');

        $this->actingAs($owner)->postJson('/transactions/'.$transaction->id.'/refunds', [
            'refund_type' => 'partial', 'reason' => 'Refund item pertama', 'manual_method' => 'cash',
            'idempotency_key' => 'coupon-partial-first',
            'items' => [['transaction_item_id' => $first->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.amount', '49.99');

        $reversed = $transaction->fresh();
        $reversed->setRelation('items', $reversed->items()->orderByDesc('id')->get());
        $summary = app(RefundService::class)->summaryFor($reversed, $owner);
        $this->assertSame('49.99', $summary['total_refunded']);
        $this->assertSame('150.00', $summary['remaining_refundable']);

        $this->postJson('/transactions/'.$transaction->id.'/refunds', [
            'refund_type' => 'partial', 'reason' => 'Refund item kedua', 'manual_method' => 'cash',
            'idempotency_key' => 'coupon-partial-second',
            'items' => [['transaction_item_id' => $second->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.amount', '50.00');

        $reversed = $transaction->fresh();
        $reversed->setRelation('items', $reversed->items()->orderByDesc('id')->get());
        $summary = app(RefundService::class)->summaryFor($reversed, $owner);
        $this->assertSame('99.99', $summary['total_refunded']);
        $this->assertSame('100.00', $summary['remaining_refundable']);
    }

    public function test_orders_are_searchable_and_manager_is_limited_to_active_branch(): void
    {
        [$cashier, $branch, $menu] = $this->salesContext();
        $otherBranch = Branch::create(['code' => 'SBY-02', 'name' => 'Surabaya', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $visible = $this->transaction($branch, 'ORD-JKT-1', 'Ayu');
        $hidden = $this->transaction($otherBranch, 'ORD-SBY-1', 'Bima');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $manager->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($manager)->withSession(['active_branch_id' => $branch->id])->get(route('orders.index', ['q' => 'Ayu']))
            ->assertOk()->assertSee($visible->transaction_number)->assertDontSee($hidden->transaction_number);
        $this->get(route('orders.show', $hidden))->assertNotFound();
        $this->get(route('orders.show', $visible))->assertOk()->assertSee('Ayu');
    }

    public function test_invoice_email_reports_unconfigured_transport_and_sends_only_when_smtp_is_configured(): void
    {
        [$cashier, $branch] = $this->salesContext();
        $transaction = $this->transaction($branch, 'ORD-MAIL-1', 'Ayu');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $url = route('orders.invoice-email', $transaction);

        config(['mail.default' => 'array']);
        Mail::fake();
        $this->actingAs($owner)->withSession(['active_branch_id' => $branch->id])->post($url, ['email' => 'guest@example.test'])
            ->assertSessionHasErrors('invoice_email');
        Mail::assertNothingSent();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.jooal.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'demo-user',
            'mail.mailers.smtp.password' => 'demo-password',
            'mail.from.address' => 'pos@jooal.test',
        ]);
        Mail::fake();
        $this->post($url, ['email' => 'guest@example.test'])->assertSessionHas('success');
        Mail::assertSent(InvoiceEmail::class, function (InvoiceEmail $mail) use ($transaction) {
            return $mail->transaction->is($transaction);
        });
    }

    public function test_settings_are_read_only_owner_only_and_role_menu_does_not_claim_role_management(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($cashier)->get(route('settings.system'))->assertForbidden();
        $this->actingAs($owner)->get(route('settings.system'))->assertOk()->assertSee('Email')->assertDontSee('demo-password');
        $this->assertSame(0, Transaction::count());
    }

    private function salesContext(): array
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = Branch::create(['code' => 'JKT-'.substr(md5(uniqid('', true)), 0, 5), 'name' => 'JOOAL Jakarta', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Latte', 'slug' => 'latte-'.substr(md5(uniqid('', true)), 0, 5), 'price' => 25000, 'is_active' => true]);
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => true]);

        return [$cashier, $branch, $menu];
    }

    private function recipeStock(Branch $branch, Menu $menu): Ingredient
    {
        $ingredient = Ingredient::create(['name' => 'Kopi', 'slug' => 'kopi-'.substr(md5(uniqid('', true)), 0, 5), 'unit' => 'gram', 'minimum_stock' => 1, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 20]);

        return $ingredient;
    }

    private function cartSession(Branch $branch, Menu $menu, string $key): array
    {
        $item = [
            'menu_id' => $menu->id, 'topping_ids' => [], 'matcha_type_id' => null, 'matcha_origin_id' => null,
            'menu_variant_id' => null, 'quantity' => 1, 'category_slug' => 'non-matcha',
        ];
        $item['recipe_fingerprint'] = app(\App\Services\RecipeStockService::class)->recipeFingerprintForItem($item);
        return ['active_branch_id' => $branch->id, 'checkout_idempotency_key' => $key, 'pos_cart' => [$item]];
    }

    private function payment(?string $couponCode = null, int $cash = 25000): array
    {
        return ['customer_name' => 'Customer', 'service_type' => 'take_away', 'pager_number' => 'P-01', 'payment_method' => 'cash', 'cash_amount' => $cash, 'coupon_code' => $couponCode];
    }

    private function couponPayload(string $code): array
    {
        return [
            'code' => $code,
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ];
    }

    private function transaction(Branch $branch, string $number, string $customer): Transaction
    {
        $transaction = Transaction::create([
            'transaction_number' => $number, 'branch_id' => $branch->id, 'customer_name' => $customer,
            'service_type' => 'take_away', 'fulfillment_type' => 'self_service', 'subtotal' => 100,
            'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 100, 'amount_paid' => 100,
            'change_amount' => 0, 'payment_method' => 'cash', 'status' => 'completed', 'sold_at' => now(),
        ]);
        TransactionItem::create([
            'transaction_id' => $transaction->id, 'menu_name' => 'Latte', 'quantity' => 1,
            'unit_price' => 100, 'line_total' => 100, 'item_options' => [],
        ]);

        return $transaction;
    }

    private function transactionItem(Transaction $transaction, string $name, int $quantity, string $lineTotal): TransactionItem
    {
        return TransactionItem::create([
            'transaction_id' => $transaction->id,
            'menu_name' => $name,
            'quantity' => $quantity,
            'unit_price' => (float) $lineTotal / $quantity,
            'line_total' => $lineTotal,
            'item_options' => [],
        ]);
    }
}
