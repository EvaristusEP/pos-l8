<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Cabang · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased">
    <x-pos-sidebar />
    <main class="min-h-screen p-4 transition-[margin] duration-300 lg:ml-20 lg:p-6">
        <div class="mx-auto max-w-7xl space-y-5">
            <header class="pos-page-header rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Dashboard Manager · {{ $branch->display_name }}</p>
                <h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Ringkasan Cabang</h1>
                <p class="mt-1 text-sm text-slate-500">Data operasional hanya untuk cabang yang sedang aktif.</p>
            </header>
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <p class="text-sm font-semibold text-slate-500">Transaksi selesai</p>
                    <p class="mt-2 text-3xl font-extrabold text-slate-950">{{ number_format($totalTransactions, 0, ',', '.') }}</p>
                </article>
                <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <p class="text-sm font-semibold text-slate-500">Penjualan cabang</p>
                    <p class="mt-2 text-2xl font-extrabold text-emerald-800">Rp {{ number_format($totalSales, 0, ',', '.') }}</p>
                </article>
                <article class="rounded-3xl bg-amber-50 p-5 ring-1 ring-amber-100">
                    <p class="text-sm font-semibold text-amber-700">Stok rendah</p>
                    <p class="mt-2 text-3xl font-extrabold text-amber-900">{{ number_format($lowStockCount, 0, ',', '.') }}</p>
                </article>
            </section>
            <nav class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Akses cepat">
                <a href="{{ route('dashboard.pos') }}" class="pos-button bg-emerald-700 text-white hover:bg-emerald-800">Buka POS / Kasir</a>
                <a href="{{ route('orders.index') }}" class="pos-button bg-white text-emerald-800 ring-1 ring-slate-200">Daftar Orders</a>
                <a href="{{ route('history') }}" class="pos-button bg-white text-emerald-800 ring-1 ring-slate-200">Riwayat Transaksi</a>
                <a href="{{ route('reports.sales') }}" class="pos-button bg-white text-emerald-800 ring-1 ring-slate-200">Laporan Penjualan</a>
            </nav>
        </div>
    </main>
</body>
</html>
