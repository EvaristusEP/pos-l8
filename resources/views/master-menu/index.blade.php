<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Master Menu · JOOAL POS</title>@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased">
<x-pos-sidebar />
<main class="min-h-screen min-w-0 p-4 lg:p-6">
    <div class="mx-auto w-full max-w-6xl min-w-0 space-y-5">
        <header class="pos-page-header bg-white">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Owner · JOOAL POS</p>
            <h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Master Menu</h1>
            <p class="mt-1 text-sm text-slate-500">Kelola produk, kategori cabang, harga standar, varian, dan status.</p>
        </header>
        @if (session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif

        <div class="master-menu-filter-bar">
            <label class="master-menu-filter-field">Cari produk<input type="search" data-filter-search="menu-list" class="pos-input mt-1" placeholder="Nama produk"></label>
            <label class="master-menu-filter-field">Kategori<select data-filter-select="menu-list" data-filter-key="category" class="pos-input mt-1"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
            <label class="master-menu-filter-field">Status<select data-filter-select="menu-list" data-filter-key="status" class="pos-input mt-1"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></label>
            <div class="master-menu-filter-actions"><span class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800">{{ $menus->count() }} produk</span><button type="button" data-dialog-open="menu-create-dialog" class="pos-button whitespace-nowrap bg-emerald-700 text-white"><span aria-hidden="true">+</span>&nbsp; Tambah Menu</button></div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-sm">
            <span class="font-semibold text-emerald-950">Cabang aktif: <strong>{{ $activeBranch ? $activeBranch->display_name : 'Belum dipilih' }}</strong></span>
            @if(!$activeBranch)<span class="text-emerald-900">Pilih cabang lewat sidebar untuk melihat kategori dan harga cabang, lalu menambahkan produk.</span>@endif
        </div>

        <section id="menu-list" class="min-w-0 space-y-3" aria-label="Daftar produk">
            @forelse ($menus as $menu)
                @php
                    $editing = old('_form') === 'menu-edit-'.$menu->id;
                    $branchSetting = $activeBranch ? $menu->branches->firstWhere('id', $activeBranch->id) : null;
                    $menuAvailable = $branchSetting && (bool) $branchSetting->pivot->is_available;
                    $displayPrice = $branchSetting && $branchSetting->pivot->price !== null ? $branchSetting->pivot->price : $menu->price;
                    $availability = $menu->availability ?? null;
                    $catalogCategory = $menu->catalog_category_display ?? null;
                    $selectedBranchCategory = $catalogCategory['slug'] ?? null;
                @endphp
                <article data-filter-item data-filter-text="{{ mb_strtolower($menu->name) }}" data-category="{{ $catalogCategory['slug'] ?? '' }}" data-status="{{ $menu->is_active ? 'active' : 'inactive' }}" class="min-w-0 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    @if(!$menu->recipe_ready)<p class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $menu->name }} belum siap dijual. <a class="font-bold underline" href="{{ route('recipes.index') }}">Lengkapi resep</a>.</p>@endif
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="break-words font-extrabold">{{ $menu->name }}</h2>
                                @if($catalogCategory)<span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{ $catalogCategory['name'] }}</span>@endif
                                <span class="rounded-md px-2 py-1 text-xs font-bold {{ $menu->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $menu->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                @if($activeBranch)<span class="rounded-md px-2 py-1 text-xs font-bold {{ $menuAvailable ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $branchSetting ? ($menuAvailable ? 'Tersedia di cabang' : 'Tidak tersedia di cabang') : 'Belum diatur di cabang' }}</span>@endif
                            </div>
                            <p class="mt-2 text-sm font-bold text-emerald-800">{{ $activeBranch ? 'Harga cabang' : 'Harga dasar' }}: Rp {{ number_format($displayPrice, 0, ',', '.') }}</p>
                            @if($activeBranch && $availability)
                                <p class="mt-1 text-sm font-semibold @if(in_array($availability['status'], ['out_of_stock', 'stock_unavailable'])) text-rose-700 @elseif($availability['status'] === 'low_stock') text-amber-700 @elseif(in_array($availability['status'], ['recipe_incomplete', 'inactive'])) text-slate-600 @else text-emerald-700 @endif">
                                    @if($availability['status'] === 'inactive') Menu nonaktif — kapasitas tidak dihitung @elseif($availability['status'] === 'recipe_incomplete') Resep belum lengkap — kapasitas belum dapat dihitung @elseif($availability['status'] === 'stock_unavailable') Stok bahan belum tercatat — kapasitas belum dapat dihitung @elseif($availability['status'] === 'out_of_stock') Habis — 0 porsi dapat dibuat @elseif($availability['status'] === 'low_stock') Stok terbatas — {{ $availability['available_portions'] }} porsi dapat dibuat @else Tersedia — {{ $availability['available_portions'] }} porsi dapat dibuat @endif
                                    @if($availability['limiting_ingredient']) <span class="font-normal text-slate-500">(periksa {{ $availability['limiting_ingredient']->name }})</span>@endif
                                </p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">{{ $menu->variants_count }} varian</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button type="button" data-dialog-open="menu-edit-{{ $menu->id }}" class="pos-button border border-slate-300 bg-white px-3 text-slate-700" aria-label="Edit menu {{ $menu->name }}">Edit</button>
                            <form method="POST" action="{{ route('master-menu.toggle-status', $menu) }}" onsubmit="return confirm('{{ $menu->is_active ? 'Nonaktifkan menu ini?' : 'Aktifkan menu ini? Resep harus lengkap.' }}')">@csrf @method('PATCH')<button class="pos-button px-3 {{ $menu->is_active ? 'bg-rose-50 text-rose-700' : 'bg-emerald-700 text-white' }}">{{ $menu->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                        </div>
                    </div>
                </article>
                <x-crud-dialog id="menu-edit-{{ $menu->id }}" title="Edit {{ $menu->name }}" description="Perbarui detail produk, kategori cabang, varian, harga, dan ketersediaan." size="wide" :open-on-load="request('edit_menu') == $menu->id || $editing">
                    @if($editing && $errors->any())<div class="mb-4 rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
                    @include('master-menu._form', ['menu' => $menu, 'formAction' => route('master-menu.update', $menu), 'formMethod' => 'PUT', 'formMarker' => 'menu-edit-'.$menu->id, 'selectedBranchCategory' => $selectedBranchCategory])
                </x-crud-dialog>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Belum ada produk. Klik <strong>Tambah Menu</strong> untuk membuat produk baru.</div>
            @endforelse
            <p data-filter-empty class="hidden rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">Tidak ada produk yang cocok dengan pencarian.</p>
        </section>
    </div>
</main>
@php
    $newMenu = new \App\Models\Menu(['price' => '0.00', 'is_active' => false]);
    $newMenu->setRelation('variants', collect())->setRelation('branches', collect())->setRelation('tags', collect());
@endphp
<x-crud-dialog id="menu-create-dialog" title="Tambah Menu" description="Produk baru disimpan nonaktif. Lengkapi resep sebelum mengaktifkan produk." size="wide" :open-on-load="old('_form') === 'menu-create' && $errors->any()">
    @if(old('_form') === 'menu-create' && $errors->any())<div class="mb-4 rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    @include('master-menu._form', ['menu' => $newMenu, 'formAction' => route('master-menu.store'), 'formMethod' => 'POST', 'formMarker' => 'menu-create', 'selectedBranchCategory' => null])
</x-crud-dialog>
</body>
</html>
