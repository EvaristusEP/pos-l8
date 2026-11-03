<form method="POST" action="{{ $creating ? route('coupons.store') : route('coupons.update', $coupon) }}" class="coupon-form">
    @csrf
    @unless($creating)@method('PUT')<input type="hidden" name="coupon_form_id" value="{{ $coupon->id }}">@endunless
    @if($errors->any() && ($creating ? !old('coupon_form_id') : (string) old('coupon_form_id') === (string) $coupon->id))
        <p role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</p>
    @endif
    <div class="coupon-form-grid">
        <label class="coupon-form-field">Kode kupon
            <input name="code" value="{{ old('code', $coupon ? $coupon->code : '') }}" maxlength="40" required class="pos-input" placeholder="HEMAT10">
        </label>
        <label class="coupon-form-field">Jenis diskon
            <select name="discount_type" class="pos-input">
                <option value="percentage" @selected(old('discount_type', $coupon ? $coupon->discount_type : 'percentage') === 'percentage')>Persentase (%)</option>
                <option value="fixed" @selected(old('discount_type', $coupon ? $coupon->discount_type : 'percentage') === 'fixed')>Nominal (Rp)</option>
            </select>
        </label>
        <label class="coupon-form-field">Nilai diskon
            <input type="number" name="discount_value" value="{{ old('discount_value', $coupon ? $coupon->discount_value : '') }}" min="0.01" max="99999999.99" step="0.01" required class="pos-input">
        </label>
        <label class="coupon-form-field">Batas pemakaian total <span class="font-normal text-slate-500">(opsional)</span>
            <input type="number" name="usage_limit" value="{{ old('usage_limit', $coupon ? $coupon->usage_limit : '') }}" min="1" class="pos-input" placeholder="Tanpa batas">
        </label>
        <label class="coupon-form-field">Mulai berlaku
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon ? $coupon->starts_at->format('Y-m-d\TH:i') : '') }}" required class="pos-input">
        </label>
        <label class="coupon-form-field">Berakhir
            <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $coupon ? $coupon->ends_at->format('Y-m-d\TH:i') : '') }}" required class="pos-input">
        </label>
    </div>
    <footer class="coupon-form-footer">
        <button type="button" data-coupon-close="{{ $formId }}" class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button>
        <button type="submit" class="pos-button bg-emerald-700 text-white">{{ $creating ? 'Buat kupon' : 'Simpan perubahan' }}</button>
    </footer>
</form>
