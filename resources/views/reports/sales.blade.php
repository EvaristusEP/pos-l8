<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laporan Penjualan - Matcha House POS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef7ef] font-sans text-slate-900 antialiased">
<x-pos-sidebar />
<main x-data="{ sidebarExpanded: localStorage.getItem('pos-sidebar-expanded') === 'true' }" @pos-sidebar.window="sidebarExpanded = $event.detail.expanded" :class="sidebarExpanded ? 'lg:ml-[300px]' : 'lg:ml-20'" class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(167,243,208,0.45),_transparent_36%),linear-gradient(180deg,_#f8fbf8_0%,_#edf7ee_100%)] p-4 transition-[margin] duration-300 lg:p-6">
<div class="mx-auto max-w-[1600px]">
<header class="rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-700">Matcha House POS</p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-emerald-950">Laporan Penjualan</h1>
            <p class="mt-2 text-sm text-slate-600">Ringkasan transaksi harian berdasarkan pembayaran yang telah selesai.</p>
        </div>
        <div class="lg:ml-auto">
            <a href="{{ route('dashboard') }}" class="pos-button bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">← Kembali ke Dashboard</a>
        </div>
    </div>
    <form method="GET" action="{{ route('reports.sales') }}" class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-end">
        <label class="block text-sm font-bold text-slate-700">Tanggal laporan
            <input type="date" name="date" value="{{ $selectedDate }}" class="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 sm:w-56">
        </label>
        <button type="submit" class="pos-button bg-emerald-700 text-white hover:bg-emerald-800">Tampilkan laporan</button>
    </form>
</header>

<div class="mt-4 rounded-[2rem] border border-emerald-200 bg-white/95 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)]">
    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Laporan tanggal</p>
    <h2 class="mt-1 text-2xl font-extrabold text-slate-950">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h2>
</div>

<div class="mt-4 grid gap-4 md:grid-cols-3">
    <article class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-slate-100">
        <p class="text-sm font-semibold text-slate-500">Total Transaksi</p>
        <p class="mt-2 text-3xl font-extrabold text-emerald-950">{{ $totalTransactions }}</p>
        <p class="mt-1 text-sm text-slate-500">transaksi selesai</p>
    </article>
    <article class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-slate-100">
        <p class="text-sm font-semibold text-slate-500">Item Terjual</p>
        <p class="mt-2 text-3xl font-extrabold text-emerald-950">{{ $totalItemsSold }}</p>
        <p class="mt-1 text-sm text-slate-500">item</p>
    </article>
    <article class="rounded-[1.5rem] bg-[#123c35] p-5 text-white shadow-lg">
        <p class="text-sm font-semibold text-emerald-100">Total Penjualan</p>
        <p class="mt-2 text-3xl font-extrabold">Rp {{ number_format($totalSales, 0, ',', '.') }}</p>
        <p class="mt-1 text-sm text-emerald-100">dari transaksi selesai</p>
    </article>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <section class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Metode pembayaran</p>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-emerald-50 p-4">
                <p class="text-sm font-semibold text-emerald-700">Cash</p>
                <p class="mt-1 text-xl font-extrabold text-emerald-950">Rp {{ number_format($paymentTotals['cash'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-600">QRIS</p>
                <p class="mt-1 text-xl font-extrabold text-slate-950">Rp {{ number_format($paymentTotals['qris'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
    </section>
    <section class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Tipe pesanan</p>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-emerald-50 p-4">
                <p class="text-sm font-semibold text-emerald-700">Dine In</p>
                <p class="mt-1 text-xl font-extrabold text-emerald-950">{{ $serviceTotals['dine_in'] ?? 0 }} transaksi</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-600">Take Away</p>
                <p class="mt-1 text-xl font-extrabold text-slate-950">{{ $serviceTotals['take_away'] ?? 0 }} transaksi</p>
            </div>
        </div>
    </section>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
    <section class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Produk terlaris</p>
        @forelse ($topProducts as $index => $product)
            <div class="mt-4 flex items-center justify-between gap-3 {{ $index > 0 ? 'border-t border-slate-100 pt-4' : '' }}">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-50 text-sm font-extrabold text-emerald-800">{{ $index + 1 }}</span>
                    <p class="truncate font-bold text-slate-950">{{ $product->menu?->name ?? 'Menu tidak tersedia' }}</p>
                </div>
                <strong class="whitespace-nowrap text-emerald-800">{{ $product->quantity_sold }} terjual</strong>
            </div>
        @empty
            <p class="mt-4 text-sm text-slate-500">Belum ada produk terjual pada tanggal ini.</p>
        @endforelse
    </section>

    <section class="rounded-[1.5rem] bg-white p-5 shadow-sm ring-1 ring-slate-100">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Daftar transaksi harian</p>
        @if ($transactions->isEmpty())
            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 p-8 text-center">
                <p class="font-bold text-slate-700">Belum ada transaksi pada tanggal ini.</p>
                <p class="mt-1 text-sm text-slate-500">Pilih tanggal lain untuk melihat laporan penjualan.</p>
            </div>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-slate-500">
                        <tr>
                            <th class="px-3 py-3 font-semibold">Invoice</th>
                            <th class="px-3 py-3 font-semibold">Waktu</th>
                            <th class="px-3 py-3 font-semibold">Pemesan</th>
                            <th class="px-3 py-3 font-semibold">Tipe</th>
                            <th class="px-3 py-3 font-semibold">Pembayaran</th>
                            <th class="px-3 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td class="px-3 py-4 font-bold text-slate-950">{{ $transaction->transaction_number }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-slate-600">{{ optional($transaction->sold_at)->format('H:i') }}</td>
                                <td class="px-3 py-4 text-slate-700">{{ $transaction->customer_name ?: '-' }}</td>
                                <td class="px-3 py-4 text-slate-700">{{ $transaction->service_type === 'dine_in' ? 'Dine In' : 'Take Away' }}</td>
                                <td class="px-3 py-4 font-semibold text-slate-700">{{ strtoupper($transaction->payment_method) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-right font-bold text-emerald-800">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

</div>
</main>
</body>
</html>