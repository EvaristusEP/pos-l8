@php
    $formMarker = $formMarker ?? (($formMethod ?? 'PUT') === 'POST' ? 'menu-create' : 'menu-edit-'.$menu->id);
    $useOld = old('_form') === $formMarker;
@endphp
<form method="POST" action="{{ $formAction }}" class="space-y-6">
    @csrf
    <input type="hidden" name="_form" value="{{ $formMarker }}">
    @if (($formMethod ?? 'PUT') !== 'POST') @method($formMethod ?? 'PUT') @endif

    <section class="master-menu-product-info">
        <header class="master-menu-product-heading">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Informasi produk</p>
            <h3 class="mt-1 text-base font-extrabold text-slate-900">Detail dasar</h3>
        </header>
        <input type="hidden" name="category_id" value="{{ $legacyCategoryId ?? $menu->category_id }}">
        <label class="master-menu-category-field text-sm font-bold text-slate-700">Kategori produk
            @if ($activeBranch && $catalogStorageAvailable)
                <select name="branch_category" required class="pos-input">
                    <option value="">Pilih kategori</option>
                    @foreach ($branchCategories as $slug => $name)
                    <option value="{{ $slug }}" @selected(($useOld ? old('branch_category', $selectedBranchCategory ?? '') : ($selectedBranchCategory ?? '')) === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            @elseif ($activeBranch)
                <span class="mt-2 block rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">Kategori cabang tersedia setelah migration kategori cabang diterapkan.</span>
            @else
                <span class="mt-2 block rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">Pilih cabang aktif pada sidebar untuk menentukan kategori produk.</span>
            @endif
        </label>
        <div class="master-menu-product-fields">
            <label class="block text-sm font-bold text-slate-700">Nama produk
                <input name="name" value="{{ $useOld ? old('name', $menu->name) : $menu->name }}" required maxlength="150" class="pos-input">
            </label>
            <label class="block text-sm font-bold text-slate-700">Harga standar
                <input type="number" name="price" value="{{ $useOld ? old('price', $menu->price) : $menu->price }}" min="0" step="0.01" required class="pos-input">
            </label>
        </div>
        <label class="master-menu-description-field text-sm font-bold text-slate-700">Deskripsi
            <textarea name="description" rows="2" class="pos-input mt-1">{{ $useOld ? old('description', $menu->description) : $menu->description }}</textarea>
        </label>
    </section>

    @if (($formMethod ?? 'PUT') !== 'POST')
    <section class="master-menu-form-section">
    <label class="block text-sm font-bold text-slate-700"><span class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Klasifikasi</span><span class="mt-1 block">Tags produk</span>
        <input type="hidden" name="sync_tag_ids" value="1">
        <select name="tag_ids[]" multiple class="pos-input mt-1 min-h-24">
            @foreach ($tags as $tag)
            <option value="{{ $tag->id }}" @selected(in_array($tag->id, $useOld ? old('tag_ids', $menu->exists ? $menu->tags->pluck('id')->all() : []) : ($menu->exists ? $menu->tags->pluck('id')->all() : [])))>{{ $tag->name }}{{ $tag->is_active ? '' : ' (nonaktif)' }}</option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs font-normal text-slate-500">Pilih satu atau beberapa tag. Kelola tag melalui Master Data → Tags.</span>
    </label>
    </section>
    @endif
    <section class="master-menu-form-section">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Informasi resep</p>
        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">@if(($formMethod ?? 'PUT') === 'POST')Produk baru disimpan nonaktif dan belum dapat dijual. Lengkapi resep serta resep varian di modul Resep sebelum mengaktifkan produk.@elseProduk hanya dapat diaktifkan jika resep produk dan semua varian yang dijual sudah lengkap.@endif</p>
        <a href="{{ route('recipes.index') }}" class="mt-2 inline-flex text-sm font-semibold text-emerald-700 underline">Buka modul Resep</a>
    </section>
    <label class="flex items-center gap-3 text-sm font-bold text-slate-700">
        <input type="hidden" name="is_active" value="0">
        @if (($formMethod ?? 'PUT') === 'POST')
            <input type="hidden" name="is_active" value="0"><span>Produk baru disimpan nonaktif sampai resep dilengkapi.</span>
        @else
            <input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($useOld ? old('is_active', $menu->is_active) : $menu->is_active) class="rounded border-slate-300 text-emerald-700"> Produk aktif
        @endif
    </label>
    <section class="master-menu-form-section">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Pengaturan varian</p><h3 class="mt-1 text-base font-extrabold">Varian produk</h3>
        <div class="mt-3 space-y-3">
            @foreach ($menu->variants as $index => $variant)
                <div class="grid min-w-0 gap-2 rounded-md border border-slate-200 p-2 sm:grid-cols-[minmax(0,1fr)_150px_auto]">
                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant->id }}">
                    <label class="master-menu-branch-price">Nama varian<input name="variants[{{ $index }}][name]" value="{{ $variant->name }}" class="pos-input"></label>
                    <label class="master-menu-branch-price">Tambahan harga<input type="number" name="variants[{{ $index }}][price_adjustment]" value="{{ $variant->price_adjustment }}" min="0" step="0.01" class="pos-input"></label>
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="variants[{{ $index }}][is_active]" value="0"><input type="checkbox" name="variants[{{ $index }}][is_active]" value="1" @checked($variant->is_active) class="rounded border-slate-300 text-emerald-700"> Aktif</label>
                </div>
            @endforeach
            @for ($index = $menu->variants->count(); $index < $menu->variants->count() + 2; $index++)
                <div class="grid min-w-0 gap-2 rounded-md border border-dashed border-slate-200 p-2 sm:grid-cols-[minmax(0,1fr)_150px_auto]">
                    <label class="master-menu-branch-price">Nama varian<input name="variants[{{ $index }}][name]" placeholder="Varian baru" class="pos-input"></label>
                    <label class="master-menu-branch-price">Tambahan harga<input type="number" name="variants[{{ $index }}][price_adjustment]" value="0" min="0" step="0.01" class="pos-input"></label>
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="variants[{{ $index }}][is_active]" value="0"><input type="checkbox" name="variants[{{ $index }}][is_active]" value="1" checked class="rounded border-slate-300 text-emerald-700"> Aktif</label>
                </div>
            @endfor
        </div>
    </section>

    <section class="master-menu-form-section">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Pengaturan cabang</p><h3 class="mt-1 text-base font-extrabold">Harga dan ketersediaan</h3>
        <p class="mt-1 text-sm text-slate-500">Kosongkan harga untuk memakai harga standar pusat.</p>
        <div class="mt-3 space-y-3">
            @forelse ($branches as $branch)
                @php $setting = $menu->branches->firstWhere('id', $branch->id); @endphp
                <div class="master-menu-branch-row">
                    <div class="master-menu-branch-name"><span>{{ $branch->display_name }}</span><span class="ml-2 text-xs font-medium {{ $branch->is_active ? 'text-emerald-700' : 'text-rose-600' }}">{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                    <label class="master-menu-branch-price">Harga standar<input type="number" name="branch_settings[{{ $branch->id }}][price]" value="{{ optional(optional($setting)->pivot)->price }}" placeholder="Gunakan harga pusat" min="0" step="0.01" class="pos-input"></label>
                    <label class="master-menu-branch-availability"><input type="hidden" name="branch_settings[{{ $branch->id }}][is_available]" value="0"><input type="checkbox" name="branch_settings[{{ $branch->id }}][is_available]" value="1" @checked(optional(optional($setting)->pivot)->is_available ?? ($activeBranch && (int) $activeBranch->id === (int) $branch->id)) class="rounded border-slate-300 text-emerald-700"> Tersedia</label>
                </div>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Belum ada cabang.</p>
            @endforelse
        </div>
    </section>

    <footer class="master-menu-form-actions"><button type="button" data-dialog-close class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button><button class="pos-button bg-emerald-700 text-white hover:bg-emerald-800">{{ ($formMethod ?? 'PUT') === 'POST' ? 'Buat Produk' : 'Simpan perubahan' }}</button></footer>
</form>
