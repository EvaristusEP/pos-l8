<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_and_check_out_use_server_timestamps_and_record_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = $this->branch('ATT-1');
        $user->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $this->travelTo(Carbon::parse('2026-10-23 08:15:20'));

        $this->actingAs($user)->withSession(['active_branch_id' => $branch->id])
            ->post('/attendance/check-in', ['branch_id' => 999, 'check_in_at' => '2000-01-01 00:00:00'])
            ->assertRedirect(route('attendance.index'));
        $attendance = Attendance::query()->firstOrFail();
        $this->assertSame($branch->id, $attendance->branch_id);
        $this->assertSame('2026-10-23 08:15:20', $attendance->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(Attendance::STATUS_CHECKED_IN, $attendance->status);

        $this->travelTo(Carbon::parse('2026-10-23 17:42:10'));
        $this->post('/attendance/check-out', ['check_out_at' => '2000-01-01 00:00:00'])->assertRedirect(route('attendance.index'));
        $attendance->refresh();
        $this->assertSame('2026-10-23 17:42:10', $attendance->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(Attendance::STATUS_CHECKED_OUT, $attendance->status);
    }

    public function test_duplicate_check_in_and_check_out_without_open_session_are_rejected(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $branch = $this->branch('ATT-2');
        $user->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($user)->withSession(['active_branch_id' => $branch->id])->post('/attendance/check-out')
            ->assertSessionHasErrors('attendance');
        $this->post('/attendance/check-in')->assertRedirect();
        $this->post('/attendance/check-in')->assertSessionHasErrors('attendance');
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_attendance_page_only_shows_the_signed_in_users_own_sessions(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Current Cashier']);
        $other = User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'Other Cashier Secret']);
        $branch = $this->branch('ATT-3');
        $viewer->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        Attendance::create(['user_id' => $viewer->id, 'branch_id' => $branch->id, 'check_in_at' => '2026-10-23 08:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);
        Attendance::create(['user_id' => $other->id, 'branch_id' => $branch->id, 'check_in_at' => '2026-10-23 09:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);

        $this->actingAs($viewer)->withSession(['active_branch_id' => $branch->id])
            ->get('/attendance')->assertOk()->assertDontSee('09:00:00')->assertSee('08:00:00');
    }

    public function test_owner_attendance_page_is_cross_branch_monitoring_with_filters_and_roster_summary(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $branchA = $this->branch('ATT-MON-1');
        $branchB = $this->branch('ATT-MON-2');
        $openEmployee = User::factory()->create(['name' => 'Open Employee', 'role' => User::ROLE_CASHIER]);
        $closedEmployee = User::factory()->create(['name' => 'Closed Employee', 'role' => User::ROLE_INVENTORY]);
        $absentEmployee = User::factory()->create(['name' => 'Absent Employee', 'role' => User::ROLE_MANAGER]);
        $openEmployee->branches()->attach($branchA, ['is_default' => true, 'is_active' => true]);
        $closedEmployee->branches()->attach($branchB, ['is_default' => true, 'is_active' => true]);
        $absentEmployee->branches()->attach($branchB, ['is_default' => true, 'is_active' => true]);
        Attendance::create(['user_id' => $openEmployee->id, 'branch_id' => $branchA->id, 'check_in_at' => '2026-10-23 08:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);
        Attendance::create(['user_id' => $closedEmployee->id, 'branch_id' => $branchB->id, 'check_in_at' => '2026-10-23 09:00:00', 'check_out_at' => '2026-10-23 17:00:00', 'status' => Attendance::STATUS_CHECKED_OUT]);

        $this->actingAs($owner)->get('/attendance?date_from=2026-10-23&date_to=2026-10-23')
            ->assertOk()
            ->assertSee('Monitoring Absensi Karyawan')
            ->assertSee('Open Employee')
            ->assertSee('Closed Employee')
            ->assertSee('Absent Employee')
            ->assertSee('Direktori')
            ->assertSee('Riwayat sesi absensi')
            ->assertSee('data-employee-search', false)
            ->assertSee('Manager')
            ->assertSee('Kasir')
            ->assertSee('Inventory')
            ->assertSee('data-employee-card', false)
            ->assertSee('Semua cabang')
            ->assertViewHas('totalSessions', 2)
            ->assertViewHas('openSessions', 1)
            ->assertViewHas('checkedOutSessions', 1)
            ->assertViewHas('employeesCheckedIn', 1)
            ->assertViewHas('employeesCheckedOut', 1)
            ->assertViewHas('employeesWithoutAttendance', 1);

        $filtered = $this->get('/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$branchB->id.'&status=checked_out')
            ->assertOk()
            ->assertSee('Closed Employee')
            ->assertSee('Absent Employee')
            ->assertDontSee('Open Employee')
            ->assertSee('Branch ATT-MON-2');
        $this->assertSame(2, substr_count($filtered->getContent(), '<article data-employee-card'));
        $this->assertStringContainsString('data-employee-search-text=', $filtered->getContent());
        $this->assertSame(2, Attendance::query()->count(), 'Rendering the directory must not create or duplicate attendance sessions.');

        $styles = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.attendance-employee-grid { display: grid; min-width: 0; grid-template-columns: minmax(0, 1fr);', $styles);
        $this->assertStringContainsString('@media (min-width: 768px) { .attendance-employee-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }', $styles);

        $this->get('/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$branchB->id.'&status=checked_out')
            ->assertOk()
            ->assertSee('Closed Employee')
            ->assertDontSee('Open Employee')
            ->assertViewHas('totalSessions', 1)
            ->assertViewHas('openSessions', 0)
            ->assertViewHas('checkedOutSessions', 1)
            ->assertViewHas('employeesCheckedIn', 0)
            ->assertViewHas('employeesCheckedOut', 1)
            ->assertViewHas('employeesWithoutAttendance', 1);

        $this->get('/attendance?date_from=2026-10-23&date_to=2026-10-23&status=invalid')
            ->assertSessionHasErrors('status');
    }

    public function test_legacy_admin_role_can_view_read_only_cross_branch_attendance_monitoring(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['name' => 'Branch Employee', 'role' => User::ROLE_CASHIER]);
        $branch = $this->branch('ATT-ADMIN');
        Attendance::create(['user_id' => $employee->id, 'branch_id' => $branch->id, 'check_in_at' => '2026-10-23 08:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);

        $this->actingAs($admin)->get('/attendance?date_from=2026-10-23&date_to=2026-10-23')
            ->assertOk()
            ->assertSee('Branch Employee')
            ->assertSee('Semua cabang');

        $this->post('/attendance/check-in')->assertForbidden();
    }

    public function test_manager_check_in_uses_assigned_active_branch_and_cannot_widen_report_scope(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $assigned = $this->branch('ATT-4');
        $other = $this->branch('ATT-5');
        $manager->branches()->attach($assigned, ['is_default' => true, 'is_active' => true]);
        $this->travelTo(Carbon::parse('2026-10-23 08:15:20'));

        $this->actingAs($manager)->withSession(['active_branch_id' => $assigned->id])
            ->post('/attendance/check-in', ['branch_id' => $other->id])->assertRedirect();
        $this->assertDatabaseHas('attendances', ['user_id' => $manager->id, 'branch_id' => $assigned->id]);
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$other->id)->assertForbidden();
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23')
            ->assertOk()->assertViewHas('totalSessions', 1)->assertViewHas('openSessions', 1);
    }

    public function test_owner_can_filter_attendance_across_branches_and_dates(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $first = $this->branch('ATT-6');
        $second = $this->branch('ATT-7');
        $firstUser = User::factory()->create(['name' => 'First Employee']);
        $secondUser = User::factory()->create(['name' => 'Second Employee']);
        Attendance::create(['user_id' => $firstUser->id, 'branch_id' => $first->id, 'check_in_at' => '2026-10-22 08:00:00', 'check_out_at' => '2026-10-22 16:00:00', 'status' => Attendance::STATUS_CHECKED_OUT]);
        Attendance::create(['user_id' => $secondUser->id, 'branch_id' => $second->id, 'check_in_at' => '2026-10-23 09:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);

        $this->actingAs($owner)->get('/reports/attendance?date_from=2026-10-22&date_to=2026-10-23')
            ->assertOk()->assertViewHas('totalSessions', 2)->assertViewHas('openSessions', 1)
            ->assertSee('First Employee')->assertSee('Second Employee');
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$first->id)
            ->assertOk()->assertViewHas('totalSessions', 0);
        $this->get('/reports/attendance?date_from=2026-10-23&date_to=2026-10-23&branch_id='.$second->id)
            ->assertOk()->assertViewHas('totalSessions', 1)->assertViewHas('openSessions', 1);
        $this->get('/reports/attendance?date_from=2026-10-24&date_to=2026-10-23')->assertSessionHasErrors('date_to');
    }

    public function test_attendance_date_filter_values_are_preserved_and_include_full_end_date(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $branch = $this->branch('ATT-DATE');
        $employee = User::factory()->create(['name' => 'Date Filter Employee', 'role' => User::ROLE_CASHIER]);
        $employee->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        Attendance::create(['user_id' => $employee->id, 'branch_id' => $branch->id, 'check_in_at' => '2020-10-28 00:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);
        Attendance::create(['user_id' => $employee->id, 'branch_id' => $branch->id, 'check_in_at' => '2020-10-29 23:59:59', 'check_out_at' => '2020-10-30 00:10:00', 'status' => Attendance::STATUS_CHECKED_OUT]);
        Attendance::create(['user_id' => $employee->id, 'branch_id' => $branch->id, 'check_in_at' => '2020-10-30 00:00:00', 'status' => Attendance::STATUS_CHECKED_IN]);

        $response = $this->actingAs($owner)->get('/attendance?date_from=2020-10-28&date_to=2020-10-29&branch_id='.$branch->id.'&status=checked_out')
            ->assertOk()
            ->assertViewHas('from', '2020-10-28')
            ->assertViewHas('to', '2020-10-29')
            ->assertViewHas('totalSessions', 2)
            ->assertViewHas('checkedOutSessions', 1)
            ->assertSee('Date Filter Employee');

        $html = $response->getContent();
        $this->assertStringContainsString('name="date_from" value="2020-10-28"', $html);
        $this->assertStringContainsString('name="date_to" value="2020-10-29"', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('value="'.$branch->id.'" selected', $html);
        $this->assertStringContainsString('value="checked_out" selected', $html);

        $this->get('/reports/attendance?date_to=2020-10-29')
            ->assertSessionHasErrors('date_to');
    }

    public function test_attendance_filter_defaults_to_today_and_uses_responsive_single_row_layout_on_desktop(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $response = $this->actingAs($owner)->get('/attendance')->assertOk()->assertViewHas('from', now()->toDateString());
        $response->assertSee('class="attendance-report-filter"', false)
            ->assertSee('class="pos-button attendance-report-submit', false)
            ->assertSee('name="date_from" value="'.now()->toDateString().'"', false)
            ->assertSee('name="date_to" value="'.now()->toDateString().'"', false);

        $styles = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.attendance-report-filter { display: grid;', $styles);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.2fr) minmax(0, .9fr) auto;', $styles);
        $this->assertStringContainsString('.attendance-report-submit { justify-self: end;', $styles);
        $this->assertStringContainsString('$from = $validated[\'date_from\'] ?? now()->toDateString();', file_get_contents(app_path('Http/Controllers/AttendanceController.php')));
    }

    public function test_inventory_role_can_manage_own_attendance_but_not_attendance_report(): void
    {
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);
        $branch = $this->branch('ATT-8');
        $inventory->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $this->actingAs($inventory)->withSession(['active_branch_id' => $branch->id])
            ->get('/attendance')->assertOk();
        $this->post('/attendance/check-in')->assertRedirect();
        $unassigned = $this->branch('ATT-9');
        $this->withSession(['active_branch_id' => $unassigned->id])
            ->post('/attendance/check-in', ['branch_id' => $unassigned->id])->assertRedirect();
        $this->assertDatabaseMissing('attendances', ['user_id' => $inventory->id, 'branch_id' => $unassigned->id]);
        $this->get('/reports/attendance')->assertForbidden();
    }

    private function branch(string $code): Branch
    {
        return Branch::create(['code' => $code, 'name' => 'Branch '.$code, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }
}
