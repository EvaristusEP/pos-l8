<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transaction History - Matcha House POS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef7ef] font-sans text-slate-900 antialiased">
    <x-pos-sidebar />
    <div x-data="{ sidebarExpanded: localStorage.getItem('pos-sidebar-expanded') === 'true' }" @pos-sidebar.window="sidebarExpanded = $event.detail.expanded" :class="sidebarExpanded ? 'lg:ml-[300px]' : 'lg:ml-20'" class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(167,243,208,0.45),_transparent_36%),linear-gradient(180deg,_#f8fbf8_0%,_#edf7ee_100%)] transition-[margin] duration-300">
        <div class="mx-auto max-w-[1600px] p-4 lg:p-6">
            <div class="rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-700">Matcha House POS</p>
                        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-emerald-950">Transaction History</h1>
                        <p class="mt-2 max-w-2xl text-sm text-slate-600">Riwayat transaksi tersimpan lengkap beserta detail item, payment method, status, dan total.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50">Back to Dashboard</a>
                        <a href="{{ route('checkout') }}" class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-800">Checkout</a>
                    </div>
                </div>
            </div>

            @if ($selectedTransaction)
                <article class="mt-4 rounded-[2rem] border border-emerald-200 bg-white/95 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Transaction Detail</p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $selectedTransaction->transaction_number }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ optional($selectedTransaction->sold_at)->format('d M Y, H:i') }}</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ $selectedTransaction->customer_name ?: '-' }} · {{ $selectedTransaction->service_type === 'dine_in' ? 'Dine in' : ($selectedTransaction->service_type === 'take_away' ? 'Take away' : '-') }}@if($selectedTransaction->pager_number) · Pager {{ $selectedTransaction->pager_number }}@endif</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div class="rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Items</p>
                                <p class="mt-1 font-bold text-emerald-950">{{ $selectedTransaction->items_count ?? $selectedTransaction->items->count() }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment</p>
                                <p class="mt-1 font-bold text-slate-950">{{ strtoupper($selectedTransaction->payment_method) }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
                                <p class="mt-1 font-bold text-slate-950">Rp {{ number_format($selectedTransaction->grand_total, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                <p class="mt-1 font-bold text-emerald-700">{{ ucfirst($selectedTransaction->status) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-[1.5rem] border border-slate-100">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">Menu</th>
                                        <th class="px-4 py-3 text-left font-semibold">Detail</th>
                                        <th class="px-4 py-3 text-center font-semibold">Qty</th>
                                        <th class="px-4 py-3 text-right font-semibold">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach ($selectedTransaction->items as $item)
                                        <tr>
                                            <td class="px-4 py-3 font-semibold text-slate-950">{{ optional($item->menu)->name ?: '-' }}</td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <div class="space-y-1">
                                                    <p>Grade: {{ $item->item_options['matcha_grade'] ?? '-' }}</p>
                                                    <p>Origin: {{ $item->item_options['matcha_origin'] ?? '-' }}</p>
                                                    @if (! empty($item->item_options['variant']))<p>Varian: {{ $item->item_options['variant'] }}</p>@endif
                                                    <p>Toppings: {{ ! empty($item->item_options['toppings']) ? implode(', ', $item->item_options['toppings']) : '-' }}</p>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center font-semibold text-slate-900">{{ $item->quantity }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-slate-900">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>
            @endif

            <div class="mt-4 space-y-4">
                @forelse ($transactions as $transaction)
                    <article class="rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Invoice</p>
                                <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $transaction->transaction_number }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ optional($transaction->sold_at)->format('d M Y, H:i') }}</p><p class="mt-2 text-sm font-semibold text-slate-700">{{ $transaction->customer_name ?: '-' }} · {{ $transaction->service_type === 'dine_in' ? 'Dine in' : ($transaction->service_type === 'take_away' ? 'Take away' : '-') }}@if($transaction->pager_number) · Pager {{ $transaction->pager_number }}@endif</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                <div class="rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Items</p>
                                    <p class="mt-1 font-bold text-emerald-950">{{ $transaction->items_count }}</p>
                                </div>
                                <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment</p>
                                    <p class="mt-1 font-bold text-slate-950">{{ strtoupper($transaction->payment_method) }}</p>
                                </div>
                                <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
                                    <p class="mt-1 font-bold text-slate-950">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</p>
                                </div>
                                <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                    <p class="mt-1 font-bold text-emerald-700">{{ ucfirst($transaction->status) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="{{ route('history.show', $transaction) }}" class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-800">View Detail</a>
                            <a href="{{ route('receipt') }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50">Open Latest Receipt</a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[2rem] border border-dashed border-emerald-200 bg-white/90 p-8 text-center shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                        <p class="text-lg font-bold text-emerald-950">Belum ada transaksi</p>
                        <p class="mt-2 text-sm text-slate-600">Transaksi yang sudah dibayar akan muncul di halaman ini.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-5">
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</body>
</html>
