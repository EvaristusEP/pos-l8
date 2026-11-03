<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kupon · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900">
<x-pos-sidebar />
<main class="min-h-screen min-w-0 p-4 lg:p-6">
    <div class="mx-auto w-full max-w-6xl min-w-0 space-y-5">
        <header class="pos-page-header rounded-3xl bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Promosi · JOOAL POS</p>
            <h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Kupon</h1>
            <p class="mt-1 text-sm text-slate-500">Kupon berlaku di seluruh cabang; satu kode dapat digunakan sekali per checkout sampai batas total tercapai.</p>
        </header>

        @if(session('success'))<p class="rounded-xl bg-emerald-50 p-3 text-emerald-800" role="status">{{ session('success') }}</p>@endif
        @if($errors->any())<p class="rounded-xl bg-rose-50 p-3 text-rose-800" role="alert">{{ $errors->first() }}</p>@endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <button type="button" class="pos-button bg-emerald-700 text-white" onclick="document.getElementById('coupon-create-dialog').showModal()">Buat kupon</button>
        </section>

        <section class="space-y-3" aria-label="Daftar kupon">
            @forelse($coupons as $coupon)
                <article class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-extrabold">{{ $coupon->code }} <span class="rounded-lg px-2 py-1 text-xs {{ $coupon->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $coupon->is_active ? 'Aktif' : 'Nonaktif' }}</span></h2>
                            <p class="mt-1 text-sm text-slate-600">{{ $coupon->discount_type === 'percentage' ? number_format($coupon->discount_value, 2, ',', '.').'%' : 'Rp '.number_format($coupon->discount_value, 0, ',', '.') }} · dipakai {{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }} · {{ $coupon->transactions_count }} transaksi</p>
                            <p class="text-xs text-slate-500">{{ $coupon->starts_at->format('d/m/Y H:i') }} – {{ $coupon->ends_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('coupons.toggle-status', $coupon) }}">@csrf @method('PATCH')<button class="rounded-lg px-3 py-2 text-sm font-bold {{ $coupon->is_active ? 'bg-rose-50 text-rose-700' : 'bg-emerald-700 text-white' }}">{{ $coupon->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                            @if($coupon->used_count === 0 && $coupon->transactions_count === 0)<form method="POST" action="{{ route('coupons.destroy', $coupon) }}" onsubmit="return confirm('Hapus kupon yang belum pernah digunakan?')">@csrf @method('DELETE')<button class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-bold text-rose-700">Hapus</button></form>@endif
                        </div>
                    </div>
                    <button type="button" class="pos-button mt-3 border border-slate-300 px-3 py-2" onclick="document.getElementById('coupon-edit-dialog-{{ $coupon->id }}').showModal()">Edit kupon</button>
                </article>
            @empty
                <p class="rounded-xl border bg-white p-6 text-center text-slate-500">Belum ada kupon.</p>
            @endforelse
        </section>
    </div>
</main>

<dialog id="coupon-create-dialog" aria-labelledby="coupon-create-title" class="crud-dialog crud-dialog-wide" @if($errors->any() && !old('coupon_form_id')) data-open-on-load @endif>
    <header class="crud-dialog-header flex items-start justify-between gap-3 border-b p-4">
        <div><h2 id="coupon-create-title" class="text-lg font-bold">Buat kupon</h2><p class="mt-1 text-sm text-slate-500">Atur nilai diskon, masa berlaku, dan batas penggunaan.</p></div>
        <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100" data-coupon-close="coupon-create-dialog" aria-label="Tutup dialog">×</button>
    </header>
    @include('coupons._form', ['coupon' => null, 'formId' => 'coupon-create-dialog', 'creating' => true])
</dialog>

@foreach($coupons as $coupon)
    <dialog id="coupon-edit-dialog-{{ $coupon->id }}" aria-labelledby="coupon-edit-title-{{ $coupon->id }}" class="crud-dialog crud-dialog-wide" @if((string) old('coupon_form_id') === (string) $coupon->id && $errors->any()) data-open-on-load @endif>
        <header class="crud-dialog-header flex items-start justify-between gap-3 border-b p-4">
            <div><h2 id="coupon-edit-title-{{ $coupon->id }}" class="text-lg font-bold">Edit {{ $coupon->code }}</h2><p class="mt-1 text-sm text-slate-500">Perbarui pengaturan kupon.</p></div>
            <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100" data-coupon-close="coupon-edit-dialog-{{ $coupon->id }}" aria-label="Tutup dialog">×</button>
        </header>
        @include('coupons._form', ['coupon' => $coupon, 'formId' => 'coupon-edit-dialog-'.$coupon->id, 'creating' => false])
    </dialog>
@endforeach

<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-coupon-close]');
    if (!button) return;
    var dialog = document.getElementById(button.getAttribute('data-coupon-close'));
    if (dialog) dialog.close();
});
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('dialog[data-open-on-load]').forEach(function (dialog) { dialog.showModal(); });
});
</script>
</body>
</html>
