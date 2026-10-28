<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Receipt - Matcha House POS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef7ef] font-sans text-slate-900 antialiased">
<x-pos-sidebar />
<div x-data="{ sidebarExpanded: localStorage.getItem('pos-sidebar-expanded') === 'true' }" @pos-sidebar.window="sidebarExpanded = $event.detail.expanded" :class="sidebarExpanded ? 'lg:ml-[300px]' : 'lg:ml-20'" class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(167,243,208,0.45),_transparent_36%),linear-gradient(180deg,_#f8fbf8_0%,_#edf7ee_100%)] transition-[margin] duration-300">
    <div class="mx-auto max-w-[1200px] p-4 lg:p-6">
        <div class="rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-700">Payment Success</p>
                    <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-emerald-950">Receipt</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">Transaksi berhasil diproses dan siap dicetak.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-800">Print Receipt</button>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50">Back to Dashboard</a>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
            <section class="rounded-[2rem] border border-emerald-200 bg-white/95 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Invoice Number</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">{{ $transaction->transaction_number }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ optional($transaction->sold_at)->format('d M Y, H:i') }}</p>

                <div class="mt-5 rounded-2xl bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-100">
                    <div class="flex justify-between gap-4"><span class="text-slate-500">Pemesan</span><strong class="text-right text-slate-950">{{ $transaction->customer_name ?: '-' }}</strong></div>
                    <div class="mt-2 flex justify-between gap-4"><span class="text-slate-500">Tipe pesanan</span><strong class="text-right text-slate-950">{{ $transaction->service_type === 'dine_in' ? 'Dine in' : ($transaction->service_type === 'take_away' ? 'Take away' : '-') }}</strong></div>
                    <div class="mt-2 flex justify-between gap-4"><span class="text-slate-500">Pengambilan</span><strong class="text-right text-slate-950">{{ $transaction->fulfillment_type === 'table_delivery' ? 'Diantar ke meja' : 'Self service' }}</strong></div>

                    @if ($transaction->table_number)
                        <div class="mt-2 flex justify-between gap-4"><span class="text-slate-500">Nomor meja</span><strong class="text-right text-slate-950">{{ $transaction->table_number }}</strong></div>
                    @endif

                    @if ($transaction->pager_number)
                        <div class="mt-2 flex justify-between gap-4"><span class="text-slate-500">Nomor pager</span><strong class="text-right text-slate-950">{{ $transaction->pager_number }}</strong></div>
                    @endif
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Payment Method</p>
                        <p class="mt-1 text-lg font-bold text-emerald-950">{{ strtoupper($transaction->payment_method) }}</p>
                    </div>
                    <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Payment</p>
                        <p class="mt-1 text-lg font-bold text-slate-950">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount Paid</p>
                        <p class="mt-1 text-lg font-bold text-slate-950">Rp {{ number_format($transaction->amount_paid, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Change</p>
                        <p class="mt-1 text-lg font-bold text-slate-950">Rp {{ number_format($transaction->change_amount, 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-[1.5rem] bg-slate-950 p-4 text-white">
                    <div class="flex items-center justify-between text-sm text-emerald-100">
                        <span>Status</span>
                        <span>{{ ucfirst($transaction->status) }}</span>
                    </div>
                    <p class="mt-3 text-2xl font-extrabold tracking-tight">{{ $transaction->transaction_number }}</p>
                    <p class="mt-2 text-sm text-slate-300">Cashier: {{ optional($transaction->cashier)->name ?: 'System' }}</p>
                </div>
            </section>

            <section class="rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-700">Receipt Items</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">Order Detail</h2>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-100">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Items</p>
                        <p class="mt-1 text-xl font-extrabold text-emerald-950">{{ $transaction->items->count() }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($transaction->items as $item)
                        <article class="rounded-[1.5rem] border border-slate-100 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h3 class="break-words text-lg font-bold text-slate-950">{{ optional($item->menu)->name ?: '-' }}</h3>
                                    <div class="mt-2 space-y-1 text-sm text-slate-600">
                                        <p>Grade: {{ $item->item_options['matcha_grade'] ?? '-' }}</p>
                                        <p>Origin: {{ $item->item_options['matcha_origin'] ?? '-' }}</p>
                                        @if (! empty($item->item_options['variant']))<p>Varian: {{ $item->item_options['variant'] }}</p>@endif
                                        <p class="break-words">Toppings: {{ ! empty($item->item_options['toppings']) ? implode(', ', $item->item_options['toppings']) : '-' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Qty</p>
                                    <p class="text-lg font-bold text-slate-950">{{ $item->quantity }}</p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Line Total</p>
                                    <p class="text-lg font-bold text-emerald-950">Rp {{ number_format($item->line_total, 0, ',', '.') }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</div>
</body>
</html>
