<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_open_branch_management(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($owner)->get('/branches')->assertOk();
        $this->actingAs($cashier)->get('/branches')->assertForbidden();
    }

    public function test_owner_without_a_branch_can_open_management_dashboard(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertRedirect(route('dashboard.owner'));

        $this->actingAs($owner)
            ->get('/branches')
            ->assertOk();
    }

    public function test_owner_can_create_the_first_branch_and_select_it(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->post('/branches', [
                'code' => 'JKT-01',
                'name' => 'JOOAL Jakarta',
                'address' => null,
                'phone' => null,
                'timezone' => 'Asia/Jakarta',
                'is_active' => 1,
            ])
            ->assertRedirect(route('branches.select'));

        $branch = Branch::query()->where('code', 'JKT-01')->firstOrFail();

        $this->actingAs($owner)
            ->post('/branches/select', ['branch_id' => $branch->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_branch_id', $branch->id);
    }

    public function test_assigned_user_gets_a_default_active_branch(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = Branch::create([
            'code' => 'JKT-01',
            'name' => 'JOOAL Jakarta',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $user->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($user)
            ->get('/dashboard/pos')
            ->assertOk()
            ->assertSessionHas('active_branch_id', $branch->id);
    }

    public function test_user_cannot_activate_an_unassigned_branch(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = Branch::create([
            'code' => 'BDG-01',
            'name' => 'JOOAL Bandung',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/branches/select', ['branch_id' => $branch->id])
            ->assertForbidden();
    }

    public function test_non_owner_without_branch_is_sent_to_branch_selection_with_clear_message(): void
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($cashier)
            ->get('/dashboard/pos')
            ->assertRedirect(route('branches.select'))
            ->assertSessionHasErrors([
                'branch' => 'Akun Anda belum ditugaskan ke cabang aktif. Hubungi Owner sebelum menggunakan POS.',
            ]);
    }

    public function test_branch_selection_returns_to_allowlisted_branch_pages_and_rejects_external_return_urls(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $branch = Branch::create(['code' => 'RET-01', 'name' => 'Return Branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        $this->actingAs($owner)->post('/branches/select', ['branch_id' => $branch->id, 'return_to' => 'tables'])
            ->assertRedirect(route('tables.index').'?branch_id='.$branch->id)->assertSessionHas('active_branch_id', $branch->id);
        $this->actingAs($owner)->post('/branches/select', ['branch_id' => $branch->id, 'return_to' => 'https://example.test'])
            ->assertSessionHasErrors('return_to');
    }

    public function test_cannot_switch_branches_while_pos_cart_has_items(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $activeBranch = Branch::create(['code' => 'CART-01', 'name' => 'Cart Branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $otherBranch = Branch::create(['code' => 'CART-02', 'name' => 'Other Branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        $this->actingAs($owner)
            ->withSession(['active_branch_id' => $activeBranch->id, 'pos_cart' => [['menu_id' => 1, 'quantity' => 1]]])
            ->from(route('dashboard.owner'))
            ->post('/branches/select', ['branch_id' => $otherBranch->id])
            ->assertRedirect(route('dashboard.owner'))
            ->assertSessionHas('active_branch_id', $activeBranch->id)
            ->assertSessionHasErrors('branch');
    }
    public function test_branch_identity_and_active_state_are_protected_when_transactions_exist(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $branch = Branch::create(['code' => 'HIST-01', 'name' => 'Historical Branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        Transaction::create(['transaction_number' => 'HIST-TX-1', 'branch_id' => $branch->id, 'subtotal' => 100,
            'grand_total' => 100, 'amount_paid' => 100, 'payment_method' => 'cash', 'status' => 'completed', 'sold_at' => now()]);

        $this->actingAs($owner)->put(route('branches.update', $branch), [
            '_form' => 'branch-edit-'.$branch->id, 'code' => 'HIST-02', 'name' => 'Renamed',
            'timezone' => 'Asia/Jakarta', 'is_active' => 1,
        ])->assertSessionHasErrors('branch');
        $this->actingAs($owner)->put(route('branches.update', $branch), [
            '_form' => 'branch-edit-'.$branch->id, 'code' => 'HIST-01', 'name' => 'Historical Branch',
            'timezone' => 'Asia/Jakarta', 'is_active' => 0,
        ])->assertSessionHasErrors('branch');
        $this->actingAs($owner)->patch(route('branches.toggle-status', $branch))->assertSessionHasErrors('branch');
        $this->assertSame('HIST-01', $branch->fresh()->code);
        $this->assertTrue($branch->fresh()->is_active);
    }
}
