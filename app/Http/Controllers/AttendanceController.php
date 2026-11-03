<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, BranchContext $context): View
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return $this->report($request, $context);
        }

        $activeBranch = $context->activeFor($user);
        $openAttendance = Attendance::query()->with('branch')
            ->where('user_id', $user->id)->whereNull('check_out_at')->latest('check_in_at')->first();
        $attendances = Attendance::query()->with('branch')->where('user_id', $user->id)
            ->latest('check_in_at')->paginate(20);

        return view('attendance.index', compact('activeBranch', 'openAttendance', 'attendances'));
    }

    public function checkIn(Request $request, BranchContext $context): RedirectResponse
    {
        $branch = $context->activeFor($request->user());
        abort_unless($branch && $context->canAccess($request->user(), $branch), 403, 'Pilih cabang aktif yang ditugaskan sebelum check-in.');

        DB::transaction(function () use ($request, $branch) {
            // Locking the stable user row serializes simultaneous check-ins for this account.
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $hasOpen = Attendance::query()->where('user_id', $request->user()->id)->whereNull('check_out_at')->exists();
            if ($hasOpen) {
                throw ValidationException::withMessages(['attendance' => 'Anda masih memiliki sesi absensi terbuka. Lakukan check-out terlebih dahulu.']);
            }

            Attendance::create([
                'user_id' => $request->user()->id,
                'branch_id' => $branch->id,
                'check_in_at' => now(),
                'check_out_at' => null,
                'status' => Attendance::STATUS_CHECKED_IN,
            ]);
        });

        return redirect()->route('attendance.index')->with('success', 'Check-in berhasil dicatat.');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $attendance = Attendance::query()->where('user_id', $request->user()->id)
                ->whereNull('check_out_at')->orderByDesc('check_in_at')->lockForUpdate()->first();
            if (! $attendance) {
                throw ValidationException::withMessages(['attendance' => 'Tidak ada sesi absensi terbuka untuk di-check-out.']);
            }

            $attendance->check_out_at = now();
            $attendance->status = Attendance::STATUS_CHECKED_OUT;
            $attendance->save();
        });

        return redirect()->route('attendance.index')->with('success', 'Check-out berhasil dicatat.');
    }

    public function report(Request $request, BranchContext $context): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', 'in:checked_in,checked_out'],
        ], [
            'date_from.date_format' => 'Tanggal awal tidak valid.',
            'date_to.date_format' => 'Tanggal akhir tidak valid.',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);

        $from = $validated['date_from'] ?? now()->toDateString();
        $to = $validated['date_to'] ?? $from;
        if (Carbon::parse($to)->lt(Carbon::parse($from))) {
            throw ValidationException::withMessages([
                'date_to' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
            ]);
        }
        $user = $request->user();
        $activeBranch = null;

        if ($user->isAdmin()) {
            $branches = Branch::query()->orderBy('name')->get();
            $selectedBranch = ! empty($validated['branch_id'])
                ? Branch::query()->findOrFail($validated['branch_id'])
                : null;
            if ($selectedBranch && $selectedBranch->is_active) $context->set($selectedBranch);
        } else {
            $activeBranch = $context->activeFor($user);
            abort_unless($activeBranch && $context->canAccess($user, $activeBranch), 403, 'Pilih cabang aktif yang ditugaskan untuk melihat laporan.');
            if (! empty($validated['branch_id']) && (int) $validated['branch_id'] !== (int) $activeBranch->id) {
                abort(403, 'Anda tidak dapat melihat laporan cabang lain.');
            }
            $selectedBranch = $activeBranch;
            $branches = collect([$activeBranch]);
        }

        $query = Attendance::query()->with(['user:id,name', 'branch:id,code,name'])
            ->whereBetween('check_in_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()]);
        if ($selectedBranch) $query->where('branch_id', $selectedBranch->id);

        $totalSessions = (clone $query)->count();
        $openSessions = (clone $query)->whereNull('check_out_at')->count();
        $checkedOutSessions = (clone $query)->whereNotNull('check_out_at')->count();
        $employeeQuery = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_MANAGER, User::ROLE_CASHIER, User::ROLE_INVENTORY])
            ->whereHas('branches', function ($branchQuery) use ($selectedBranch) {
                $branchQuery->where('branches.is_active', true)->where('branch_user.is_active', true);
                if ($selectedBranch) $branchQuery->where('branches.id', $selectedBranch->id);
            });
        $employees = (clone $employeeQuery)
            ->with(['branches' => function ($branchQuery) use ($selectedBranch) {
                $branchQuery->where('branches.is_active', true)->where('branch_user.is_active', true);
                if ($selectedBranch) $branchQuery->where('branches.id', $selectedBranch->id);
                $branchQuery->orderBy('branches.name');
            }])
            ->orderBy('name')
            ->get();
        $eligibleEmployees = (clone $employeeQuery)->count();
        $employeesWithAttendance = (clone $query)
            ->whereIn('user_id', (clone $employeeQuery)->select('users.id'))
            ->distinct('user_id')
            ->count('user_id');
        $employeesWithoutAttendance = max(0, $eligibleEmployees - $employeesWithAttendance);
        $employeesCheckedIn = (clone $query)
            ->whereNull('check_out_at')
            ->whereIn('user_id', (clone $employeeQuery)->select('users.id'))
            ->distinct('user_id')
            ->count('user_id');
        $employeesCheckedOut = (clone $query)
            ->whereNotNull('check_out_at')
            ->whereIn('user_id', (clone $employeeQuery)->select('users.id'))
            ->distinct('user_id')
            ->count('user_id');

        if (($validated['status'] ?? null) === Attendance::STATUS_CHECKED_IN) {
            $query->whereNull('check_out_at');
        } elseif (($validated['status'] ?? null) === Attendance::STATUS_CHECKED_OUT) {
            $query->whereNotNull('check_out_at');
        }
        $attendances = $query->orderByDesc('check_in_at')->paginate(30)->withQueryString();
        $filterRoute = $request->routeIs('attendance.index') ? 'attendance.index' : 'reports.attendance';

        return view('attendance.report', compact('branches', 'selectedBranch', 'employees', 'from', 'to', 'totalSessions', 'openSessions', 'checkedOutSessions', 'employeesCheckedIn', 'employeesCheckedOut', 'employeesWithoutAttendance', 'attendances', 'filterRoute'));
    }
}
