<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_management_dashboard_without_active_branch(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertRedirect(route('dashboard.owner'));

        $this->actingAs($owner)
            ->get('/dashboard/owner')
            ->assertOk()
            ->assertSee('Dashboard Manajemen')
            ->assertDontSee('Checkout');
    }

    public function test_owner_can_checkout_only_after_selecting_a_branch_and_inventory_is_denied(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);

        $branch = $this->createBranch('OWN-01');
        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('dashboard.owner'));
        $this->actingAs($owner)->get('/checkout')->assertOk()->assertSessionHas('active_branch_id', $branch->id);
        $this->actingAs($inventory)->get('/checkout')->assertForbidden();
    }

    public function test_manager_dashboard_shows_only_the_assigned_active_branch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $assigned = $this->createBranch('MGR-01');
        $other = $this->createBranch('MGR-02');
        $manager->branches()->attach($assigned, ['is_default' => true, 'is_active' => true]);
        $localTransaction = $this->createTransaction('TX-MGR-LOCAL', $assigned, $manager);
        $localTransaction->update(['grand_total' => 12345]);
        $otherTransaction = $this->createTransaction('TX-MGR-OTHER', $other, $manager);
        $otherTransaction->update(['grand_total' => 98765]);

        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])
            ->get('/dashboard')->assertRedirect(route('dashboard.manager'));
        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])
            ->get('/dashboard/manager')->assertOk()
            ->assertSee($assigned->display_name)
            ->assertSee('Rp 12.345')
            ->assertDontSee('Rp 98.765')
            ->assertSee(route('dashboard.pos'), false)
            ->assertSee(route('orders.index'), false)
            ->assertSee(route('history'), false);

        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])
            ->get('/dashboard/cashier')->assertForbidden();
    }

    public function test_cashier_dashboard_is_separate_from_pos_and_keeps_assigned_branch_access(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = $this->createBranch('CSH-01');
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard')->assertRedirect(route('dashboard.cashier'));
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/cashier')->assertOk()
            ->assertSee($branch->display_name)
            ->assertSee(route('dashboard.pos'), false)
            ->assertSee(route('orders.index'), false)
            ->assertSee(route('history'), false);
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/manager')->assertForbidden();
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk();
    }

    public function test_cashier_can_open_checkout_on_assigned_branch(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = $this->createBranch('JKT-01');
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($cashier)
            ->get('/checkout')
            ->assertOk()
            ->assertSee('Checkout');
    }

    public function test_inventory_gets_placeholder_dashboard_without_checkout_menu(): void
    {
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $branch = $this->createBranch('BDG-01');
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($inventory)
            ->get('/dashboard')
            ->assertRedirect(route('inventory.stocks.index'));

        $this->actingAs($inventory)
            ->get('/dashboard/inventory')
            ->assertOk()
            ->assertSee('Stok per Cabang')
            ->assertDontSee('Checkout');
    }

    public function test_manager_cannot_switch_to_an_unassigned_branch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $assignedBranch = $this->createBranch('JKT-01');
        $otherBranch = $this->createBranch('SBY-01');
        $manager->branches()->attach($assignedBranch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($manager)
            ->post('/branches/select', ['branch_id' => $otherBranch->id])
            ->assertForbidden();
    }

    public function test_history_and_receipt_do_not_expose_another_branch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $assignedBranch = $this->createBranch('JKT-01');
        $otherBranch = $this->createBranch('SBY-01');
        $manager->branches()->attach($assignedBranch, ['is_default' => true, 'is_active' => true]);

        $assignedTransaction = $this->createTransaction('TX-JKT-001', $assignedBranch, $manager);
        $otherTransaction = $this->createTransaction('TX-SBY-001', $otherBranch, $manager);

        $this->actingAs($manager)
            ->withSession(['active_branch_id' => $assignedBranch->id])
            ->get('/history')
            ->assertOk()
            ->assertSee($assignedTransaction->transaction_number)
            ->assertDontSee($otherTransaction->transaction_number);

        $this->actingAs($manager)
            ->withSession(['active_branch_id' => $assignedBranch->id])
            ->get('/receipt/'.$otherTransaction->id)
            ->assertNotFound();
    }

    private function createBranch(string $code): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => 'JOOAL '.$code,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    private function createTransaction(string $number, Branch $branch, User $cashier): Transaction
    {
        return Transaction::create([
            'transaction_number' => $number,
            'cashier_id' => $cashier->id,
            'branch_id' => $branch->id,
            'customer_name' => 'Test Customer',
            'service_type' => 'take_away',
            'fulfillment_type' => 'self_service',
            'subtotal' => 10000,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10000,
            'amount_paid' => 10000,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
            'sold_at' => now(),
        ]);
    }
}
