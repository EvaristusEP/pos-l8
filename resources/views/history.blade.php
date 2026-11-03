<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transaction History - JOOAL POS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef7ef] font-sans text-slate-900 antialiased">
    <x-pos-sidebar />
    <div class="min-h-screen min-w-0 bg-[#f5f8f5]">
        <div class="mx-auto max-w-[1600px] p-4 lg:p-6">
            <div class="pos-page-header rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">JOOAL POS</p>
                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-emerald-950">Riwayat Transaksi</h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600">Transaksi aktual, termasuk cabang, pelanggan, detail item, pembayaran, status, dan total.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if (auth()->user()->isManager() || auth()->user()->isCashier())
                            <a href="{{ route('checkout') }}" class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-800">Checkout</a>
                        @endif
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('history') }}" class="pos-filter mt-4">
                @if(auth()->user()->isOwner())<label class="text-sm font-semibold">Cabang<select name="branch_id" class="pos-input mt-1"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)($filters['branch_id'] ?? '')===(string)$branch->id)>{{ $branch->display_name }}</option>@endforeach</select></label>@endif
                <label class="text-sm font-semibold">Dari tanggal<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="pos-input mt-1"></label>
                <label class="text-sm font-semibold">Sampai tanggal<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="pos-input mt-1"></label>
                <button class="pos-button self-end bg-emerald-700 font-semibold text-white">Filter transaksi</button>
            </form>

            @if (session('status'))
                <div role="status" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
            @endif

            @if ($selectedTransaction)
                <article class="mt-4 rounded-[2rem] border border-emerald-200 bg-white/95 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Transaction Detail</p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $selectedTransaction->transaction_number }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ optional($selectedTransaction->branch)->display_name ?: 'Histori tanpa cabang' }}</p>
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

                    @if ($canViewRefunds)
                        <section class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-emerald-950">Catatan refund</h3>
                                    <p class="mt-1 text-sm text-slate-600">Invoice dan nilai transaksi asli di atas tetap seperti saat transaksi dibuat.</p>
                                </div>
                                @if ($selectedRefundSummary && $selectedRefundSummary['total_refunded'] !== null)
                                    <div class="text-sm">
                                        <p><span class="text-slate-600">Total refund tercatat:</span> <strong class="text-slate-900">Rp {{ number_format((float) $selectedRefundSummary['total_refunded'], 0, ',', '.') }}</strong></p>
                                        @if ($selectedRefundSummary['remaining_refundable'] !== null)
                                            <p class="mt-1"><span class="text-slate-600">Sisa nilai refundable:</span> <strong class="text-slate-900">Rp {{ number_format((float) $selectedRefundSummary['remaining_refundable'], 0, ',', '.') }}</strong></p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if ($selectedTransaction->refunds->isEmpty())
                                <p class="mt-4 rounded-xl bg-white/80 px-4 py-3 text-sm text-slate-600">Belum ada refund untuk transaksi ini.</p>
                            @else
                                <div class="mt-4 space-y-3">
                                    @foreach ($selectedTransaction->refunds as $refund)
                                        <article class="rounded-xl border border-emerald-100 bg-white p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-bold text-slate-900">{{ $refund->refund_number }}</p>
                                                    <p class="mt-1 text-xs text-slate-600">Dicatat {{ optional($refund->recorded_at)->format('d M Y, H:i') }} oleh {{ optional($refund->recorder)->name ?: 'Pengguna tidak tersedia' }}</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="font-bold text-slate-900">Rp {{ number_format((float) $refund->amount, 0, ',', '.') }}</p>
                                                    @if ($refund->status === 'manually_confirmed')
                                                        <span class="mt-1 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-900">Dikonfirmasi manual — tidak diverifikasi sistem</span>
                                                    @else
                                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">Dicatat — belum terverifikasi</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <p class="mt-3 text-sm text-slate-700"><span class="font-semibold">Alasan:</span> {{ $refund->reason }}</p>
                                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                                @foreach ($refund->items as $refundItem)
                                                    <li>{{ optional($refundItem->transactionItem)->menu_name ?: 'Item transaksi' }} · {{ $refundItem->quantity }} item · Rp {{ number_format((float) $refundItem->amount, 0, ',', '.') }}</li>
                                                @endforeach
                                            </ul>
                                            @if ($refund->status === 'recorded')
                                                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">Tercatat di POS bukan bukti bahwa uang telah dikembalikan.</p>
                                            @else
                                                <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-900">Dikonfirmasi manual oleh {{ optional($refund->confirmer)->name ?: 'petugas' }} pada {{ optional($refund->manually_confirmed_at)->format('d M Y, H:i') }}. Sistem tidak memverifikasi pembayaran secara independen.</p>
                                            @endif
                                            @if ($refund->status === 'recorded')
                                                <form method="POST" action="{{ route('transactions.refunds.confirm-manual', [$selectedTransaction, $refund]) }}" class="refund-confirm-form mt-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex min-h-10 items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Konfirmasi manual</button>
                                                </form>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif

                            @if ($selectedRefundSummary && $selectedRefundSummary['remaining_quantities'] !== null && (float) $selectedRefundSummary['remaining_refundable'] > 0)
                                <form id="refund-create-form" method="POST" action="{{ route('transactions.refunds.store', $selectedTransaction) }}" data-endpoint="{{ route('transactions.refunds.store', $selectedTransaction) }}" data-remaining-cents="{{ (int) round((float) $selectedRefundSummary['remaining_refundable'] * 100) }}" class="mt-5 rounded-xl border border-emerald-200 bg-white p-4 sm:p-5">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <h4 class="text-base font-bold text-slate-900">Catat refund</h4>
                                    <p class="mt-1 text-sm text-slate-600">Estimasi hanya untuk referensi. Nilai akhir dihitung dari snapshot transaksi oleh server.</p>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm text-slate-800">
                                            <input type="radio" name="refund_type" value="full" checked class="mt-1">
                                            <span><strong>Refund penuh</strong><span class="block text-slate-600">Sisa nilai transaksi yang belum direfund.</span></span>
                                        </label>
                                        <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm text-slate-800">
                                            <input type="radio" name="refund_type" value="partial" class="mt-1">
                                            <span><strong>Refund sebagian</strong><span class="block text-slate-600">Pilih item dan kuantitas di bawah.</span></span>
                                        </label>
                                    </div>
                                    <fieldset id="refund-items" disabled class="mt-4 space-y-2">
                                        <legend class="mb-2 text-sm font-semibold text-slate-800">Item yang direfund</legend>
                                        @foreach ($selectedTransaction->items as $item)
                                            @php
                                                $remainingQuantity = (int) ($selectedRefundSummary['remaining_quantities'][$item->getKey()] ?? 0);
                                                $lineCents = (int) round((float) $item->line_total * 100);
                                                $refundedQuantity = (int) $item->quantity - $remainingQuantity;
                                            @endphp
                                            @if ($remainingQuantity > 0)
                                                <label class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                    <span class="flex min-w-0 flex-1 items-start gap-2 text-slate-800">
                                                        <input type="checkbox" class="refund-item-toggle mt-1" value="{{ $item->getKey() }}">
                                                        <span><strong>{{ $item->menu_name ?: (optional($item->menu)->name ?: 'Item transaksi') }}</strong><span class="block text-slate-600">Sisa {{ $remainingQuantity }} dari {{ $item->quantity }} · Rp {{ number_format((float) $item->line_total, 0, ',', '.') }}</span></span>
                                                    </span>
                                                    <input type="number" min="1" max="{{ $remainingQuantity }}" value="1" disabled aria-label="Kuantitas refund {{ $item->menu_name ?: 'item' }}" class="refund-item-quantity w-20 rounded-lg border border-slate-300 px-2 py-1 text-slate-900" data-item-id="{{ $item->getKey() }}" data-line-cents="{{ $lineCents }}" data-sold-quantity="{{ (int) $item->quantity }}" data-refunded-quantity="{{ $refundedQuantity }}">
                                                </label>
                                            @endif
                                        @endforeach
                                    </fieldset>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <label class="block text-sm font-semibold text-slate-800">Metode pencatatan manual
                                            <select name="manual_method" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900">
                                                <option value="cash">Tunai</option>
                                                <option value="bank_transfer">Transfer bank</option>
                                                <option value="other">Lainnya</option>
                                            </select>
                                        </label>
                                        <label class="block text-sm font-semibold text-slate-800">Alasan refund <span class="font-normal text-slate-600">(wajib)</span>
                                            <textarea name="reason" required minlength="3" maxlength="2000" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900" placeholder="Tuliskan alasan refund"></textarea>
                                        </label>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-slate-800">Estimasi refund: <span id="refund-estimate">Rp {{ number_format((float) $selectedRefundSummary['remaining_refundable'], 0, ',', '.') }}</span></p>
                                    <p id="refund-form-message" class="mt-2 hidden rounded-lg px-3 py-2 text-sm" role="status" aria-live="polite"></p>
                                    <button type="submit" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Catat refund</button>
                                </form>
                            @elseif ($selectedRefundSummary && $selectedRefundSummary['remaining_quantities'] !== null)
                                <p class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700">Tidak ada nilai atau kuantitas yang tersisa untuk direfund.</p>
                            @else
                                <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Refund tidak dapat ditawarkan karena nilai atau rincian transaksi tidak dapat direkonsiliasi dengan aman.</p>
                            @endif
                        </section>
                    @endif
                </article>
            @endif

            <div class="mt-4 space-y-4">
                @forelse ($transactions as $transaction)
                    <article class="rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_20px_80px_rgba(16,24,40,0.08)] backdrop-blur-xl">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Invoice</p>
                                <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $transaction->transaction_number }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ optional($transaction->sold_at)->format('d M Y, H:i') }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700">{{ optional($transaction->branch)->display_name ?: 'Tanpa cabang' }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700">{{ $transaction->customer_name ?: '-' }} / {{ $transaction->service_type === 'dine_in' ? 'Dine in' : ($transaction->service_type === 'take_away' ? 'Take away' : '-') }}@if($transaction->pager_number) / Pager {{ $transaction->pager_number }}@endif</p>
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

                        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-700" aria-label="Item transaksi">
                            @forelse ($transaction->items as $item)
                                <li>{{ $item->menu_name ?: optional($item->menu)->name ?: 'Item' }} × {{ $item->quantity }}</li>
                            @empty
                                <li>Detail item tidak tersedia</li>
                            @endforelse
                        </ul>

                        @if ($canViewRefunds && ! empty($refundSummaries[$transaction->getKey()]['has_refunds']))
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                <span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-900">Memiliki refund</span>
                                @if ($refundSummaries[$transaction->getKey()]['total_refunded'] !== null)
                                    <span class="text-slate-700">Refund tercatat Rp {{ number_format((float) $refundSummaries[$transaction->getKey()]['total_refunded'], 0, ',', '.') }}</span>
                                @else
                                    <span class="text-amber-900">Nilai refund perlu ditinjau</span>
                                @endif
                            </div>
                        @endif

                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="{{ route('history.show', $transaction) }}" class="inline-flex items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-800">{{ $canViewRefunds && ! empty($refundSummaries[$transaction->getKey()]['has_refunds']) ? 'Detail & Refund' : 'View Detail' }}</a>
                            <a href="{{ route('receipt.show', $transaction) }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-50">Buka invoice</a>
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
    <script>
        (function () {
            var refundForm = document.getElementById('refund-create-form');
            if (refundForm) {
                var typeInputs = refundForm.querySelectorAll('input[name="refund_type"]');
                var itemFieldset = document.getElementById('refund-items');
                var estimate = document.getElementById('refund-estimate');
                var message = document.getElementById('refund-form-message');
                var submitButton = refundForm.querySelector('button[type="submit"]');
                var requestSignature = null;
                var pendingStorageKey = 'jooal-refund-pending:' + refundForm.dataset.endpoint;

                function selectedType() {
                    var selected = refundForm.querySelector('input[name="refund_type"]:checked');
                    return selected ? selected.value : 'full';
                }

                function formatRupiah(cents) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(cents / 100);
                }

                function newIdempotencyKey() {
                    return window.crypto && window.crypto.randomUUID
                        ? window.crypto.randomUUID()
                        : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
                }

                function restorePendingRequest() {
                    try {
                        var pending = JSON.parse(window.sessionStorage.getItem(pendingStorageKey) || 'null');
                        if (!pending || !pending.payload || !pending.key) return;
                        refundForm.elements.idempotency_key.value = pending.key;
                        requestSignature = pending.signature;
                        refundForm.querySelector('input[name="refund_type"][value="' + pending.payload.refund_type + '"]').checked = true;
                        refundForm.elements.manual_method.value = pending.payload.manual_method;
                        refundForm.elements.reason.value = pending.payload.reason;
                        (pending.payload.items || []).forEach(function (item) {
                            var checkbox = refundForm.querySelector('.refund-item-toggle[value="' + item.transaction_item_id + '"]');
                            if (!checkbox) return;
                            checkbox.checked = true;
                            checkbox.closest('label').querySelector('.refund-item-quantity').value = item.quantity;
                        });
                        showRefundMessage('Ada permintaan sebelumnya yang responsnya belum diterima. Isian dipulihkan; kirim ulang agar server memeriksa dengan kunci yang sama.', true);
                    } catch (error) {
                        window.sessionStorage.removeItem(pendingStorageKey);
                    }
                }

                function updateRefundForm() {
                    var partial = selectedType() === 'partial';
                    itemFieldset.disabled = !partial;
                    var totalCents = partial ? 0 : Number(refundForm.dataset.remainingCents || 0);
                    refundForm.querySelectorAll('.refund-item-toggle').forEach(function (checkbox) {
                        var quantity = checkbox.closest('label').querySelector('.refund-item-quantity');
                        quantity.disabled = !partial || !checkbox.checked;
                        if (partial && checkbox.checked) {
                            var amount = Number(quantity.dataset.lineCents || 0);
                            var sold = Number(quantity.dataset.soldQuantity || 0);
                            var prior = Number(quantity.dataset.refundedQuantity || 0);
                            var selectedQuantity = Number(quantity.value || 0);
                            if (sold > 0 && selectedQuantity > 0) {
                                totalCents += Math.floor(amount * (prior + selectedQuantity) / sold) - Math.floor(amount * prior / sold);
                            }
                        }
                    });
                    estimate.textContent = formatRupiah(totalCents);
                }

                function showRefundMessage(text, isError) {
                    message.textContent = text;
                    message.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-900', 'bg-rose-50', 'text-rose-900');
                    message.classList.add(isError ? 'bg-rose-50' : 'bg-emerald-50', isError ? 'text-rose-900' : 'text-emerald-900');
                }

                typeInputs.forEach(function (input) { input.addEventListener('change', updateRefundForm); });
                refundForm.querySelectorAll('.refund-item-toggle, .refund-item-quantity').forEach(function (input) {
                    input.addEventListener('change', updateRefundForm);
                    input.addEventListener('input', updateRefundForm);
                });

                refundForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    var type = selectedType();
                    var items = [];
                    if (type === 'partial') {
                        refundForm.querySelectorAll('.refund-item-toggle:checked').forEach(function (checkbox) {
                            var quantity = checkbox.closest('label').querySelector('.refund-item-quantity');
                            items.push({ transaction_item_id: Number(checkbox.value), quantity: Number(quantity.value) });
                        });
                        if (!items.length) {
                            showRefundMessage('Pilih setidaknya satu item untuk refund sebagian.', true);
                            return;
                        }
                    }

                    var payload = {
                        refund_type: type,
                        manual_method: refundForm.elements.manual_method.value,
                        reason: refundForm.elements.reason.value.trim()
                    };
                    if (type === 'partial') payload.items = items;
                    var signature = JSON.stringify(payload);
                    var keyInput = refundForm.elements.idempotency_key;
                    if (requestSignature && requestSignature !== signature) {
                        showRefundMessage('Permintaan sebelumnya mungkin belum mendapat respons. Muat ulang halaman sebelum mengubah pilihan refund.', true);
                        return;
                    }
                    requestSignature = signature;
                    var pendingRequestSaved = false;
                    try {
                        window.sessionStorage.setItem(pendingStorageKey, JSON.stringify({
                            key: keyInput.value,
                            signature: signature,
                            payload: payload
                        }));
                        pendingRequestSaved = true;
                    } catch (error) {
                        showRefundMessage('Penyimpanan sesi untuk mengulang permintaan tidak tersedia pada browser ini. Permintaan belum dikirim; periksa pengaturan browser lalu coba lagi.', true);
                    }
                    if (!pendingRequestSaved) return;
                    submitButton.disabled = true;
                    submitButton.textContent = 'Mengirim…';
                    try {
                        var response = await fetch(refundForm.dataset.endpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': refundForm.querySelector('input[name="_token"]').value,
                                'Idempotency-Key': keyInput.value
                            },
                            body: JSON.stringify(payload)
                        });
                        var result = await response.json().catch(function () { return {}; });
                        var contentType = response.headers.get('content-type') || '';
                        if (!response.ok || response.redirected || contentType.indexOf('application/json') === -1) {
                            var errors = result.errors ? Object.keys(result.errors).map(function (key) { return result.errors[key].join(' '); }).join(' ') : result.message;
                            showRefundMessage(errors || 'Refund gagal diproses. Periksa data dan coba lagi.', true);
                            if (response.status < 500 && !response.redirected) {
                                window.sessionStorage.removeItem(pendingStorageKey);
                                requestSignature = null;
                                keyInput.value = newIdempotencyKey();
                            }
                            return;
                        }
                        window.sessionStorage.removeItem(pendingStorageKey);
                        showRefundMessage(result.message || 'Catatan refund berhasil dibuat.', false);
                        window.location.reload();
                    } catch (error) {
                        showRefundMessage('Koneksi terputus atau respons server tidak diterima. Jangan ubah isian; kirim ulang untuk memeriksa permintaan yang sama dengan kunci idempotensi yang sama.', true);
                    } finally {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Catat refund';
                    }
                });
                restorePendingRequest();
                updateRefundForm();
            }

            document.querySelectorAll('.refund-confirm-form').forEach(function (form) {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    if (!window.confirm('Tindakan ini hanya mencatat konfirmasi manual petugas dan tidak memverifikasi dana secara otomatis. Lanjutkan?')) return;
                    var button = form.querySelector('button[type="submit"]');
                    button.disabled = true;
                    try {
                        var response = await fetch(form.action, {
                            method: 'PATCH',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                            }
                        });
                        var result = await response.json().catch(function () { return {}; });
                        var contentType = response.headers.get('content-type') || '';
                        if (!response.ok || response.redirected || contentType.indexOf('application/json') === -1) {
                            throw new Error(result.message || 'Konfirmasi manual gagal. Status tidak diubah.');
                        }
                        window.alert(result.message || 'Konfirmasi manual berhasil dicatat.');
                        window.location.reload();
                    } catch (error) {
                        window.alert(error.message || 'Koneksi gagal. Konfirmasi belum dapat dipastikan; muat ulang halaman untuk memeriksa status.');
                        button.disabled = false;
                    }
                });
            });
        })();
    </script>
</body>
</html>
