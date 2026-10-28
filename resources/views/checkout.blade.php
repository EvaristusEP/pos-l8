<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Checkout · Matcha House POS</title><link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased"><x-pos-sidebar />
<main x-data="{ sidebarExpanded: localStorage.getItem('pos-sidebar-expanded') === 'true' }" @pos-sidebar.window="sidebarExpanded = $event.detail.expanded" :class="sidebarExpanded ? 'lg:ml-[300px]' : 'lg:ml-20'" class="min-h-screen px-4 py-5 transition-[margin] duration-300 lg:px-7">
    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-700">Pembayaran</p><h1 class="mt-1 text-3xl font-extrabold text-[#123c35]">Checkout</h1><p class="mt-1 text-sm text-slate-500">Pastikan pesanan dan metode pembayaran sudah benar.</p></div><a href="{{ route('dashboard') }}" class="pos-button self-start bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 sm:self-auto">← Kembali ke pesanan</a></header>
    @if ($errors->any())<div class="mb-5 rounded-2xl bg-rose-50 p-4 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_420px]"><section class="min-w-0 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100"><div class="flex items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Order summary</p><h2 class="text-xl font-extrabold">Detail pesanan</h2></div><span class="shrink-0 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800">{{ $cartItemCount }} item</span></div><div class="mt-5 max-h-[65vh] space-y-3 overflow-y-auto pr-1">@forelse($cart as $item)<article class="rounded-2xl bg-slate-50 p-4"><div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><h3 class="break-words font-extrabold">{{ $item['menu_name'] }}</h3>
@if($item['matcha_grade_name'])
<p class="mt-1 break-words text-sm text-slate-600">{{ $item['matcha_grade_name'] }}{{ $item['matcha_origin_name'] ? ' · '.$item['matcha_origin_name'] : '' }}</p>
@endif
@if($item['menu_variant_name'])
<p class="mt-1 break-words text-sm text-slate-600">Varian: {{ $item['menu_variant_name'] }}</p>
@endif
@if(!empty($item['topping_names']))
<p class="mt-1 break-words text-sm text-slate-500">{{ implode(', ', $item['topping_names']) }}</p>
@endif
<p class="mt-2 text-sm font-semibold text-slate-700">Qty {{ $item['quantity'] }}</p></div><strong class="shrink-0 whitespace-nowrap text-emerald-800">Rp {{ number_format($item['line_total'],0,',','.') }}</strong></div></article>@empty<div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500">Cart kosong. <a class="font-bold text-emerald-700" href="{{ route('dashboard') }}">Pilih menu dulu</a>.</div>@endforelse</div></section>
    <aside class="h-fit rounded-3xl bg-[#123c35] p-5 text-white shadow-lg xl:sticky xl:top-5"><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-200">Total tagihan</p><h2 class="mt-1 text-3xl font-extrabold" data-checkout-subtotal>Rp {{ number_format($cartSubtotal,0,',','.') }}</h2><form method="POST" action="{{ route('checkout.store') }}" class="mt-6 space-y-4" data-checkout-form data-subtotal="{{ $cartSubtotal }}" data-cash-submit-label="Selesaikan pembayaran" data-qris-submit-label="Konfirmasi pembayaran QRIS">@csrf
        @php($hasPastry = collect($cart)->contains(fn ($item) => ($item['category_slug'] ?? null) === 'pastry'))
        @php($hasBeverage = collect($cart)->contains(fn ($item) => in_array($item['category_slug'] ?? null, ['matcha', 'non-matcha'], true)))
        <div class="space-y-3 border-b border-white/10 pb-5" data-order-fulfillment data-has-pastry="{{ $hasPastry ? 'true' : 'false' }}" data-has-beverage="{{ $hasBeverage ? 'true' : 'false' }}"><p class="text-sm font-bold text-emerald-50">Informasi pesanan</p><label class="block text-sm font-bold text-emerald-50">Nama pemesan<input type="text" name="customer_name" value="{{ old('customer_name') }}" required maxlength="100" autocomplete="name" class="mt-2 w-full rounded-xl border-0 bg-white px-4 py-3 text-slate-900"></label><fieldset><legend class="mb-2 text-sm font-bold text-emerald-50">Tipe pesanan</legend><div class="grid grid-cols-2 gap-3"><label class="cursor-pointer rounded-2xl bg-white/10 p-3 text-center text-sm font-bold ring-1 ring-white/10 has-[:checked]:bg-emerald-400 has-[:checked]:text-[#123c35]"><input class="sr-only" type="radio" name="service_type" value="dine_in" @checked(old('service_type', 'dine_in') === 'dine_in')>Dine in</label><label class="cursor-pointer rounded-2xl bg-white/10 p-3 text-center text-sm font-bold ring-1 ring-white/10 has-[:checked]:bg-emerald-400 has-[:checked]:text-[#123c35]"><input class="sr-only" type="radio" name="service_type" value="take_away" @checked(old('service_type') === 'take_away')>Take away</label></div></fieldset><div data-fulfillment-self-service><p class="rounded-xl bg-white/10 px-3 py-2 text-sm text-emerald-50"><strong>Self service.</strong> Minuman dan pesanan take away diambil di counter.</p><label class="mt-3 block text-sm font-bold text-emerald-50">Nomor pager<input type="text" name="pager_number" value="{{ old('pager_number') }}" maxlength="30" inputmode="numeric" autocomplete="off" class="mt-2 w-full rounded-xl border-0 bg-white px-4 py-3 text-slate-900"></label></div><div data-fulfillment-table class="hidden"><p class="rounded-xl bg-emerald-400 px-3 py-2 text-sm font-medium text-[#123c35]"><strong>Pastry akan diantar ke meja.</strong> Minuman tetap diambil di counter.</p><label class="mt-3 block text-sm font-bold text-emerald-50">Nomor meja<input type="text" name="table_number" value="{{ old('table_number') }}" maxlength="30" inputmode="numeric" autocomplete="off" class="mt-2 w-full rounded-xl border-0 bg-white px-4 py-3 text-slate-900"></label></div></div>
        <fieldset><legend class="mb-2 text-sm font-bold text-emerald-50">Payment method</legend><div class="grid grid-cols-2 gap-3"><label class="cursor-pointer rounded-2xl bg-white/10 p-4 text-center text-sm font-bold ring-1 ring-white/10 has-[:checked]:bg-emerald-400 has-[:checked]:text-[#123c35]"><input class="sr-only" type="radio" name="payment_method" value="cash" checked>Cash</label><label class="cursor-pointer rounded-2xl bg-white/10 p-4 text-center text-sm font-bold ring-1 ring-white/10 has-[:checked]:bg-emerald-400 has-[:checked]:text-[#123c35]"><input class="sr-only" type="radio" name="payment_method" value="qris">QRIS</label></div></fieldset>
        <div data-payment-panel="cash" class="space-y-3"><label class="block text-sm font-bold text-emerald-50">Uang diterima<input type="number" inputmode="numeric" name="cash_amount" data-cash-input value="{{ $cashAmount ?? '' }}" min="0" class="mt-2 w-full rounded-xl border-0 bg-white px-4 py-3 text-slate-900" placeholder="Contoh: 50000"></label><div class="flex justify-between gap-3 rounded-xl bg-white/10 px-4 py-3 text-sm"><span class="text-emerald-100">Kembalian</span><strong data-cash-change>Rp {{ number_format($cashChange ?? 0,0,',','.') }}</strong></div></div>
        <div data-payment-panel="qris" class="hidden rounded-2xl bg-white p-5 text-center text-slate-800"><div class="mx-auto grid h-36 w-36 place-items-center rounded-xl border-4 border-slate-900 text-4xl" aria-label="Kode QRIS">▦</div><p class="mt-3 text-sm font-bold">QRIS</p><p class="mt-1 text-xs leading-5 text-slate-500">Scan QRIS untuk membayar</p></div>
        <button type="submit" data-checkout-submit @disabled($cartItemCount === 0) class="pos-button w-full bg-emerald-400 text-[#123c35] hover:bg-emerald-300 disabled:cursor-not-allowed disabled:bg-slate-500 disabled:text-slate-300">Selesaikan pembayaran</button></form></aside></div>
    <script>
        (function () {
            var form = document.querySelector('[data-checkout-form]');

            if (!form) {
                return;
            }

            var radios = form.querySelectorAll('input[name="payment_method"]');
            var cashPanel = form.querySelector('[data-payment-panel="cash"]');
            var qrisPanel = form.querySelector('[data-payment-panel="qris"]');
            var cashInput = form.querySelector('[data-cash-input]');
            var subtotal = Number(form.getAttribute('data-subtotal') || 0);

            if (qrisPanel && !qrisPanel.querySelector('[data-qris-total]')) {
                qrisPanel.insertAdjacentHTML('beforeend', '<p class="mt-3 text-sm font-bold text-emerald-800" data-qris-total></p>');
            }

            function formatCurrency(value) {
                return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
            }

            function syncPaymentPanel() {
                var selected = form.querySelector('input[name="payment_method"]:checked');
                var isQris = selected && selected.value === 'qris';

                if (cashPanel) {
                    cashPanel.classList.toggle('hidden', isQris);
                }

                if (cashInput) {
                    cashInput.disabled = isQris;
                }

                if (qrisPanel) {
                    qrisPanel.classList.toggle('hidden', !isQris);
                    if (isQris) {
                        qrisPanel.style.setProperty('display', 'block', 'important');
                    } else {
                        qrisPanel.style.removeProperty('display');
                    }

                    var total = qrisPanel.querySelector('[data-qris-total]');
                    if (total) {
                        total.textContent = 'Total pembayaran: ' + formatCurrency(subtotal);
                    }
                }
            }

            for (var index = 0; index < radios.length; index++) {
                radios[index].addEventListener('change', syncPaymentPanel);
            }

            syncPaymentPanel();

            document.addEventListener('DOMContentLoaded', function () {
                var fulfillmentPanel = document.querySelector('[data-order-fulfillment]');

                if (!fulfillmentPanel) {
                    return;
                }

                var selfServicePanel = fulfillmentPanel.querySelector('[data-fulfillment-self-service]');
                var pagerInput = fulfillmentPanel.querySelector('input[name="pager_number"]');

                function syncPagerRequirement() {
                    if (selfServicePanel) {
                        selfServicePanel.classList.remove('hidden');
                    }

                    if (pagerInput) {
                        pagerInput.disabled = false;
                        pagerInput.required = true;
                    }
                }

                var serviceInputs = fulfillmentPanel.querySelectorAll('input[name="service_type"]');
                for (var serviceIndex = 0; serviceIndex < serviceInputs.length; serviceIndex++) {
                    serviceInputs[serviceIndex].addEventListener('change', function () {
                        window.setTimeout(syncPagerRequirement, 0);
                    });
                }

                window.setTimeout(syncPagerRequirement, 0);
            });
        }());
    </script>
</main></body></html>
