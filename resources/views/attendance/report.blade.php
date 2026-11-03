<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $filterRoute === 'attendance.index' ? 'Monitoring Absensi' : 'Laporan Absensi' }} - JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900">
    <x-pos-sidebar />
    <main class="min-h-screen p-4 transition-[margin] duration-300 lg:ml-20 lg:p-6">
        <div class="mx-auto max-w-6xl space-y-5">
            <header class="pos-page-header rounded-3xl bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">JOOAL POS / Absensi</p>
                <h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">{{ $filterRoute === 'attendance.index' ? 'Monitoring Absensi Karyawan' : 'Laporan Absensi' }}</h1>
                <p class="text-sm text-slate-500">Data sesi presensi sesuai periode dan cakupan cabang yang dipilih.</p>
            </header>

            <form method="GET" action="{{ route($filterRoute) }}" class="attendance-report-filter">
                <label class="text-sm font-semibold">Dari tanggal
                    <input type="date" name="date_from" value="{{ request()->query('date_from', old('date_from', $from)) }}" required class="pos-input">
                </label>
                <label class="text-sm font-semibold">Sampai tanggal
                    <input type="date" name="date_to" value="{{ request()->query('date_to', old('date_to', $to)) }}" required class="pos-input">
                </label>
                @if(auth()->user()->isAdmin())
                    <label class="text-sm font-semibold">Cabang
                        <select name="branch_id" class="pos-input">
                            <option value="">Semua cabang</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) request()->query('branch_id', old('branch_id', optional($selectedBranch)->id)) === (string) $branch->id)>{{ $branch->display_name }}{{ $branch->is_active ? '' : ' (nonaktif)' }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <input type="hidden" name="branch_id" value="{{ $selectedBranch->id }}">
                    <label class="text-sm font-semibold">Cabang aktif
                        <input readonly value="{{ $selectedBranch->display_name }}" class="pos-input">
                    </label>
                @endif
                <label class="text-sm font-semibold">Status
                    <select name="status" class="pos-input">
                        <option value="">Semua status</option>
                        <option value="checked_in" @selected(request()->query('status', old('status')) === 'checked_in')>Belum check-out</option>
                        <option value="checked_out" @selected(request()->query('status', old('status')) === 'checked_out')>Sudah check-out</option>
                    </select>
                </label>
                <button class="pos-button attendance-report-submit rounded-lg bg-emerald-700 px-4 py-2 font-bold text-white">Tampilkan</button>
            </form>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan absensi">
                <article class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-sm text-slate-500">Jumlah sesi</p>
                    <p class="mt-1 text-2xl font-extrabold text-[#123c35]">{{ number_format($totalSessions) }}</p>
                </article>
                @if(auth()->user()->isAdmin())
                    <article class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Karyawan sedang check-in</p>
                        <p class="mt-1 text-2xl font-extrabold text-amber-800">{{ number_format($employeesCheckedIn) }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Karyawan belum check-in pada periode</p>
                        <p class="mt-1 text-2xl font-extrabold text-slate-700">{{ number_format($employeesWithoutAttendance) }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Karyawan sudah check-out</p>
                        <p class="mt-1 text-2xl font-extrabold text-emerald-800">{{ number_format($employeesCheckedOut) }}</p>
                    </article>
                @else
                    <article class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Sesi belum check-out</p>
                        <p class="mt-1 text-2xl font-extrabold text-amber-800">{{ number_format($openSessions) }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-sm text-slate-500">Sesi sudah check-out</p>
                        <p class="mt-1 text-2xl font-extrabold text-emerald-800">{{ number_format($checkedOutSessions) }}</p>
                    </article>
                @endif
            </section>

            @if(auth()->user()->isAdmin())
            <section class="attendance-employee-directory rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="attendance-employees-title">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Direktori</p><h2 id="attendance-employees-title" class="mt-1 text-lg font-extrabold">Karyawan aktif</h2><p class="mt-1 text-sm text-slate-500">Daftar akun aktif yang ditugaskan ke cabang. Daftar ini terpisah dari riwayat sesi.</p></div>
                    <label class="block w-full text-sm font-semibold sm:max-w-sm">Cari karyawan<input type="search" data-employee-search class="pos-input mt-1" placeholder="Nama, role, atau cabang"></label>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ $employees->count() }} karyawan{{ $selectedBranch ? ' · '.$selectedBranch->display_name : ' · semua cabang' }}</p>
                <div class="attendance-employee-grid mt-3" data-employee-grid>
                    @forelse($employees as $employee)
                        @php
                            $roleNames = ['manager' => 'Manager', 'cashier' => 'Kasir', 'inventory' => 'Inventory'];
                            $employeeBranches = $employee->branches->pluck('display_name')->implode(', ');
                        @endphp
                        <article data-employee-card data-employee-search-text="{{ mb_strtolower($employee->name.' '.($roleNames[$employee->role] ?? $employee->role).' '.$employeeBranches) }}" class="attendance-employee-card">
                            <div class="flex min-w-0 items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-words font-extrabold text-slate-900">{{ $employee->name }}</h3><p class="mt-1 text-sm font-semibold text-emerald-800">{{ $roleNames[$employee->role] ?? ucfirst($employee->role) }}</p></div><span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800">Aktif</span></div>
                            <p class="mt-3 text-sm text-slate-600"><span class="font-semibold">Cabang:</span> {{ $employeeBranches ?: 'Belum ada cabang aktif' }}</p>
                        </article>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">Tidak ada karyawan aktif pada cakupan cabang ini.</p>
                    @endforelse
                </div>
                <p data-employee-search-empty class="mt-3 hidden rounded-lg border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">Tidak ada karyawan yang cocok dengan pencarian.</p>
            </section>
            @endif

            <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-5">
                <div class="mb-3"><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Riwayat</p><h2 class="mt-1 text-lg font-extrabold">Riwayat sesi absensi</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($totalSessions) }} sesi sesuai periode, cabang, dan status filter.</p></div>
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase text-slate-500">
                            <th class="p-2">Karyawan</th>
                            <th class="p-2">Cabang</th>
                            <th class="p-2">Check-in</th>
                            <th class="p-2">Check-out</th>
                            <th class="p-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr class="border-b">
                                <td class="p-2 font-semibold">{{ $attendance->user->name }}</td>
                                <td class="p-2">{{ $attendance->branch->display_name }}</td>
                                <td class="p-2">{{ $attendance->check_in_at->format('d/m/Y H:i:s') }}</td>
                                <td class="p-2">{{ $attendance->check_out_at ? $attendance->check_out_at->format('d/m/Y H:i:s') : '—' }}</td>
                                <td class="p-2"><span class="rounded-lg px-2 py-1 text-xs font-bold {{ $attendance->isOpen() ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800' }}">{{ $attendance->isOpen() ? 'Belum check-out' : 'Selesai' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6 text-center text-slate-500">Tidak ada sesi pada filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $attendances->links() }}</div>
            </section>
        </div>
    </main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var search = document.querySelector('[data-employee-search]');
        var cards = Array.from(document.querySelectorAll('[data-employee-card]'));
        var empty = document.querySelector('[data-employee-search-empty]');
        if (!search || !cards.length) return;
        search.addEventListener('input', function () {
            var term = search.value.trim().toLocaleLowerCase('id');
            var visible = 0;
            cards.forEach(function (card) {
                var matches = (card.dataset.employeeSearchText || '').includes(term);
                card.hidden = !matches;
                if (matches) visible += 1;
            });
            if (empty) empty.classList.toggle('hidden', visible !== 0);
        });
    });
</script>
</body>
</html>
