<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Kasir · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased">
    <x-pos-sidebar />
    <main class="min-h-screen p-4 transition-[margin] duration-300 lg:ml-20 lg:p-6">
        <div class="mx-auto max-w-5xl space-y-5">
            <header class="pos-page-header rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Dashboard Kasir · {{ $branch->display_name }}</p>
                <h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Selamat bertugas, {{ auth()->user()->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Pilih halaman operasional yang ingin dibuka.</p>
            </header>
            <nav class="grid gap-3 sm:grid-cols-2" aria-label="Akses kasir">
                <a href="{{ route('dashboard.pos') }}" class="pos-button min-h-16 bg-emerald-700 text-white hover:bg-emerald-800">Buka POS / Kasir</a>
                <a href="{{ route('orders.index') }}" class="pos-button min-h-16 bg-white text-emerald-800 ring-1 ring-slate-200">Daftar Orders</a>
                <a href="{{ route('history') }}" class="pos-button min-h-16 bg-white text-emerald-800 ring-1 ring-slate-200">Riwayat Transaksi</a>
                <a href="{{ route('attendance.index') }}" class="pos-button min-h-16 bg-white text-emerald-800 ring-1 ring-slate-200">Absensi</a>
            </nav>
        </div>
    </main>
</body>
</html>
