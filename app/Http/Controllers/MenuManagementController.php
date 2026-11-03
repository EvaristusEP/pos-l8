<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuVariant;
use App\Models\Tag;
use App\Services\BranchContext;
use App\Services\BranchMenuCategoryCatalog;
use App\Services\RecipeStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuManagementController extends Controller
{
    public function index(RecipeStockService $recipeStock, BranchContext $branchContext): View
    {
        $menus = Menu::query()->with(['category', 'variants', 'branches', 'tags'])->withCount('variants')->orderBy('name')->get();
        $activeBranch = $branchContext->activeFor(request()->user());
        $categoryCatalog = app(BranchMenuCategoryCatalog::class);
        $catalogStorageAvailable = $categoryCatalog->storageAvailable();
        $menus->each(function (Menu $menu) use ($recipeStock, $activeBranch, $categoryCatalog) {
            $menu->setAttribute('recipe_ready', $recipeStock->menuRecipeReady($menu));
            if ($activeBranch) {
                $menu->setAttribute('availability', $recipeStock->availability($menu, $activeBranch));
                $menu->setAttribute('catalog_category_display', $categoryCatalog->resolve($menu, $activeBranch));
            }
        });
        $categories = $activeBranch ? $this->categoriesInUse($menus, $activeBranch, $categoryCatalog) : collect();
        $branchCategories = $activeBranch ? $categoryCatalog->categoriesFor($activeBranch) : [];
        $legacyCategoryId = $activeBranch ? $categoryCatalog->legacyCategoryIdFor($activeBranch) : null;
        $branches = Branch::query()->orderBy('name')->get();
        $tags = Tag::query()->orderBy('name')->get();

        return view('master-menu.index', compact('menus', 'categories', 'branches', 'tags', 'activeBranch', 'branchCategories', 'legacyCategoryId', 'catalogStorageAvailable'));
    }

    public function store(Request $request): RedirectResponse
    {
        $activeBranch = app(BranchContext::class)->activeFor($request->user());
        $validated = $this->validatedMenu($request, null, $activeBranch);
        $validated['is_active'] = false;
        $validated['slug'] = $this->uniqueMenuSlug(($validated['slug'] ?? null) ?: $validated['name']);

        $menu = DB::transaction(function () use ($validated, $request) {
            $menu = Menu::create($validated);
            $this->syncVariants($request, $menu);
            $this->syncBranchSettings($request, $menu);
            $this->syncTags($request, $menu);

            return $menu;
        });

        return redirect()->route('master-menu.edit', $menu)->with('success', 'Menu berhasil dibuat.');
    }

    public function edit(Menu $menu): View
    {
        $menu->load(['category', 'variants', 'branches', 'tags']);
        $activeBranch = app(BranchContext::class)->activeFor(request()->user());
        $categoryCatalog = app(BranchMenuCategoryCatalog::class);
        $catalogStorageAvailable = $categoryCatalog->storageAvailable();
        $branchCategories = $activeBranch ? $categoryCatalog->categoriesFor($activeBranch) : [];
        $selectedBranchCategory = $activeBranch ? $categoryCatalog->resolve($menu, $activeBranch)['slug'] : null;
        $legacyCategoryId = $menu->category_id;
        $branches = Branch::query()->orderBy('name')->get();
        $tags = Tag::query()->orderBy('name')->get();

        return view('master-menu.edit', compact('menu', 'branches', 'tags', 'activeBranch', 'branchCategories', 'selectedBranchCategory', 'legacyCategoryId', 'catalogStorageAvailable'));
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $activeBranch = app(BranchContext::class)->activeFor($request->user());
        try {
            $validated = $this->validatedMenu($request, $menu, $activeBranch);
        } catch (ValidationException $exception) {
            return redirect()->route('master-menu.index', ['edit_menu' => $menu->getKey()])
                ->withErrors($exception->errors())
                ->withInput();
        }
        $validated['slug'] = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : $menu->slug;

        DB::transaction(function () use ($validated, $request, $menu) {
            $locked = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($validated);
            $this->syncVariants($request, $locked);
            $this->syncBranchSettings($request, $locked);
            $this->syncTags($request, $locked);
        });

        return redirect()->route('master-menu.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function toggleStatus(Menu $menu, RecipeStockService $recipeStock): RedirectResponse
    {
        DB::transaction(function () use ($menu, $recipeStock) {
            $locked = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->is_active && ! $recipeStock->menuRecipeReady($locked)) {
                throw ValidationException::withMessages(['recipe' => 'Produk belum dapat diaktifkan. Lengkapi resep untuk semua varian yang dijual terlebih dahulu.']);
            }
            $locked->update(['is_active' => ! $locked->is_active]);
        });
        $menu->refresh();

        return redirect()->route('master-menu.index')->with('success', $menu->is_active
            ? 'Menu berhasil diaktifkan.'
            : 'Menu berhasil dinonaktifkan.');
    }

    private function validatedMenu(Request $request, ?Menu $menu = null, ?Branch $activeBranch = null): array
    {
        $slugRule = Rule::unique('menus', 'slug');
        if ($menu) {
            $slugRule = $slugRule->ignore($menu->getKey());
        }

        $validated = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'branch_category' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', $slugRule],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:100'],
            'variants.*.price_adjustment' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'branch_settings' => ['nullable', 'array'],
            'branch_settings.*.price' => ['nullable', 'numeric', 'min:0'],
            'branch_settings.*.is_available' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
        ]);

        if ($activeBranch) {
            $categoryCatalog = app(BranchMenuCategoryCatalog::class);
            if (! $categoryCatalog->storageAvailable()) {
                throw ValidationException::withMessages(['branch_category' => 'Pilihan kategori cabang belum dapat disimpan. Terapkan migration kategori cabang terlebih dahulu.']);
            }
            $branchCategories = $categoryCatalog->categoriesFor($activeBranch);
            if (! isset($branchCategories[$validated['branch_category'] ?? ''])) {
                throw ValidationException::withMessages(['branch_category' => 'Pilih kategori yang sesuai dengan cabang aktif.']);
            }

            if ($menu) {
                $validated['category_id'] = $menu->category_id;
            } else {
                $validated['category_id'] = $categoryCatalog->legacyCategoryIdFor($activeBranch);
                if (! $validated['category_id']) {
                    throw ValidationException::withMessages(['branch_category' => 'Kategori kompatibilitas produk belum tersedia. Lengkapi kategori data lama sebelum membuat produk.']);
                }
            }
        }

        return $validated;
    }

    private function uniqueMenuSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'produk';
        $slug = $base;
        $suffix = 2;

        while (Menu::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function syncTags(Request $request, Menu $menu): void
    {
        if ($request->has('sync_tag_ids')) {
            $menu->tags()->sync($request->input('tag_ids', []));
        }
    }

    private function syncVariants(Request $request, Menu $menu): void
    {
        foreach ($request->input('variants', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $variant = ! empty($row['id'])
                ? $menu->variants()->whereKey((int) $row['id'])->first()
                : new MenuVariant();

            if (! $variant) {
                abort(422, 'Varian menu tidak valid.');
            }

            $variant->menu_id = $menu->getKey();
            $variant->name = $name;
            $variant->slug = $menu->slug.'-'.Str::slug($name).($variant->exists ? '-'.$variant->getKey() : '');
            $variant->price_adjustment = (float) ($row['price_adjustment'] ?? 0);
            $variant->is_active = (bool) ($row['is_active'] ?? false);
            $variant->save();
        }
    }

    private function syncBranchSettings(Request $request, Menu $menu): void
    {
        $settings = $request->input('branch_settings', []);
        $activeBranch = app(BranchContext::class)->activeFor($request->user());
        if (! $request->has('branch_settings')) {
            if ($activeBranch && $request->filled('branch_category') && app(BranchMenuCategoryCatalog::class)->storageAvailable()) {
                $existing = DB::table('branch_menu')->where('branch_id', $activeBranch->id)->where('menu_id', $menu->id)->first();
                $menu->branches()->syncWithoutDetaching([$activeBranch->id => [
                    'price' => $existing?->price,
                    'is_available' => $existing?->is_available ?? true,
                    'catalog_category' => $request->input('branch_category'),
                ]]);
            }
            return;
        }
        $pivot = [];
        $storageAvailable = app(BranchMenuCategoryCatalog::class)->storageAvailable();
        $existingCategories = $storageAvailable
            ? DB::table('branch_menu')->where('menu_id', $menu->id)->pluck('catalog_category', 'branch_id')
            : collect();

        foreach (Branch::query()->pluck('id') as $branchId) {
            $setting = $settings[$branchId] ?? [];
            $pivot[$branchId] = [
                'price' => ($setting['price'] ?? '') === '' ? null : (float) $setting['price'],
                'is_available' => (bool) ($setting['is_available'] ?? false),
            ];
            if ($storageAvailable) {
                $pivot[$branchId]['catalog_category'] = $activeBranch && (int) $branchId === (int) $activeBranch->id && $request->filled('branch_category')
                    ? $request->input('branch_category')
                    : ($existingCategories[$branchId] ?? null);
            }
        }

        $menu->branches()->sync($pivot);
    }

    private function categoriesInUse($menus, Branch $branch, BranchMenuCategoryCatalog $catalog)
    {
        $categories = [];

        foreach ($menus as $menu) {
            $setting = $menu->branches->firstWhere('id', $branch->id);
            if (! $menu->is_active || ! $setting || ! $setting->pivot->is_available) {
                continue;
            }

            $resolved = $catalog->resolve($menu, $branch);
            $categories[$resolved['slug']] = (object) ['id' => $resolved['slug'], 'name' => $resolved['name']];
        }

        return collect($categories)->sortBy('name')->values();
    }
}
